const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

function createElement(tag, className = '', text = '') {
    const element = document.createElement(tag);

    if (className) {
        element.className = className;
    }

    if (text) {
        element.textContent = text;
    }

    return element;
}

function initSystemMap(root) {
    if (root.dataset.systemMapReady === '1') {
        return;
    }

    root.dataset.systemMapReady = '1';

    const dataElement = root.querySelector('[data-system-map-data]');
    const viewport = root.querySelector('[data-system-map-viewport]');
    const host = root.querySelector('[data-system-map-host]');
    const scene = root.querySelector('[data-system-map-scene]');
    const inspector = root.querySelector('[data-system-map-inspector]');
    const search = root.querySelector('[data-system-map-search]');
    const groupFilter = root.querySelector('[data-system-map-group]');
    const statusFilter = root.querySelector('[data-system-map-status]');
    const resultCount = root.querySelector('[data-system-map-result-count]');
    const clearFocus = root.querySelector('[data-system-map-clear-focus]');

    if (!dataElement || !viewport || !host || !scene || !inspector) {
        return;
    }

    const graph = JSON.parse(dataElement.textContent || '{}');
    const nodeById = new Map((graph.nodes || []).map((node) => [node.id, node]));
    const nodeElements = new Map(
        Array.from(root.querySelectorAll('[data-system-map-node]')).map((element) => [element.dataset.nodeId, element]),
    );
    const edgeElements = Array.from(root.querySelectorAll('[data-system-map-edge]'));
    const edgeByKey = new Map(edgeElements.map((element) => [
        `${element.dataset.source}::${element.dataset.target}`,
        element,
    ]));

    const state = {
        zoom: 0.6,
        selectedId: null,
        presetIds: null,
        query: '',
        group: 'all',
        status: 'all',
        touched: false,
    };

    const baseWidth = Number(graph.width || 2600);
    const baseHeight = Number(graph.height || 1300);

    function resizeHost() {
        host.style.width = `${Math.round(baseWidth * state.zoom)}px`;
        host.style.height = `${Math.round(baseHeight * state.zoom)}px`;
        scene.style.transform = `scale(${state.zoom})`;
    }

    function visibleByFilters(node) {
        if (state.group !== 'all' && node.group !== state.group) {
            return false;
        }

        if (state.status !== 'all' && node.status !== state.status) {
            return false;
        }

        if (state.query === '') {
            return true;
        }

        const haystack = [
            node.label,
            node.summary,
            node.humanPurpose,
            node.truth,
            ...(node.tags || []),
        ].join(' ').toLocaleLowerCase();

        return haystack.includes(state.query);
    }

    function neighborhood(id) {
        const ids = new Set([id]);

        for (const edge of graph.edges || []) {
            if (edge.source === id) {
                ids.add(edge.target);
            }

            if (edge.target === id) {
                ids.add(edge.source);
            }
        }

        return ids;
    }

    function activeFocusIds() {
        if (state.selectedId) {
            return neighborhood(state.selectedId);
        }

        if (state.presetIds) {
            return new Set(state.presetIds);
        }

        return null;
    }

    function applyState() {
        const focusIds = activeFocusIds();
        const visible = new Set();

        for (const node of graph.nodes || []) {
            const element = nodeElements.get(node.id);

            if (!element) {
                continue;
            }

            const show = visibleByFilters(node);
            element.hidden = !show;

            if (!show) {
                continue;
            }

            visible.add(node.id);
            const dim = focusIds && !focusIds.has(node.id);
            element.classList.toggle('opacity-20', Boolean(dim));
            element.classList.toggle('ring-4', node.id === state.selectedId);
            element.classList.toggle('ring-sky-400/50', node.id === state.selectedId);
            element.classList.toggle('dark:ring-sky-300/40', node.id === state.selectedId);
        }

        for (const edge of graph.edges || []) {
            const element = edgeByKey.get(`${edge.source}::${edge.target}`);

            if (!element) {
                continue;
            }

            const show = visible.has(edge.source) && visible.has(edge.target);
            element.hidden = !show;

            if (!show) {
                continue;
            }

            const connected = state.selectedId && (edge.source === state.selectedId || edge.target === state.selectedId);
            const inPreset = state.presetIds
                && state.presetIds.includes(edge.source)
                && state.presetIds.includes(edge.target);
            const dim = focusIds && !connected && !inPreset;

            element.classList.toggle('opacity-10', Boolean(dim));
            element.classList.toggle('text-sky-500', Boolean(connected));
            element.classList.toggle('dark:text-sky-300', Boolean(connected));
        }

        if (resultCount) {
            resultCount.textContent = String(visible.size);
        }

        if (clearFocus) {
            clearFocus.hidden = !state.selectedId && !state.presetIds;
        }
    }

    function centerOnNode(node) {
        const nodeWidth = 230;
        const nodeHeight = 110;
        viewport.scrollTo({
            left: Math.max(0, (node.x + nodeWidth / 2) * state.zoom - viewport.clientWidth / 2),
            top: Math.max(0, (node.y + nodeHeight / 2) * state.zoom - viewport.clientHeight / 2),
            behavior: 'smooth',
        });
    }

    function addListSection(container, title, values, formatter = null) {
        if (!values || values.length === 0) {
            return;
        }

        const section = createElement('section', 'space-y-2');
        section.append(createElement('h3', 'text-xs font-semibold uppercase tracking-wide text-zinc-500', title));

        const list = createElement('ul', 'space-y-2 text-sm');

        for (const value of values) {
            const item = createElement('li', 'rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-900');
            if (formatter) {
                formatter(item, value);
            } else {
                item.textContent = value;
            }
            list.append(item);
        }

        section.append(list);
        container.append(section);
    }

    function renderInspector(node) {
        inspector.replaceChildren();

        const heading = createElement('div', 'space-y-2');
        const badgeRow = createElement('div', 'flex flex-wrap gap-2');
        badgeRow.append(createElement('span', 'rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium dark:bg-zinc-800', node.group));
        badgeRow.append(createElement(
            'span',
            node.status === 'direction'
                ? 'rounded-full border border-dashed border-amber-400 px-2 py-1 text-xs font-medium text-amber-700 dark:text-amber-300'
                : 'rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
            node.status === 'direction' ? 'Long-term direction' : 'Implemented',
        ));
        heading.append(badgeRow);
        heading.append(createElement('h2', 'text-xl font-semibold', node.label));
        heading.append(createElement('p', 'text-sm text-zinc-600 dark:text-zinc-300', node.summary));
        inspector.append(heading);

        if (node.route) {
            const link = createElement('a', 'inline-flex items-center rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900', 'Open this area ↗');
            link.href = node.route;
            inspector.append(link);
        }

        addListSection(inspector, 'Human purpose', [node.humanPurpose]);
        addListSection(inspector, 'Authoritative truth', [node.truth]);
        addListSection(inspector, 'Review questions', node.review || []);
        addListSection(inspector, 'Documentation', node.docs || []);
        addListSection(inspector, 'Code anchors', node.code || []);

        const connections = [];
        for (const edge of graph.edges || []) {
            if (edge.source === node.id || edge.target === node.id) {
                const otherId = edge.source === node.id ? edge.target : edge.source;
                const other = nodeById.get(otherId);
                if (other) {
                    connections.push({
                        id: other.id,
                        label: other.label,
                        relation: edge.source === node.id
                            ? `${edge.label} →`
                            : `← ${edge.label}`,
                    });
                }
            }
        }

        addListSection(inspector, 'Connections', connections, (item, connection) => {
            const button = createElement('button', 'w-full text-start');
            button.type = 'button';
            const relation = createElement('span', 'block text-xs text-zinc-500', connection.relation);
            const label = createElement('span', 'block font-medium', connection.label);
            button.append(relation, label);
            button.addEventListener('click', () => selectNode(connection.id, true));
            item.replaceChildren(button);
        });
    }

    function renderEmptyInspector() {
        inspector.replaceChildren();

        inspector.append(createElement('h2', 'text-lg font-semibold', 'Explore the system'));
        inspector.append(createElement(
            'p',
            'text-sm text-zinc-600 dark:text-zinc-300',
            'Select any node to see why it exists, what truth it owns, how it connects, and what questions are worth reviewing next.',
        ));

        const hint = createElement('div', 'rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-900');
        hint.append(createElement('div', 'font-medium', 'Useful starting point'));
        hint.append(createElement(
            'p',
            'mt-1 text-zinc-600 dark:text-zinc-300',
            'Choose “Financial flow” to review Planner costs → Fulfillment → Obligation → Settlement → Accounting as one human story.',
        ));
        inspector.append(hint);
    }

    function selectNode(id, center = false) {
        const node = nodeById.get(id);

        if (!node) {
            return;
        }

        state.selectedId = id;
        state.presetIds = null;
        renderInspector(node);
        applyState();

        if (center) {
            centerOnNode(node);
        }
    }

    function setZoom(nextZoom, preserveCenter = true) {
        const previous = state.zoom;
        const next = clamp(nextZoom, 0.28, 1.6);

        if (next === previous) {
            return;
        }

        const centerX = viewport.scrollLeft + viewport.clientWidth / 2;
        const centerY = viewport.scrollTop + viewport.clientHeight / 2;
        const logicalX = centerX / previous;
        const logicalY = centerY / previous;

        state.zoom = next;
        state.touched = true;
        resizeHost();

        if (preserveCenter) {
            viewport.scrollLeft = logicalX * next - viewport.clientWidth / 2;
            viewport.scrollTop = logicalY * next - viewport.clientHeight / 2;
        }
    }

    function fit(ids = null) {
        const nodes = ids
            ? (graph.nodes || []).filter((node) => ids.includes(node.id))
            : (graph.nodes || []);

        if (nodes.length === 0 || viewport.clientWidth === 0 || viewport.clientHeight === 0) {
            return;
        }

        const padding = 80;
        const nodeWidth = 230;
        const nodeHeight = 110;
        const minX = Math.min(...nodes.map((node) => node.x));
        const minY = Math.min(...nodes.map((node) => node.y));
        const maxX = Math.max(...nodes.map((node) => node.x + nodeWidth));
        const maxY = Math.max(...nodes.map((node) => node.y + nodeHeight));
        const width = maxX - minX + padding * 2;
        const height = maxY - minY + padding * 2;
        const zoom = clamp(Math.min(viewport.clientWidth / width, viewport.clientHeight / height), 0.28, 1.05);

        state.zoom = zoom;
        resizeHost();

        viewport.scrollTo({
            left: Math.max(0, (minX - padding) * zoom),
            top: Math.max(0, (minY - padding) * zoom),
            behavior: 'smooth',
        });
    }

    function clearFocusedState() {
        state.selectedId = null;
        state.presetIds = null;
        renderEmptyInspector();
        applyState();
    }

    for (const [id, element] of nodeElements.entries()) {
        element.addEventListener('click', () => selectNode(id));
    }

    search?.addEventListener('input', () => {
        state.query = search.value.trim().toLocaleLowerCase();
        applyState();
    });

    groupFilter?.addEventListener('change', () => {
        state.group = groupFilter.value;
        applyState();
    });

    statusFilter?.addEventListener('change', () => {
        state.status = statusFilter.value;
        applyState();
    });

    root.querySelector('[data-system-map-zoom-in]')?.addEventListener('click', () => setZoom(state.zoom + 0.12));
    root.querySelector('[data-system-map-zoom-out]')?.addEventListener('click', () => setZoom(state.zoom - 0.12));
    root.querySelector('[data-system-map-fit]')?.addEventListener('click', () => fit());
    clearFocus?.addEventListener('click', clearFocusedState);

    for (const button of root.querySelectorAll('[data-system-map-preset]')) {
        button.addEventListener('click', () => {
            const ids = graph.presets?.[button.dataset.systemMapPreset] || [];
            state.selectedId = null;
            state.presetIds = ids;
            renderEmptyInspector();
            applyState();
            fit(ids);
        });
    }

    let dragging = false;
    let startX = 0;
    let startY = 0;
    let startLeft = 0;
    let startTop = 0;

    viewport.addEventListener('pointerdown', (event) => {
        if (event.target.closest('button, a, input, select, textarea')) {
            return;
        }

        dragging = true;
        startX = event.clientX;
        startY = event.clientY;
        startLeft = viewport.scrollLeft;
        startTop = viewport.scrollTop;
        viewport.setPointerCapture(event.pointerId);
        viewport.classList.add('cursor-grabbing');
    });

    viewport.addEventListener('pointermove', (event) => {
        if (!dragging) {
            return;
        }

        viewport.scrollLeft = startLeft - (event.clientX - startX);
        viewport.scrollTop = startTop - (event.clientY - startY);
    });

    viewport.addEventListener('pointerup', (event) => {
        dragging = false;
        viewport.releasePointerCapture(event.pointerId);
        viewport.classList.remove('cursor-grabbing');
    });

    viewport.addEventListener('wheel', (event) => {
        if (!event.ctrlKey && !event.metaKey && !event.altKey) {
            return;
        }

        event.preventDefault();
        setZoom(state.zoom + (event.deltaY < 0 ? 0.08 : -0.08));
    }, { passive: false });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && (state.selectedId || state.presetIds)) {
            clearFocusedState();
        }
    });

    const requestedLens = root.dataset.initialLens;
    if (requestedLens && graph.presets?.[requestedLens]) {
        state.presetIds = graph.presets[requestedLens];
        applyState();
        renderEmptyInspector();
        requestAnimationFrame(() => fit(state.presetIds));
    } else {
        resizeHost();
        applyState();
        renderEmptyInspector();
        requestAnimationFrame(() => fit());
    }
}

function bootSystemMaps() {
    document.querySelectorAll('[data-system-map]').forEach(initSystemMap);
}

document.addEventListener('DOMContentLoaded', bootSystemMaps);
document.addEventListener('livewire:navigated', bootSystemMaps);
