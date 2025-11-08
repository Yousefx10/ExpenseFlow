<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId       = $_SESSION['user_id'];
$userName     = $_SESSION['user_name'] ?? 'User';
$userEmail    = $_SESSION['user_email'] ?? '';
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

$defaultDate = date('Y-m-d');

$formData = [
    'tx_date'     => $defaultDate,
    'cost'        => '',
    'expense'     => '',
    'profit'      => '',
    'payment_method' => 'cash',
    'bank_id'     => '',
    'title'       => '',
    'description' => '',
    'category_id' => '',
];

$successMessage = '';
$errors = [];

$categorySql = "SELECT id, name FROM categories";
$categoryParams = [];
if ($tenantMode === 'isolated') {
    $categorySql .= " WHERE user_id = :uid";
    $categoryParams[':uid'] = $userId;
}
$categorySql .= " ORDER BY name ASC";
$stmtCats = $pdo->prepare($categorySql);
$stmtCats->execute($categoryParams);
$categories = $stmtCats->fetchAll();
$categoryMap = [];
foreach ($categories as $cat) {
    $categoryMap[(int)$cat['id']] = $cat['name'];
}

$bankSql = "SELECT id, name FROM bank_accounts";
$bankParams = [];
if ($tenantMode === 'isolated') {
    $bankSql .= " WHERE user_id = :uid";
    $bankParams[':uid'] = $userId;
}
$bankSql .= " ORDER BY name ASC";
$stmtBanks = $pdo->prepare($bankSql);
$stmtBanks->execute($bankParams);
$banks = $stmtBanks->fetchAll();
$bankMap = [];
foreach ($banks as $bank) {
    $bankMap[(int)$bank['id']] = $bank['name'];
}

