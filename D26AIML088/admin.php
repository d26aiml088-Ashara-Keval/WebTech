<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
$currentUser = studentHubRequireRole('admin');

$stats = ['users' => 0, 'students' => 0, 'events' => 0, 'registrations' => 0];
$auditRows = [];
$error = '';

try {
    $connection = studentHubDb();
    foreach (array_keys($stats) as $table) {
        $result = $connection->query('SELECT COUNT(*) AS record_count FROM ' . $table);
        $stats[$table] = (int) $result->fetch_assoc()['record_count'];
        $result->free();
    }

    $result = $connection->query(
        'SELECT a.audit_id, a.action, a.entity_type, a.entity_id, a.ip_address, a.details, a.created_at,
                u.username
         FROM audit_log AS a
         LEFT JOIN users AS u ON u.user_id = a.actor_user_id
         ORDER BY a.created_at DESC, a.audit_id DESC
         LIMIT 50'
    );
    $auditRows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
} catch (RuntimeException $exception) {
    error_log('StudentHub admin dashboard error: ' . $exception->getMessage());
    $error = 'Dashboard statistics or audit entries could not be loaded. Run setup.php and check the database configuration.';
}

$pageTitle = 'Admin dashboard | Student Hub';
require __DIR__ . '/partials/header.php';
?>
<header class="page-intro">
    <p class="eyebrow">Practical 14 · Administrator · <?= studentHubEscape($currentUser['username']) ?></p>
    <h1>StudentHub administration.</h1>
    <p class="lede">Statistics are read from MySQL. Student and event management pages require administrator access on every request.</p>
</header>

<?php if ($error !== ''): ?><p class="form-feedback form-feedback-error" role="alert"><?= studentHubEscape($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="Live StudentHub statistics">
    <?php foreach ($stats as $label => $count): ?>
        <article class="stat-card">
            <p class="stat-label"><?= studentHubEscape(ucfirst($label)) ?></p>
            <p class="stat-value"><?= $count ?></p>
            <?php if ($label === 'students'): ?><a href="students.php">Manage students</a><?php endif; ?>
            <?php if ($label === 'events'): ?><a href="practical-12.php">Manage events</a><?php endif; ?>
            <?php if ($label === 'registrations'): ?><a href="events.php">View event catalogue</a><?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<section class="content-panel" aria-labelledby="audit-title">
    <div class="section-heading">
        <div><p class="eyebrow">Traceability</p><h2 id="audit-title">Recent audit activity</h2></div>
        <p>Latest <?= count($auditRows) ?> entries</p>
    </div>
    <div class="table-wrapper">
        <table>
            <caption>Recent user, student, event, and registration actions</caption>
            <thead><tr><th scope="col">Time</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col">IP address</th><th scope="col">Details</th></tr></thead>
            <tbody>
                <?php foreach ($auditRows as $entry): ?>
                    <tr>
                        <td><?= studentHubEscape($entry['created_at']) ?></td>
                        <td><?= studentHubEscape($entry['username'] ?? 'System / removed user') ?></td>
                        <td><?= studentHubEscape($entry['action']) ?></td>
                        <td><?= studentHubEscape(trim(($entry['entity_type'] ?? '') . ' ' . ($entry['entity_id'] ?? ''))) ?></td>
                        <td><?= studentHubEscape($entry['ip_address'] ?? 'Not recorded') ?></td>
                        <td><?= studentHubEscape($entry['details'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($auditRows === []): ?><tr><td colspan="6">No audit activity has been recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
