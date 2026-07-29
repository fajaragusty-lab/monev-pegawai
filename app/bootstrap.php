<?php
session_start();

require __DIR__ . '/helpers.php';

$appConfig = require __DIR__ . '/../config/app.php';
$runtimeConfig = file_exists(__DIR__ . '/../config/config.php')
    ? require __DIR__ . '/../config/config.php'
    : require __DIR__ . '/../config/config.sample.php';
$appConfig = array_replace_recursive($appConfig, $runtimeConfig);

date_default_timezone_set(app_config('app.timezone', 'Asia/Jakarta'));

require __DIR__ . '/Database.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/AppService.php';
require __DIR__ . '/views.php';
require __DIR__ . '/controllers.php';

$db = new Database($appConfig['db']);
$auth = new Auth($db);
$service = new AppService($db, $appConfig);
