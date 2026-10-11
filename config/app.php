<?php

declare(strict_types=1);

return [
    'app' => [
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
        'environment' => $_ENV['APP_ENV'] ?? 'production',
        'name' => $_ENV['APP_NAME'] ?? 'Jinstan',
    ],
];
