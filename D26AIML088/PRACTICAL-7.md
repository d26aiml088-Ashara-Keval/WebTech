# Practical 7: PHP Form Processing

## Run locally

Run this project through PHP, not by opening the HTML files with `file://`.

- **XAMPP/WAMP:** place the `D26AIML088` folder in the server's document root and open `http://localhost/D26AIML088/practical-7.php`.
- **PHP built-in server:** open a terminal in `D26AIML088` and run `php -S localhost:8000 router.php`. Then open `http://localhost:8000/practical-7.php`. The router blocks direct requests to stored submissions.

The PHP `mbstring` extension is required for safe UTF-8 length checks. The web-server account must be allowed to create and write files in `storage/`.
Use PHP 8.1 or later.

## Deliverables and test cases

- PHP source code: `practical-7.php` (form handling) and `router.php` (the built-in server's private-storage guard).
- Test cases: [PRACTICAL-7-TEST-CASES.md](./PRACTICAL-7-TEST-CASES.md), covering successful submissions, invalid inputs, CSRF, and storage access.
- JSON storage example: [submissions.generated.sample.json](./storage/submissions.generated.sample.json) contains fictional example contact and registration records in the same format as successful submissions.
- Live generated data: `storage/submissions.json` is created after a valid form submission. It is ignored by version control to keep submitted personal details private.

## What to test

1. Submit a valid contact message and a valid registration; each should redirect to a success message.
2. Try a malformed email, a short message, an unsupported course, or an invalid enrollment number; the server should show field errors and keep the submitted values.
3. Submit without a valid CSRF token; the server should reject the request.
4. Inspect `storage/submissions.json` on the server. It is created on the first successful submission and contains a JSON array of records.
5. Request `storage/submissions.json` directly in the browser; Apache and IIS are configured to deny access.

The contact and registration records use the same JSON file. File writes are protected with an exclusive lock. Submitted values are normalized, validated again on the server, and escaped when redisplayed. Passwords are not collected or stored. Real submissions are excluded from version control by `storage/.gitignore`; `storage/submissions.example.json` shows the empty initial file format.
