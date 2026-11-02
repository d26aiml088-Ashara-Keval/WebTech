<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
$currentUser = studentHubRequireLogin();

$stats = ['students' => 0, 'events' => 0, 'registrations' => 0];
$error = '';

try {
    $connection = studentHubDb();
    foreach (array_keys($stats) as $table) {
        $result = $connection->query('SELECT COUNT(*) AS record_count FROM ' . $table);
        $stats[$table] = (int) $result->fetch_assoc()['record_count'];
        $result->free();
    }

    $recentEventsResult = $connection->query(
        'SELECT event_id, title, category, event_date, location
         FROM events
         ORDER BY event_date, event_id
         LIMIT 5'
    );
    $recentEvents = $recentEventsResult->fetch_all(MYSQLI_ASSOC);
    $recentEventsResult->free();

    if ($currentUser['role'] !== 'admin') {
        $statement = $connection->prepare(
            'SELECT COUNT(*) AS registration_count
             FROM registrations AS r
             INNER JOIN students AS s ON s.student_id = r.student_id
             WHERE s.email = ? AND r.status = \'registered\''
        );
        $email = $currentUser['email'];
        $statement->bind_param('s', $email);
        $statement->execute();
        $registrationCount = (int) $statement->get_result()->fetch_assoc()['registration_count'];
        $statement->close();
        $stats['registrations'] = $registrationCount;
    }
} catch (RuntimeException $exception) {
    error_log('StudentHub dashboard error: ' . $exception->getMessage());
    $error = 'Dashboard data could not be loaded. Run setup.php and check the database configuration.';
    $recentEvents = [];
}

$pageTitle = 'Dashboard | Student Hub';
require __DIR__ . '/partials/header.php';
?>
<header class="page-intro">
    <p class="eyebrow"><?= studentHubEscape(ucfirst($currentUser['role'])) ?> dashboard</p>
    <h1>Welcome, <?= studentHubEscape($currentUser['display_name']) ?>.</h1>
    <p class="lede">Your StudentHub account is signed in. Your available tools are based on your assigned role.</p>
</header>

<?php if ($error !== ''): ?><p class="form-feedback form-feedback-error" role="alert"><?= studentHubEscape($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="StudentHub statistics">
    <?php if ($currentUser['role'] === 'admin'): ?>
        <article class="stat-card"><p class="stat-label">Students</p><p class="stat-value"><?= $stats['students'] ?></p><a href="students.php">Manage students</a></article>
        <article class="stat-card"><p class="stat-label">Campus events</p><p class="stat-value"><?= $stats['events'] ?></p><a href="practical-12.php">Manage events</a></article>
        <article class="stat-card"><p class="stat-label">Registrations</p><p class="stat-value"><?= $stats['registrations'] ?></p><a href="admin.php">Open admin dashboard</a></article>
    <?php else: ?>
        <article class="stat-card"><p class="stat-label">Upcoming events</p><p class="stat-value"><?= $stats['events'] ?></p><a href="events.php">Browse events</a></article>
        <article class="stat-card"><p class="stat-label">Your active registrations</p><p class="stat-value"><?= $stats['registrations'] ?></p><a href="events.php">Manage registrations</a></article>
    <?php endif; ?>
</section>

<section class="content-panel" aria-labelledby="upcoming-title">
    <div class="section-heading">
        <div><p class="eyebrow">Campus calendar</p><h2 id="upcoming-title">Upcoming events</h2></div>
        <a href="events.php">View all events</a>
    </div>
    <div class="table-wrapper">
        <table>
            <caption>Next campus events</caption>
            <thead><tr><th scope="col">Event</th><th scope="col">Category</th><th scope="col">Date</th><th scope="col">Location</th></tr></thead>
            <tbody>
                <?php foreach ($recentEvents as $event): ?>
                    <tr><td><?= studentHubEscape($event['title']) ?></td><td><?= studentHubEscape($event['category']) ?></td><td><?= studentHubEscape($event['event_date']) ?></td><td><?= studentHubEscape($event['location']) ?></td></tr>
                <?php endforeach; ?>
                <?php if ($recentEvents === []): ?><tr><td colspan="4">No events have been added.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
