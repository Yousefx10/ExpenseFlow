<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$currencyCode = $_SESSION['currency'] ?? 'USD';
$symbolMap = [
    'USD' => '$',
    'SAR' => '﷼',
    'EGP' => '£',
    'INR' => '₹',
];
$cur = $symbolMap[$currencyCode] ?? '$';

$start  = $_GET['start_date'] ?? '';
$end    = $_GET['end_date']   ?? '';
$type   = $_GET['type']       ?? 'all';
$method = $_GET['method']     ?? 'all';

$query = "
    SELECT t.tx_date, t.type, t.payment_method, t.amount, t.description,
           c.name AS category_name, t.created_by, t.is_deleted
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = :uid
";
$params = [':uid' => $userId];

if ($start && $end) {
    $query .= " AND t.tx_date BETWEEN :start AND :end";
    $params[':start'] = $start;
    $params[':end']   = $end;
}

if ($type !== 'all') {
    $query .= " AND t.type = :type";
    $params[':type'] = $type;
}

if ($method !== 'all') {
    $query .= " AND t.payment_method = :method";
    $params[':method'] = $method;
}

$query .= " ORDER BY t.tx_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = "expenseflow_export_" . date('Y-m-d_H-i-s') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fputcsv($output, ['Date', 'Type', 'Payment Method', 'Amount (' . $cur . ')', 'Category', 'Description', 'Created By', 'Deleted']);

foreach ($rows as $r) {
    fputcsv($output, [
        $r['tx_date'],
        ucfirst($r['type']),
        ucfirst($r['payment_method']),
        number_format($r['amount'], 2),
        $r['category_name'] ?: 'No category',
        $r['description'] ?: '',
        $r['created_by'] ?: '',
        $r['is_deleted'] ? 'Yes' : 'No'
    ]);
}

fclose($output);
exit;

