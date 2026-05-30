<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/api_helper.php';

api_ok([
    'name' => 'KASPINDO Mobile API',
    'status' => 'ok',
    'capabilities' => [
        'auth',
        'products',
        'shift',
        'transactions',
        'support_chat',
        'ai_assistant',
    ],
    'server_time' => date('c'),
]);
