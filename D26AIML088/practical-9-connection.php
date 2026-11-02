<?php
declare(strict_types=1);

$connection = null;
$tableCounts = [];
$connectionError = '';

try {
    require_once __DIR__ . '/db.php';
    $connection = createMysqliConnection();

    foreach (['users', 'registration_audit'] as $table) {
        $result = $connection->query('SELECT COUNT(*) AS record_count FROM ' . $table);
        $row = $result->fetch_assoc();
        $tableCounts[$table] = (int) $row['record_count'];
        $result->free();
    }
} catch (mysqli_sql_exception | RuntimeException $exception) {
    error_log('Practical 9 connection test error: ' . $exception->getMessage());
    $connectionError = 'Could not connect to the StudentHub database or read the Practical 9 tables. Check the MySQL service and database settings.';
}

if ($connection instanceof mysqli) {
    $connection->close();
}

function escapeConnectionTestHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Verify the Practical 9 StudentHub MySQLi database connection.">
    <title>Student Hub | Practical 9 Database Connection Test</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="index.html"><span class="brand-mark" aria-hidden="true">S</span> Student Hub</a>
            <nav class="site-nav" aria-label="Main navigation">
                <a href="index.html">Home</a>
                <a href="register.php">Practical 9 Registration</a>
                <a href="practical-9-connection.php" aria-current="page">Connection Test</a>
            </nav>
        </div>
    </header>

    <main class="page-shell">
        <header class="page-intro">
            <p class="eyebrow">Practical 9 · MySQLi</p>
            <h1>Database connection test</h1>
            <p class="lede">This page checks the MySQLi connection and confirms that the Practical 9 tables are accessible.</p>
        </header>

        <section class="content-panel" aria-labelledby="connection-status-title">
            <h2 id="connection-status-title">Connection status</h2>
            <?php if ($connection instanceof mysqli && $connectionError === ''): ?>
                <p class="form-feedback form-feedback-success" role="status">Connected successfully to the StudentHub database using MySQLi.</p>
                <ul>
                    <?php foreach ($tableCounts as $table => $count): ?>
                        <li><?= escapeConnectionTestHtml($table) ?>: <?= $count ?> records</li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="form-feedback form-feedback-error" role="alert"><?= escapeConnectionTestHtml($connectionError) ?></p>
            <?php endif; ?>
            <p><a class="button-link" href="register.php">Open the registration form</a></p>
        </section>
    </main>

    <footer class="site-footer"><p>Student Hub · Practical 9: MySQLi Connection Test</p></footer>
</body>
</html>
