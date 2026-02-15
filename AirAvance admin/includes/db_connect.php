<?php
// 1. Connection credentials
$username = 'alex';
$password = 'alex';
$dbname = 'localhost/XE';

try {
    $conn = new PDO(
        "oci:dbname=" . $dbname ,$username,$password);

    // Set error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Connection failed: " . htmlentities($e->getMessage(), ENT_QUOTES));
}
?>
