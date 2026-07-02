// ==UserScript==
// @name         SIASN Token Bridge — Auto Kirim Token ke Aplikasi Lokal
// @namespace    https://disiplinbkpsdm.banjarmasinkota.go.id/
// @version      1.0
// @description  Setelah login di ASN Digital / SIASN Instansi, otomatis kirim access_token JWT ke parent window (popup opener) agar tidak perlu paste manual. Untuk integrasi internal BKPSDM.
// @author       BKPSDM Kota Banjarmasin
// @match        https://asndigital.bkn.go.id/*
// @match        https://siasn-instansi.bkn.go.id/*
// @grant        none
// @run-at       document-idle
// ==/UserScript==

(function () {
  'use strict';

  // ─────────────────── Hanya aktif jika dibuka sebagai popup ───────────────────
  // Script ini HANYA mengirim token jika window dibuka via window.open (popup)
  // dari aplikasi lokal kita. Tidak melakukan apa-apa di tab biasa.
  if (!window.opener) {
    return;
  }

  // ─────────────────── Config ───────────────────
  const POLL_INTERVAL_MS = 2000;
  const MAX_POLL_DURATION_MS = 600000; // 10 menit
  const startTime = Date.now();
  let tokenSent = false;

  // ─────────────────── Helpers ───────────────────

  function isJwt(str) {
    return typeof str === 'string'
      && str.startsWith('eyJ')
      && str.split('.').length === 3
      && str.length > 50;
  }

  function jwtPayload(token) {
    try {
      const parts = token.split('.');
      if (parts.length < 2) return null;
      let payload = parts[1].replace(/-/g, '+').replace(/_/g, '/');
      payload += '='.repeat((4 - payload.length % 4) % 4);
      return JSON.parse(atob(payload));
    } catch {
      return null;
    }
  }

  function isValidAccessToken(token) {
    if (!isJwt(token)) return false;
    const payload = jwtPayload(token);
    if (!payload) return false;
    // Tolak refresh token
    if ((payload.typ || '').toLowerCase() === 'refresh') return false;
    // Tolak token expired
    if (payload.exp && payload.exp * 1000 <= Date.now()) return false;
    return true;
  }

  // ─────────────────── Cari token dari storage ───────────────────

  function findTokenFromCookies() {
    const cookies = document.cookie.split(';');
    for (const c of cookies) {
      const parts = c.trim().split('=');
      const name = (parts[0] || '').trim();
      const val = parts.slice(1).join('=').trim();
      if (val && isValidAccessToken(val)) return val;
      // Cek nama cookie yang mungkin berisi token
      if (/token|access.token|bearer|jwt/i.test(name) && val.startsWith('eyJ')) {
        if (isValidAccessToken(val)) return val;
      }
    }
    return null;
  }

  function findTokenFromStorage(storage) {
    try {
      for (let i = 0; i < storage.length; i++) {
        const key = storage.key(i);
        const val = storage.getItem(key) || '';

        // Langsung JWT
        if (isValidAccessToken(val)) return val;

        // JSON yang berisi token
        try {
          const obj = JSON.parse(val);
          if (obj && typeof obj === 'object') {
            for (const k of ['access_token', 'token', 'accessToken', 'bearer_token']) {
              if (obj[k] && isValidAccessToken(obj[k])) return obj[k];
            }
          }
        } catch {}
      }
    } catch {}
    return null;
  }

  function findToken() {
    return findTokenFromCookies()
      || findTokenFromStorage(localStorage)
      || findTokenFromStorage(sessionStorage);
  }

  // ─────────────────── Kirim token ke parent ───────────────────

  function sendTokenToOpener(token) {
    if (tokenSent) return;
    tokenSent = true;

    try {
      window.opener.postMessage({
        type: 'SIASN_TOKEN',
        token: token,
        source: 'siasn-token-bridge',
      }, '*');
    } catch (e) {
      // opener mungkin sudah ditutup
    }

    showBadge('success', '✅ Token SIASN terkirim ke aplikasi!');

    // Auto-close popup setelah 3 detik
    setTimeout(() => {
      try { window.close(); } catch {}
    }, 3000);
  }

  // ─────────────────── Floating badge UI ───────────────────

  function createBadge() {
    const badge = document.createElement('div');
    badge.id = 'siasn-bridge-badge';
    badge.style.cssText = [
      'position: fixed',
      'bottom: 16px',
      'right: 16px',
      'z-index: 999999',
      'background: #1e40af',
      'color: #fff',
      'padding: 10px 16px',
      'border-radius: 10px',
      'font-family: "Segoe UI", Tahoma, sans-serif',
      'font-size: 13px',
      'font-weight: 600',
      'box-shadow: 0 4px 20px rgba(0,0,0,0.25)',
      'display: flex',
      'align-items: center',
      'gap: 8px',
      'max-width: 360px',
      'transition: all 0.3s ease',
    ].join(';');
    badge.innerHTML = '⏳ Menunggu login SIASN... Token akan otomatis terkirim.';
    document.body.appendChild(badge);
    return badge;
  }

  function showBadge(type, message) {
    let badge = document.getElementById('siasn-bridge-badge');
    if (!badge) badge = createBadge();

    badge.textContent = message;

    if (type === 'success') {
      badge.style.background = '#059669';
    } else if (type === 'error') {
      badge.style.background = '#dc2626';
    } else {
      badge.style.background = '#1e40af';
    }
  }

  // ─────────────────── Intercept XHR/Fetch ───────────────────

  // Hook XMLHttpRequest agar bisa menangkap token dari response header
  const origXhrOpen = XMLHttpRequest.prototype.open;
  const origXhrSend = XMLHttpRequest.prototype.send;

  XMLHttpRequest.prototype.open = function (method, url) {
    this._siasnUrl = url;
    return origXhrOpen.apply(this, arguments);
  };

  XMLHttpRequest.prototype.send = function () {
    this.addEventListener('load', function () {
      if (tokenSent) return;
      try {
        // Cek Authorization response header
        const authHeader = this.getResponseHeader('Authorization');
        if (authHeader) {
          const token = authHeader.replace(/^Bearer\s+/i, '').trim();
          if (isValidAccessToken(token)) {
            sendTokenToOpener(token);
            return;
          }
        }

        // Cek response body untuk JSON berisi token
        const contentType = this.getResponseHeader('Content-Type') || '';
        if (contentType.includes('json')) {
          try {
            const json = JSON.parse(this.responseText);
            for (const k of ['access_token', 'token', 'accessToken', 'bearer_token']) {
              if (json[k] && isValidAccessToken(json[k])) {
                sendTokenToOpener(json[k]);
                return;
              }
            }
          } catch {}
        }
      } catch {}
    });

    return origXhrSend.apply(this, arguments);
  };

  // Hook fetch
  const origFetch = window.fetch;
  window.fetch = function () {
    return origFetch.apply(this, arguments).then(response => {
      if (tokenSent) return response;

      // Clone response agar bisa dibaca dua kali
      const cloned = response.clone();

      // Cek header Authorization
      try {
        const authHeader = response.headers.get('Authorization');
        if (authHeader) {
          const token = authHeader.replace(/^Bearer\s+/i, '').trim();
          if (isValidAccessToken(token)) {
            sendTokenToOpener(token);
            return response;
          }
        }
      } catch {}

      // Cek body JSON
      cloned.text().then(text => {
        if (tokenSent) return;
        try {
          const json = JSON.parse(text);
          for (const k of ['access_token', 'token', 'accessToken', 'bearer_token']) {
            if (json[k] && isValidAccessToken(json[k])) {
              sendTokenToOpener(json[k]);
              return;
            }
          }
        } catch {}
      }).catch(() => {});

      return response;
    });
  };

  // ─────────────────── Init: Poll untuk token ───────────────────

  createBadge();

  // Cek langsung saat load
  const immediateToken = findToken();
  if (immediateToken) {
    sendTokenToOpener(immediateToken);
  } else {
    // Poll berkala
    const poller = setInterval(() => {
      if (tokenSent) {
        clearInterval(poller);
        return;
      }

      // Timeout
      if (Date.now() - startTime > MAX_POLL_DURATION_MS) {
        clearInterval(poller);
        showBadge('error', '⏰ Timeout. Login SIASN tidak terdeteksi dalam 10 menit.');
        return;
      }

      const token = findToken();
      if (token) {
        clearInterval(poller);
        sendTokenToOpener(token);
      }
    }, POLL_INTERVAL_MS);
  }
})();
