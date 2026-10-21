# Practical 7: Test Cases

Run the PHP server as described in [PRACTICAL-7.md](./PRACTICAL-7.md). Each request starts from a fresh GET so the form has a valid session and CSRF token. For tests that modify storage, take a copy of `storage/submissions.json` first and restore it after testing.

| ID | Form / scenario | Test input | Expected result |
|---|---|---|---|
| TC01 | Contact: valid submission | Name `Riya Shah`, email `riya@example.edu`, message `Please share the workshop schedule.` | HTTP 303 redirect, then success message; one contact record is appended to the JSON array. |
| TC02 | Registration: valid submission | Name `Arjun Rao`, email `arjun@example.edu`, enrollment `STU-2026/42`, course `BTech` | HTTP 303 redirect, then success message; one registration record is appended to the JSON array. |
| TC03 | Invalid email | Submit either form with email `not-an-email` and otherwise valid fields | HTTP 422; an email validation error appears, submitted values remain in the form, and no record is appended. |
| TC04 | Invalid name | Submit with name `R` or `Riya123` | HTTP 422; a name validation error appears and no record is appended. |
| TC05 | Short contact message | Submit a contact message with fewer than 10 characters | HTTP 422; a message validation error appears and no record is appended. |
| TC06 | Oversized contact message | Submit a contact message with more than 2,000 characters | HTTP 422; a message length error appears and no record is appended. |
| TC07 | Invalid enrollment number | Submit a registration with enrollment `A!` | HTTP 422; an enrollment validation error appears and no record is appended. |
| TC08 | Unsupported course | Submit a registration with course `PhD` | HTTP 422; a course validation error appears and no record is appended. |
| TC09 | Missing/invalid CSRF token | Submit either form without its valid `csrf_token` | HTTP 403; request is rejected and no record is appended. |
| TC10 | Honeypot filled | Submit either form with a non-empty `website` field | HTTP 400; request is rejected and no record is appended. |
| TC11 | Stored-data access | Request `/storage/submissions.json` directly | Access is denied by Apache/IIS configuration or returns 404 through the built-in-server router. |
| TC12 | Concurrent-safe storage format | Submit one valid contact message and one valid registration | Both entries remain in a parseable JSON array; each has a unique ID, timestamp, type, name, and email, with type-specific fields. |

`storage/submissions.generated.sample.json` is a tracked, fictional example of the JSON produced by TC01 and TC02. Live submissions are written separately to `storage/submissions.json`, which is excluded from version control.
