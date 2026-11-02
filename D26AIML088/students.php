<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
$currentUser = studentHubRequireRole('admin');

$error = '';
$editStudent = null;
$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$departmentFilter = isset($_GET['department']) && is_string($_GET['department'])
    ? trim($_GET['department'])
    : '';
$search = substr($search, 0, 100);
$departmentFilter = substr($departmentFilter, 0, 100);

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod !== 'GET' && $requestMethod !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('This request method is not supported.');
}

if ($requestMethod === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    $action = $_POST['action'] ?? '';

    if (!studentHubVerifyCsrf(is_string($csrfToken) ? $csrfToken : null)) {
        $error = 'Your session expired. Reload the page and try again.';
    } elseif (!is_string($action)) {
        $error = 'Choose a valid student action.';
    } else {
        $connection = null;
        $transactionStarted = false;

        try {
            $connection = studentHubDb();
            $connection->begin_transaction();
            $transactionStarted = true;

            if ($action === 'delete') {
                $id = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                if ($id === false) {
                    throw new InvalidArgumentException('Choose a valid student record.');
                }

                $statement = $connection->prepare('DELETE FROM students WHERE student_id = ?');
                $statement->bind_param('i', $id);
                $statement->execute();
                $deleted = $statement->affected_rows === 1;
                $statement->close();

                if (!$deleted) {
                    throw new OutOfBoundsException('That student record no longer exists.');
                }

                studentHubAudit('student_deleted', (int) $currentUser['user_id'], 'student', $id);
                $connection->commit();
                studentHubFlash('status', 'Student deleted successfully.');
                header('Location: students.php', true, 303);
                exit;
            }

            if ($action !== 'save') {
                throw new InvalidArgumentException('Choose a valid student action.');
            }

            $name = $_POST['full_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $department = $_POST['department'] ?? '';
            $studyYear = filter_var($_POST['study_year'] ?? null, FILTER_VALIDATE_INT);
            $idValue = $_POST['student_id'] ?? '';
            $id = $idValue === '' || $idValue === '0'
                ? false
                : filter_var($idValue, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);

            if (!is_string($name) || !is_string($email) || !is_string($department)) {
                throw new InvalidArgumentException('Enter valid student details.');
            }
            if ($id === false && $idValue !== '' && $idValue !== '0') {
                throw new InvalidArgumentException('Choose a valid student record.');
            }

            $name = trim($name);
            $email = strtolower(trim($email));
            $department = trim($department);
            $nameLength = preg_match_all('/./us', $name);
            $departmentLength = preg_match_all('/./us', $department);

            if ($nameLength === false || $nameLength < 2 || $nameLength > 120) {
                throw new InvalidArgumentException('Enter a student name between 2 and 120 characters.');
            }
            if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Enter a valid student email address.');
            }
            if ($departmentLength === false || $departmentLength < 2 || $departmentLength > 100) {
                throw new InvalidArgumentException('Enter a department between 2 and 100 characters.');
            }
            if (!is_int($studyYear) || $studyYear < 1 || $studyYear > 8) {
                throw new InvalidArgumentException('Choose a study year between 1 and 8.');
            }

            if ($id === false || $id === 0) {
                $statement = $connection->prepare(
                    'INSERT INTO students (full_name, email, department, study_year)
                     VALUES (?, ?, ?, ?)'
                );
                $statement->bind_param('sssi', $name, $email, $department, $studyYear);
                $statement->execute();
                $studentId = (int) $connection->insert_id;
                $statement->close();
                $actionName = 'student_created';
                $message = 'Student added successfully.';
            } else {
                $statement = $connection->prepare(
                    'UPDATE students
                     SET full_name = ?, email = ?, department = ?, study_year = ?
                     WHERE student_id = ?'
                );
                $statement->bind_param('sssii', $name, $email, $department, $studyYear, $id);
                $statement->execute();
                $changed = $statement->affected_rows;
                $statement->close();

                if ($changed === 0) {
                    $exists = $connection->prepare('SELECT student_id FROM students WHERE student_id = ?');
                    $exists->bind_param('i', $id);
                    $exists->execute();
                    $result = $exists->get_result();
                    $found = $result->fetch_assoc() !== null;
                    $exists->close();
                    if (!$found) {
                        throw new OutOfBoundsException('That student record no longer exists.');
                    }
                }

                $studentId = $id;
                $actionName = 'student_updated';
                $message = 'Student updated successfully.';
            }

            studentHubAudit($actionName, (int) $currentUser['user_id'], 'student', $studentId);
            $connection->commit();
            studentHubFlash('status', $message);
            header('Location: students.php', true, 303);
            exit;
        } catch (InvalidArgumentException | OutOfBoundsException $exception) {
            if ($connection instanceof mysqli && $transactionStarted) {
                $connection->rollback();
            }
            $error = $exception->getMessage();
        } catch (RuntimeException $exception) {
            if ($connection instanceof mysqli && $transactionStarted) {
                $connection->rollback();
            }
            error_log('StudentHub student management error: ' . $exception->getMessage());
            $error = $exception instanceof mysqli_sql_exception && $exception->getCode() === 1062
                ? 'A student with that email address already exists.'
                : 'The student record could not be saved. Check the database and try again.';
        }
    }
}

