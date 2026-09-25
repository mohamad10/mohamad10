/* لایه ذخیره‌سازی مشترک بین سایت و پنل مدیریت */
const STORE_KEY = 'team-site-data';
const Store = {
  load() {
    try {
      const raw = localStorage.getItem(STORE_KEY);
      if (raw) return JSON.parse(raw);
    } catch (e) {}
    return JSON.parse(JSON.stringify(window.DEFAULT_DATA));
  },
  save(data) { localStorage.setItem(STORE_KEY, JSON.stringify(data)); },
  reset() { localStorage.removeItem(STORE_KEY); }
};
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const LEVELS = { Junior: 'جونیور', Mid: 'میدلول', Senior: 'سینیور', Lead: 'لید' };
