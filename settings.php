<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT name, email, currency FROM users WHERE id = ?');
$stmt->execute([$userId]);
$userRow = $stmt->fetch();

if ($userRow) {
    $_SESSION['user_name']  = $userRow['name'];
    $_SESSION['user_email'] = $userRow['email'];
    $_SESSION['currency']   = $userRow['currency'] ?? 'USD';
}

$userName  = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$currentCurrency = $_SESSION['currency'] ?? 'USD';

$success = '';
$error   = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'currency') {
    $currency = $_POST['currency'] ?? 'USD';
    if (!in_array($currency, ['USD','SAR','EGP','INR'], true)) {
        $currency = 'USD';
    }


    $stmt = $pdo->prepare('UPDATE users SET currency = ? WHERE id = ?');
    $stmt->execute([$currency, $userId]);

    $_SESSION['currency'] = $currency;
    $currentCurrency = $currency;
    $success = 'Currency saved successfully.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_category') {
    $catName = trim($_POST['category_name'] ?? '');
    if ($catName === '') {
        $error = 'Category name cannot be empty.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (user_id, name) VALUES (:uid, :name)");
            $stmt->execute([
                ':uid'  => $userId,
                ':name' => $catName
            ]);
            $success = 'Category added.';
        } catch (PDOException $e) {

            $error = 'This category already exists.';
        }
    }
}


