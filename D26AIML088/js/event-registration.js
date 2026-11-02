const statusMessage = document.querySelector('#event-registration-status');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const registrationButtons = [...document.querySelectorAll('.event-registration-button')];
const registrations = new Set();

function showRegistrationMessage(message, isError = false) {
    statusMessage.textContent = message;
    statusMessage.classList.toggle('form-feedback-error', isError);
    statusMessage.classList.toggle('form-feedback-success', !isError);
    statusMessage.hidden = false;
}

function setRegistrationButton(button, registered) {
    button.dataset.registered = registered ? 'true' : 'false';
    button.textContent = registered ? 'Cancel registration' : 'Register for event';
    button.setAttribute('aria-pressed', registered ? 'true' : 'false');
}

async function loadRegistrations() {
    if (registrationButtons.length === 0) return;

    try {
        const response = await fetch('api/registrations.php', {
            headers: { Accept: 'application/json' }
        });
        const result = await response.json();

        if (!response.ok) {
            showRegistrationMessage(result.error ?? 'Your registrations could not be loaded.', true);
            return;
        }

        if (!result.linked) {
            showRegistrationMessage(
                'Your account is not linked to a student record. Ask an administrator to add a student record with your account email.'
            );
            registrationButtons.forEach((button) => { button.disabled = true; });
            return;
        }

        result.registrations.forEach((registration) => {
            registrations.add(Number(registration.event_id));
        });
        registrationButtons.forEach((button) => {
            setRegistrationButton(button, registrations.has(Number(button.dataset.eventId)));
        });
    } catch {
        showRegistrationMessage('Your registrations could not be loaded. Check your connection and try again.', true);
    }
}

registrationButtons.forEach((button) => {
    button.addEventListener('click', async () => {
        const eventId = Number(button.dataset.eventId);
        const registered = registrations.has(eventId);
        button.disabled = true;

        try {
            const response = await fetch('api/registrations.php', {
                method: registered ? 'DELETE' : 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ event_id: eventId })
            });
            const result = await response.json();

            if (!response.ok) {
                showRegistrationMessage(result.error ?? 'Your registration could not be saved.', true);
                return;
            }

            if (registered) {
                registrations.delete(eventId);
            } else {
                registrations.add(eventId);
            }
            setRegistrationButton(button, !registered);
            showRegistrationMessage(result.message);
        } catch {
            showRegistrationMessage('Your registration could not be saved. Check your connection and try again.', true);
        } finally {
            button.disabled = false;
        }
    });
});

loadRegistrations();
