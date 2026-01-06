<?php
require_once 'config.php';

if (isLoggedIn()) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'ADMIN':
            header("Location: admin/dashboard.php");
            break;
        case 'CLUB_LEADER':
            header("Location: club_leader/dashboard.php");
            break;
        case 'MEMBER':
            header("Location: member/dashboard.php");
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>University Club Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div id="branding">
                <h1>UniClubs</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="card" style="text-align: center; margin-top: 50px;">
            <h1>Welcome to the Student Club & Event Management System</h1>
            <p>Join clubs, participate in events, and earn rewards!</p>
            <br>
            <a href="login.php" class="button">Login</a>
            <a href="register.php" class="button">Register</a>
        </div>
    </div>
</body>
</html>