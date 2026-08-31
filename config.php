<?php
// config.php

return [
    'data_dir' => __DIR__ . '/data',
    'app_url' => 'https://sporbita-developers.ru/test_constructor',
    'api_url' => 'https://sporbita-developers.ru/testrus/NewTest/NEWtesting/index.php',
    'sync_enabled' => true,
    'sync_async' => false,
    'entity_type_id' => 106061,
    'bitrix_webhook' => 'https://sporbita.bitrix24.ru/rest/106061/dm73uvyl7hn9lhqx/',
    'admin_login' => 'admin',
    'admin_pass' => 'admin8800',
    'upload_max_size' => 5 * 1024 * 1024,
    'paths' => [
        'cache' => __DIR__ . '/cache',
        'logs' => __DIR__ . '/logs',
        'data' => __DIR__ . '/data'
    ]
];

