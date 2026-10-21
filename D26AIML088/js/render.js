const makeElement = (tagName, className, text) => {
    const element = document.createElement(tagName);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
};

const addField = (card, label, value) => {
    const row = makeElement('p', 'record-detail');
    const strong = makeElement('strong', '', `${label}: `);
    row.append(strong, document.createTextNode(value));
    card.append(row);
};

const renderEvent = (record) => {
    const card = makeElement('article', 'data-card');
    card.append(
        makeElement('span', 'data-tag', record.category),
        makeElement('h3', '', record.title)
    );
    addField(card, 'Date', new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium'
    }).format(new Date(`${record.date}T00:00:00`)));
    addField(card, 'Location', record.location);
    card.append(makeElement('p', 'data-description', record.description));
    return card;
};

const renderStudent = (record) => {
    const card = makeElement('article', 'data-card');
    card.append(
        makeElement('span', 'data-tag', record.department),
        makeElement('h3', '', record.name)
    );
    addField(card, 'Year', `Year ${record.year}`);
    addField(card, 'Interests', record.interests.join(', '));
    return card;
};

const renderFaq = (record) => {
    const card = makeElement('article', 'data-card');
    card.append(
        makeElement('span', 'data-tag', record.category),
        makeElement('h3', '', record.question),
        makeElement('p', 'data-description', record.answer)
    );
    return card;
};

const renderers = {
    events: renderEvent,
    students: renderStudent,
    faqs: renderFaq
};

export function renderRecords(container, records, dataset) {
    const renderRecord = renderers[dataset];

    if (!renderRecord) {
        throw new Error(`No renderer is configured for "${dataset}".`);
    }

    const fragment = document.createDocumentFragment();
    records.forEach((record) => fragment.append(renderRecord(record)));
    container.replaceChildren(fragment);
}
