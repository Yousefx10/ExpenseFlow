<?php
// config.php
$host = 'localhost';
$db   = 'expenseflow';   // change to your DB name
$user = 'root';          // DB user
$pass = '';              // DB password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    exit('Database connection failed.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
