<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // Soft delete: any logged-in user can delete any transaction
    $stmt = $pdo->prepare('UPDATE transactions SET is_deleted = 1 WHERE id = :id');
    $stmt->execute([
        ':id' => $id
    ]);
}

// Deletion is triggered only from the report page
header('Location: report.php');
exit;
