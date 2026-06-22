const state = {
  payload: null,
  skpd: [],
  selectedIndex: 0,
  skpdQuery: '',
  treeQuery: '',
  depth: 'all',
};

const elements = {
  lastSync: document.querySelector('#last-sync'),
  pageTitle: document.querySelector('#page-title'),
  skpdSearch: document.querySelector('#skpd-search'),
  skpdList: document.querySelector('#skpd-list'),
  treeSearch: document.querySelector('#tree-search'),
  depthFilter: document.querySelector('#depth-filter'),
  treeRoot: document.querySelector('#tree-root'),
  emptyState: document.querySelector('#empty-state'),
  expandAll: document.querySelector('#expand-all'),
  collapseAll: document.querySelector('#collapse-all'),
  metricSkpd: document.querySelector('#metric-skpd'),
  metricJabatan: document.querySelector('#metric-jabatan'),
  metricAsn: document.querySelector('#metric-asn'),
  metricSelected: document.querySelector('#metric-selected'),
};

const formatter = new Intl.NumberFormat('id-ID');

function normalize(value) {
  return String(value || '').toLowerCase().trim();
}

function childrenOf(node) {
  return Array.isArray(node?.children) ? node.children : [];
}

function countNodes(nodes) {
  return nodes.reduce((total, node) => total + 1 + countNodes(childrenOf(node)), 0);
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function highlight(value, query) {
  const text = escapeHtml(value || '-');
  const needle = query.trim();
  if (!needle) {
    return text;
  }

  const escapedNeedle = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return text.replace(new RegExp(`(${escapedNeedle})`, 'ig'), '<mark>$1</mark>');
}

function matchesDepth(node) {
  if (state.depth === 'all') {
    return true;
  }

  const depth = Number(node.depth || 0);
  return state.depth === '3' ? depth >= 3 : depth === Number(state.depth);
}

function filterTree(nodes) {
  const query = normalize(state.treeQuery);

  return nodes.reduce((result, node) => {
    const filteredChildren = filterTree(childrenOf(node));
    const haystack = normalize(`${node.jabatan || ''} ${node.pegawai || ''} ${node.kelas || ''}`);
    const queryMatch = !query || haystack.includes(query);
    const depthMatch = matchesDepth(node);

    if ((queryMatch && depthMatch) || filteredChildren.length > 0) {
      result.push({ ...node, children: filteredChildren });
    }

    return result;
  }, []);
}

function filteredSkpd() {
  const query = normalize(state.skpdQuery);
  if (!query) {
    return state.skpd;
  }

  return state.skpd.filter((item) => normalize(`${item.kode || ''} ${item.nama || ''}`).includes(query));
}

function renderSkpdList() {
  const items = filteredSkpd();

  elements.skpdList.innerHTML = items.map((item) => {
    const active = item.index === state.selectedIndex ? ' is-active' : '';
    const count = item.jabatan_count ?? item.peta_jabatan_count ?? countNodes(item.tree || []);

    return `
      <button class="skpd-button${active}" type="button" data-index="${escapeHtml(item.index)}">
        <span>
          <span class="skpd-name">${highlight(item.nama, state.skpdQuery)}</span>
          <span class="skpd-code">${highlight(item.kode || '-', state.skpdQuery)}</span>
        </span>
        <span class="skpd-count">${formatter.format(count)}</span>
      </button>
    `;
  }).join('');
}

function renderTree(nodes, query) {
  if (nodes.length === 0) {
    return '';
  }

  return `
    <ul class="tree-list">
      ${nodes.map((node) => {
        const children = childrenOf(node);
        const level = Math.min(Number(node.depth || 0), 3);
        const closedClass = children.length > 0 ? '' : ' is-closed';
        const childMarkup = children.length > 0
          ? `<div class="node-children">${renderTree(children, query)}</div>`
          : '';

        return `
          <li class="tree-item">
            <article class="node level-${level}${closedClass}">
              <button class="node-toggle" type="button" ${children.length === 0 ? 'disabled' : ''}>
                <span class="node-arrow" aria-hidden="true">${children.length > 0 ? 'v' : '-'}</span>
                <span>
                  <span class="node-title">${highlight(node.jabatan, query)}</span>
                  <span class="node-person">${highlight(node.pegawai || 'Kosong', query)}</span>
                </span>
                <span class="node-meta">
                  <span class="pill">K${escapeHtml(node.kelas || '-')}</span>
                  <span class="pill">L${escapeHtml(node.depth ?? 0)}</span>
                </span>
              </button>
              ${childMarkup}
            </article>
          </li>
        `;
      }).join('')}
    </ul>
  `;
}

function selectedSkpd() {
  return state.skpd.find((item) => item.index === state.selectedIndex) || state.skpd[0];
}

function renderSummary(item) {
  const totalJabatan = state.payload?.meta?.total_jabatan
    ?? state.skpd.reduce((total, skpd) => total + Number(skpd.jabatan_count || skpd.peta_jabatan_count || 0), 0);
  const totalAsn = state.skpd.reduce((total, skpd) => total + Number(skpd.asn_count || 0), 0);
  const selectedCount = item?.jabatan_count ?? item?.peta_jabatan_count ?? countNodes(item?.tree || []);

  elements.metricSkpd.textContent = formatter.format(state.skpd.length);
  elements.metricJabatan.textContent = formatter.format(totalJabatan);
  elements.metricAsn.textContent = formatter.format(totalAsn);
  elements.metricSelected.textContent = formatter.format(selectedCount);
}

function renderMain() {
  const item = selectedSkpd();
  if (!item) {
    return;
  }

  const nodes = filterTree(item.tree || []);
  elements.pageTitle.textContent = item.nama || 'Peta Jabatan';
  elements.treeRoot.innerHTML = renderTree(nodes, state.treeQuery);
  elements.emptyState.hidden = nodes.length > 0;
  renderSummary(item);
  renderSkpdList();
}

function toggleAll(open) {
  document.querySelectorAll('.node').forEach((node) => {
    node.classList.toggle('is-closed', !open);
  });
}

async function boot() {
  const response = await fetch('/data/tpp_peta_jabatan_real.json');
  if (!response.ok) {
    throw new Error(`Gagal memuat data: ${response.status}`);
  }

  state.payload = await response.json();
  state.skpd = Array.isArray(state.payload.skpd) ? state.payload.skpd : [];
  state.selectedIndex = state.skpd[0]?.index || 0;

  const fetchedAt = state.payload?.meta?.fetched_at || '-';
  elements.lastSync.textContent = `Sinkron ${fetchedAt}`;

  renderMain();
}

elements.skpdSearch.addEventListener('input', (event) => {
  state.skpdQuery = event.target.value;
  renderSkpdList();
});

elements.skpdList.addEventListener('click', (event) => {
  const button = event.target.closest('[data-index]');
  if (!button) {
    return;
  }

  state.selectedIndex = Number(button.dataset.index);
  renderMain();
});

elements.treeSearch.addEventListener('input', (event) => {
  state.treeQuery = event.target.value;
  renderMain();
});

elements.depthFilter.addEventListener('change', (event) => {
  state.depth = event.target.value;
  renderMain();
});

elements.treeRoot.addEventListener('click', (event) => {
  const button = event.target.closest('.node-toggle');
  if (!button || button.disabled) {
    return;
  }

  button.closest('.node')?.classList.toggle('is-closed');
});

elements.expandAll.addEventListener('click', () => toggleAll(true));
elements.collapseAll.addEventListener('click', () => toggleAll(false));

boot().catch((error) => {
  elements.lastSync.textContent = 'Data gagal dimuat';
  elements.emptyState.hidden = false;
  elements.emptyState.textContent = error.message;
});
