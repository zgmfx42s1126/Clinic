<?php
$host = "localhost";      // usually localhost
$user = "root";           // your DB username
$password = "";           // your DB password
$dbname = "hcm";          // your database name

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Connected successfully"; // for testing
?>
