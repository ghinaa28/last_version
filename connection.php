<?php
$servername = "localhost";  // XAMPP default
$username = "root";         // XAMPP default
$password = "";             // XAMPP default is empty
$dbname = "gis";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Connected successfully"; // optional for testing
?>