<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/event-functions.php';
$currentUser = studentHubRequireRole('admin');

$error = '';
$editEvent = null;
$uploadedPoster = null;
$transactionStarted = false;
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod !== 'GET' && $requestMethod !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('This request method is not supported.');
}

if ($requestMethod === 'POST') {
    $connection = null;

    try {
        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!studentHubVerifyCsrf(is_string($csrfToken) ? $csrfToken : null)) {
            throw new InvalidArgumentException('Your session expired. Reload the page and try again.');
        }

        $action = $_POST['action'] ?? '';
        if (!is_string($action) || !in_array($action, ['save', 'delete'], true)) {
            throw new InvalidArgumentException('Choose a valid event action.');
        }

        $connection = studentHubDb();
        $connection->begin_transaction();
        $transactionStarted = true;

        if ($action === 'delete') {
            $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($eventId === false) {
                throw new InvalidArgumentException('Choose a valid event to delete.');
            }

            $findPoster = $connection->prepare('SELECT poster_path FROM events WHERE event_id = ? FOR UPDATE');
            $findPoster->bind_param('i', $eventId);
            $findPoster->execute();
            $posterRow = $findPoster->get_result()->fetch_assoc();
            $findPoster->close();

            if ($posterRow === null) {
                throw new OutOfBoundsException('That event no longer exists.');
            }

            $delete = $connection->prepare('DELETE FROM events WHERE event_id = ?');
            $delete->bind_param('i', $eventId);
            $delete->execute();
            $delete->close();
            studentHubAudit('event_deleted', (int) $currentUser['user_id'], 'event', $eventId);
            $connection->commit();
            $transactionStarted = false;
            studentHubDeletePoster($posterRow['poster_path']);
            studentHubFlash('status', 'Event deleted successfully.');
            header('Location: practical-12.php', true, 303);
            exit;
        }

        [$values, $errors] = studentHubValidateEvent($_POST);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', array_values($errors)));
        }

        $eventIdValue = $_POST['event_id'] ?? '';
        $eventId = $eventIdValue === '' || $eventIdValue === '0'
            ? false
            : filter_var($eventIdValue, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
        if ($eventId === false && $eventIdValue !== '' && $eventIdValue !== '0') {
            throw new InvalidArgumentException('Choose a valid event to update.');
        }

        $existingPoster = null;
        if ($eventId !== false) {
            $findPoster = $connection->prepare('SELECT poster_path FROM events WHERE event_id = ? FOR UPDATE');
            $findPoster->bind_param('i', $eventId);
            $findPoster->execute();
            $posterRow = $findPoster->get_result()->fetch_assoc();
            $findPoster->close();

            if ($posterRow === null) {
                throw new OutOfBoundsException('That event no longer exists.');
            }
            $existingPoster = $posterRow['poster_path'];
        }

        $uploadedPoster = studentHubSavePoster(isset($_FILES['poster']) && is_array($_FILES['poster']) ? $_FILES['poster'] : null);
        $posterPath = $uploadedPoster ?? $existingPoster;

        if ($eventId === false) {
            $statement = $connection->prepare(
                'INSERT INTO events (title, category, event_date, location, description, poster_path)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->bind_param(
                'ssssss',
                $values['title'],
                $values['category'],
                $values['event_date'],
                $values['location'],
                $values['description'],
                $posterPath
            );
            $statement->execute();
            $eventId = (int) $connection->insert_id;
            $statement->close();
            $auditAction = 'event_created';
            $message = 'Event added successfully.';
        } else {
            $statement = $connection->prepare(
                'UPDATE events
                 SET title = ?, category = ?, event_date = ?, location = ?, description = ?, poster_path = ?
                 WHERE event_id = ?'
            );
            $statement->bind_param(
                'ssssssi',
                $values['title'],
                $values['category'],
                $values['event_date'],
                $values['location'],
                $values['description'],
                $posterPath,
                $eventId
            );
            $statement->execute();
            $statement->close();
            $auditAction = 'event_updated';
            $message = 'Event updated successfully.';
        }

        studentHubAudit($auditAction, (int) $currentUser['user_id'], 'event', $eventId);
        $connection->commit();
        $transactionStarted = false;

        if ($uploadedPoster !== null && $existingPoster !== null && $existingPoster !== $uploadedPoster) {
            studentHubDeletePoster($existingPoster);
        }

        studentHubFlash('status', $message);
        header('Location: practical-12.php', true, 303);
        exit;
    } catch (mysqli_sql_exception $exception) {
        if ($connection instanceof mysqli && $transactionStarted) {
            $connection->rollback();
        }
        if ($uploadedPoster !== null) {
            studentHubDeletePoster($uploadedPoster);
        }
        error_log('StudentHub event management error: ' . $exception->getMessage());
        $error = 'The event could not be saved. Check the database and try again.';
    } catch (InvalidArgumentException | OutOfBoundsException $exception) {
        if ($connection instanceof mysqli && $transactionStarted) {
            $connection->rollback();
        }
        if ($uploadedPoster !== null) {
            studentHubDeletePoster($uploadedPoster);
        }
        $error = $exception->getMessage();
    } catch (RuntimeException $exception) {
        if ($connection instanceof mysqli && $transactionStarted) {
            $connection->rollback();
        }
        if ($uploadedPoster !== null) {
            studentHubDeletePoster($uploadedPoster);
        }
        error_log('StudentHub event management error: ' . $exception->getMessage());
        $error = 'The event could not be saved. Check the database and try again.';
    }
}

