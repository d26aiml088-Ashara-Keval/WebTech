# Practical 9: Registration Test Report

## Setup

1. Import `database.sql` in phpMyAdmin to create the `studenthub` database, `users` table, and registration audit table.
2. Configure `STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_PORT`, `STUDENTHUB_DB_NAME`, `STUDENTHUB_DB_USER`, and `STUDENTHUB_DB_PASSWORD` for PHP. The database environment settings are shared with Practical 8.
3. Enable PHP's `mysqli` extension and open `http://localhost/D26AIML088/register.php` through XAMPP.

## Test cases

| Case | Input/action | Expected result | Actual result | Status |
|---|---|---|---|---|
| Static source/schema checks | Inspect required form controls, backend validators, prepared queries, password hashing order, messages, database keys, and compatible connection factories | Required implementation and schema elements are present. | All 17 source/schema assertions passed; editor diagnostics show no errors in the checked PHP files. | Pass |
| Valid registration | Name `Alex Student`, username `alex_student`, email `alex.student@example.test`, matching password `Campus123!` | Success message; one user and one audit row are committed. | Not run; requires XAMPP/MySQL and form submission. | Manual verification |
| Successful database insertion | Query `users` for `alex_student` after valid submission | Exactly one matching user row exists. | Not run; requires XAMPP/phpMyAdmin. | Manual verification |
| Password storage | Inspect the inserted row in phpMyAdmin | `password_hash` differs from the submitted password and contains a password hash. | Not run; no live database available. | Manual verification |
| Empty fields | Submit blank name, username, email, password, and confirmation | Browser required checks and backend validation prevent insertion. | Not run; browser/PHP runtime unavailable. | Manual verification |
| Invalid name | Submit whitespace or a name longer than 100 characters | Backend rejects the value; no row is inserted. | Not run; requires PHP runtime. | Manual verification |
| Invalid username | Submit `ab`, `alex student`, or `alex!` | Browser/server rejects it; no row is inserted. | Not run; requires browser/PHP runtime. | Manual verification |
| Invalid email | Submit `not-an-email` | Browser/server rejects it; no row is inserted. | Not run; requires browser/PHP runtime. | Manual verification |
| Invalid password | Submit fewer than 8 characters or non-printable/non-ASCII characters | Browser/server rejects it; no row is inserted. | Not run; requires browser/PHP runtime. | Manual verification |
| Password mismatch | Enter different password and confirmation values | Browser/server shows validation error; no row is inserted. | Not run; requires browser/PHP runtime. | Manual verification |
| Duplicate username | Submit a new email using a previously registered username | Duplicate message; no extra user or audit row. | Not run; requires seeded registration in MySQL. | Manual verification |
| Duplicate email | Submit a new username using a previously registered email | Duplicate message; no extra user or audit row. | Not run; requires seeded registration in MySQL. | Manual verification |
| Invalid CSRF token | Remove or alter the hidden `csrf_token` and submit | Request is rejected with HTTP 400; nothing is inserted. | Not run; requires PHP runtime. | Manual verification |
| SQL injection-shaped input | Submit SQL fragments as username/email | Validation or prepared statements prevent query manipulation; no unintended insert. | Not run; requires PHP/MySQL integration test. | Manual verification |
| Audit transaction | Check `registration_audit` after successful registration | One `user_registered` row for the new user exists. | Not run; requires phpMyAdmin. | Manual verification |

The registration transaction rolls back both records if inserting the user or audit row fails. Duplicate indexes on username and email cover race conditions between the duplicate check and insert.

## Checks performed in this workspace

- Source inspection confirms frontend constraints and backend checks for name, username, email, password, and confirmation.
- Editor diagnostics reported no errors in the PHP registration or connection files.
- SQL schema inspection confirmed a primary key, unique username/email indexes, hashed-password column, and audit foreign key.
- PHP CLI, MySQL, and XAMPP are not available in this environment, so PHP linting and all database/browser tests remain unexecuted. No runtime test is reported as passed.
