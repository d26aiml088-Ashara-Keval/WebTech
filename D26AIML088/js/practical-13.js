const form = document.querySelector('#api-event-form');
const eventList = document.querySelector('#api-events');
const searchInput = document.querySelector('#api-search');
const message = document.querySelector('#api-message');
const loading = document.querySelector('#api-loading');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const editId = document.querySelector('#api-event-id');
const saveButton = document.querySelector('#api-save');
const cancelButton = document.querySelector('#api-cancel');
const formTitle = document.querySelector('#api-form-title');
let searchTimer;

function showMessage(text, isError = false) {
    message.textContent = text;
    message.classList.toggle('form-feedback-error', isError);
    message.classList.toggle('form-feedback-success', !isError);
    message.hidden = false;
}

function appendCell(row, value) {
    const cell = document.createElement('td');
    cell.textContent = value;
    row.append(cell);
    return cell;
}

function createActionButton(label, action, event) {
    const button = document.createElement('button');
    button.className = 'text-button';
    button.type = 'button';
    button.textContent = label;
    button.addEventListener('click', () => action(event));
    return button;
}

function drawEvents(events) {
    eventList.replaceChildren();

    if (events.length === 0) {
        const row = document.createElement('tr');
        const cell = appendCell(row, 'No events match this search.');
        cell.colSpan = 5;
        eventList.append(row);
        return;
    }

    events.forEach((event) => {
        const row = document.createElement('tr');
        appendCell(row, event.title);
        appendCell(row, event.category);
        appendCell(row, event.event_date);
        appendCell(row, event.location);
        const actions = document.createElement('td');
        actions.className = 'table-actions';
        actions.append(
            createActionButton('Edit', beginEdit, event),
            createActionButton('Delete', deleteEvent, event)
        );
        row.append(actions);
        eventList.append(row);
    });
}

async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(options.method ? { 'X-CSRF-Token': csrfToken } : {}),
            ...options.headers
        }
    });
    const result = await response.json();

    if (!response.ok) {
        const fieldErrors = result.fields && typeof result.fields === 'object'
            ? Object.values(result.fields).join(' ')
            : '';
        throw new Error(fieldErrors || result.error || 'The request could not be completed.');
    }

    return result;
}

async function loadEvents() {
    loading.hidden = false;

    try {
        const query = searchInput.value.trim();
        const result = await requestJson(`api/events.php?q=${encodeURIComponent(query)}`);
        drawEvents(result.events);
    } catch (error) {
        eventList.replaceChildren();
        showMessage(error.message, true);
    } finally {
        loading.hidden = true;
    }
}

function beginEdit(event) {
    editId.value = event.event_id;
    form.elements.title.value = event.title;
    form.elements.category.value = event.category;
    form.elements.event_date.value = event.event_date;
    form.elements.location.value = event.location;
    form.elements.description.value = event.description;
    formTitle.textContent = 'Edit event';
    saveButton.textContent = 'Save changes';
    cancelButton.hidden = false;
    form.elements.title.focus();
}

function resetForm() {
    form.reset();
    editId.value = '';
    formTitle.textContent = 'Add an event';
    saveButton.textContent = 'Add event';
    cancelButton.hidden = true;
}

async function deleteEvent(event) {
    if (!window.confirm(`Delete "${event.title}" and its registrations?`)) return;

    try {
        const result = await requestJson('api/events.php', {
            method: 'DELETE',
            body: JSON.stringify({ event_id: Number(event.event_id) })
        });
        showMessage(result.message);
        await loadEvents();
    } catch (error) {
        showMessage(error.message, true);
    }
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const formData = new FormData(form);
    const eventId = editId.value;
    const payload = Object.fromEntries(
        ['title', 'category', 'event_date', 'location', 'description']
            .map((field) => [field, formData.get(field)])
    );
    if (eventId !== '') payload.event_id = Number(eventId);

    try {
        const result = await requestJson('api/events.php', {
            method: eventId === '' ? 'POST' : 'PUT',
            body: JSON.stringify(payload)
        });
        resetForm();
        showMessage(result.message);
        await loadEvents();
    } catch (error) {
        showMessage(error.message, true);
    }
});

cancelButton.addEventListener('click', resetForm);
searchInput.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(loadEvents, 300);
});

loadEvents();
