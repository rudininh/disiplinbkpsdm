// ==UserScript==
// @name         SIASN Cek Data Pegawai - Peremajaan Profil
// @namespace    https://disiplinbkpsdm.banjarmasinkota.go.id/
// @version      1.3
// @description  Menambahkan tombol cek data pegawai ke SIASN API di halaman peremajaan profil PNS & PPPK Paruh Waktu. Menampilkan status ada/tidak ada data di SIASN.
// @author       BKPSDM Kota Banjarmasin
// @match        https://siasn-instansi.bkn.go.id/peremajaan/profil/pns/*
// @match        https://siasn-instansi.bkn.go.id/peremajaan/profil/pppk-paruh-waktu/*
// @grant        GM_xmlhttpRequest
// @grant        GM_addStyle
// @connect      api-siasn.bkn.go.id
// @run-at       document-idle
// ==/UserScript==

(function () {
  'use strict';

  // ─────────────────── Konfigurasi ───────────────────
  const SIASN_API_BASE = 'https://api-siasn.bkn.go.id';
  const CHECK_DELAY_MS = 800;    // jeda antar request saat cek semua (ms)
  const MAX_CONCURRENT = 1;      // satu-satu agar tidak kena rate limit

  // ─────────────────── CSS ───────────────────────────
  GM_addStyle(`
    /* ── Floating panel ── */
    #siasn-cek-panel {
      position: fixed;
      top: 10px;
      right: 10px;
      z-index: 99999;
      background: #fff;
      border: 2px solid #1565c0;
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,.18);
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      font-size: 13px;
      min-width: 380px;
      max-width: 500px;
      max-height: 90vh;
      display: flex;
      flex-direction: column;
    }
    #siasn-cek-panel .panel-header {
      background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
      color: #fff;
      padding: 10px 14px;
      border-radius: 10px 10px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: move;
      user-select: none;
    }
    #siasn-cek-panel .panel-header h3 {
      margin: 0;
      font-size: 14px;
      font-weight: 600;
    }
    #siasn-cek-panel .panel-body {
      padding: 12px 14px;
      overflow-y: auto;
      max-height: 70vh;
    }
    #siasn-cek-panel .panel-footer {
      padding: 8px 14px;
      border-top: 1px solid #e0e0e0;
      background: #fafafa;
      border-radius: 0 0 10px 10px;
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }

    /* ── Buttons ── */
    .siasn-btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 6px 12px;
      border: none;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all .15s;
    }
    .siasn-btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
    .siasn-btn:active { transform: translateY(0); }
    .siasn-btn:disabled { opacity: .5; cursor: not-allowed; filter: none; transform: none; }
    .siasn-btn-primary { background: #1565c0; color: #fff; }
    .siasn-btn-success { background: #2e7d32; color: #fff; }
    .siasn-btn-danger  { background: #c62828; color: #fff; }
    .siasn-btn-warning { background: #e65100; color: #fff; }
    .siasn-btn-sm { padding: 3px 8px; font-size: 11px; }
    .siasn-btn-xs { padding: 2px 6px; font-size: 10px; border-radius: 4px; }

    /* ── Token input ── */
    #siasn-token-input {
      width: 100%;
      padding: 8px 10px;
      border: 2px solid #bbdefb;
      border-radius: 6px;
      font-size: 12px;
      font-family: monospace;
      margin-bottom: 8px;
      box-sizing: border-box;
      transition: border-color .2s;
    }
    #siasn-token-input:focus { border-color: #1565c0; outline: none; }

    /* ── Status badges ── */
    .siasn-status {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: .3px;
      white-space: nowrap;
    }
    .siasn-status-ada     { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
    .siasn-status-tidak   { background: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; }
    .siasn-status-error   { background: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }
    .siasn-status-loading { background: #e3f2fd; color: #0d47a1; border: 1px solid #90caf9; }
    .siasn-status-pending { background: #f5f5f5; color: #616161; border: 1px solid #e0e0e0; }

    /* ── Progress bar ── */
    .siasn-progress {
      width: 100%;
      height: 6px;
      background: #e0e0e0;
      border-radius: 3px;
      margin: 6px 0;
      overflow: hidden;
    }
    .siasn-progress-bar {
      height: 100%;
      background: linear-gradient(90deg, #1565c0, #42a5f5);
      border-radius: 3px;
      transition: width .3s ease;
    }

    /* ── Log area ── */
    #siasn-log {
      max-height: 200px;
      overflow-y: auto;
      font-size: 11px;
      line-height: 1.6;
      color: #424242;
      margin-top: 8px;
      padding: 6px 8px;
      background: #fafafa;
      border-radius: 6px;
      border: 1px solid #eee;
    }
    #siasn-log .log-ok    { color: #2e7d32; }
    #siasn-log .log-fail  { color: #c62828; }
    #siasn-log .log-warn  { color: #e65100; }
    #siasn-log .log-info  { color: #1565c0; }

    /* ── Summary cards ── */
    .siasn-summary {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 6px;
      margin: 8px 0;
    }
    .siasn-summary-card {
      text-align: center;
      padding: 6px 4px;
      border-radius: 6px;
      border: 1px solid #e0e0e0;
    }
    .siasn-summary-card .num {
      font-size: 18px;
      font-weight: 700;
      display: block;
    }
    .siasn-summary-card .lbl {
      font-size: 10px;
      color: #757575;
      display: block;
    }
    .siasn-summary-card.sc-total  .num { color: #1565c0; }
    .siasn-summary-card.sc-ada    .num { color: #2e7d32; }
    .siasn-summary-card.sc-tidak  .num { color: #c62828; }
    .siasn-summary-card.sc-error  .num { color: #e65100; }

    /* ── Inline row badge ── */
    .siasn-row-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      margin-left: 6px;
    }

    /* ── Toggle button ── */
    #siasn-toggle-btn {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 99998;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
      color: #fff;
      border: none;
      box-shadow: 0 4px 16px rgba(0,0,0,.25);
      font-size: 22px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all .2s;
    }
    #siasn-toggle-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 24px rgba(0,0,0,.35);
    }

    /* ── Minimized state ── */
    #siasn-cek-panel.minimized .panel-body,
    #siasn-cek-panel.minimized .panel-footer { display: none; }
  `);

  // ─────────────────── State ─────────────────────────
  const state = {
    token: localStorage.getItem('siasn_cek_token') || '',
    checking: false,
    aborted: false,
    results: new Map(), // nip -> { status, message, data }
    stats: { total: 0, ada: 0, tidak: 0, error: 0 },
  };

  // ─────────────────── Helpers ───────────────────────

  /** Cari NIP dari dalam sebuah elemen tabel row */
  function extractNipFromRow(row) {
    // Coba dari kolom yang berisi 18 digit angka (NIP format)
    const cells = row.querySelectorAll('td');
    for (const cell of cells) {
      const text = (cell.textContent || '').trim();
      // NIP PNS/PPPK = 18 digit
      const match = text.match(/\b(\d{18})\b/);
      if (match) return match[1];
    }
    // Coba dari link/anchor
    const links = row.querySelectorAll('a[href]');
    for (const link of links) {
      const href = link.getAttribute('href') || '';
      const match = href.match(/(\d{18})/);
      if (match) return match[1];
    }
    // Coba dari input hidden
    const inputs = row.querySelectorAll('input[type="hidden"], input[name*="nip"]');
    for (const input of inputs) {
      const match = (input.value || '').match(/(\d{18})/);
      if (match) return match[1];
    }
    return null;
  }

  /** Cari nama pegawai dari row */
  function extractNameFromRow(row) {
    const cells = row.querySelectorAll('td');
    for (const cell of cells) {
      const text = (cell.textContent || '').trim();
      // Nama biasanya di kolom setelah nomor urut dan sebelum NIP, panjang > 3 dan bukan angka murni
      if (text.length > 3 && !/^\d+$/.test(text) && !/^\d{18}$/.test(text)) {
        // Skip jika cell berisi status/tanggal/angka
        if (/^\d{2}[\/-]\d{2}[\/-]\d{4}$/.test(text)) continue;
        if (/^(Aktif|Non Aktif|PNS|PPPK|CPNS)$/i.test(text)) continue;
        return text;
      }
    }
    return null;
  }

  /** Ambil semua baris pegawai yang visible di tabel */
  function getEmployeeRows() {
    // SIASN menggunakan berbagai kemungkinan tabel
    const tables = document.querySelectorAll('table');
    const rows = [];

    for (const table of tables) {
      const bodyRows = table.querySelectorAll('tbody tr');
      for (const row of bodyRows) {
        const nip = extractNipFromRow(row);
        if (nip) {
          rows.push({ row, nip, name: extractNameFromRow(row) });
        }
      }
    }

    // Jika tidak ada tabel, coba elemen card/list (SIASN terkadang pakai card)
    if (rows.length === 0) {
      const cards = document.querySelectorAll(
        '.card, .list-group-item, [class*="pegawai"], [class*="employee"], ' +
        'tr, .row'
      );
      for (const card of cards) {
        const nip = extractNipFromRow(card) || (() => {
          const text = (card.textContent || '');
          const m = text.match(/\b(\d{18})\b/);
          return m ? m[1] : null;
        })();
        if (nip) {
          const name = extractNameFromRow(card) || (() => {
            const text = (card.textContent || '').trim();
            const lines = text.split('\n').map(l => l.trim()).filter(Boolean);
            return lines.find(l => l.length > 3 && !/^\d+$/.test(l) && !/\d{18}/.test(l)) || null;
          })();
          rows.push({ row: card, nip, name });
        }
      }
    }

    // Deduplicate by NIP
    const seen = new Set();
    return rows.filter(r => {
      if (seen.has(r.nip)) return false;
      seen.add(r.nip);
      return true;
    });
  }

  /** Normalize token (ambil bearer token dari berbagai format) */
  function normalizeToken(raw) {
    let token = (raw || '').trim();
    // Dari header format
    let m = token.match(/authorization\s*:\s*bearer\s+([A-Za-z0-9_\-.]+\.[A-Za-z0-9_\-.]+\.[A-Za-z0-9_\-.]+)/i);
    if (m) return m[1];
    // Dari JSON {"token":"..."}
    m = token.match(/["']token["']\s*:\s*["']([^"']+)["']/i);
    if (m) return m[1].trim();
    // Dari cookie token=...
    m = token.match(/(?:^|[;\s])token\s*=\s*"?([^";\s]+)"?/i);
    if (m) return m[1].trim();
    // Dari "Bearer ..."
    if (token.toLowerCase().startsWith('bearer ')) return token.slice(7).trim();
    return token;
  }

  /** Decode JWT payload */
  function jwtPayload(token) {
    try {
      const parts = token.split('.');
      if (parts.length < 2) return null;
      let payload = parts[1].replace(/-/g, '+').replace(/_/g, '/');
      payload += '='.repeat((4 - payload.length % 4) % 4);
      return JSON.parse(atob(payload));
    } catch { return null; }
  }

  /** Cek apakah token expired */
  function isTokenExpired(token) {
    const payload = jwtPayload(token);
    if (!payload || !payload.exp) return false;
    return payload.exp * 1000 <= Date.now();
  }

  /** Cek 1 NIP di SIASN API. Coba PNS dulu, lalu PPPK */
  function checkNipInSiasn(nip, token) {
    return new Promise((resolve) => {
      const endpoints = [
        { label: 'PNS',  url: `${SIASN_API_BASE}/profilasn/api/pns-siasn?nip_baru=${nip}` },
        { label: 'PPPK', url: `${SIASN_API_BASE}/profilasn/api/pppk?nip_lama=&nip_baru=${nip}` },
        { label: 'PPPK-v2', url: `${SIASN_API_BASE}/profilasn/api/pppk-siasn?nip_baru=${nip}` },
      ];

      let idx = 0;

      function tryNext() {
        if (idx >= endpoints.length) {
          resolve({
            status: 'tidak',
            message: 'Data PNS/PPPK TIDAK DITEMUKAN di SIASN',
            data: null,
          });
          return;
        }

        const ep = endpoints[idx];
        idx++;

        GM_xmlhttpRequest({
          method: 'GET',
          url: ep.url,
          headers: {
            'Authorization': 'Bearer ' + token,
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          timeout: 30000,
          onload: function (response) {
            if (response.status === 401 || response.status === 403) {
              resolve({
                status: 'error',
                message: 'Token ditolak/expired (HTTP ' + response.status + ')',
                data: null,
              });
              return;
            }

            if (response.status === 404) {
              tryNext();
              return;
            }

            if (response.status < 200 || response.status >= 300) {
              tryNext();
              return;
            }

            try {
              const json = JSON.parse(response.responseText);
              const values = json.Value || json.value || json.data;
              if (Array.isArray(values) && values.length > 0 && values[0]) {
                const d = values[0];
                resolve({
                  status: 'ada',
                  message: `✓ ADA di SIASN (${ep.label})`,
                  data: {
                    jenisAsn: ep.label,
                    nama: d.nama || '-',
                    jabatan: d.nama_jabatan || d.jabatan_fungsional_nama || '-',
                    unorNama: d.unor_nama || '-',
                    instansi: d.instansi_kerja_nama || '-',
                    id: d.id || '-',
                  },
                });
              } else {
                tryNext();
              }
            } catch (e) {
              tryNext();
            }
          },
          onerror: function () {
            resolve({
              status: 'error',
              message: 'Gagal menghubungi API SIASN (network error)',
              data: null,
            });
          },
          ontimeout: function () {
            resolve({
              status: 'error',
              message: 'Timeout menghubungi API SIASN',
              data: null,
            });
          },
        });
      }

      tryNext();
    });
  }

  /** Escape HTML */
  function esc(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
  }

  // ─────────────────── Ambil Token Otomatis ──────────
  /** Coba ambil token dari cookie/sessionStorage/localStorage SIASN */
  function tryAutoToken() {
    // Coba dari cookie
    const cookies = document.cookie.split(';');
    for (const c of cookies) {
      const [name, ...rest] = c.trim().split('=');
      const val = rest.join('=');
      if (/token|access.token|bearer/i.test(name) && val.startsWith('eyJ')) {
        return val;
      }
    }
    // Coba dari sessionStorage
    for (let i = 0; i < sessionStorage.length; i++) {
      const key = sessionStorage.key(i);
      const val = sessionStorage.getItem(key) || '';
      if (val.startsWith('eyJ') && val.split('.').length === 3) return val;
      try {
        const obj = JSON.parse(val);
        if (obj.access_token && obj.access_token.startsWith('eyJ')) return obj.access_token;
        if (obj.token && obj.token.startsWith('eyJ')) return obj.token;
      } catch {}
    }
    // Coba dari localStorage
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key === 'siasn_cek_token') continue; // skip own token
      const val = localStorage.getItem(key) || '';
      if (val.startsWith('eyJ') && val.split('.').length === 3) return val;
      try {
        const obj = JSON.parse(val);
        if (obj.access_token && obj.access_token.startsWith('eyJ')) return obj.access_token;
        if (obj.token && obj.token.startsWith('eyJ')) return obj.token;
      } catch {}
    }
    return null;
  }

  // ─────────────────── UI ────────────────────────────

  function createPanel() {
    // Toggle FAB
    const fab = document.createElement('button');
    fab.id = 'siasn-toggle-btn';
    fab.innerHTML = '🔍';
    fab.title = 'Buka/Tutup Panel Cek SIASN';
    document.body.appendChild(fab);

    // Panel
    const panel = document.createElement('div');
    panel.id = 'siasn-cek-panel';

    const pageType = location.pathname.includes('pppk-paruh-waktu') ? 'PPPK Paruh Waktu' : 'PNS';

    panel.innerHTML = `
      <div class="panel-header">
        <h3>🔍 Cek Data SIASN — ${esc(pageType)}</h3>
        <div style="display:flex;gap:6px;align-items:center;">
          <button class="siasn-btn siasn-btn-xs" id="siasn-minimize" title="Minimize" style="background:rgba(255,255,255,.2);color:#fff;">─</button>
          <button class="siasn-btn siasn-btn-xs" id="siasn-close" title="Tutup" style="background:rgba(255,255,255,.2);color:#fff;">✕</button>
        </div>
      </div>
      <div class="panel-body">
        <label style="font-weight:600;margin-bottom:4px;display:block;">
          🔑 Bearer Token SIASN:
          <button class="siasn-btn siasn-btn-xs siasn-btn-primary" id="siasn-auto-token" title="Coba ambil token otomatis dari session">Auto</button>
        </label>
        <textarea id="siasn-token-input" rows="2" placeholder="Paste token dari browser DevTools (F12) → Network → Header Authorization: Bearer eyJ...">${esc(state.token)}</textarea>
        <div id="siasn-token-status" style="font-size:11px;margin-bottom:8px;"></div>

        <div class="siasn-summary" id="siasn-summary">
          <div class="siasn-summary-card sc-total"><span class="num" id="ss-total">0</span><span class="lbl">Total</span></div>
          <div class="siasn-summary-card sc-ada"><span class="num" id="ss-ada">0</span><span class="lbl">Ada ✓</span></div>
          <div class="siasn-summary-card sc-tidak"><span class="num" id="ss-tidak">0</span><span class="lbl">Tidak Ada</span></div>
          <div class="siasn-summary-card sc-error"><span class="num" id="ss-error">0</span><span class="lbl">Error</span></div>
        </div>

        <div class="siasn-progress" id="siasn-progress" style="display:none;">
          <div class="siasn-progress-bar" id="siasn-progress-bar" style="width:0%"></div>
        </div>
        <div id="siasn-progress-text" style="font-size:11px;color:#757575;text-align:center;display:none;"></div>

        <div id="siasn-log"></div>
      </div>
      <div class="panel-footer">
        <button class="siasn-btn siasn-btn-primary" id="siasn-scan">📋 Scan Pegawai</button>
        <button class="siasn-btn siasn-btn-success" id="siasn-cek-all" disabled>🚀 Cek Semua</button>
        <button class="siasn-btn siasn-btn-danger" id="siasn-stop" style="display:none;">⏹ Stop</button>
        <button class="siasn-btn siasn-btn-warning" id="siasn-export" disabled>📊 Export</button>
      </div>
    `;
    document.body.appendChild(panel);

    // Event handlers
    fab.addEventListener('click', () => {
      panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
    });

    document.getElementById('siasn-minimize').addEventListener('click', () => {
      panel.classList.toggle('minimized');
    });

    document.getElementById('siasn-close').addEventListener('click', () => {
      panel.style.display = 'none';
    });

    document.getElementById('siasn-token-input').addEventListener('input', (e) => {
      const raw = e.target.value;
      const token = normalizeToken(raw);
      state.token = token;
      localStorage.setItem('siasn_cek_token', token);
      updateTokenStatus();
    });

    document.getElementById('siasn-auto-token').addEventListener('click', () => {
      const autoToken = tryAutoToken();
      if (autoToken) {
        state.token = normalizeToken(autoToken);
        document.getElementById('siasn-token-input').value = state.token;
        localStorage.setItem('siasn_cek_token', state.token);
        updateTokenStatus();
        addLog('Token berhasil diambil otomatis dari session browser.', 'ok');
      } else {
        addLog('Tidak bisa menemukan token otomatis. Paste manual dari DevTools.', 'warn');
      }
    });

    document.getElementById('siasn-scan').addEventListener('click', scanEmployees);
    document.getElementById('siasn-cek-all').addEventListener('click', checkAllEmployees);
    document.getElementById('siasn-stop').addEventListener('click', () => { state.aborted = true; });
    document.getElementById('siasn-export').addEventListener('click', exportResults);

    // Draggable panel
    makeDraggable(panel, panel.querySelector('.panel-header'));

    // Initial token status
    updateTokenStatus();
  }

  function updateTokenStatus() {
    const el = document.getElementById('siasn-token-status');
    if (!state.token) {
      el.innerHTML = '<span style="color:#e65100;">⚠️ Token belum diisi</span>';
      return;
    }
    if (isTokenExpired(state.token)) {
      el.innerHTML = '<span style="color:#c62828;">❌ Token sudah expired! Login ulang di SIASN.</span>';
      return;
    }
    const payload = jwtPayload(state.token);
    if (payload) {
      const nama = payload.pegawai?.nama || payload.nama || '';
      const exp = payload.exp ? new Date(payload.exp * 1000).toLocaleString('id-ID') : '?';
      el.innerHTML = `<span style="color:#2e7d32;">✓ Token valid${nama ? ' — ' + esc(nama) : ''} | Expired: ${esc(exp)}</span>`;
    } else {
      el.innerHTML = '<span style="color:#e65100;">⚠️ Token tidak bisa didecode, tapi akan dicoba</span>';
    }
  }

  function updateSummary() {
    document.getElementById('ss-total').textContent = state.stats.total;
    document.getElementById('ss-ada').textContent = state.stats.ada;
    document.getElementById('ss-tidak').textContent = state.stats.tidak;
    document.getElementById('ss-error').textContent = state.stats.error;
  }

  function addLog(message, type = 'info') {
    const log = document.getElementById('siasn-log');
    const line = document.createElement('div');
    line.className = 'log-' + type;
    const time = new Date().toLocaleTimeString('id-ID');
    line.innerHTML = `<small style="color:#9e9e9e;">[${time}]</small> ${message}`;
    log.appendChild(line);
    log.scrollTop = log.scrollHeight;
  }

  function clearLog() {
    document.getElementById('siasn-log').innerHTML = '';
  }

  function updateProgress(current, total) {
    const bar = document.getElementById('siasn-progress-bar');
    const text = document.getElementById('siasn-progress-text');
    const pct = total > 0 ? Math.round((current / total) * 100) : 0;
    bar.style.width = pct + '%';
    text.textContent = `${current} / ${total} (${pct}%)`;
  }

  function showProgress(show) {
    document.getElementById('siasn-progress').style.display = show ? 'block' : 'none';
    document.getElementById('siasn-progress-text').style.display = show ? 'block' : 'none';
  }

  /** Tambahkan badge status di samping baris pegawai di tabel */
  function addStatusBadgeToRow(row, nip, result) {
    // Hapus badge lama jika ada
    const oldBadge = row.querySelector('.siasn-row-badge');
    if (oldBadge) oldBadge.remove();

    const badge = document.createElement('span');
    badge.className = 'siasn-row-badge';

    const statusClass = result.status === 'ada' ? 'siasn-status-ada'
      : result.status === 'tidak' ? 'siasn-status-tidak'
      : 'siasn-status-error';

    let tooltip = result.message;
    if (result.data) {
      tooltip += `\nNama: ${result.data.nama}\nJabatan: ${result.data.jabatan}\nUnor: ${result.data.unorNama}\nJenis: ${result.data.jenisAsn}`;
    }

    badge.innerHTML = `<span class="siasn-status ${statusClass}" title="${esc(tooltip)}">${
      result.status === 'ada' ? '✓ ADA' :
      result.status === 'tidak' ? '✗ TIDAK ADA' :
      '⚠ ERROR'
    }</span>`;

    // Coba tambahkan di kolom terakhir atau di samping NIP
    const cells = row.querySelectorAll('td');
    let targetCell = null;

    // Cari cell yang berisi NIP
    for (const cell of cells) {
      if ((cell.textContent || '').includes(nip)) {
        targetCell = cell;
        break;
      }
    }

    if (targetCell) {
      targetCell.appendChild(badge);
    } else if (cells.length > 0) {
      cells[cells.length - 1].appendChild(badge);
    } else {
      row.appendChild(badge);
    }
  }

  /** Set badge loading */
  function setRowLoading(row) {
    const oldBadge = row.querySelector('.siasn-row-badge');
    if (oldBadge) oldBadge.remove();

    const badge = document.createElement('span');
    badge.className = 'siasn-row-badge';
    badge.innerHTML = '<span class="siasn-status siasn-status-loading">⏳ Checking...</span>';

    const cells = row.querySelectorAll('td');
    if (cells.length > 0) {
      cells[cells.length - 1].appendChild(badge);
    } else {
      row.appendChild(badge);
    }
  }

  // ─────────────────── Actions ───────────────────────

  /** Scan tabel untuk menemukan pegawai */
  function scanEmployees() {
    const employees = getEmployeeRows();
    state.stats = { total: employees.length, ada: 0, tidak: 0, error: 0 };
    state.results.clear();
    updateSummary();
    clearLog();

    if (employees.length === 0) {
      addLog('⚠️ Tidak ditemukan data pegawai di halaman ini. Pastikan tabel pegawai sudah tampil/di-load.', 'warn');
      addLog('Tips: Klik "Tampilkan" atau navigasi ke halaman list pegawai, lalu klik "Scan Pegawai" lagi.', 'info');
      document.getElementById('siasn-cek-all').disabled = true;
      return;
    }

    addLog(`📋 Ditemukan <strong>${employees.length}</strong> pegawai di halaman ini:`, 'info');
    employees.forEach((emp, i) => {
      addLog(`  ${i + 1}. ${esc(emp.name || '-')} — NIP ${esc(emp.nip)}`, 'info');
      // Tambah badge pending
      const badge = document.createElement('span');
      badge.className = 'siasn-row-badge';
      badge.innerHTML = '<span class="siasn-status siasn-status-pending">⏸ Belum dicek</span>';
      const cells = emp.row.querySelectorAll('td');
      const oldBadge = emp.row.querySelector('.siasn-row-badge');
      if (oldBadge) oldBadge.remove();
      if (cells.length > 0) cells[cells.length - 1].appendChild(badge);
      else emp.row.appendChild(badge);
    });

    document.getElementById('siasn-cek-all').disabled = false;
    addLog('Klik <strong>"🚀 Cek Semua"</strong> untuk mulai cek ke API SIASN.', 'info');
  }

  /** Cek semua pegawai satu per satu */
  async function checkAllEmployees() {
    if (!state.token) {
      addLog('❌ Token SIASN belum diisi! Paste token terlebih dahulu.', 'fail');
      return;
    }
    if (isTokenExpired(state.token)) {
      addLog('❌ Token SIASN sudah expired! Login ulang di SIASN lalu paste token baru.', 'fail');
      return;
    }

    const employees = getEmployeeRows();
    if (employees.length === 0) {
      addLog('⚠️ Scan ulang: tidak ada pegawai ditemukan di halaman.', 'warn');
      return;
    }

    state.checking = true;
    state.aborted = false;
    state.stats = { total: employees.length, ada: 0, tidak: 0, error: 0 };
    state.results.clear();
    updateSummary();
    clearLog();

    document.getElementById('siasn-cek-all').disabled = true;
    document.getElementById('siasn-stop').style.display = 'inline-flex';
    document.getElementById('siasn-export').disabled = true;
    showProgress(true);
    updateProgress(0, employees.length);

    addLog(`🚀 Mulai cek ${employees.length} pegawai ke API SIASN...`, 'info');

    let checked = 0;

    for (const emp of employees) {
      if (state.aborted) {
        addLog('⏹ Proses dihentikan oleh user.', 'warn');
        break;
      }

      setRowLoading(emp.row);
      addLog(`⏳ Cek ${esc(emp.name || emp.nip)} (NIP: ${esc(emp.nip)})...`, 'info');

      const result = await checkNipInSiasn(emp.nip, state.token);
      state.results.set(emp.nip, { ...result, name: emp.name });

      // Update stats
      if (result.status === 'ada') state.stats.ada++;
      else if (result.status === 'tidak') state.stats.tidak++;
      else state.stats.error++;

      // Update UI
      checked++;
      updateSummary();
      updateProgress(checked, employees.length);
      addStatusBadgeToRow(emp.row, emp.nip, result);

      // Log result
      if (result.status === 'ada') {
        const d = result.data;
        addLog(`✅ <strong>${esc(emp.name || emp.nip)}</strong> — ADA (${esc(d.jenisAsn)}) | ${esc(d.unorNama)}`, 'ok');
      } else if (result.status === 'tidak') {
        addLog(`❌ <strong>${esc(emp.name || emp.nip)}</strong> — <strong>TIDAK ADA DATA di SIASN</strong> (kemungkinan pensiun/mutasi/belum terdaftar)`, 'fail');
      } else {
        addLog(`⚠️ <strong>${esc(emp.name || emp.nip)}</strong> — ERROR: ${esc(result.message)}`, 'warn');
        // Jika token expired, hentikan proses
        if (result.message.includes('Token ditolak') || result.message.includes('expired')) {
          addLog('🛑 Token ditolak/expired! Proses dihentikan. Login ulang di SIASN.', 'fail');
          state.aborted = true;
        }
      }

      // Delay antar request
      if (!state.aborted && checked < employees.length) {
        await new Promise(r => setTimeout(r, CHECK_DELAY_MS));
      }
    }

    // Selesai
    state.checking = false;
    document.getElementById('siasn-cek-all').disabled = false;
    document.getElementById('siasn-stop').style.display = 'none';
    document.getElementById('siasn-export').disabled = false;
    showProgress(false);

    addLog('', 'info');
    addLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'info');
    addLog(`📊 <strong>SELESAI</strong> — Total: ${state.stats.total} | ✓ Ada: ${state.stats.ada} | ✗ Tidak ada: ${state.stats.tidak} | ⚠ Error: ${state.stats.error}`, 'info');

    if (state.stats.tidak > 0) {
      addLog('', 'info');
      addLog('📋 <strong>Daftar pegawai TIDAK ADA di SIASN:</strong>', 'fail');
      for (const [nip, res] of state.results) {
        if (res.status === 'tidak') {
          addLog(`   • ${esc(res.name || '-')} — NIP ${esc(nip)}`, 'fail');
        }
      }
    }
  }

  /** Export hasil ke CSV */
  function exportResults() {
    if (state.results.size === 0) {
      addLog('⚠️ Belum ada hasil untuk di-export.', 'warn');
      return;
    }

    const pageType = location.pathname.includes('pppk-paruh-waktu') ? 'PPPK_Paruh_Waktu' : 'PNS';
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
    const filename = `cek_siasn_${pageType}_${timestamp}.csv`;

    let csv = '﻿'; // BOM for Excel
    csv += 'No,NIP,Nama,Status SIASN,Jenis ASN,Jabatan,Unit Organisasi,Instansi,Keterangan\n';

    let no = 1;
    for (const [nip, res] of state.results) {
      const status = res.status === 'ada' ? 'ADA' : res.status === 'tidak' ? 'TIDAK ADA' : 'ERROR';
      const d = res.data || {};
      csv += [
        no++,
        nip,
        `"${(res.name || '-').replace(/"/g, '""')}"`,
        status,
        d.jenisAsn || '-',
        `"${(d.jabatan || '-').replace(/"/g, '""')}"`,
        `"${(d.unorNama || '-').replace(/"/g, '""')}"`,
        `"${(d.instansi || '-').replace(/"/g, '""')}"`,
        `"${(res.message || '').replace(/"/g, '""')}"`,
      ].join(',') + '\n';
    }

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);

    addLog(`📥 File <strong>${esc(filename)}</strong> berhasil di-download.`, 'ok');
  }

  /** Make element draggable */
  function makeDraggable(el, handle) {
    let startX, startY, initialX, initialY;
    handle.addEventListener('mousedown', (e) => {
      if (e.target.tagName === 'BUTTON') return;
      startX = e.clientX;
      startY = e.clientY;
      const rect = el.getBoundingClientRect();
      initialX = rect.left;
      initialY = rect.top;

      function onMove(ev) {
        el.style.left = (initialX + ev.clientX - startX) + 'px';
        el.style.top = (initialY + ev.clientY - startY) + 'px';
        el.style.right = 'auto';
      }
      function onUp() {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
      }
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  }

  // ─────────────────── Pagination Observer ────────────

  /** Observe perubahan halaman (SPA / pagination) */
  function observePageChanges() {
    // MutationObserver untuk detect kalau tabel berubah (pagination, filter, dll)
    const observer = new MutationObserver(() => {
      // Jika ada tabel baru muncul dan kita sedang tidak checking, reset badge
      if (!state.checking) {
        // Auto-scan setelah delay kecil
        clearTimeout(window._siasnRescanTimer);
        window._siasnRescanTimer = setTimeout(() => {
          const employees = getEmployeeRows();
          if (employees.length > 0) {
            // Re-apply badges dari results yang sudah ada
            for (const emp of employees) {
              const existing = state.results.get(emp.nip);
              if (existing) {
                addStatusBadgeToRow(emp.row, emp.nip, existing);
              }
            }
          }
        }, 1000);
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

  // ─────────────────── Init ──────────────────────────

  // Tunggu halaman fully loaded
  function init() {
    createPanel();
    observePageChanges();

    // Coba auto-detect token
    if (!state.token) {
      const autoToken = tryAutoToken();
      if (autoToken) {
        state.token = normalizeToken(autoToken);
        document.getElementById('siasn-token-input').value = state.token;
        localStorage.setItem('siasn_cek_token', state.token);
        updateTokenStatus();
        addLog('🔑 Token otomatis terdeteksi dari session browser.', 'ok');
      }
    }

    addLog(`🏁 Script SIASN Cek Pegawai aktif di halaman ${location.pathname.includes('pppk-paruh-waktu') ? 'PPPK Paruh Waktu' : 'PNS'}.`, 'info');
    addLog('Klik <strong>"📋 Scan Pegawai"</strong> untuk mulai scan tabel pegawai.', 'info');
  }

  if (document.readyState === 'complete') {
    init();
  } else {
    window.addEventListener('load', init);
  }
})();
