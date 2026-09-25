/* لایه داده مشترک بین سایت و پنل مدیریت (API لاراول یا حالت استاتیک) */
const STORE_KEY = 'team-site-data';
const TOKEN_KEY = 'team-site-token';
const API = ((window.SITE_CONFIG || {}).apiUrl || '').replace(/\/$/, '');

const Store = {
  api: !!API,
  online: false,
  load() {
    try {
      const raw = localStorage.getItem(STORE_KEY);
      if (raw) return JSON.parse(raw);
    } catch (e) {}
    return JSON.parse(JSON.stringify(window.DEFAULT_DATA));
  },
  save(data) { try { localStorage.setItem(STORE_KEY, JSON.stringify(data)); } catch (e) {} },
  reset() { try { localStorage.removeItem(STORE_KEY); } catch (e) {} },
  /* محتوای سایت: اول از API، در صورت خطا از داده محلی */
  async loadSite() {
    if (API) {
      try { const d = await Api.req('GET', '/site'); this.online = true; return d; } catch (e) { console.warn('API در دسترس نیست؛ استفاده از data.js', e); }
    }
    return this.load();
  }
};

const Api = {
  get token() { try { return localStorage.getItem(TOKEN_KEY); } catch (e) { return null; } },
  set token(v) { try { v ? localStorage.setItem(TOKEN_KEY, v) : localStorage.removeItem(TOKEN_KEY); } catch (e) {} },
  async req(method, path, body) {
    const isForm = body instanceof FormData;
    const res = await fetch(API + path, {
      method,
      headers: { Accept: 'application/json', ...(isForm || !body ? {} : { 'Content-Type': 'application/json' }), ...(this.token ? { Authorization: 'Bearer ' + this.token } : {}) },
      body: body ? (isForm ? body : JSON.stringify(body)) : undefined
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
      const err = new Error(json.errors ? Object.values(json.errors).flat()[0] : (json.message || 'خطای سرور'));
      err.status = res.status; throw err;
    }
    return json;
  }
};

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const safeUrl = u => /^(https?:|mailto:|tel:|data:image\/|\/|#|\.)/i.test(String(u || '').trim()) ? u : '#';
const LEVELS = { Junior: 'جونیور', Mid: 'میدلول', Senior: 'سینیور', Lead: 'لید' };
