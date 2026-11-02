<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$currentUser = null;
$events = [];
$counts = ['students' => 0, 'events' => 0, 'registrations' => 0];
$databaseError = '';

try {
    $currentUser = studentHubUser();
    $connection = studentHubDb();
    foreach (array_keys($counts) as $table) {
        $result = $connection->query('SELECT COUNT(*) AS record_count FROM ' . $table);
        $counts[$table] = (int) $result->fetch_assoc()['record_count'];
        $result->free();
    }

    $result = $connection->query(
        'SELECT event_id, title, category, event_date, location
         FROM events
         ORDER BY event_date, event_id
         LIMIT 3'
    );
    $events = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
} catch (RuntimeException $exception) {
    error_log('StudentHub home page error: ' . $exception->getMessage());
    $databaseError = 'Connect the database and run local setup to enable live StudentHub features.';
}

$pageTitle = 'Student Hub | Campus community';
require __DIR__ . '/partials/header.php';
?>
<section class="hero" aria-labelledby="home-title">
    <div class="hero-copy">
        <p class="eyebrow">Your campus, in one place</p>
        <h1 id="home-title">Make room for what’s next.</h1>
        <p class="lede">Discover campus events, connect students, and keep the student community moving.</p>
        <?php if ($currentUser === null): ?>
            <a class="button-link" href="register.php">Create your account</a>
        <?php else: ?>
            <a class="button-link" href="dashboard.php">Open your dashboard</a>
        <?php endif; ?>
    </div>
    <aside class="hero-visual" aria-label="A note about the Student Hub">
        <p class="visual-note">A little more connected. A lot more ready.</p>
        <span class="visual-stamp">LEARN<br>TOGETHER</span>
    </aside>
</section>

<?php if ($databaseError !== ''): ?>
    <p class="form-feedback" role="status"><?= studentHubEscape($databaseError) ?> <a href="setup.php">Run local setup</a>.</p>
<?php else: ?>
    <section class="stats-grid" aria-label="Live StudentHub statistics">
        <article class="stat-card"><p class="stat-label">Students</p><p class="stat-value"><?= $counts['students'] ?></p></article>
        <article class="stat-card"><p class="stat-label">Campus events</p><p class="stat-value"><?= $counts['events'] ?></p></article>
        <article class="stat-card"><p class="stat-label">Registrations</p><p class="stat-value"><?= $counts['registrations'] ?></p></article>
    </section>
<?php endif; ?>

<section aria-labelledby="explore-title">
    <div class="section-heading">
        <div><p class="eyebrow">Start here</p><h2 id="explore-title">Explore your hub</h2></div>
        <p>Useful tools for every part of student life.</p>
    </div>
    <div class="feature-grid">
        <article class="feature-card"><span class="feature-index" aria-hidden="true">01</span><h3>Campus events</h3><p>Browse workshops, meetups, and opportunities to try something new.</p><a href="events.php">Browse events</a></article>
        <article class="feature-card"><span class="feature-index" aria-hidden="true">02</span><h3>Your dashboard</h3><p>See live statistics, upcoming events, and your active registrations.</p><a href="<?= $currentUser === null ? 'login.php' : 'dashboard.php' ?>">Open dashboard</a></article>
        <article class="feature-card"><span class="feature-index" aria-hidden="true">03</span><h3>Student services</h3><p>Find student records and campus information in one place.</p><a href="register.php">Get started</a></article>
    </div>
</section>

<?php if ($events !== []): ?>
    <section class="content-panel" aria-labelledby="home-events-title">
        <div class="section-heading">
            <div><p class="eyebrow">Coming up</p><h2 id="home-events-title">Upcoming events</h2></div>
            <a href="events.php">View event catalogue</a>
        </div>
        <ul class="upcoming-list">
            <?php foreach ($events as $event): ?>
                <li><strong><?= studentHubEscape($event['title']) ?></strong><span><?= studentHubEscape($event['event_date']) ?> · <?= studentHubEscape($event['location']) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
