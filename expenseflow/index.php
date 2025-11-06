<?php
require 'config.php';

$mode = $_GET['mode'] ?? 'login'; // login or register
$error = '';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['currency'] = $user['currency'] ?? 'USD';
        header('Location: dashboard.php');
        exit;
    }
    else {
            $error = 'Invalid email or password.';
        }
    }

    if ($_POST['action'] === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            $error = 'Please fill all fields.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, currency) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $email, $hash, 'USD']); // default
                $userId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['currency'] = 'USD';
                header('Location: dashboard.php');
                exit;

            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ExpenseFlow - Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-title">ExpenseFlow</div>
        <div class="page-subtitle">Track your income and expenses</div>

        <div class="auth-tabs">
            <a href="?mode=login" class="<?= $mode === 'login' ? 'active' : '' ?>">Login</a>
            <a href="?mode=register" class="<?= $mode === 'register' ? 'active' : '' ?>">Register</a>
        </div>

        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($mode === 'login'): ?>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-group" style="margin-bottom:10px;">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <button class="btn btn-primary" type="submit">Login</button>
            </form>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="register">
                <div class="form-group" style="margin-bottom:10px;">
                    <label>Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <button class="btn btn-primary" type="submit">Create account</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
