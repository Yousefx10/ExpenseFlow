<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    exit('Not authorized');
}

$userId = $_SESSION['user_id'];

$currencyCode = $_SESSION['currency'] ?? 'USD';
$symbolMap = [
    'USD' => '$',
    'SAR' => '﷼',
    'EGP' => '£',
    'INR' => '₹',
];
$currencyDisplayMode = $_SESSION['currency_display'] ?? 'symbol';
$currencySymbol = $symbolMap[$currencyCode] ?? '$';
$currencyLabel = $currencyDisplayMode === 'symbol' ? $currencySymbol : $currencyCode;





$dataset = $_GET['dataset'] ?? 'transactions';

if ($dataset === 'finance') {
    $start  = $_GET['start_date'] ?? '';
    $end    = $_GET['end_date']   ?? '';
    $cat    = $_GET['category_id'] ?? '';

    $sql = "SELECT 
                fb.tx_date,
                fb.title,
                fb.expense,
                fb.cost,
                fb.profit,
                fb.description,
                u.name AS user_name,
                c.name AS category_name,
                b.name AS bank_name,
                fb.payment_method
            FROM finance_book fb
            LEFT JOIN users u ON fb.user_id = u.id
            LEFT JOIN categories c ON fb.category_id = c.id
            LEFT JOIN bank_accounts b ON fb.bank_id = b.id
            WHERE fb.is_deleted = 0";

    $params = [];

    if ($tenantMode === 'isolated') {
        $sql .= " AND fb.user_id = :uid";
        $params[':uid'] = $userId;
    }

    if ($start !== '') {
        $sql .= " AND fb.tx_date >= :start";
        $params[':start'] = $start;
    }

    if ($end !== '') {
        $sql .= " AND fb.tx_date <= :end";
        $params[':end'] = $end;
    }

    if ($cat !== '') {
        $sql .= " AND fb.category_id = :cat";
        $params[':cat'] = (int)$cat;
    }

    $sql .= " ORDER BY fb.tx_date ASC, fb.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $filename = "finance_book_" . date('Y-m-d_H-i-s') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date','Title','Cost (' . $currencyLabel . ')','Expense (' . $currencyLabel . ')','Income (' . $currencyLabel . ')','Payment Method','Net (' . $currencyLabel . ')','Category','Bank','Description','Created By']);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $net = ($row['profit'] ?? 0) - ($row['expense'] ?? 0) - ($row['cost'] ?? 0);
        fputcsv($output, [
            $row['tx_date'],
            $row['title'],
            $row['cost'],
            $row['expense'],
            $row['profit'],
            ucfirst($row['payment_method'] ?? 'cash'),
            $net,
            $row['category_name'] ?? '',
            $row['bank_name'] ?? '',
            $row['description'],
            $row['user_name'] ?? '',
        ]);
    }

    fclose($output);
    exit;
}

$start  = $_GET['start_date'] ?? '';
$end    = $_GET['end_date']   ?? '';
$type   = $_GET['type']       ?? 'all';
$method = $_GET['method']     ?? 'all';

$sql = "SELECT 
            t.tx_date,
            t.type,
            t.payment_method,
            t.bank_id,
            t.amount,
            t.description,
            t.created_by,
            c.name AS category_name,
            b.name AS bank_name
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN bank_accounts b ON t.bank_id = b.id
        WHERE t.is_deleted = 0";

$params = [];

if ($tenantMode === 'isolated') {
    $sql .= " AND t.user_id = :uid";
    $params[':uid'] = $userId;
}

if ($start !== '') {
    $sql .= " AND t.tx_date >= :start";
    $params[':start'] = $start;
}

if ($end !== '') {
    $sql .= " AND t.tx_date <= :end";
    $params[':end'] = $end;
}

if ($type !== 'all') {
    $sql .= " AND t.type = :type";
    $params[':type'] = $type;
}

if ($method !== 'all') {
    $sql .= " AND t.payment_method = :pm";
    $params[':pm'] = $method;
}

$cat = $_GET['category_id'] ?? '';
if ($cat !== '') {
    $sql .= " AND t.category_id = :cat";
    $params[':cat'] = (int)$cat;
}

$sql .= " ORDER BY t.tx_date ASC, t.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = "transactions_" . date('Y-m-d_H-i-s') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' .$filename);

$output = fopen('php://output', 'w');
fputcsv($output, ['Date','Type','Payment Method','Bank','Amount (' .$currencyLabel.')','Description','Created By','Category']);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['tx_date'],
        $row['type'],
        $row['payment_method'],
        $row['bank_name'] ?? '',
        $row['amount'],
        $row['description'],
        $row['created_by'],
        $row['category_name'],
    ]);
}

fclose($output);
exit;
