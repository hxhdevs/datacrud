<?php
// Include config file
//include_once 'config.php';
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'datatables');
// Connect to the database 
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); 
if($conn->connect_error){ 
    die("Failed to connect with MySQL: " . $conn->connect_error); 
}