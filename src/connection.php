<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "lab6";

function getConnection()
{
    global $servername, $username, $password, $dbname;
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

function closeConnection($conn)
{
    if ($conn) {
        $conn->close();
    }
}
