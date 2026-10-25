# Practical 8: MySQL Schema Design, ER Model, and PDO

## Deliverables

- `practical-8.sql` creates and seeds the normalized StudentHub schema.
- `db.php` creates a PDO connection using environment-based credentials.
- `practical-8.php` displays connection status, table counts, and a prepared student lookup.
- `practical-8-advanced.sql` is the optional stored-procedure extension.
- Practical 9's `db-mysqli.php` uses the same environment variables with MySQLi.

## Run with XAMPP

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin/`, select **Import**, and import `practical-8.sql`.
3. Configure these environment variables for the Apache process:

   - `STUDENTHUB_DB_HOST` — optional; defaults to `127.0.0.1`
   - `STUDENTHUB_DB_PORT` — optional; defaults to `3306`
   - `STUDENTHUB_DB_NAME` — optional; defaults to `studenthub`
   - `STUDENTHUB_DB_USER` — required; for a default local XAMPP installation, `root`
   - `STUDENTHUB_DB_PASSWORD` — required; may be empty only for a local XAMPP account configured without a password

   Do not add database credentials to source control. For local testing, set environment variables before starting the PHP process. For Apache, define them in its environment configuration and restart Apache.

4. Open `http://localhost/D26AIML088/practical-8.php`. A successful connection shows record counts for all three tables.
5. Search using a seeded address such as `aarav.mehta@studenthub.example` to test the PDO prepared statement.
6. Capture the page showing the successful connection and counts as the required connection-test screenshot. The screenshot must come from your running XAMPP/MySQL environment.

Use PHP 8.1+ with the `pdo_mysql` extension enabled.

For a PHP built-in server, set the same variables in the PowerShell session and run the server from this directory:

```powershell
$env:STUDENTHUB_DB_USER = 'root'
$env:STUDENTHUB_DB_PASSWORD = ''
php -S localhost:8000 router.php
```

Then open `http://localhost:8000/practical-8.php`. Use the actual MySQL account password if your local account has one.

## ER model and normalization

```text
students (student_id PK) 1 ───< registrations (registration_id PK, student_id FK, event_id FK) >─── 1 events (event_id PK)
```

`students` and `events` store attributes about one entity each. `registrations` resolves the many-to-many relationship and stores registration-specific data (`registered_at` and `status`). The unique `(student_id, event_id)` key prevents duplicates; foreign keys enforce that each registration references existing records. Unique student emails and atomic scalar columns support a normalized design without repeating event/registration lists in student rows.

The design follows 1NF by keeping column values atomic, 2NF by storing each entity's attributes with its key and keeping registration-specific values in the junction table, and 3NF by avoiding non-key attributes that depend on other non-key attributes. The unique student/event pair is a candidate key for a registration.

## Security and error handling

- PDO uses MySQL `utf8mb4`, exception-based errors, and native prepared statements.
- Credentials come from environment variables, not committed PHP source.
- Student lookup binds the user-supplied email as a parameter. Output is HTML-escaped.
- Detailed connection errors are written to the PHP error log; the page shows a generic error instead of database details.

## Optional stored-procedure extension

After importing `practical-8.sql`, import `practical-8-advanced.sql` in phpMyAdmin. It creates `RegisterStudentForEvent`, which rejects duplicate registration attempts and inserts a valid student/event pair. Foreign keys reject unknown IDs.

For a quick procedure test, run `CALL RegisterStudentForEvent(15, 10);` in phpMyAdmin, then run it again. The first call inserts a registration; the second reports the intentional duplicate-registration error.

## Manual checks

1. Search for `aarav.mehta@studenthub.example`; the matching student should be displayed.
2. Search for another valid but unknown email; the page should show the no-match message.
3. Send a crafted value such as `email=' OR 1=1 -- ` in the query string; the prepared lookup should return no student rather than listing records.
4. Delete a student or event that has registrations in a local test database; related registrations should be deleted through the foreign-key cascade.
