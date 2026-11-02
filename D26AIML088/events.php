<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$events = [];
$currentUser = null;
$databaseError = '';

try {
    $currentUser = studentHubUser();
    $result = studentHubDb()->query(
        'SELECT event_id, title, category, event_date, location, description, poster_path
         FROM events
         ORDER BY event_date, event_id
         LIMIT 100'
    );
    $events = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
} catch (RuntimeException $exception) {
    error_log('StudentHub public event list error: ' . $exception->getMessage());
    $databaseError = 'The event list could not be loaded. Run setup.php and check the database configuration.';
}

$pageTitle = 'Campus events | Student Hub';
require __DIR__ . '/partials/header.php';
?>
<header class="page-intro">
    <p class="eyebrow">Practical 15 · Integrated events</p>
    <h1>Find your next campus event.</h1>
    <p class="lede">Browse workshops, campus activities, and student meetups. Signed-in students can register without leaving this page.</p>
</header>

<?php if ($databaseError !== ''): ?>
    <p class="form-feedback form-feedback-error" role="alert"><?= studentHubEscape($databaseError) ?> <a href="setup.php">Run local setup</a>.</p>
<?php endif; ?>
<?php if ($currentUser === null): ?>
    <p class="form-feedback" role="status"><a href="login.php">Sign in</a> to register for events. Your account email must match a student record.</p>
<?php endif; ?>
<p id="event-registration-status" class="form-feedback" role="status" aria-live="polite" hidden></p>

<section class="event-grid" aria-label="Campus events">
    <?php foreach ($events as $event): ?>
        <article class="event-card">
            <?php if (is_string($event['poster_path']) && $event['poster_path'] !== ''): ?>
                <img class="event-poster" src="<?= studentHubEscape($event['poster_path']) ?>" alt="Poster for <?= studentHubEscape($event['title']) ?>" loading="lazy">
            <?php endif; ?>
            <div class="event-card-body">
                <p class="eyebrow"><?= studentHubEscape($event['category']) ?></p>
                <h2><?= studentHubEscape($event['title']) ?></h2>
                <p class="event-meta"><?= studentHubEscape($event['event_date']) ?> · <?= studentHubEscape($event['location']) ?></p>
                <p><?= studentHubEscape($event['description']) ?></p>
                <?php if ($currentUser !== null && $currentUser['role'] !== 'admin'): ?>
                    <button class="button-secondary event-registration-button" type="button" data-event-id="<?= (int) $event['event_id'] ?>">Register for event</button>
                <?php elseif ($currentUser === null): ?>
                    <a class="button-secondary" href="login.php">Sign in to register</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if ($events === [] && $databaseError === ''): ?>
        <p class="content-panel">No events have been added yet.</p>
    <?php endif; ?>
</section>
<script src="js/event-registration.js" defer></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
