<?php
require_once __DIR__ . '/env.php';

$server = ams_env('AMS_DB_HOST', ams_env('DB_HOST', 'localhost'));
$user = ams_env('AMS_DB_USER', ams_env('DB_USER', ams_env('DB_USERNAME', 'tomoh.ikfingeh')));
$password = ams_env('AMS_DB_PASS', ams_env('DB_PASS', ams_env('DB_PASSWORD', 'STCL@ude20@?')));
$database = ams_env('AMS_DB_NAME', ams_env('DB_NAME', ams_env('DB_DATABASE', 'webtech_2025A_tomoh_ikfingeh')));
$port = (int) ams_env('AMS_DB_PORT', ams_env('DB_PORT', 3306));

$connection = new mysqli($server, $user, $password, $database, $port);


if($connection->connect_error){
    die("Connection Failed: " . $connection->connect_error);
}
 
$connection->set_charset("utf8mb4");
?>