<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app.php';
require_once dirname(__DIR__) . '/event-functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function studentHubApiRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function studentHubApiBody(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
        throw new InvalidArgumentException('Send event data as a JSON object.');
    }

    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new InvalidArgumentException('Send event data as a JSON object.');
    }

    return $decoded;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        $search = isset($_GET['q']) && is_string($_GET['q']) ? trim(substr($_GET['q'], 0, 100)) : '';
        $pattern = '%' . $search . '%';
        $statement = studentHubDb()->prepare(
            "SELECT event_id, title, category, event_date, location, description, poster_path
             FROM events
             WHERE (? = '' OR title LIKE ? OR category LIKE ? OR location LIKE ?)
             ORDER BY event_date, event_id
             LIMIT 100"
        );
        $statement->bind_param('ssss', $search, $pattern, $pattern, $pattern);
        $statement->execute();
        $events = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();
        studentHubApiRespond(['events' => $events]);
    } catch (RuntimeException $exception) {
        error_log('StudentHub event API read error: ' . $exception->getMessage());
        studentHubApiRespond(['error' => 'Events could not be loaded.'], 500);
    }
}

if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    studentHubApiRespond(['error' => 'This HTTP method is not supported.'], 405);
}

$currentUser = studentHubUser();
if ($currentUser === null) {
    studentHubApiRespond(['error' => 'Sign in to manage events.'], 401);
}
if ($currentUser['role'] !== 'admin') {
    studentHubApiRespond(['error' => 'Only administrators can change events.'], 403);
}
if (!studentHubVerifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    studentHubApiRespond(['error' => 'The request could not be verified. Reload the page and try again.'], 400);
}

$connection = null;
$transactionStarted = false;
$removedPoster = null;

try {
    $connection = studentHubDb();
    $connection->begin_transaction();
    $transactionStarted = true;

    if ($method === 'DELETE') {
        $input = studentHubApiBody();
        $eventId = filter_var($input['event_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($eventId === false) {
            throw new InvalidArgumentException('Choose a valid event to delete.');
        }

        $find = $connection->prepare('SELECT poster_path FROM events WHERE event_id = ? FOR UPDATE');
        $find->bind_param('i', $eventId);
        $find->execute();
        $event = $find->get_result()->fetch_assoc();
        $find->close();
        if ($event === null) {
            $connection->rollback();
            studentHubApiRespond(['error' => 'That event was not found.'], 404);
        }

        $removedPoster = $event['poster_path'];
        $delete = $connection->prepare('DELETE FROM events WHERE event_id = ?');
        $delete->bind_param('i', $eventId);
        $delete->execute();
        $delete->close();
        studentHubAudit('event_deleted', (int) $currentUser['user_id'], 'event', $eventId, ['source' => 'fetch_api']);
        $connection->commit();
        $transactionStarted = false;
        studentHubDeletePoster($removedPoster);
        studentHubApiRespond(['message' => 'Event deleted successfully.']);
    }

    $input = studentHubApiBody();
    [$values, $errors] = studentHubValidateEvent($input);
    if ($errors !== []) {
        $connection->rollback();
        studentHubApiRespond(['error' => 'Check the event details.', 'fields' => $errors], 422);
    }

    if ($method === 'POST') {
        $statement = $connection->prepare(
            'INSERT INTO events (title, category, event_date, location, description)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->bind_param(
            'sssss',
            $values['title'],
            $values['category'],
            $values['event_date'],
            $values['location'],
            $values['description']
        );
        $statement->execute();
        $eventId = (int) $connection->insert_id;
        $statement->close();
        $action = 'event_created';
    } else {
        $eventId = filter_var($input['event_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($eventId === false) {
            throw new InvalidArgumentException('Choose a valid event to update.');
        }

        $exists = $connection->prepare('SELECT event_id FROM events WHERE event_id = ? FOR UPDATE');
        $exists->bind_param('i', $eventId);
        $exists->execute();
        $found = $exists->get_result()->fetch_assoc() !== null;
        $exists->close();
        if (!$found) {
            $connection->rollback();
            studentHubApiRespond(['error' => 'That event was not found.'], 404);
        }

        $statement = $connection->prepare(
            'UPDATE events
             SET title = ?, category = ?, event_date = ?, location = ?, description = ?
             WHERE event_id = ?'
        );
        $statement->bind_param(
            'sssssi',
            $values['title'],
            $values['category'],
            $values['event_date'],
            $values['location'],
            $values['description'],
            $eventId
        );
        $statement->execute();
        $statement->close();
        $action = 'event_updated';
    }

    studentHubAudit($action, (int) $currentUser['user_id'], 'event', $eventId, ['source' => 'fetch_api']);
    $connection->commit();
    $transactionStarted = false;
    studentHubApiRespond([
        'message' => $method === 'POST' ? 'Event added successfully.' : 'Event updated successfully.',
        'event_id' => $eventId,
    ], $method === 'POST' ? 201 : 200);
} catch (JsonException | InvalidArgumentException $exception) {
    if ($connection instanceof mysqli && $transactionStarted) {
        $connection->rollback();
    }
    studentHubApiRespond(['error' => $exception instanceof JsonException ? 'The request body is not valid JSON.' : $exception->getMessage()], 400);
} catch (RuntimeException $exception) {
    if ($connection instanceof mysqli && $transactionStarted) {
        $connection->rollback();
    }
    error_log('StudentHub event API write error: ' . $exception->getMessage());
    studentHubApiRespond(['error' => 'The event could not be saved. Check the database and try again.'], 500);
}
