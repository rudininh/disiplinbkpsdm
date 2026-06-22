const state = {
  payload: null,
  skpd: [],
  selectedKey: 'all',
  pendingKey: 'all',
  query: '',
};

const elements = {
  statusTitle: document.querySelector('#status-title'),
  statusMessage: document.querySelector('#status-message'),
  lastSync: document.querySelector('#last-sync'),
  metricSkpd: document.querySelector('#metric-skpd'),
  metricSuccess: document.querySelector('#metric-success'),
  metricFailed: document.querySelector('#metric-failed'),
  metricJabatan: document.querySelector('#metric-jabatan'),
  skpdSelect: document.querySelector('#skpd-select'),
  showButton: document.querySelector('#show-button'),
  treeSearch: document.querySelector('#tree-search'),
  treeContainer: document.querySelector('#tree-container'),
  emptyState: document.querySelector('#empty-state'),
  expandAll: document.querySelector('#expand-all'),
  collapseAll: document.querySelector('#collapse-all'),
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

function highlight(value) {
  const text = escapeHtml(value || '-');
  const needle = state.query.trim();
  if (!needle) {
    return text;
  }

  const escapedNeedle = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return text.replace(new RegExp(`(${escapedNeedle})`, 'ig'), '<mark>$1</mark>');
}

function selectedRows() {
  if (state.selectedKey === 'all') {
    return state.skpd;
  }

  if (state.selectedKey.startsWith('skpd:')) {
    const id = state.selectedKey.slice(5);
    return state.skpd.filter((item) => String(item.skpd_id || '') === id);
  }

  const index = state.selectedKey.slice(6);
  return state.skpd.filter((item) => String(item.index || '') === index);
}

function filterTree(nodes) {
  const query = normalize(state.query);

  return nodes.reduce((result, node) => {
    const filteredChildren = filterTree(childrenOf(node));
    const haystack = normalize(`${node.kelas || ''} ${node.jabatan || ''} ${node.pegawai || ''}`);
    const isMatch = !query || haystack.includes(query);

    if (isMatch || filteredChildren.length > 0) {
      result.push({ ...node, children: filteredChildren });
    }

    return result;
  }, []);
}

function isCategory(node) {
  const source = node.source || 'tpp';
  return source === 'category' || node.callout_class === 'functional';
}

function isSiasnGroup(node) {
  return node.source === 'siasn_group';
}

function isFilled(node) {
  const person = String(node.pegawai || '').trim();
  return (person !== '' && person !== '-') || isSiasnGroup(node);
}

function nodeTone(node) {
  if (isCategory(node)) {
    return 'is-category';
  }

  return isFilled(node) ? '' : 'is-empty';
}

function statusText(node) {
  if (isCategory(node)) {
    return 'Kategori';
  }

  if (isFilled(node)) {
    return 'Terisi';
  }

  const vacancyCount = Number(node.vacancy_count || 1);
  return `Ada ${formatter.format(vacancyCount)} Jabatan Lowong`;
}

function extraText(node) {
  const person = String(node.pegawai || '').trim();
  const source = node.source || 'tpp';

  if (isSiasnGroup(node)) {
    return `B ${formatter.format(Number(node.bezetting || 0))} pegawai SIASN`;
  }

  if (isFilled(node) && !isCategory(node)) {
    return person;
  }

  if (isCategory(node) && person) {
    return person;
  }

  if (['excel', 'tpp_empty', 'excel_parent'].includes(source)) {
    return `B ${node.bezting || node.bezetting || 0} pegawai terisi / K ${node.kebutuhan || 0} kebutuhan / +/- ${node.selisih ?? '-'} kekurangan`;
  }

  return '';
}

function renderTree(nodes) {
  if (nodes.length === 0) {
    return '';
  }

  return `
    <ul class="tree-list">
      ${nodes.map((node) => {
        const children = childrenOf(node);
        const tone = nodeTone(node);
        const category = isCategory(node);
        const hasChildren = children.length > 0;
        const extra = extraText(node);

        return `
          <li class="tree-item">
            <span class="tree-dot ${tone}"></span>
            <div class="node-card ${tone}">
              <button class="node-button" type="button" ${hasChildren ? '' : 'disabled'}>
                <span class="node-main">
                  <span class="kelas">${highlight(node.kelas ?? '-')}</span>
                  <span class="separator">|</span>
                  <span class="job">${highlight(node.jabatan ?? '-')}</span>
                  ${extra ? `
                    <span class="separator">|</span>
                    <span class="${category ? 'category-text' : tone === 'is-empty' ? 'vacant-text' : 'person'}">${highlight(extra)}</span>
                  ` : ''}
                  ${node.sheet_name ? `
                    <span class="separator">|</span>
                    <span class="person">${highlight(node.sheet_name)}</span>
                  ` : ''}
                </span>
                <span class="node-status ${tone}">${escapeHtml(statusText(node))}</span>
              </button>
              ${hasChildren ? `<div class="node-children">${renderTree(children)}</div>` : ''}
            </div>
          </li>
        `;
      }).join('')}
    </ul>
  `;
}

function renderOptions() {
  elements.skpdSelect.innerHTML = [
    '<option value="all">Semua SKPD</option>',
    ...state.skpd.map((item) => {
      const value = item.skpd_id ? `skpd:${item.skpd_id}` : `index:${item.index || ''}`;
      const label = `${item.kode || '-'} - ${item.nama || 'SKPD tanpa nama'}`;
      return `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`;
    }),
  ].join('');
  elements.skpdSelect.value = state.pendingKey;
}

function renderStats() {
  const successCount = state.skpd.filter((item) => item.success === true).length;
  const failedCount = state.skpd.filter((item) => item.success === false).length;
  const totalJabatan = state.payload?.meta?.total_jabatan
    ?? state.skpd.reduce((total, item) => total + Number(item.jabatan_count || 0), 0);

  elements.metricSkpd.textContent = formatter.format(state.skpd.length);
  elements.metricSuccess.textContent = formatter.format(successCount);
  elements.metricFailed.textContent = formatter.format(failedCount);
  elements.metricJabatan.textContent = formatter.format(totalJabatan);
}

function renderCards() {
  const rows = selectedRows();
  const markup = rows.map((item) => {
    const filtered = filterTree(Array.isArray(item.tree) ? item.tree : []);
    const count = item.jabatan_count ?? item.peta_jabatan_count ?? countNodes(item.tree || []);

    return `
      <article class="skpd-card">
        <div class="skpd-heading">
          <div>
            <h2>${escapeHtml(item.nama || 'SKPD tanpa nama')}</h2>
            <p>Kode SKPD: ${escapeHtml(item.kode || '-')} | ID login: ${escapeHtml(item.skpd_id || '-')}</p>
          </div>
          <div class="skpd-badges">
            <span>ASN ${formatter.format(Number(item.asn_count || 0))}</span>
            <span>Jabatan ${formatter.format(Number(count || 0))}</span>
          </div>
        </div>
        <div class="skpd-body">
          ${item.success === false
            ? `<div class="empty-state">${escapeHtml(item.message || 'Data SKPD ini belum berhasil diambil.')}</div>`
            : filtered.length > 0
              ? renderTree(filtered)
              : '<div class="empty-state">Tidak ada struktur jabatan pada halaman ini.</div>'}
        </div>
      </article>
    `;
  }).join('');

  elements.treeContainer.innerHTML = markup;
  elements.emptyState.hidden = rows.length > 0;
}

function render() {
  renderStats();
  renderCards();
}

function toggleAll(open) {
  document.querySelectorAll('.node-card').forEach((node) => {
    node.classList.toggle('is-closed', !open);
  });
}

async function boot() {
  const response = await fetch('/data/static_peta_jabatan_real.json');
  if (!response.ok) {
    throw new Error(`Gagal memuat data: ${response.status}`);
  }

  state.payload = await response.json();
  state.skpd = Array.isArray(state.payload.skpd) ? state.payload.skpd : [];

  const meta = state.payload.meta || {};
  elements.statusTitle.textContent = state.payload.message || 'Data Peta Jabatan Real berhasil dimuat.';
  elements.statusMessage.textContent = `${formatter.format(Number(meta.success_count || 0))} SKPD berhasil, ${formatter.format(Number(meta.failed_count || 0))} gagal.`;
  elements.lastSync.textContent = `Sinkron ${meta.fetched_at || '-'}`;

  renderOptions();
  render();
}

elements.skpdSelect.addEventListener('change', (event) => {
  state.pendingKey = event.target.value;
});

elements.showButton.addEventListener('click', () => {
  state.selectedKey = state.pendingKey;
  renderCards();
  window.scrollTo({ top: 0, behavior: 'smooth' });
});

elements.treeSearch.addEventListener('input', (event) => {
  state.query = event.target.value;
  renderCards();
});

elements.treeContainer.addEventListener('click', (event) => {
  const button = event.target.closest('.node-button');
  if (!button || button.disabled) {
    return;
  }

  button.closest('.node-card')?.classList.toggle('is-closed');
});

elements.expandAll.addEventListener('click', () => toggleAll(true));
elements.collapseAll.addEventListener('click', () => toggleAll(false));

boot().catch((error) => {
  elements.statusTitle.textContent = 'Data gagal dimuat';
  elements.statusMessage.textContent = error.message;
  elements.emptyState.hidden = false;
});
