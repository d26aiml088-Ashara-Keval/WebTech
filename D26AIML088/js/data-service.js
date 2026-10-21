const DATASETS = {
    events: {
        label: 'Events',
        url: './data/events.json',
        filterLabel: 'Category',
        filterAllLabel: 'All categories',
        filterKey: 'category',
        sortOptions: { date: 'Date', title: 'Title' }
    },
    students: {
        label: 'Students',
        url: './data/students.json',
        filterLabel: 'Department',
        filterAllLabel: 'All departments',
        filterKey: 'department',
        sortOptions: { name: 'Name', year: 'Year' }
    },
    faqs: {
        label: 'FAQs',
        url: './data/faqs.json',
        filterLabel: 'Category',
        filterAllLabel: 'All categories',
        filterKey: 'category',
        sortOptions: { question: 'Question' }
    }
};

const cacheKeyFor = (dataset) => `student-hub-json-${dataset}`;

export async function fetchDataset(dataset) {
    const config = DATASETS[dataset];

    if (!config) {
        throw new Error(`Unknown dataset: ${dataset}`);
    }

    let responseError;

    try {
        const response = await fetch(config.url);

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        const records = await response.json();

        if (!Array.isArray(records)) {
            throw new Error('The JSON file must contain an array of records.');
        }

        try {
            localStorage.setItem(cacheKeyFor(dataset), JSON.stringify({
                records,
                savedAt: new Date().toISOString()
            }));
        } catch {
            // Caching is optional; a successful network response can still be rendered.
        }

        return { records, source: 'network' };
    } catch (error) {
        responseError = error;
    }

    try {
        const cached = localStorage.getItem(cacheKeyFor(dataset));

        if (cached) {
            const parsed = JSON.parse(cached);

            if (Array.isArray(parsed.records)) {
                return { records: parsed.records, source: 'cache' };
            }
        }
    } catch {
        // An unavailable or invalid cache is treated as a cache miss.
    }

    throw new Error(`Could not load ${config.label.toLowerCase()}. ${responseError.message}`);
}

export { DATASETS };