$events = [];
$search = isset($_GET['search']) && is_string($_GET['search']) ? substr(trim($_GET['search']), 0, 100) : '';
$databaseError = '';
try {
    $connection = studentHubDb();
    $pattern = '%' . $search . '%';
    $statement = $connection->prepare(
        "SELECT event_id, title, category, event_date, location, description, poster_path
         FROM events
         WHERE (? = '' OR title LIKE ? OR category LIKE ? OR location LIKE ?)
         ORDER BY event_date DESC, event_id DESC
         LIMIT 200"
    );
    $statement->bind_param('ssss', $search, $pattern, $pattern, $pattern);
    $statement->execute();
    $events = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();

    $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($editId !== false) {
        $edit = $connection->prepare(
            'SELECT event_id, title, category, event_date, location, description, poster_path
             FROM events WHERE event_id = ?'
        );
        $edit->bind_param('i', $editId);
        $edit->execute();
        $editEvent = $edit->get_result()->fetch_assoc() ?: null;
        $edit->close();
    }
} catch (RuntimeException $exception) {
    error_log('StudentHub event list error: ' . $exception->getMessage());
    $databaseError = 'Events could not be loaded. Run setup.php and check the database configuration.';
}

$status = studentHubFlash('status');
$pageTitle = 'Event management | Student Hub';
require __DIR__ . '/partials/header.php';
?>
<header class="page-intro">
    <p class="eyebrow">Practical 12 · Event management</p>
    <h1>Manage campus events.</h1>
    <p class="lede">Add, edit, search, and remove events. Poster uploads are verified by file content, image type, and size before storage.</p>
</header>

<?php if ($status !== null): ?><p class="form-feedback form-feedback-success" role="status"><?= studentHubEscape($status) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="form-feedback form-feedback-error" role="alert"><?= studentHubEscape($error) ?></p><?php endif; ?>
<?php if ($databaseError !== ''): ?><p class="form-feedback form-feedback-error" role="alert"><?= studentHubEscape($databaseError) ?></p><?php endif; ?>

<section class="registration-form" aria-labelledby="event-form-title">
    <h2 id="event-form-title"><?= $editEvent === null ? 'Add an event' : 'Edit event' ?></h2>
    <form action="practical-12.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= studentHubEscape(studentHubCsrfToken()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="event_id" value="<?= (int) ($editEvent['event_id'] ?? 0) ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        <div class="form-grid">
            <div class="form-field">
                <label for="event-title">Event title</label>
                <input id="event-title" name="title" type="text" minlength="3" maxlength="160" value="<?= studentHubEscape($editEvent['title'] ?? '') ?>" required>
            </div>
            <div class="form-field">
                <label for="event-category">Category</label>
                <input id="event-category" name="category" type="text" maxlength="60" value="<?= studentHubEscape($editEvent['category'] ?? '') ?>" required>
            </div>
            <div class="form-field">
                <label for="event-date">Date</label>
                <input id="event-date" name="event_date" type="date" value="<?= studentHubEscape($editEvent['event_date'] ?? '') ?>" required>
            </div>
            <div class="form-field">
                <label for="event-location">Location</label>
                <input id="event-location" name="location" type="text" maxlength="160" value="<?= studentHubEscape($editEvent['location'] ?? '') ?>" required>
            </div>
            <div class="form-field form-field-wide">
                <label for="event-description">Description</label>
                <textarea id="event-description" name="description" minlength="10" maxlength="3000" required><?= studentHubEscape($editEvent['description'] ?? '') ?></textarea>
            </div>
            <div class="form-field form-field-wide">
                <label for="event-poster">Poster image (JPEG, PNG, or WebP; maximum 2 MB)</label>
                <input id="event-poster" name="poster" type="file" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($editEvent['poster_path'])): ?>
                    <p>Current poster: <a href="<?= studentHubEscape($editEvent['poster_path']) ?>">View image</a></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-actions">
            <button class="button-primary" type="submit"><?= $editEvent === null ? 'Add event' : 'Save event' ?></button>
            <?php if ($editEvent !== null): ?><a class="button-secondary" href="practical-12.php">Cancel edit</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="content-panel" aria-labelledby="event-list-title">
    <div class="section-heading">
        <div><p class="eyebrow">Event catalogue</p><h2 id="event-list-title">All events</h2></div>
        <p><?= count($events) ?> events</p>
    </div>
    <form action="practical-12.php" method="get" class="filter-form">
        <div class="form-field">
            <label for="event-search">Search events</label>
            <input id="event-search" name="search" type="search" maxlength="100" value="<?= studentHubEscape($search) ?>">
        </div>
        <div class="form-actions">
            <button class="button-primary" type="submit">Search</button>
            <a class="button-secondary" href="practical-12.php">Show all</a>
        </div>
    </form>
    <div class="table-wrapper">
        <table>
            <caption>Events available in StudentHub</caption>
            <thead><tr><th scope="col">Event</th><th scope="col">Category</th><th scope="col">Date</th><th scope="col">Location</th><th scope="col">Poster</th><th scope="col">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= studentHubEscape($event['title']) ?></td>
                        <td><?= studentHubEscape($event['category']) ?></td>
                        <td><?= studentHubEscape($event['event_date']) ?></td>
                        <td><?= studentHubEscape($event['location']) ?></td>
                        <td><?= $event['poster_path'] !== null ? '<a href="' . studentHubEscape($event['poster_path']) . '">View poster</a>' : 'None' ?></td>
                        <td class="table-actions">
                            <a href="practical-12.php?edit=<?= (int) $event['event_id'] ?>">Edit</a>
                            <form action="practical-12.php" method="post" onsubmit="return confirm('Delete this event and its registrations?');">
                                <input type="hidden" name="csrf_token" value="<?= studentHubEscape(studentHubCsrfToken()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                                <button class="text-button" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($events === []): ?><tr><td colspan="6">No events match this search.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
