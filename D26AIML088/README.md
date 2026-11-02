# StudentHub Web Technology Practicals

StudentHub combines the PHP, MySQL, authentication, CRUD, Fetch API, and admin-dashboard practicals into one locally deployable campus portal.

## Requirements

- XAMPP with Apache and MySQL/MariaDB, or PHP's built-in server and a local MySQL server.
- PHP 8.1 or later with `mysqli`, `pdo_mysql`, and `fileinfo` enabled.
- MySQL with permission to create and alter tables.

## Install and run with XAMPP

1. Copy `D26AIML088` into `C:\xampp\htdocs\D26AIML088`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin/`. Import `database.sql`, then import `practical-8.sql`. The scripts create the `studenthub` database, users, sample students, events, and registrations.
4. Configure the database environment variables for Apache and restart Apache:

   ```apache
   SetEnv STUDENTHUB_DB_HOST 127.0.0.1
   SetEnv STUDENTHUB_DB_PORT 3306
   SetEnv STUDENTHUB_DB_NAME studenthub
   SetEnv STUDENTHUB_DB_USER root
   SetEnv STUDENTHUB_DB_PASSWORD ""
   ```

   An empty password is appropriate only if the local MySQL account actually has no password. Never commit database credentials.

5. Open `http://localhost/D26AIML088/setup.php` **from the same computer**. It applies the role, event-poster, remember-me, and audit-log schema. It is intentionally restricted to local requests.
6. Create an account at `http://localhost/D26AIML088/register.php`.
7. To grant your trusted account administrator access, run this query in phpMyAdmin after replacing the username:

   ```sql
   UPDATE users SET role = 'admin' WHERE username = 'your_username';
   ```

8. Sign in at `http://localhost/D26AIML088/login.php`. Open the integrated site at `http://localhost/D26AIML088/` or `index.php`.

To try event registration as a student, register an account with the email address of an existing student record (for example, `aarav.mehta@studenthub.example`). Alternatively, an administrator can add a student record using the account email.

## Run with PHP's built-in server

Set the same database environment variables in PowerShell, then run the server from this folder:

```powershell
$env:STUDENTHUB_DB_USER = 'root'
$env:STUDENTHUB_DB_PASSWORD = ''
php -S localhost:8000 router.php
```

Open `http://localhost:8000/`. The router serves `index.php`, redirects the previous static page URLs to the integrated pages, and blocks direct requests to private storage, configuration, and non-image poster files.

## Practical modules

| Practical | Implementation |
|---|---|
| 7 | `practical-7.php` — server-side form validation, CSRF, and private JSON storage |
| 8 | `practical-8.sql`, `practical-8.php` — normalized MySQL schema, PDO connection, prepared lookup |
| 9 | `register.php`, `database.sql`, `practical-9-connection.php` — MySQLi registration, password hashing, duplicate checks, and connection status |
| 10 | `login.php`, `logout.php`, `app.php` — password verification, regenerated sessions, role checks, five-minute inactivity timeout, and rotating 30-day remember-me token |
| 11 | `students.php` — administrator-only student CRUD with search and department filter |
| 12 | `practical-12.php`, `event-functions.php` — event CRUD and JPEG/PNG/WebP poster validation (2 MB maximum) |
| 13 | `practical-13.php`, `api/events.php`, `api/registrations.php` — JSON APIs and Fetch-based CRUD/registration without page reload |
| 14 | `admin.php`, `setup.php` — role-protected dashboard, live statistics, and audit log |
| 15 | `index.php`, `dashboard.php`, `events.php`, `README.md`, `test-report.md` — integrated navigation, role-aware pages, deployment notes, and test evidence |

`database.sql` and `practical-8.sql` can be imported again without duplicating their sample records. Run `setup.php` after either import so the incremental schema is present. Existing static page URLs such as `login.html`, `events.html`, and `Dashboard.html` redirect to the integrated PHP pages.

## Security and operations

- User input is revalidated by PHP; database operations use prepared statements.
- Passwords use `password_hash()` and `password_verify()`. There are no built-in accounts or hard-coded passwords.
- Session cookies are HttpOnly and SameSite; authentication regenerates the session ID. Remember-me cookies contain random tokens while only token hashes are stored in MySQL.
- Mutating forms and JSON API requests require CSRF tokens. Student and event management pages and API writes verify the administrator role on the server.
- Poster uploads are checked using the detected MIME type and image decoder, receive random file names, and are limited to 2 MB. Executable uploads are blocked by Apache rules and the built-in server router.
- Database details are read from environment variables. Errors are logged on the server and replaced with generic user-facing messages.
- The admin dashboard records login, logout, registration, student, event, and event-registration activity.

## Verification and screenshots

Follow `test-report.md` for the manual integration checklist. `PRACTICAL-9-SCREENSHOTS.md` and `PRACTICAL-10-15-SCREENSHOTS.md` document the screenshot evidence checklists. Capture screenshots from the running local XAMPP/MySQL application using fictional data; do not include passwords, database credentials, or real personal information. Runtime/database scenarios are marked as manual where this environment has no PHP/MySQL server.
