<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-backend',
    'homeUrl' => '/crm-panel/',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'backend\controllers',
    'bootstrap' => ['log'],
    'modules' => [
        'gridview' => ['class' => 'kartik\grid\Module'],
        'admin' => [
            'class' => 'mdm\admin\Module',
            //'layout' => 'left-menu',
            'mainLayout' => '@app/views/layouts/main.php',
            /* 'as access' => [
                 'class' => 'yii\filters\AccessControl',
                 'rules' => [
                     [
                         'allow' => true,
                         'roles' => ['admin'],
                     ]
                 ]
             ],  */
        ],
    ],
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-backend',
            'baseUrl' => '/crm-panel',
        ],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-backend', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the backend
            'name' => 'advanced-backend',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'useFileTransport' => false, // false — real, true — in file
            'transport' => [
                'dsn' => 'sendmail://default'
            ],
        ],
        'assetManager' => [
            'bundles' => [
                'hail812\adminlte3\assets\AdminLteAsset' => [
                    //'skin' => 'blue',
                    'js' => [
                        'js/adminlte.min.js',
                    ],
                    'css' => [
                        'css/adminlte.min.css', 
                    ],
                ],
                'kartik\base\BootstrapAsset' => [
                    'bsVersion' => '4.x',
                ],
            ],
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                '<action:login|logout|signup|change-password>' => 'site/<action>',
            ],
        ],
        'authManager' => [
            'class' => 'yii\rbac\DbManager',
            'cache' => \yii\caching\ArrayCache::class,
            //'defaultRoles' => ['employee']
        ],
        'aftership' => [
            'class' => 'common\components\AfterShipComponent',
        ],
    ],
    'as access' => [
        'class' => 'mdm\admin\components\AccessControl',
        'allowActions' => [
            'site/*',
            //'gii/*',
            "debug/*",
            "employees/*",
            "admin/*",
            //'settings/*',
           /* 'personal/contract',
            'personal/view-contract-pdf',
            'personal/get-contract',
            'personal/tasks',
            'personal/packages',
            'personal/package',
            'personal/task',
            'personal/accept-task',
            'personal/reject-task',
            'personal/complete-task',
            'personal/accept-package',
            'personal/reject-package',
            'personal/complete-package',*/
            'personal/*',
            '/chat/download-attachment',
            'packages/create-for-courier',
            'tasks/create-for-package',
            'tasks/get-tracking-status',
            'packages/get-tracking-status',

            'tasks/download-label',
            'tasks/download-document',

            'packages/download-label',
            'packages/download-document',

            'tasks/return-to-work',
            'tasks/complete-task',

            'packages/return-to-work',
            'packages/complete-package',

            'reminders/*',
            'notification/*',

            'my-chat/*',
            'chat/*',

            'urgent-call/*',
            'companies/*',
            'logs-admin/*'

            //'packages/test',
            //'test-after-ship/*'
        ]
    ],
    'params' => $params,
];
