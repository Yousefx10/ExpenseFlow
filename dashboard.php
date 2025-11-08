<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId       = $_SESSION['user_id'];
$userName     = $_SESSION['user_name'] ?? 'User';
$currencyCode = $_SESSION['currency'] ?? 'USD';

$symbolMap = [
    'USD' => '$',
    'SAR' => '﷼',
    'EGP' => '£',
    'INR' => '₹',
];
$currencyDisplayMode = $_SESSION['currency_display'] ?? 'symbol';
$currencySymbol = $symbolMap[$currencyCode] ?? '$';
$currencyPrefix = $currencyDisplayMode === 'symbol' ? $currencySymbol : $currencyCode . ' ';

$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');
$weekStart  = date('Y-m-d', strtotime('sunday last week +1 day'));
$weekEnd    = date('Y-m-d', strtotime('saturday this week'));


function getTotalsRange(PDO $pdo, $userId, $start, $end) {
    global $tenantMode;

    $where  = "WHERE is_deleted = 0 AND tx_date BETWEEN :start AND :end";
    $params = [
        ':start' => $start,
        ':end'   => $end,
    ];

    if ($tenantMode === 'isolated') {
        $where  = "WHERE user_id = :uid AND is_deleted = 0 AND tx_date BETWEEN :start AND :end";
        $params[':uid'] = $userId;
    }

    $stmt = $pdo->prepare("
        SELECT
          SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS total_income,
          SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expense
        FROM transactions
        $where
    ");
    $stmt->execute($params);
    $row = $stmt->fetch();
    foreach (['total_income','total_expense'] as $k) {
        if ($row[$k] === null) {
            $row[$k] = 0;
        }
    }
    return $row;
}


function getTotalsAll(PDO $pdo, $userId) {
    global $tenantMode;

    $where  = "WHERE is_deleted = 0";
    $params = [];

    if ($tenantMode === 'isolated') {
        $where  = "WHERE user_id = :uid AND is_deleted = 0";
        $params[':uid'] = $userId;
    }

    $stmt = $pdo->prepare("
        SELECT
          SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS total_income,
          SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expense,
          SUM(CASE 
              WHEN payment_method = 'cash' AND type = 'income' THEN amount
              WHEN payment_method = 'cash' AND type = 'expense' THEN -amount
              ELSE 0 END) AS cash_balance,
          SUM(CASE 
              WHEN payment_method = 'bank' AND type = 'income' THEN amount
              WHEN payment_method = 'bank' AND type = 'expense' THEN -amount
              ELSE 0 END) AS bank_balance
        FROM transactions
        $where
    ");
    $stmt->execute($params);
    $row = $stmt->fetch();
    foreach (['total_income','total_expense','cash_balance','bank_balance'] as $k) {
        if ($row[$k] === null) {
            $row[$k] = 0;
        }
    }
    return $row;
}

$todayTotals   = getTotalsRange($pdo, $userId, $today, $today);
$weekTotals    = getTotalsRange($pdo, $userId, $weekStart, $weekEnd);
$monthTotals   = getTotalsRange($pdo, $userId, $monthStart, $monthEnd);
$overallTotals = getTotalsAll($pdo, $userId);
$netBalance    = $overallTotals['total_income'] - $overallTotals['total_expense'];


if ($tenantMode === 'isolated') {
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = :uid ORDER BY name ASC");
    $stmt->execute([':uid' => $userId]);
} else {
    $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name ASC");
    $stmt->execute();
}
$categories = $stmt->fetchAll();

$bankSql = "SELECT id, name FROM bank_accounts";
$bankParams = [];
if ($tenantMode === 'isolated') {
    $bankSql .= " WHERE user_id = :uid";
    $bankParams[':uid'] = $userId;
}
$bankSql .= " ORDER BY name ASC";
$stmt = $pdo->prepare($bankSql);
$stmt->execute($bankParams);
$banks = $stmt->fetchAll();


$sqlRecent = "
    SELECT t.*, c.name AS category_name, b.name AS bank_name 
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN bank_accounts b ON t.bank_id = b.id
";
$paramsRecent = [];

if ($tenantMode === 'isolated') {
    $sqlRecent .= "WHERE t.user_id = :uid ";
    $paramsRecent[':uid'] = $userId;
}

$sqlRecent .= "ORDER BY t.tx_date DESC, t.id DESC
    LIMIT 10";

$stmt = $pdo->prepare($sqlRecent);
$stmt->execute($paramsRecent);
$recent = $stmt->fetchAll();

$txError = $_SESSION['tx_error'] ?? '';
unset($_SESSION['tx_error']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - ExpenseFlow</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="logo">
    Expense<span>Flow</span>
    <?php if (!empty($companyName)): ?>
        <div class="company-name"><?= htmlspecialchars($companyName) ?></div>
    <?php endif; ?>
</div>

        <ul class="nav-links">
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="financebook.php">Finance Book</a></li>
            <li><a href="report.php">Report History</a></li>
            <li><a href="analysis.php">Analysis</a></li>
            <li><a href="settings.php">Settings</a></li>
        </ul>
        <div class="user">
            <div><?= htmlspecialchars($userName) ?></div>
            <div class="page-subtitle"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
            <div style="margin-top:8px;">
                <a href="logout.php" class="badge">Logout</a>
            </div>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <button class="topbar-menu-btn" type="button" onclick="toggleSidebar()">
                ☰
            </button>

            <div>
                <div class="page-title">Dashboard</div>
                <div class="page-subtitle"><?= date('l, F j, Y') ?></div>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('newTx').scrollIntoView({behavior:'smooth'})">
                + Add Transaction
            </button>
        </div>

        <section class="card">
            <div class="section-title">Today's Summary</div>
            <div class="summary-grid mt-8">
                <div class="card">
                    <div class="summary-card-title">Total Income</div>
                    <div class="summary-card-value summary-income">
                        <?= $currencyPrefix . number_format($todayTotals['total_income'], 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Total Expenses</div>
                    <div class="summary-card-value summary-expense">
                        <?= $currencyPrefix . number_format($todayTotals['total_expense'], 2) ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="section-title">Weekly Overview</div>
            <div class="summary-grid mt-8">
                <div class="card">
                    <div class="summary-card-title">Total Income (This Week)</div>
                    <div class="summary-card-value summary-income">
                        <?= $currencyPrefix . number_format($weekTotals['total_income'], 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Total Expenses (This Week)</div>
                    <div class="summary-card-value summary-expense">
                        <?= $currencyPrefix . number_format($weekTotals['total_expense'], 2) ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="section-title">Monthly Overview</div>
            <div class="summary-grid mt-8">
                <div class="card">
                    <div class="summary-card-title">Total Income (This Month)</div>
                    <div class="summary-card-value summary-income">
                        <?= $currencyPrefix . number_format($monthTotals['total_income'], 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Total Expenses (This Month)</div>
                    <div class="summary-card-value summary-expense">
                        <?= $currencyPrefix . number_format($monthTotals['total_expense'], 2) ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="section-title">Net Balance</div>
            <div class="summary-grid mt-8">
                <div class="card">
                    <div class="summary-card-title">Net Balance (All Time)</div>
                    <div class="summary-card-value <?= $netBalance >= 0 ? 'summary-income' : 'summary-expense' ?>">
                        <?= $currencyPrefix . number_format($netBalance, 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Cash Balance</div>
                    <div class="summary-card-value summary-balance">
                        <?= $currencyPrefix . number_format($overallTotals['cash_balance'], 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Bank Balance</div>
                    <div class="summary-card-value summary-balance">
                        <?= $currencyPrefix . number_format($overallTotals['bank_balance'], 2) ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="card" id="newTx">
            <div class="section-title">New Transaction</div>
            <?php if (!empty($txError)): ?>
                <div class="badge" style="background:#fee2e2;color:#b91c1c;margin-bottom:10px;">
                    <?= htmlspecialchars($txError) ?>
                </div>
            <?php endif; ?>
            <form method="post" action="save_transaction.php">
                <input type="hidden" name="id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Type</label>
                        <div class="radio-row">
                            <label>
                                <input type="radio" name="type" value="income" checked>
                                <div class="radio-pill income">Income</div>
                            </label>
                            <label>
                                <input type="radio" name="type" value="expense">
                                <div class="radio-pill expense">Expense</div>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="tx_date" value="<?= htmlspecialchars($today) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" step="0.01" name="amount" required>
                    </div>
                    <div class="form-group" data-payment-choice>
                        <label>Payment Method</label>
                        <div class="radio-row">
                            <label>
                                <input type="radio" name="payment_method" value="cash" checked data-payment-option>
                                <div class="radio-pill cash">Cash</div>
                            </label>
                            <label>
                                <input type="radio" name="payment_method" value="bank" data-payment-option>
                                <div class="radio-pill bank">Bank</div>
                            </label>
                        </div>
                    </div>
                    <div class="form-group bank-select" data-bank-select data-linked-payment="payment_method">
                        <label>Bank Account</label>
                        <?php if ($banks): ?>
                            <select name="bank_id">
                                <option value="">Select bank</option>
                                <?php foreach ($banks as $bank): ?>
                                    <option value="<?= $bank['id'] ?>"><?= htmlspecialchars($bank['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <div class="page-subtitle">No banks configured. Add one under Settings.</div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">No category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Description / Notes</label>
                        <textarea name="description" rows="2" maxlength="300" placeholder="Add notes about this transaction... (Max 300 letter)"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Transaction</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="section-title">Recent Transactions</div>
            <div class="tx-list">
                <?php if (!$recent): ?>
                    <div class="page-subtitle">No transactions yet.</div>
                <?php else: ?>
                    <?php foreach ($recent as $tx): 
                        $isDeleted = (int) $tx['is_deleted'] === 1;

                        $descRaw = $tx['description'] ?: 'No description';
                        $limit   = 120;
                        $descShort = (strlen($descRaw) > $limit)
                            ? substr($descRaw, 0, $limit) . '…'
                            : $descRaw;
                    ?>
                        <div class="tx-item <?= $isDeleted ? 'deleted' : '' ?>">
                            <div class="tx-main">
                                <strong class="tx-desc-short"
                                        data-full-description="<?= htmlspecialchars($descRaw, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($descShort) ?>
                                </strong>
                                <div class="tx-meta">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($tx['tx_date']))) ?>
                                    • <?= ucfirst($tx['payment_method']) ?>
                                    • <?= ucfirst($tx['type']) ?>
                                    <?php if ($tx['payment_method'] === 'bank'): ?>
                                        • Bank: <?= htmlspecialchars($tx['bank_name'] ?? 'N/A') ?>
                                    <?php endif; ?>
                                    <?php if (!empty($tx['category_name'])): ?>
                                        • <?= htmlspecialchars($tx['category_name']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($tx['created_by'])): ?>
                                        • by <?= htmlspecialchars($tx['created_by']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isDeleted): ?>
                                    <div class="tx-deleted-label">Deleted</div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="tx-amount 
                                    <?= $isDeleted ? 'deleted' : ($tx['type'] === 'income' ? 'income' : 'expense') ?>">
                                    <?= $tx['type'] === 'income' ? '+' : '-' ?>
                                    <?= $currencyPrefix . number_format($tx['amount'], 2) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
