# StudentHub WebTech Practicals

This folder contains the StudentHub practical exercises. Practical 9 is the secure MySQLi user-registration implementation.

## Practical 9: setup with XAMPP

### Requirements

- XAMPP with Apache and MySQL (MariaDB)
- PHP 8.1 or later with the `mysqli` extension enabled
- The project folder available under `C:\xampp\htdocs\D26AIML088`

### Create the database

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin/`.
3. Select **Import**, choose `database.sql` from this folder, and import it. The script creates the `studenthub` database, the `users` table, and the registration audit table.

### Configure database credentials

The application reads connection details from environment variables; it does not contain a database password. Configure these variables for Apache, then restart Apache:

```apache
SetEnv STUDENTHUB_DB_HOST 127.0.0.1
SetEnv STUDENTHUB_DB_PORT 3306
SetEnv STUDENTHUB_DB_NAME studenthub
SetEnv STUDENTHUB_DB_USER root
SetEnv STUDENTHUB_DB_PASSWORD ""
```

Add the directives to the Apache configuration used for this local project. An empty password is only suitable when the local XAMPP database account is actually configured without one. If it has a password, set the local value in your environment and never commit it or include it in screenshots.

### Open and test the registration page

1. Visit `http://localhost/D26AIML088/practical-9-connection.php` and confirm the connected status and `users`/`registration_audit` counts.
2. Capture the connection status for your Practical 9 evidence, then visit `http://localhost/D26AIML088/register.php`.
3. Register a test user with a unique username and email address.
4. In phpMyAdmin, open `studenthub.users` and confirm the new row exists. The `password_hash` column must contain a hash, not the password.
5. Try submitting blank fields, an invalid email, a password shorter than 8 characters, mismatching passwords, and the same email or username again. The form should reject each invalid or duplicate case.

`practical-9.php` remains as a compatibility URL and loads `register.php`. Practical 8 continues to use PDO through the same `db.php` configuration, while Practical 9 uses MySQLi.

## Submission files

- `register.php` — registration form and server-side handling.
- `db.php` — shared PDO/MySQLi connection functions using environment variables.
- `database.sql` — database, users table, unique constraints, and audit table.
- `practical-9-connection.php` — live MySQLi connection check and table counts for screenshot evidence.
- `test-report.md` — test scenarios, expected results, and honest execution status.
- `PRACTICAL-9-SCREENSHOTS.md` — manual screenshot capture checklist.

See `PRACTICAL-7.md` and `PRACTICAL-8.md` for the other PHP practicals.
