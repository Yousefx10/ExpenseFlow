<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    if ($tenantMode === 'isolated') {
        $stmt = $pdo->prepare('UPDATE finance_book SET is_deleted = 1 WHERE id = :id AND user_id = :uid');
        $stmt->execute([
            ':id'  => $id,
            ':uid' => $userId,
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE finance_book SET is_deleted = 1 WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
        ]);
    }
}

header('Location: report.php?view=finance');
exit;
