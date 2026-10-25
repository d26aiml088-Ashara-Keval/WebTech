# Practical 9: Registration Test Report

## Setup

1. Run `practical-8.sql` if the `studenthub` database does not exist.
2. Import `practical-9.sql` in phpMyAdmin to create `users` and `registration_audit`.
3. Configure `STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_PORT`, `STUDENTHUB_DB_NAME`, `STUDENTHUB_DB_USER`, and `STUDENTHUB_DB_PASSWORD` for PHP. The database environment settings are shared with Practical 8.
4. Enable PHP's `mysqli` extension and open `http://localhost/D26AIML088/practical-9.php` through XAMPP.

## Test cases

| Case | Input/action | Expected result |
|---|---|---|
| Valid registration | Name `Alex Student`, username `alex_student`, email `alex.student@example.test`, matching password `Campus123!` | Success message; one `users` row and one `user_registered` audit row are committed. |
| Password storage | Inspect the inserted row in phpMyAdmin | `password_hash` contains a password hash and does not equal the submitted password. |
| Invalid name | Submit an empty name or a name longer than 100 characters | Browser constraint or server validation rejects the request; no database row is inserted. |
| Invalid username | Submit `ab`, `alex student`, or `alex!` | Browser constraint or server validation rejects the request; no row is inserted. |
| Invalid email | Submit `not-an-email` | Browser constraint or server validation rejects the request; no row is inserted. |
| Weak/invalid password | Submit fewer than 8 characters or non-printable/non-ASCII characters | Browser constraint or server validation rejects the request; no row is inserted. |
| Password mismatch | Enter different password and confirmation values | Browser reports mismatch or server shows a validation error; no row is inserted. |
| Duplicate username | Submit a new email using the successful test username | Generic duplicate username/email message; no additional user or audit row. |
| Duplicate email | Submit a new username using the successful test email | Generic duplicate username/email message; no additional user or audit row. |
| Invalid CSRF token | Remove or change the hidden `csrf_token` and submit | Request is rejected with HTTP 400; nothing is inserted. |
| SQL injection-shaped input | Try a username/email containing SQL fragments | Input fails validation or is treated strictly as a prepared-statement value; no unintended records are read or inserted. |
| Audit transaction | Check `registration_audit` after success | One audit entry with action `user_registered` and the created user ID exists. |

The registration transaction rolls back both records if inserting the user or audit row fails. Duplicate indexes on username and email cover race conditions between the duplicate check and insert.
