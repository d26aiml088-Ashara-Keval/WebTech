<?php
$navigationUser = array_key_exists('currentUser', get_defined_vars())
    ? $currentUser
    : studentHubUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= studentHubEscape(studentHubCsrfToken()) ?>">
    <title><?= studentHubEscape($pageTitle ?? 'Student Hub') ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">S</span> Student Hub</a>
            <nav class="site-nav" aria-label="Main navigation">
                <a href="index.php">Home</a>
                <a href="events.php">Events</a>
                <?php if ($navigationUser !== null): ?>
                    <a href="dashboard.php">Dashboard</a>
                    <?php if ($navigationUser['role'] === 'admin'): ?>
                        <a href="students.php">Students</a>
                        <a href="practical-12.php">Manage events</a>
                        <a href="practical-13.php">AJAX manager</a>
                        <a href="admin.php">Admin</a>
                    <?php endif; ?>
                    <form class="nav-logout" action="logout.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= studentHubEscape(studentHubCsrfToken()) ?>">
                        <button class="nav-logout-button" type="submit">Log out</button>
                    </form>
                <?php else: ?>
                    <a href="login.php">Log in</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="page-shell">
