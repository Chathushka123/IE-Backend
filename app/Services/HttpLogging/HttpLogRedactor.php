<?php

namespace App\Services\HttpLogging;

use Illuminate\Http\UploadedFile;

class HttpLogRedactor
{
    /** @var string[] */
    private array $redactKeys;

    public function __construct(array $redactKeys)
    {
        $this->redactKeys = array_map('strtolower', $redactKeys);
    }

    /**
     * Recursively redacts sensitive keys and replaces uploaded files with
     * metadata, so the result is always safe to json_encode and log.
     */
    public function normalizeAndRedact(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                '__file__' => true,
                'original_name' => $value->getClientOriginalName(),
                'mime_type' => $value->getClientMimeType(),
                'size_bytes' => $value->isValid() ? $value->getSize() : null,
            ];
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(strtolower($key), $this->redactKeys, true)) {
                    $result[$key] = '[REDACTED]';
                    continue;
                }
                $result[$key] = $this->normalizeAndRedact($item);
            }
            return $result;
        }

        return $value;
    }

    /**
     * Picks only the given (case-insensitive) header names out of a full
     * header set, e.g. from Request/Response headers->all() — used to keep
     * logged headers to an audit-relevant whitelist instead of a raw dump.
     *
     * @param array<string, string[]> $headers
     * @param string[] $names
     * @return array<string, string>
     */
    public function pickHeaders(array $headers, array $names): array
    {
        $wanted = array_map('strtolower', $names);
        $result = [];
        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), $wanted, true)) {
                $result[$name] = implode(', ', (array) $values);
            }
        }
        return $result;
    }
}
