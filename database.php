<?php
$server = "localhost";
$user = "tomoh.ikfingeh";
$password = "STCL@ude20@?";
$database = "Attendance_management_system";
$port = 3306;

$connection = new mysqli($server, $user, $password, $database, $port);


if($connection->connect_error){
    die("Connection Failed: " . $connection->connect_error);
}
 
$connection->set_charset("utf8mb4");
?>