if (isset($_GET['delete_category'])) {
    $catId = (int)$_GET['delete_category'];
    if ($catId > 0) {
        try {
            $pdo->beginTransaction();

            if ($tenantMode === 'isolated') {
                // Isolated mode: delete only this user's category + unlink their transactions
                $stmt = $pdo->prepare("
                    UPDATE transactions 
                    SET category_id = NULL 
                    WHERE user_id = :uid AND category_id = :cid
                ");
                $stmt->execute([
                    ':uid' => $userId,
                    ':cid' => $catId
                ]);

                $stmt = $pdo->prepare("
                    DELETE FROM categories 
                    WHERE id = :cid AND user_id = :uid
                ");
                $stmt->execute([
                    ':cid' => $catId,
                    ':uid' => $userId
                ]);
            } else {
                // Shared mode: category is global, affect all users
                $stmt = $pdo->prepare("
                    UPDATE transactions 
                    SET category_id = NULL 
                    WHERE category_id = :cid
                ");
                $stmt->execute([
                    ':cid' => $catId
                ]);

                $stmt = $pdo->prepare("
                    DELETE FROM categories 
                    WHERE id = :cid
                ");
                $stmt->execute([
                    ':cid' => $catId
                ]);
            }

            $pdo->commit();
            $success = 'Category deleted.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Could not delete category.';
        }
    }
}








// Hard reset: wipe all data and users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hard_reset') {
    $confirmText = strtolower(trim($_POST['confirm_text'] ?? ''));

    if ($confirmText !== 'delete') {
        $error = 'You must type delete to reset everything.';
    } else {
        try {
            $pdo->beginTransaction();


            $pdo->exec("DELETE FROM transactions");
            $pdo->exec("DELETE FROM categories");
            $pdo->exec("DELETE FROM users");

            $pdo->commit();


            session_destroy();
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'System reset failed. Please try again.';
        }
    }
}






if ($tenantMode === 'isolated') {
    // Each user sees only their own categories
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = :uid ORDER BY name ASC");
    $stmt->execute([':uid' => $userId]);
} else {
    // Shared mode: all users see all categories
    $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name ASC");
    $stmt->execute();
}
$categories = $stmt->fetchAll();

$symbolMap = [
    'USD' => '$',
    'SAR' => '﷼',
    'EGP' => '£',
    'INR' => '₹',
];

$symbol = $symbolMap[$currentCurrency] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings - ExpenseFlow</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="layout">
    <aside class="sidebar"  id="sidebar">
        <div class="logo">
    Expense<span>Flow</span>
    <?php if (!empty($companyName)): ?>
        <div class="company-name"><?= htmlspecialchars($companyName) ?></div>
    <?php endif; ?>
</div>

<ul class="nav-links">
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="report.php">Report History</a></li>
    <li><a href="analysis.php" >Analysis</a></li>
    <li><a href="settings.php" class="active">Settings</a></li>
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
                <div class="page-title">Settings</div>
                <div class="page-subtitle">Currency & Categories</div>
            </div>
        </div>

        <section class="card">
            <?php if ($success): ?>
                <div class="badge" style="background:#dcfce7;color:#166534;margin-bottom:10px;">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="badge" style="background:#fee2e2;color:#b91c1c;margin-bottom:10px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="section-title">Currency</div>
            <form method="post" style="margin-top:10px;">
                <input type="hidden" name="action" value="currency">
                <div class="form-group">
                    <label>Currency</label>
<select name="currency">
    <option value="USD" <?= $currentCurrency==='USD'?'selected':'' ?>>
        USD ($) – Dollar
    </option>
    <option value="SAR" <?= $currentCurrency==='SAR'?'selected':'' ?>>
        SAR (﷼) – Saudi Riyal
    </option>
    <option value="EGP" <?= $currentCurrency==='EGP'?'selected':'' ?>>
        EGP (£) – Egyptian Pound
    </option>
    <option value="INR" <?= $currentCurrency==='INR'?'selected':'' ?>>
        INR (₹) – Indian Rupee
    </option>
</select>

                </div>
                <div class="page-subtitle" style="margin-top:8px;">
                    Current symbol: <strong><?= htmlspecialchars($symbol) ?></strong>
                </div>
                <div class="form-actions" style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary">Save Currency</button>
                </div>
            </form>
        </section>

        <section class="card" style="margin-top:18px;">
            <div class="section-title">Categories</div>

            <form method="post" style="margin-top:10px;">
                <input type="hidden" name="action" value="add_category">
                <div class="form-grid">
                    <div class="form-group">
                        <label>New Category Name</label>
                        <input type="text" name="category_name" placeholder="e.g. Groceries, Rent, Salary" required>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:12px;">
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>

            <div class="mt-16">
                <div class="summary-card-title" style="margin-bottom:8px;">Existing Categories</div>
                <?php if (!$categories): ?>
                    <div class="page-subtitle">No categories yet.</div>
                <?php else: ?>
                    <div class="tx-list">
                        <?php foreach ($categories as $cat): ?>
                            <div class="tx-item">
                                <div class="tx-main">
                                    <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                </div>
                                <div>
                                    <a class="tx-actions"
                                       href="settings.php?delete_category=<?= $cat['id'] ?>"
                                       onclick="return confirm('Delete this category? Transactions will lose this category but stay in history.');">
                                        Delete
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

<section class="card card-danger" style="margin-top:18px;">
    <div class="section-title">Danger Zone</div>
    <p class="page-subtitle" style="margin-top:6px;">
        Resetting will <strong>delete all users, transactions, categories and settings</strong>.
        This action cannot be undone. You will return to the registration screen.
    </p>

    <form method="post" id="hardResetForm">
        <input type="hidden" name="action" value="hard_reset">
        <input type="hidden" name="confirm_text" id="hardResetConfirm">
        <div class="form-actions" style="margin-top:14px;">
            <button type="button" class="btn btn-danger" onclick="handleHardResetClick()">
                Reset system (click 3x)
            </button>
        </div>
        <div class="page-subtitle" id="hardResetHint" style="margin-top:6px;"></div>
    </form>
</section>




    </main>
</div>


<script>
let hardResetClicks = 0;

function handleHardResetClick() {
    hardResetClicks++;
    const hint = document.getElementById('hardResetHint');
    const remaining = 3 - hardResetClicks;

    if (remaining > 0) {
        hint.textContent = 'Press ' + remaining + ' more time(s) to start reset...';
        return;
    }


    const text = prompt(
        "WARNING: This will DELETE all data and users.\nType DELETE to confirm:",
        ""
    );

    if (text === null) {

        hardResetClicks = 0;
        hint.textContent = '';
        return;
    }

    if (text.toLowerCase() !== 'delete') {
        alert('You must type delete exactly to proceed.');
        hardResetClicks = 0;
        hint.textContent = '';
        return;
    }


    document.getElementById('hardResetConfirm').value = text;
    document.getElementById('hardResetForm').submit();
}
</script>

<script src="assets/app.js"></script>
</body>
</html>
