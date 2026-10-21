import { DATASETS, fetchDataset } from './data-service.js';
import { renderRecords } from './render.js';

const pageSize = 6;
const elements = {
    dataset: document.querySelector('#dataset'),
    search: document.querySelector('#search'),
    filterLabel: document.querySelector('#filter-label'),
    filter: document.querySelector('#filter'),
    sort: document.querySelector('#sort'),
    direction: document.querySelector('#direction'),
    grid: document.querySelector('#records'),
    loading: document.querySelector('#loading'),
    error: document.querySelector('#error'),
    empty: document.querySelector('#empty'),
    resultCount: document.querySelector('#result-count'),
    sourceNote: document.querySelector('#source-note'),
    pagination: document.querySelector('#pagination'),
    previous: document.querySelector('#previous'),
    next: document.querySelector('#next'),
    pageStatus: document.querySelector('#page-status')
};

let records = [];
let currentPage = 1;
let loadRequest = 0;

function populateSelect(select, options, firstLabel) {
    select.replaceChildren(new Option(firstLabel, ''));
    options.forEach(([value, label]) => select.add(new Option(label, value)));
}

function updateControls(dataset) {
    const config = DATASETS[dataset];
    elements.filterLabel.textContent = config.filterLabel;
    elements.filter.setAttribute('aria-label', `Filter by ${config.filterLabel.toLowerCase()}`);

    const filterOptions = [...new Set(records.map((record) => record[config.filterKey]))]
        .sort((first, second) => first.localeCompare(second));
    populateSelect(elements.filter, filterOptions.map((value) => [value, value]), config.filterAllLabel);
    populateSelect(elements.sort, Object.entries(config.sortOptions), 'Default order');

    elements.search.value = '';
    elements.direction.value = 'asc';
    elements.filter.value = '';
    elements.sort.value = '';
}

function getVisibleRecords() {
    const dataset = elements.dataset.value;
    const config = DATASETS[dataset];
    const query = elements.search.value.trim().toLocaleLowerCase();
    const selectedFilter = elements.filter.value;
    const sortKey = elements.sort.value;
    const direction = elements.direction.value === 'desc' ? -1 : 1;

    let visible = records.filter((record) => {
        const matchesFilter = !selectedFilter || record[config.filterKey] === selectedFilter;
        const searchableText = Object.values(record)
            .flat()
            .join(' ')
            .toLocaleLowerCase();
        return matchesFilter && searchableText.includes(query);
    });

    if (sortKey) {
        visible = [...visible].sort((first, second) => {
            const firstValue = first[sortKey];
            const secondValue = second[sortKey];
            const comparison = typeof firstValue === 'number'
                ? firstValue - secondValue
                : String(firstValue).localeCompare(String(secondValue), undefined, {
                    numeric: true,
                    sensitivity: 'base'
                });
            return comparison * direction;
        });
    }

    return visible;
}

function updateView() {
    const visible = getVisibleRecords();
    const pageCount = Math.ceil(visible.length / pageSize);
    currentPage = Math.min(currentPage, Math.max(pageCount, 1));
    const start = (currentPage - 1) * pageSize;
    const pageRecords = visible.slice(start, start + pageSize);

    renderRecords(elements.grid, pageRecords, elements.dataset.value);
    elements.resultCount.textContent = `${visible.length} ${visible.length === 1 ? 'result' : 'results'}`;
    elements.empty.hidden = visible.length > 0;
    elements.pagination.hidden = visible.length === 0;
    elements.previous.disabled = currentPage <= 1;
    elements.next.disabled = currentPage >= pageCount;
    elements.pageStatus.textContent = `Page ${currentPage} of ${pageCount || 1}`;
}

async function loadSelectedDataset() {
    const request = ++loadRequest;
    const dataset = elements.dataset.value;

    elements.loading.hidden = false;
    elements.error.hidden = true;
    elements.empty.hidden = true;
    elements.pagination.hidden = true;
    elements.sourceNote.textContent = '';
    elements.grid.replaceChildren();
    elements.resultCount.textContent = '';

    try {
        const result = await fetchDataset(dataset);
        if (request !== loadRequest) return;

        records = result.records;
        currentPage = 1;
        updateControls(dataset);
        updateView();
        elements.sourceNote.textContent = result.source === 'cache'
            ? 'Showing the latest saved copy. The network request failed, so this may be offline data.'
            : 'Loaded from the JSON file.';
    } catch (error) {
        if (request !== loadRequest) return;
        records = [];
        elements.error.textContent = `${error.message} Start this project with a local web server and try again.`;
        elements.error.hidden = false;
    } finally {
        if (request === loadRequest) elements.loading.hidden = true;
    }
}

elements.dataset.addEventListener('change', loadSelectedDataset);
elements.search.addEventListener('input', () => {
    currentPage = 1;
    updateView();
});
[elements.filter, elements.sort, elements.direction].forEach((control) => {
    control.addEventListener('change', () => {
        currentPage = 1;
        updateView();
    });
});

elements.previous.addEventListener('click', () => {
    if (currentPage > 1) {
        currentPage -= 1;
        updateView();
    }
});

elements.next.addEventListener('click', () => {
    const pageCount = Math.ceil(getVisibleRecords().length / pageSize);
    if (currentPage < pageCount) {
        currentPage += 1;
        updateView();
    }
});

loadSelectedDataset();
