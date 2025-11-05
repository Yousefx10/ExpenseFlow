<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare('UPDATE transactions SET is_deleted = 1 WHERE id = :id AND user_id = :uid');
    $stmt->execute([
        ':id'  => $id,
        ':uid' => $userId
    ]);
}

header('Location: report.php');
exit;

