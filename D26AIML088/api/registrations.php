<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function studentHubRegistrationRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = studentHubUser();
if ($currentUser === null) {
    studentHubRegistrationRespond(['error' => 'Sign in to manage your event registrations.'], 401);
}

$connection = null;
try {
    $connection = studentHubDb();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $studentLookup = $connection->prepare('SELECT student_id FROM students WHERE email = ? LIMIT 1');
    $studentEmail = $currentUser['email'];
    $studentLookup->bind_param('s', $studentEmail);
    $studentLookup->execute();
    $studentRow = $studentLookup->get_result()->fetch_assoc();
    $studentLookup->close();

    if ($method === 'GET') {
        if ($studentRow === null) {
            studentHubRegistrationRespond(['linked' => false, 'registrations' => []]);
        }

        $studentId = (int) $studentRow['student_id'];
        $statement = $connection->prepare(
            "SELECT r.event_id, r.status
             FROM registrations AS r
             WHERE r.student_id = ? AND r.status <> 'cancelled'"
        );
        $statement->bind_param('i', $studentId);
        $statement->execute();
        $registrations = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();
        studentHubRegistrationRespond(['linked' => true, 'registrations' => $registrations]);
    }

    if (!in_array($method, ['POST', 'DELETE'], true)) {
        header('Allow: GET, POST, DELETE');
        studentHubRegistrationRespond(['error' => 'This HTTP method is not supported.'], 405);
    }

    if ($currentUser['role'] !== 'student') {
        studentHubRegistrationRespond(['error' => 'Only student accounts can change event registrations.'], 403);
    }

    if (!studentHubVerifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        studentHubRegistrationRespond(['error' => 'The request could not be verified. Reload the page and try again.'], 400);
    }
    if ($studentRow === null) {
        studentHubRegistrationRespond([
            'error' => 'Your account is not linked to a student record. Ask an administrator to add a student record with your account email.',
        ], 409);
    }

    $rawBody = file_get_contents('php://input');
    $input = is_string($rawBody) && $rawBody !== ''
        ? json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR)
        : null;
    if (!is_array($input) || array_is_list($input)) {
        studentHubRegistrationRespond(['error' => 'Send the event ID as a JSON object.'], 400);
    }

    $eventId = filter_var($input['event_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($eventId === false) {
        studentHubRegistrationRespond(['error' => 'Choose a valid event.'], 422);
    }

    $studentId = (int) $studentRow['student_id'];
    $connection->begin_transaction();

    if ($method === 'POST') {
        $statement = $connection->prepare(
            "INSERT INTO registrations (student_id, event_id, status)
             VALUES (?, ?, 'registered')"
        );
        $statement->bind_param('ii', $studentId, $eventId);
        $statement->execute();
        $statement->close();
        studentHubAudit(
            'event_registration_created',
            (int) $currentUser['user_id'],
            'event',
            $eventId,
            ['student_id' => $studentId]
        );
        $connection->commit();
        studentHubRegistrationRespond(['message' => 'You are registered for this event.'], 201);
    }

    $statement = $connection->prepare(
        "UPDATE registrations
         SET status = 'cancelled'
         WHERE student_id = ? AND event_id = ? AND status = 'registered'"
    );
    $statement->bind_param('ii', $studentId, $eventId);
    $statement->execute();
    $changed = $statement->affected_rows;
    $statement->close();

    if ($changed !== 1) {
        $connection->rollback();
        studentHubRegistrationRespond(['error' => 'An active registration for this event was not found.'], 404);
    }

    studentHubAudit(
        'event_registration_cancelled',
        (int) $currentUser['user_id'],
        'event',
        $eventId,
        ['student_id' => $studentId]
    );
    $connection->commit();
    studentHubRegistrationRespond(['message' => 'Your event registration was cancelled.']);
} catch (JsonException $exception) {
    if ($connection instanceof mysqli) {
        $connection->rollback();
    }
    studentHubRegistrationRespond(['error' => 'The request body is not valid JSON.'], 400);
} catch (RuntimeException $exception) {
    if ($connection instanceof mysqli) {
        $connection->rollback();
    }
    if ($exception instanceof mysqli_sql_exception && $exception->getCode() === 1062) {
        studentHubRegistrationRespond(['error' => 'You are already registered for this event.'], 409);
    }
    if ($exception instanceof mysqli_sql_exception && $exception->getCode() === 1452) {
        studentHubRegistrationRespond(['error' => 'That event no longer exists.'], 404);
    }
    error_log('StudentHub registration API error: ' . $exception->getMessage());
    studentHubRegistrationRespond(['error' => 'The registration could not be saved. Check the database and try again.'], 500);
}
