<?php
// config.php - Database connection
$host = 'localhost';
$dbname = 'university_club_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session
session_start();

// Helper function to check login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function to check role
function requireRole($role) {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }
    if ($_SESSION['role'] !== $role) {
        die("Access Denied: You do not have the required permissions.");
    }
}
?>