$students = [];
$departments = [];
try {
    $connection = studentHubDb();
    $departmentResult = $connection->query('SELECT DISTINCT department FROM students ORDER BY department');
    while ($row = $departmentResult->fetch_assoc()) {
        $departments[] = $row['department'];
    }
    $departmentResult->free();

    $pattern = '%' . $search . '%';
    $statement = $connection->prepare(
        "SELECT student_id, full_name, email, department, study_year
         FROM students
         WHERE (? = '' OR full_name LIKE ? OR email LIKE ? OR department LIKE ?)
           AND (? = '' OR department = ?)
         ORDER BY full_name, student_id
         LIMIT 200"
    );
    $statement->bind_param(
        'ssssss',
        $search,
        $pattern,
        $pattern,
        $pattern,
        $departmentFilter,
        $departmentFilter
    );
    $statement->execute();
    $result = $statement->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    $statement->close();

    $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($editId !== false) {
        $edit = $connection->prepare(
            'SELECT student_id, full_name, email, department, study_year
             FROM students WHERE student_id = ?'
        );
        $edit->bind_param('i', $editId);
        $edit->execute();
        $editResult = $edit->get_result();
        $editStudent = $editResult->fetch_assoc() ?: null;
        $edit->close();
    }
} catch (RuntimeException $exception) {
    error_log('StudentHub student list error: ' . $exception->getMessage());
    $error = $error !== ''
        ? $error
        : 'Student records could not be loaded. Run setup.php and check the database configuration.';
}

$status = studentHubFlash('status');
$pageTitle = 'Student management | Student Hub';
require __DIR__ . '/partials/header.php';
?>
<header class="page-intro">
    <p class="eyebrow">Practical 11 · Student management</p>
    <h1>Student records</h1>
    <p class="lede">Create, review, edit, search, and filter student records. Changes are checked on the server and recorded in the audit log.</p>
</header>

<?php if ($status !== null): ?><p class="form-feedback form-feedback-success" role="status"><?= studentHubEscape($status) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="form-feedback form-feedback-error" role="alert"><?= studentHubEscape($error) ?></p><?php endif; ?>

<section class="registration-form" aria-labelledby="student-form-title">
    <h2 id="student-form-title"><?= $editStudent === null ? 'Add a student' : 'Edit student' ?></h2>
    <form action="students.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= studentHubEscape(studentHubCsrfToken()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="student_id" value="<?= (int) ($editStudent['student_id'] ?? 0) ?>">
        <div class="form-grid">
            <div class="form-field">
                <label for="full-name">Full name</label>
                <input id="full-name" name="full_name" type="text" minlength="2" maxlength="120" value="<?= studentHubEscape($editStudent['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-field">
                <label for="student-email">Email</label>
                <input id="student-email" name="email" type="email" maxlength="254" value="<?= studentHubEscape($editStudent['email'] ?? '') ?>" required>
            </div>
            <div class="form-field">
                <label for="department">Department / course</label>
                <input id="department" name="department" type="text" maxlength="100" value="<?= studentHubEscape($editStudent['department'] ?? '') ?>" required>
            </div>
            <div class="form-field">
                <label for="study-year">Study year</label>
                <select id="study-year" name="study_year" required>
                    <option value="">Choose a year</option>
                    <?php for ($year = 1; $year <= 8; $year++): ?>
                        <option value="<?= $year ?>" <?= (int) ($editStudent['study_year'] ?? 0) === $year ? 'selected' : '' ?>>Year <?= $year ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button class="button-primary" type="submit"><?= $editStudent === null ? 'Add student' : 'Save changes' ?></button>
            <?php if ($editStudent !== null): ?><a class="button-secondary" href="students.php">Cancel edit</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="content-panel" aria-labelledby="student-list-title">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Search and filter</p>
            <h2 id="student-list-title">Student directory</h2>
        </div>
        <p><?= count($students) ?> matching records</p>
    </div>
    <form action="students.php" method="get" class="filter-form">
        <div class="form-field">
            <label for="student-search">Name, email, or department</label>
            <input id="student-search" name="search" type="search" maxlength="100" value="<?= studentHubEscape($search) ?>">
        </div>
        <div class="form-field">
            <label for="department-filter">Department</label>
            <select id="department-filter" name="department">
                <option value="">All departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= studentHubEscape($department) ?>" <?= $departmentFilter === $department ? 'selected' : '' ?>><?= studentHubEscape($department) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button class="button-primary" type="submit">Search</button>
            <a class="button-secondary" href="students.php">Show all</a>
        </div>
    </form>
    <div class="table-wrapper">
        <table>
            <caption>Student records matching the current search and department filter</caption>
            <thead><tr><th scope="col">ID</th><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Department</th><th scope="col">Year</th><th scope="col">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= (int) $student['student_id'] ?></td>
                        <td><?= studentHubEscape($student['full_name']) ?></td>
                        <td><?= studentHubEscape($student['email']) ?></td>
                        <td><?= studentHubEscape($student['department']) ?></td>
                        <td><?= (int) $student['study_year'] ?></td>
                        <td class="table-actions">
                            <a href="students.php?edit=<?= (int) $student['student_id'] ?>">Edit</a>
                            <form action="students.php" method="post" onsubmit="return confirm('Delete this student and their event registrations?');">
                                <input type="hidden" name="csrf_token" value="<?= studentHubEscape(studentHubCsrfToken()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>">
                                <button class="text-button" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($students === []): ?><tr><td colspan="6">No student records match this search.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
