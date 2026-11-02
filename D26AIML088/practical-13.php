<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
$currentUser = studentHubRequireRole('admin');
$pageTitle = 'Fetch API event management | Student Hub';
require __DIR__ . '/partials/header.php';
?>
<header class="page-intro">
    <p class="eyebrow">Practical 13 · JSON API and Fetch</p>
    <h1>Manage events without reloading.</h1>
    <p class="lede">This page uses asynchronous JSON requests for event search and CRUD. The PHP API revalidates every field and checks administrator authorization on the server.</p>
    <p><a class="button-secondary" href="practical-12.php">Manage event posters</a></p>
</header>

<p id="api-message" class="form-feedback" role="status" aria-live="polite" hidden></p>
<section class="registration-form" aria-labelledby="api-form-title">
    <h2 id="api-form-title">Add an event</h2>
    <form id="api-event-form">
        <input id="api-event-id" name="event_id" type="hidden">
        <div class="form-grid">
            <div class="form-field">
                <label for="api-title">Event title</label>
                <input id="api-title" name="title" type="text" minlength="3" maxlength="160" required>
            </div>
            <div class="form-field">
                <label for="api-category">Category</label>
                <input id="api-category" name="category" type="text" maxlength="60" required>
            </div>
            <div class="form-field">
                <label for="api-date">Date</label>
                <input id="api-date" name="event_date" type="date" required>
            </div>
            <div class="form-field">
                <label for="api-location">Location</label>
                <input id="api-location" name="location" type="text" maxlength="160" required>
            </div>
            <div class="form-field form-field-wide">
                <label for="api-description">Description</label>
                <textarea id="api-description" name="description" minlength="10" maxlength="3000" required></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button id="api-save" class="button-primary" type="submit">Add event</button>
            <button id="api-cancel" class="button-secondary" type="button" hidden>Cancel edit</button>
        </div>
    </form>
</section>

<section class="content-panel" aria-labelledby="api-list-title">
    <div class="section-heading">
        <div><p class="eyebrow">Fetch API</p><h2 id="api-list-title">Event catalogue</h2></div>
        <label class="form-field" for="api-search">Search events
            <input id="api-search" type="search" maxlength="100" autocomplete="off">
        </label>
    </div>
    <p id="api-loading" role="status" hidden>Loading events…</p>
    <div class="table-wrapper">
        <table>
            <caption>Events managed through the StudentHub JSON API</caption>
            <thead><tr><th scope="col">Event</th><th scope="col">Category</th><th scope="col">Date</th><th scope="col">Location</th><th scope="col">Actions</th></tr></thead>
            <tbody id="api-events"></tbody>
        </table>
    </div>
</section>
<script src="js/practical-13.js" defer></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
