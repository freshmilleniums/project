<?php
return [
    'adminEmail' => 'admin@example.com',
    'bsDependencyEnabled' => false,
    'bsVersion' => '4.x',
    'hail812/yii2-adminlte3' => [
        'pluginMap' => [
            'sweetalert2' => [
                'css' => 'sweetalert2-theme-bootstrap-4/bootstrap-4.min.css',
                'js' => 'sweetalert2/sweetalert2.min.js',
            ],
            'toastr' => [
                'css' => ['toastr/toastr.min.css'],
                'js' => ['toastr/toastr.min.js'],
            ],
        ],
    ],
    'uploadPath' => dirname(__DIR__) . '/web/uploads/',
    'uploadUrl' => '/uploads/',

];
