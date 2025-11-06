<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = $_POST['type'] ?? 'income';
    $txDate  = $_POST['tx_date'] ?? date('Y-m-d');
    $amount  = (float)($_POST['amount'] ?? 0);
    $payment = $_POST['payment_method'] ?? 'cash';
    $desc    = trim($_POST['description'] ?? '');

    // category
    $categoryId = null;
    if (isset($_POST['category_id']) && $_POST['category_id'] !== '') {
        $categoryId = (int)$_POST['category_id'];


        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = :cid AND user_id = :uid");
        $stmt->execute([
            ':cid' => $categoryId,
            ':uid' => $userId
        ]);
        if (!$stmt->fetch()) {
            $categoryId = null; 
        }
    }

    if ($amount <= 0) {
        header('Location: dashboard.php');
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO transactions 
            (user_id, tx_date, type, payment_method, amount, description, created_by, category_id)
        VALUES 
            (:uid, :dt, :type, :pm, :amt, :descr, :cb, :cat)
    ");
    $stmt->execute([
        ':uid'   => $userId,
        ':dt'    => $txDate,
        ':type'  => $type === 'expense' ? 'expense' : 'income',
        ':pm'    => $payment === 'bank' ? 'bank' : 'cash',
        ':amt'   => $amount,
        ':descr' => $desc,
        ':cb'    => $userName,
        ':cat'   => $categoryId,
    ]);
}

header('Location: dashboard.php');
exit;
