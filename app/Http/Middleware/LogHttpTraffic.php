<?php

namespace App\Http\Middleware;

use App\Services\HttpLogging\HttpLogFileManager;
use App\Services\HttpLogging\HttpLogRedactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs every request (payload, route params, query params) and its matching
 * response (status, full body for JSON/text — metadata only for binary
 * bodies) as a correlated pair of JSON Lines into the currently pinned
 * http-log file, for audit purposes: who (user_id/user_email) did what
 * (method/url/body) and when (timestamp), and what happened (status/body).
 *
 * The request entry's data is captured up front (before the controller runs,
 * so it reflects exactly what arrived), but its write — like the response
 * entry's — is deferred until after $next() returns, because the requester's
 * identity is only known once auth middleware further down the stack (e.g.
 * JwtAuthenticate, which sits in a route-level group, not this global one)
 * has run. Trade-off: a hard crash mid-request (not a normal
 * exception — Laravel's handler already turns those into a response) means
 * neither entry gets written, rather than just the response half being lost.
 *
 * See HttpLogFileManager for the rotation contract that keeps a request's
 * pair of entries from ever being split across files.
 */
class LogHttpTraffic
{
    public function __construct(private readonly HttpLogFileManager $fileManager)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('http_logging.enabled')) {
            return $next($request);
        }

        $redactor = new HttpLogRedactor(config('http_logging.redact_keys'));
        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);

        $requestEntry = [
            'type' => 'request',
            'request_id' => $requestId,
            'timestamp' => now('UTC')->toIso8601ZuluString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => optional($request->route())->getName(),
            'ip' => $request->ip(),
            'route_params' => $request->route()
                ? $redactor->normalizeAndRedact($request->route()->parameters())
                : [],
            'query' => $redactor->normalizeAndRedact($request->query()),
            'body' => $redactor->normalizeAndRedact(array_merge($request->request->all(), $request->allFiles())),
            'headers' => $redactor->pickHeaders($request->headers->all(), config('http_logging.audit_request_headers')),
        ];

        $handle = null;
        try {
            $handle = $this->fileManager->pin();
        } catch (\Throwable $e) {
            Log::error('HTTP request logging failed to pin a log file', ['error' => $e->getMessage()]);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            if ($handle !== null) {
                $this->fileManager->release($handle);
            }
            throw $e;
        }

        $response->headers->set('X-Request-Id', $requestId);

        if ($handle !== null) {
            try {
                $user = auth('api')->user();
                $requestEntry['user_id'] = $user?->id;
                $requestEntry['user_email'] = $user?->email;
                $this->fileManager->append($handle, $requestEntry);

                $contentType = (string) $response->headers->get('Content-Type', '');
                $isFullBody = collect(config('http_logging.full_body_content_types'))
                    ->contains(fn ($type) => str_starts_with($contentType, $type));

                $rawContent = method_exists($response, 'getContent') ? (string) $response->getContent() : '';

                $bodyForLog = $isFullBody
                    ? $redactor->normalizeAndRedact($this->decodeIfJson($rawContent))
                    : [
                        'note' => 'binary/non-text response body omitted from log',
                        'content_length' => strlen($rawContent),
                    ];

                $this->fileManager->append($handle, [
                    'type' => 'response',
                    'request_id' => $requestId,
                    'timestamp' => now('UTC')->toIso8601ZuluString(),
                    'user_id' => $user?->id,
                    'user_email' => $user?->email,
                    'status' => $response->getStatusCode(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'content_type' => $contentType,
                    'body' => $bodyForLog,
                ]);
            } catch (\Throwable $e) {
                Log::error('HTTP response logging failed', ['error' => $e->getMessage()]);
            } finally {
                $this->fileManager->release($handle);
            }
        }

        return $response;
    }

    private function decodeIfJson(string $raw): mixed
    {
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }
}