function normalizeAmount($value): float {
    if ($value === null || $value === '') {
        return 0.0;
    }
    $value = str_replace(',', '', $value);
    return is_numeric($value) ? (float)$value : 0.0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txDate = $_POST['tx_date'] ?? $defaultDate;
    $title   = trim($_POST['title'] ?? '');
    $expense = normalizeAmount($_POST['expense'] ?? 0);
    $cost    = normalizeAmount($_POST['cost'] ?? 0);
    $profit  = normalizeAmount($_POST['profit'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $categoryIdRaw = $_POST['category_id'] ?? '';
    $paymentMethod = strtolower($_POST['payment_method'] ?? 'cash');
    if (!in_array($paymentMethod, ['cash','bank'], true)) {
        $paymentMethod = 'cash';
    }
    $bankIdRaw = $_POST['bank_id'] ?? '';

    $formData = [
        'tx_date'     => $txDate,
        'cost'        => $_POST['cost'] ?? '',
        'expense'     => $_POST['expense'] ?? '',
        'profit'      => $_POST['profit'] ?? '',
        'payment_method' => in_array($paymentMethod, ['cash','bank'], true) ? $paymentMethod : 'cash',
        'bank_id'     => $bankIdRaw,
        'title'       => $title,
        'description' => $description,
        'category_id' => $categoryIdRaw,
    ];

    if ($title !== '') {
        if (function_exists('mb_substr')) {
            $title = mb_substr($title, 0, 150);
        } else {
            $title = substr($title, 0, 150);
        }
    }

    $dt = DateTime::createFromFormat('Y-m-d', $txDate);
    if (!$dt || $dt->format('Y-m-d') !== $txDate) {
        $errors[] = 'Invalid date selected.';
    }

    $categoryId = null;
    if ($categoryIdRaw !== '') {
        $categoryId = (int)$categoryIdRaw;
        if (!isset($categoryMap[$categoryId])) {
            $errors[] = 'Invalid category selected.';
            $categoryId = null;
        }
    }

    $bankId = null;
    if ($paymentMethod === 'bank') {
        if ($bankIdRaw !== '') {
            $bankId = (int)$bankIdRaw;
            if (!isset($bankMap[$bankId])) {
                $errors[] = 'Invalid bank selected.';
                $bankId = null;
            }
        } else {
            $errors[] = 'Please select a bank account.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO finance_book (user_id, tx_date, payment_method, bank_id, title, expense, cost, profit, description, category_id)
                VALUES (:uid, :tx_date, :pm, :bank_id, :title, :expense, :cost, :profit, :description, :category_id)
            ");
            $stmt->execute([
                ':uid'         => $userId,
                ':tx_date'     => $txDate,
                ':pm'          => $paymentMethod,
                ':bank_id'     => $bankId,
                ':title'       => $title !== '' ? $title : null,
                ':expense'     => $expense,
                ':cost'        => $cost,
                ':profit'      => $profit,
                ':description' => $description,
                ':category_id' => $categoryId ?: null,
            ]);

            header('Location: financebook.php?added=1');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Could not save the finance entry. Please try again.';
        }
    }
}

if (isset($_GET['added'])) {
    $successMessage = 'Finance entry recorded successfully.';
}

$baseWhere = '';
$whereParams = [];
if ($tenantMode === 'isolated') {
    $baseWhere = 'WHERE fb.user_id = :uid';
    $whereParams[':uid'] = $userId;
}

$activeWhere = $baseWhere;
if ($activeWhere === '') {
    $activeWhere = 'WHERE fb.is_deleted = 0';
} else {
    $activeWhere .= ' AND fb.is_deleted = 0';
}

$totalsSql = "
    SELECT
        COALESCE(SUM(fb.cost), 0)    AS total_cost,
        COALESCE(SUM(fb.expense), 0) AS total_expense,
        COALESCE(SUM(fb.profit), 0)  AS total_income
    FROM finance_book fb
    $activeWhere
";
$stmtTotals = $pdo->prepare($totalsSql);
$stmtTotals->execute($whereParams);
$totals = $stmtTotals->fetch() ?: [
    'total_cost'    => 0,
    'total_expense' => 0,
    'total_income'  => 0,
];

$netProfitOverall = $totals['total_income'] - $totals['total_expense'] - $totals['total_cost'];

$recentSql = "
    SELECT fb.*, u.name AS user_name, c.name AS category_name, b.name AS bank_name
    FROM finance_book fb
    LEFT JOIN users u ON fb.user_id = u.id
    LEFT JOIN categories c ON fb.category_id = c.id
    LEFT JOIN bank_accounts b ON fb.bank_id = b.id
    " . ($baseWhere ?: '') . "
    ORDER BY fb.tx_date DESC, fb.id DESC
    LIMIT 25
";
$stmtRecent = $pdo->prepare($recentSql);
$stmtRecent->execute($whereParams);
$recentEntries = $stmtRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Finance Book - ExpenseFlow</title>
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
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="financebook.php" class="active">Finance Book</a></li>
            <li><a href="report.php">Report History</a></li>
            <li><a href="analysis.php">Analysis</a></li>
            <li><a href="settings.php">Settings</a></li>
        </ul>
        <div class="user">
            <div><?= htmlspecialchars($userName) ?></div>
            <div class="page-subtitle"><?= htmlspecialchars($userEmail) ?></div>
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
                <div class="page-title">Finance Book</div>
                <div class="page-subtitle">Track expense, cost, and profit in one place</div>
            </div>
            <button class="btn btn-primary" type="button" onclick="document.getElementById('financeForm').scrollIntoView({behavior:'smooth'});">
                + Add Entry
            </button>
        </div>

        <section class="card">
            <div class="section-title">Performance Overview</div>
            <div class="summary-grid mt-8">
                <div class="card">
                    <div class="summary-card-title">Total Cost</div>
                    <div class="summary-card-value summary-balance">
                        <?= $currencyPrefix . number_format($totals['total_cost'], 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Total Expense</div>
                    <div class="summary-card-value summary-expense">
                        <?= $currencyPrefix . number_format($totals['total_expense'], 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Total Income</div>
                    <div class="summary-card-value summary-income">
                        <?= $currencyPrefix . number_format($totals['total_income'], 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Net Profit</div>
                    <div class="summary-card-value <?= $netProfitOverall >= 0 ? 'summary-income' : 'summary-expense' ?>">
                        <?= $currencyPrefix . number_format($netProfitOverall, 2) ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="card" id="financeForm">
            <div class="section-title">Add Finance Entry</div>

            <?php if (!empty($successMessage)): ?>
                <div class="badge" style="background:#dcfce7;color:#166534;margin-bottom:10px;">
                    <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="badge" style="background:#fee2e2;color:#b91c1c;margin-bottom:10px;">
                    <?= htmlspecialchars(implode(' ', $errors)) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="tx_date" value="<?= htmlspecialchars($formData['tx_date']) ?>" required>
                    </div>
                    <div class="form-group" data-payment-choice>
                        <label>Payment Method</label>
                        <div class="radio-row">
                            <label>
                                <input type="radio" name="payment_method" value="cash"
                                       <?= ($formData['payment_method'] ?? 'cash') === 'cash' ? 'checked' : '' ?>
                                       data-payment-option>
                                <div class="radio-pill cash">Cash</div>
                            </label>
                            <label>
                                <input type="radio" name="payment_method" value="bank"
                                       <?= ($formData['payment_method'] ?? 'cash') === 'bank' ? 'checked' : '' ?>
                                       data-payment-option>
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
                                    <option value="<?= $bank['id'] ?>"
                                        <?= ($formData['bank_id'] !== '' && (int)$formData['bank_id'] === (int)$bank['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($bank['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <div class="page-subtitle">No banks configured. Add one under Settings.</div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Cost</label>
                        <input type="number" step="0.01" name="cost" value="<?= htmlspecialchars($formData['cost']) ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Expense</label>
                        <input type="number" step="0.01" name="expense" value="<?= htmlspecialchars($formData['expense']) ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Income</label>
                        <input type="number" step="0.01" name="profit" value="<?= htmlspecialchars($formData['profit']) ?>">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Title</label>
                        <input type="text" name="title" maxlength="150" placeholder="E.g. Project Alpha Recap" value="<?= htmlspecialchars($formData['title']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">No category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($formData['category_id'] !== '' && (int)$formData['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Description (optional)</label>
                        <textarea name="description" rows="2" placeholder="Add short notes..."><?= htmlspecialchars($formData['description']) ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-secondary" type="reset">Reset</button>
                    <button class="btn btn-primary" type="submit">Save Entry</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="section-title">Recent Entries</div>
            <?php if (!$recentEntries): ?>
                <div class="page-subtitle" style="margin-top:8px;">No finance entries recorded yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="finance-table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Cost</th>
                            <th>Expense</th>
                            <th>Income</th>
                            <th>Method</th>
                            <th>Net</th>
                            <th>Category</th>
                            <th>Bank</th>
                            <th>Created By</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentEntries as $entry): 
                            $net = ($entry['profit'] ?? 0) - ($entry['expense'] ?? 0) - ($entry['cost'] ?? 0);
                            $title = $entry['title'] ?: 'Untitled';
                            $isDeletedRow = (int)($entry['is_deleted'] ?? 0) === 1;
                        ?>
                            <tr class="finance-row <?= $isDeletedRow ? 'finance-row-deleted' : '' ?>"
                                data-finance-entry='<?= htmlspecialchars(json_encode([
                                    'date'        => date('M j, Y', strtotime($entry['tx_date'])),
                                    'raw_date'    => $entry['tx_date'],
                                    'title'       => $title,
                                    'cost'        => (float)$entry['cost'],
                                    'expense'     => (float)$entry['expense'],
                                    'income'      => (float)$entry['profit'],
                                    'net'         => $net,
                                    'description' => $entry['description'] ?: 'No description provided.',
                                    'method'      => ucfirst($entry['payment_method'] ?? 'cash'),
                                    'category'    => $entry['category_name'] ?? 'No category',
                                    'bank'        => $entry['bank_name'] ?? 'No bank',
                                    'creator'     => $entry['user_name'] ?? 'Unknown',
                                    'is_deleted'  => $isDeletedRow ? 1 : 0,
                                ]), ENT_QUOTES, 'UTF-8') ?>'
                                data-currency="<?= htmlspecialchars($currencySymbol) ?>">
                                <td><?= htmlspecialchars(date('M j, Y', strtotime($entry['tx_date']))) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($title) ?></div>
                                    <?php if ($isDeletedRow): ?>
                                        <div class="tx-deleted-label" style="display:inline-block;margin-top:4px;">Deleted</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $currencyPrefix . number_format($entry['cost'], 2) ?></td>
                                <td><?= $currencyPrefix . number_format($entry['expense'], 2) ?></td>
                                <td><?= $currencyPrefix . number_format($entry['profit'], 2) ?></td>
                                <td><?= htmlspecialchars(ucfirst($entry['payment_method'] ?? 'cash')) ?></td>
                                <td class="<?= $net >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $currencyPrefix . number_format($net, 2) ?>
                                </td>
                                <td><?= htmlspecialchars($entry['category_name'] ?? 'No category') ?></td>
                                <td><?= htmlspecialchars($entry['bank_name'] ?? 'No bank') ?></td>
                                <td><?= htmlspecialchars($entry['user_name'] ?? 'Unknown') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<div class="modal-backdrop" id="financeModalBackdrop"></div>
<div class="modal" id="financeModal" role="dialog" aria-modal="true" aria-labelledby="financeModalTitle">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <div class="modal-title" id="financeModalTitle">Entry Details</div>
                <div class="modal-subtitle" id="financeModalDate"></div>
            </div>
            <button class="modal-close" type="button" id="financeModalClose">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-field">
                <span class="field-label">Title</span>
                <span class="field-value" id="financeModalFieldTitle"></span>
            </div>
            <div class="modal-field">
                <span class="field-label">Cost</span>
                <span class="field-value" id="financeModalFieldCost"></span>
            </div>
            <div class="modal-field">
                <span class="field-label">Expense</span>
                <span class="field-value" id="financeModalFieldExpense"></span>
            </div>
            <div class="modal-field">
                <span class="field-label">Income</span>
                <span class="field-value" id="financeModalFieldIncome"></span>
            </div>
            <div class="modal-field">
                <span class="field-label">Payment Method</span>
                <span class="field-value" id="financeModalFieldMethod"></span>
            </div>
            <div class="modal-field">
                <span class="field-label">Net</span>
                <span class="field-value" id="financeModalFieldNet"></span>
            </div>
            <div class="modal-field">
                <span class="field-label">Category</span>
                <span class="field-value" id="financeModalFieldCategory"></span>
            </div>
            <div class="modal-field">
                <span class="field-label">Bank</span>
                <span class="field-value" id="financeModalFieldBank"></span>
            </div>
            <div class="modal-field">
                <span class="field-label">Created By</span>
                <span class="field-value" id="financeModalFieldCreator"></span>
            </div>
            <div class="modal-field">
                <span class="field-label">Description</span>
                <span class="field-value" id="financeModalFieldDescription"></span>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" type="button" id="financeModalCloseFooter">Close</button>
        </div>
    </div>
</div>
<script src="assets/app.js"></script>
</body>
</html>
