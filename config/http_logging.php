<?php

return [

    'enabled' => env('HTTP_LOGGING_ENABLED', true),

    // A file is rotated once it reaches this size, but never mid-request:
    // the request that pushes it over the limit is allowed to finish in it.
    'max_bytes' => (int) env('HTTP_LOG_MAX_BYTES', 20 * 1024 * 1024),

    'local_root' => storage_path('app/http_logs'),
    'active_dir' => storage_path('app/http_logs/active'),
    'pending_upload_dir' => storage_path('app/http_logs/pending_upload'),
    'pointer_file' => storage_path('app/http_logs/.pointer'),

    'drive_disk' => 'google',
    'drive_root_folder' => 'SkillMatrixLogs',

    // Case-insensitive key names redacted anywhere in request/response bodies and query params.
    'redact_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'api_key',
        'client_secret',
    ],

    // Only these request headers are logged (audit-relevant only — not a raw
    // header dump). Authorization/Cookie are deliberately excluded; the
    // request's user_email/user_id fields cover "who", so the token itself
    // isn't needed.
    'audit_request_headers' => [
        'content-type',
        'user-agent',
        'x-factory-ids',
        'x-timezone',
    ],

    // Response Content-Types logged in full; anything else logs metadata only (no raw bytes).
    'full_body_content_types' => [
        'application/json',
        'text/plain',
        'text/xml',
        'application/xml',
    ],
];
