<?php
return [
    'name' => 'CRM Employers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
            'directoryLevel' => 1,
            'cachePath' => '@runtime/cache',
            'defaultDuration' => 3600, 
            'fileMode' => 0644,
            'dirMode' => 0755,
        ],
        'authManager' => [
            'class' => 'yii\rbac\DbManager',
        ],
        'webSocket' => [
            'class' => 'common\components\WebSocketComponent',
            'serverUrl' => 'https://localhost:8901',
            'timeout' => 3,
            'retryCount' => 2,
            'enabled' => true
        ],
    ],
];
