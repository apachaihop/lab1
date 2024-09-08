<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "lab1";

function getConnection() {
    global $servername, $username, $password, $dbname;
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

function closeConnection($conn) {
    $conn->close();
}
?>