# Practical 9: Screenshot Evidence Checklist

Capture each image from the running local XAMPP application and phpMyAdmin. Use fictional test data only. Do not show a real password, database credential, or another person's personal information.

1. **Connection test:** Open `http://localhost/D26AIML088/practical-9-connection.php` and capture the successful MySQLi status and users/audit table counts.
2. **Registration form:** Open `http://localhost/D26AIML088/register.php` and capture the empty form before submission.
3. **Successful registration:** Submit a valid fictional name, a unique test username/email, and a matching test password. Capture the success message. Keep the password fields hidden/empty in the screenshot.
4. **Validation error:** Submit a malformed email or an invalid password and capture the validation message. Use fictional values.
5. **Duplicate rejection:** Submit the same test email or username a second time and capture the duplicate-account message.
6. **Hashed database record:** In phpMyAdmin, select `studenthub.users` and capture the new user's `user_id`, username, and `password_hash`. Make sure the displayed hash is visibly different from the test password. Hide or crop email if preferred; never capture password fields or database credentials.

Save the images in `D26AIML088/screenshots/` with descriptive names, for example `practical-9-connection.png`, `practical-9-form.png`, `practical-9-success.png`, `practical-9-validation.png`, `practical-9-duplicate.png`, and `practical-9-hash.png`.

No Practical 9 screenshots have been captured in this workspace; the live PHP/MySQL services are not available here.
