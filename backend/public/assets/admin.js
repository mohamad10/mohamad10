/* پنل مدیریت: ویرایش چندزبانه همه محتوای سایت از طریق API لاراول */
(() => {
const $ = (s, r = document) => r.querySelector(s);
const h = html => { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstElementChild; };
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const LEVELS = { Junior: 'جونیور', Mid: 'میدلول', Senior: 'سینیور', Lead: 'لید' };
const TOKEN_KEY = 'team-site-token', LANG_KEY = 'admin-edit-lang';
const store = { get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} } };

/* ---------- API ---------- */
const Api = {
  async req(method, path, body) {
    const isForm = body instanceof FormData, token = store.get(TOKEN_KEY);
    const res = await fetch(ADMIN.api + path, {
      method,
      headers: { Accept: 'application/json', ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}), ...(token ? { Authorization: 'Bearer ' + token } : {}) },
      body: body ? (isForm ? body : JSON.stringify(body)) : undefined
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw Object.assign(new Error(json.errors ? Object.values(json.errors).flat()[0] : (json.message || 'خطای سرور')), { status: res.status, errors: json.errors });
    return json;
  }
};

/* ---------- State ---------- */
let data, meta, lang, current;
const locales = () => meta.locales;
const def = () => meta.default_locale;
const locInfo = code => locales().find(l => l.code === code) || { dir: 'ltr', native: code };
/* مقدار یک فیلد ترجمه‌پذیر در زبان دلخواه (با بازگشت به زبان پیش‌فرض) */
const tx = (v, code = lang) => v && typeof v === 'object' ? (v[code] || v[def()] || Object.values(v).find(Boolean) || '') : (v || '');

/* ---------- Login ---------- */
const lockMsg = (t, err) => { $('#lockMsg').textContent = t; $('#lockMsg').style.color = err ? 'var(--err)' : ''; };
if (store.get(TOKEN_KEY)) Api.req('GET', '/auth/me').then(enter).catch(() => store.set(TOKEN_KEY, null));
$('#lockForm').onsubmit = async e => {
  e.preventDefault();
  const btn = e.target.querySelector('button'); btn.disabled = true;
  try {
    const r = await Api.req('POST', '/auth/login', { email: $('#email').value, password: $('#pass').value });
    store.set(TOKEN_KEY, r.token); await enter(r.user);
  } catch (err) { lockMsg(err.status === 429 ? 'تلاش‌های زیاد؛ یک دقیقه صبر کنید' : (err.message === 'Failed to fetch' ? 'اتصال به سرور برقرار نشد' : err.message), true); }
  btn.disabled = false;
};
async function enter() {
  const res = await Api.req('GET', '/site');
  meta = res.meta; delete res.meta; data = res;
  lang = locales().some(l => l.code === store.get(LANG_KEY)) ? store.get(LANG_KEY) : def();
  $('#lock')?.remove(); $('#app').hidden = false;
  renderLangSwitch(); showTab('team'); refreshUnread();
}
$('#logout').onclick = async () => { await Api.req('POST', '/auth/logout').catch(() => {}); store.set(TOKEN_KEY, null); location.reload(); };

function renderLangSwitch() {
  const box = $('#langSwitch');
  box.innerHTML = locales().map(l => `<button lang="${l.code}" data-l="${l.code}" class="${l.code === lang ? 'on' : ''}">${esc(l.native)}</button>`).join('');
  box.onclick = e => { const b = e.target.closest('button'); if (!b) return; lang = b.dataset.l; store.set(LANG_KEY, lang); renderLangSwitch(); showTab(current); };
  $('#viewSite').href = locInfo(lang).url || ADMIN.site;
}

/* ---------- Autosave ---------- */
let statusTimer, syncTimer, syncing = false, pending = false, dirty = false;
const status = (t, cls = '') => { const el = $('#saved'); el.textContent = t; el.className = 'saved ' + cls; clearTimeout(statusTimer); if (cls === 'ok') statusTimer = setTimeout(() => el.textContent = '', 2000); };
function save() { dirty = true; status('در حال ذخیره…'); clearTimeout(syncTimer); syncTimer = setTimeout(sync, 700); }
async function sync() {
  if (syncing) { pending = true; return; }
  syncing = true; dirty = false;
  try { await Api.req('PUT', '/admin/site', data); status('ذخیره شد ✓', 'ok'); }
  catch (err) { dirty = true; status('ذخیره نشد: ' + err.message, 'err'); if (err.status === 401) location.reload(); }
  syncing = false;
  if (pending) { pending = false; sync(); }
}
addEventListener('beforeunload', e => { if (dirty || syncing) e.preventDefault(); });

/* ---------- Field definitions ----------
 * t: text | textarea | number | select | bool | tags | image | members | list | skills
 * tr: translatable (stored as {en: "...", fa: "..."}) */
const S = {
  team: [
    { k: 'name', l: 'نام تیم', tr: 1 }, { k: 'tagline', l: 'شعار (تیتر اصلی صفحه)', tr: 1, wide: 1 },
    { k: 'about', l: 'درباره تیم', t: 'textarea', tr: 1, wide: 1 },
    { k: 'email', l: 'ایمیل', dir: 'ltr' }, { k: 'phone', l: 'تلفن', dir: 'ltr' }, { k: 'location', l: 'آدرس', tr: 1 },
    { k: 'stats', l: 'آمار صفحه اصلی', t: 'list', sub: [{ k: 'value', l: 'مقدار (مثلاً 40+)', dir: 'ltr' }, { k: 'label', l: 'عنوان', tr: 1 }] },
    { k: 'socials', l: 'شبکه‌های اجتماعی (نام: GitHub، Telegram، X، Instagram، …)', t: 'list', sub: [{ k: 'label', l: 'نام', dir: 'ltr' }, { k: 'url', l: 'لینک', dir: 'ltr' }] }
  ],
  services: [{ k: 'icon', l: 'آیکون (ایموجی)' }, { k: 'title', l: 'عنوان', tr: 1 }, { k: 'desc', l: 'توضیح', t: 'textarea', tr: 1, wide: 1 }],
  members: [
    { k: 'name', l: 'نام و نام خانوادگی', tr: 1 }, { k: 'role', l: 'سمت', tr: 1 },
    { k: 'level', l: 'سطح تخصص', t: 'select', opts: LEVELS }, { k: 'years', l: 'سال‌های سابقه', t: 'number' },
    { k: 'location', l: 'شهر', tr: 1 }, { k: 'education', l: 'تحصیلات', tr: 1 }, { k: 'languages', l: 'زبان‌ها', tr: 1 },
    { k: 'available', l: 'آماده همکاری', t: 'bool' },
    { k: 'avatar', l: 'عکس پروفایل', t: 'image', preset: 'avatar', wide: 1 },
    { k: 'bio', l: 'بیوگرافی', t: 'textarea', tr: 1, wide: 1 },
    { k: 'skills', l: 'مهارت‌ها و میزان تسلط', t: 'skills' },
    { k: 'links', l: 'لینک‌ها', t: 'list', sub: [{ k: 'label', l: 'نام', dir: 'ltr' }, { k: 'url', l: 'لینک', dir: 'ltr' }] }
  ],
  projects: [
    { k: 'title', l: 'عنوان پروژه', tr: 1 }, { k: 'category', l: 'دسته‌بندی', tr: 1, suggest: 1 }, { k: 'year', l: 'سال', tr: 1 },
    { k: 'link', l: 'لینک', dir: 'ltr' },
    { k: 'desc', l: 'توضیح', t: 'textarea', tr: 1, wide: 1 },
    { k: 'tech', l: 'تکنولوژی‌ها (با کاما جدا کنید)', t: 'tags', wide: 1 },
    { k: 'image', l: 'تصویر کاور', t: 'image', preset: 'cover', wide: 1 },
    { k: 'members', l: 'اعضای درگیر در پروژه', t: 'members', wide: 1 }
  ]
};
const TABS = { team: 'اطلاعات تیم', services: 'تخصص‌ها و خدمات', members: 'اعضای تیم', projects: 'نمونه‌کارها', messages: 'پیام‌ها', tools: 'پشتیبان‌گیری و تنظیمات' };
const both = (en, fa) => Object.fromEntries(locales().map(l => [l.code, l.code === 'fa' ? fa : l.code === 'en' ? en : '']));
const NEW = {
  services: () => ({ icon: '✨', title: both('New service', 'خدمت جدید'), desc: {} }),
  members: () => ({ id: 'm' + Date.now().toString(36), name: both('New member', 'عضو جدید'), role: {}, level: 'Mid', years: 1, avatar: '', available: true, bio: {}, skills: [], links: [], location: {}, education: {}, languages: {} }),
  projects: () => ({ title: both('New project', 'پروژه جدید'), category: {}, year: {}, image: '', link: '', desc: {}, tech: [], members: [] })
};

/* ---------- Form builder ---------- */
function form(obj, fields, onChange) {
  const f = h('<div class="form"></div>');
  fields.forEach(d => f.append(field(obj, d, onChange)));
  return f;
}
/* ورودی متنی؛ اگر ترجمه‌پذیر باشد به زبان انتخاب‌شده متصل می‌شود */
function textInput(obj, d, onChange, tag = 'input') {
  const el = document.createElement(tag);
  if (d.tr) {
    if (!obj[d.k] || typeof obj[d.k] !== 'object') obj[d.k] = obj[d.k] ? { [def()]: obj[d.k] } : {};
    const info = locInfo(lang);
    el.lang = lang; el.dir = info.dir;
    el.value = obj[d.k][lang] || '';
    if (lang !== def()) el.placeholder = obj[d.k][def()] || '';
    const mark = () => el.classList.toggle('missing', !el.value && !!obj[d.k][def()] && lang !== def());
    mark();
    el.oninput = () => { obj[d.k][lang] = el.value; mark(); save(); onChange?.(); };
  } else {
    if (d.dir) el.dir = d.dir;
    el.value = obj[d.k] ?? '';
    el.oninput = () => { obj[d.k] = el.value; save(); onChange?.(); };
  }
  return el;
}
function field(obj, d, onChange) {
  const wide = d.wide || ['list', 'skills', 'members', 'tags'].includes(d.t);
  const wrap = h(`<label class="field${wide ? ' wide' : ''}"><span>${esc(d.l)}${d.tr ? ` <i class="lang-tag">${lang.toUpperCase()}</i>` : ''}</span></label>`);
  const set = v => { obj[d.k] = v; save(); onChange?.(); };
  let el;
  switch (d.t) {
    case 'textarea': el = textInput(obj, d, onChange, 'textarea'); break;
    case 'number': el = h('<input type="number" min="0" max="80" dir="ltr">'); el.value = obj[d.k] ?? 0; el.oninput = () => set(Math.max(0, parseInt(el.value) || 0)); break;
    case 'select': el = h(`<select>${Object.entries(d.opts).map(([v, l]) => `<option value="${v}">${l}</option>`).join('')}</select>`); el.value = obj[d.k]; el.onchange = () => set(el.value); break;
    case 'bool': wrap.classList.add('check'); el = h('<input type="checkbox">'); el.checked = !!obj[d.k]; el.onchange = () => set(el.checked); wrap.prepend(el); return wrap;
    case 'tags': el = h('<input dir="ltr">'); el.value = (obj[d.k] || []).join(', '); el.oninput = () => set(el.value.split(/[,،]/).map(s => s.trim()).filter(Boolean)); break;
    case 'image': el = imageField(obj, d); break;
    case 'members': el = h(`<div class="checks">${data.members.map(m => `<label><input type="checkbox" value="${esc(m.id)}" ${(obj[d.k] || []).includes(m.id) ? 'checked' : ''}>${esc(tx(m.name))}</label>`).join('') || '<span>ابتدا عضو اضافه کنید</span>'}</div>`);
      el.onchange = () => set([...el.querySelectorAll('input:checked')].map(i => i.value)); break;
    case 'list': case 'skills': el = subList(obj, d); break;
    default:
      el = textInput(obj, d, onChange);
      if (d.suggest) {
        const id = 'dl-' + d.k + '-' + lang;
        const opts = [...new Set(data.projects.map(p => tx(p[d.k])).filter(Boolean))];
        el.setAttribute('list', id); wrap.append(h(`<datalist id="${id}">${opts.map(o => `<option value="${esc(o)}">`).join('')}</datalist>`));
      }
  }
  wrap.append(el); return wrap;
}

/* ---------- Images: uploaded to the server and converted to WebP there ---------- */
const imgPreview = src => !src ? '' : /^(https?:)?\/\//.test(src) ? src : `${ADMIN.site}/storage/cache/thumb/160/${src.replace(/^\/?storage\//, '').replace(/\.\w+$/, '.webp')}`;
function imageField(obj, d) {
  const el = h(`<div class="img-f">
    <div class="prev${d.preset === 'cover' ? ' wide' : ''}"></div>
    <input placeholder="آپلود کنید یا آدرس تصویر خارجی (https://…) وارد کنید" dir="ltr">
    <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
    <button type="button" class="btn sm">آپلود</button><button type="button" class="btn danger sm">حذف</button>
    <span class="img-note">تصویر به‌صورت خودکار به WebP تبدیل و برای هر بخش سایت در اندازه مناسب ساخته می‌شود.</span></div>`);
  const [prev, url, file, up, del] = el.children;
  const show = () => { prev.style.backgroundImage = obj[d.k] ? `url("${imgPreview(obj[d.k])}")` : ''; url.value = obj[d.k] || ''; };
  url.onchange = () => { obj[d.k] = url.value.trim(); save(); show(); };
  up.onclick = () => file.click();
  del.onclick = () => { obj[d.k] = ''; save(); show(); };
  file.onchange = async () => {
    const f = file.files[0]; if (!f) return;
    up.disabled = true; up.textContent = 'در حال آپلود…';
    try {
      const fd = new FormData(); fd.append('file', f);
      obj[d.k] = (await Api.req('POST', '/admin/uploads', fd)).path;
      save(); show();
    } catch (err) { alert('آپلود ناموفق: ' + err.message); }
    up.disabled = false; up.textContent = 'آپلود'; file.value = '';
  };
  show(); return el;
}

function subList(obj, d) {
  obj[d.k] ||= [];
  const box = h('<div class="sub"></div>');
  const draw = () => {
    box.innerHTML = '';
    obj[d.k].forEach((it, i) => {
      let row;
      if (d.t === 'skills') {
        row = h(`<div class="sub-row skill"><input placeholder="نام مهارت" dir="ltr"><div class="rng"><input type="range" min="0" max="100"><output></output></div><div class="acts"><button type="button" class="del" title="حذف">✕</button></div></div>`);
        const [n, rng] = row.querySelectorAll('input'), out = row.querySelector('output');
        n.value = it.name || ''; rng.value = it.level ?? 70; out.textContent = rng.value + '%';
        n.oninput = () => { it.name = n.value; save(); };
        rng.oninput = () => { it.level = +rng.value; out.textContent = rng.value + '%'; save(); };
      } else {
        row = h(`<div class="sub-row"><div class="acts"><button type="button" class="del" title="حذف">✕</button></div></div>`);
        d.sub.forEach(s => { const inp = textInput(it, s); inp.placeholder ||= s.l; row.insertBefore(inp, row.lastElementChild); });
      }
      row.querySelector('.del').onclick = () => { obj[d.k].splice(i, 1); save(); draw(); };
      box.append(row);
    });
    const add = h('<button type="button" class="btn sm">+ افزودن</button>');
    add.onclick = () => { obj[d.k].push(d.t === 'skills' ? { name: '', level: 70 } : Object.fromEntries(d.sub.map(s => [s.k, s.tr ? {} : '']))); save(); draw(); };
    box.append(add);
  };
  draw(); return box;
}

/* تعداد فیلدهای ترجمه‌نشده یک آیتم در زبان فعلی */
const missingCount = (obj, fields) => lang === def() ? 0 : fields.filter(d => d.tr && obj[d.k]?.[def()] && !obj[d.k]?.[lang]).length;

/* ---------- Tabs ---------- */
$('#tabs').innerHTML = Object.entries(TABS).map(([k, l]) => `<button data-k="${k}">${l}</button>`).join('');
$('#tabs').onclick = e => { const b = e.target.closest('button'); if (b) showTab(b.dataset.k); };

function showTab(k) {
  current = k;
  $('#tabs').querySelectorAll('button').forEach(b => b.classList.toggle('on', b.dataset.k === k));
  const p = $('#panel'); p.innerHTML = '';
  if (k === 'team') {
    p.append(h(`<div class="head-row"><h2>${TABS[k]}</h2></div>`), langHint());
    const c = h('<div class="card"></div>'); c.append(form(data.team, S.team)); p.append(c);
  } else if (k === 'tools') tools(p);
  else if (k === 'messages') messages(p);
  else collection(p, k);
}
const langHint = () => h(`<p class="hint">در حال ویرایش محتوای زبان <b>${esc(locInfo(lang).native)}</b>. برای ویرایش زبان‌های دیگر از دکمه‌های بالای صفحه استفاده کنید${lang !== def() ? '؛ فیلدهای خالی با متن زبان پیش‌فرض نمایش داده می‌شوند' : ''}.</p>`);

function collection(p, k) {
  const list = data[k];
  const head = h(`<div class="head-row"><h2>${TABS[k]} <span class="muted">(${list.length})</span></h2><button class="btn primary sm">+ افزودن</button></div>`);
  head.querySelector('button').onclick = () => { list.unshift(NEW[k]()); save(); showTab(k); $('#panel details')?.setAttribute('open', ''); };
  p.append(head, langHint());
  list.forEach((it, i) => {
    const d = h(`<details class="card item"><summary><span class="sum-thumb"></span><span class="ttl"></span><span class="miss" hidden></span>
      <span class="acts"><button title="بالا">↑</button><button title="پایین">↓</button><button class="del" title="حذف">🗑</button></span></summary></details>`);
    const thumb = d.querySelector('.sum-thumb'), ttl = d.querySelector('.ttl'), miss = d.querySelector('.miss');
    const refresh = () => {
      ttl.textContent = tx(it.name || it.title) || '(بدون عنوان)';
      const img = it.avatar || it.image;
      thumb.style.backgroundImage = img ? `url("${imgPreview(img)}")` : '';
      thumb.textContent = img ? '' : (it.icon || tx(it.name || it.title).slice(0, 1));
      const n = missingCount(it, S[k]); miss.hidden = !n; miss.textContent = `${n} ترجمه ناقص`;
    };
    refresh();
    const [upB, dnB, delB] = d.querySelectorAll('.acts button');
    const move = (e, dir) => { e.preventDefault(); const j = i + dir; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; save(); showTab(k); };
    upB.onclick = e => move(e, -1); dnB.onclick = e => move(e, 1);
    delB.onclick = e => {
      e.preventDefault(); if (!confirm('این مورد حذف شود؟')) return;
      list.splice(i, 1);
      if (k === 'members') data.projects.forEach(pr => pr.members = (pr.members || []).filter(id => id !== it.id));
      save(); showTab(k);
    };
    d.append(form(it, S[k], refresh)); p.append(d);
  });
}

function tools(p) {
  p.append(h(`<div class="head-row"><h2>${TABS.tools}</h2></div>`));
  const w = h(`<div class="tools">
    <div class="card"><h3>پشتیبان‌گیری</h3><p class="muted">کل محتوای سایت (همه زبان‌ها) را به‌صورت فایل JSON دانلود کنید.</p>
      <div class="row"><button class="btn primary sm" id="exp">دانلود فایل پشتیبان</button></div></div>
    <div class="card"><h3>بازیابی از فایل پشتیبان</h3><p class="muted">محتوای فعلی سایت با محتوای فایل جایگزین می‌شود.</p>
      <div class="row"><input type="file" accept=".json,application/json" id="imp" style="max-width:340px"></div></div>
    <div class="card"><h3>تغییر رمز عبور</h3><p class="muted">حداقل ۸ کاراکتر.</p>
      <div class="row"><input type="password" id="op" placeholder="رمز فعلی" dir="ltr" style="max-width:200px"><input type="password" id="np" placeholder="رمز جدید" dir="ltr" style="max-width:200px"><button class="btn sm" id="cp">ذخیره رمز</button></div></div></div>`);
  p.append(w);
  w.querySelector('#exp').onclick = () => {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }));
    a.download = `site-backup-${new Date().toISOString().slice(0, 10)}.json`; a.click(); URL.revokeObjectURL(a.href);
  };
  w.querySelector('#imp').onchange = async e => {
    try {
      const d = JSON.parse(await e.target.files[0].text()); delete d.meta;
      if (!d.team || !Array.isArray(d.members)) throw 0;
      if (!confirm('محتوای فعلی سایت جایگزین شود؟')) return;
      const res = await Api.req('PUT', '/admin/site', d); delete res.meta; data = res;
      alert('بازیابی شد ✓');
    } catch (err) { alert(err?.message ? 'بازیابی ناموفق: ' + err.message : 'فایل نامعتبر است'); }
    e.target.value = '';
  };
  w.querySelector('#cp').onclick = async () => {
    try { await Api.req('PUT', '/auth/password', { current_password: w.querySelector('#op').value, password: w.querySelector('#np').value }); alert('رمز تغییر کرد ✓'); }
    catch (err) { alert(err.message); }
  };
}

/* ---------- Contact messages ---------- */
async function refreshUnread() {
  try {
    const { unread } = await Api.req('GET', '/admin/messages');
    const b = $('#tabs [data-k=messages]'); if (b) b.innerHTML = TABS.messages + (unread ? ` <span class="badge">${unread}</span>` : '');
  } catch (e) {}
}
async function messages(p) {
  p.append(h(`<div class="head-row"><h2>${TABS.messages}</h2></div>`));
  const box = h('<div class="msgs"><p class="muted">در حال بارگذاری…</p></div>'); p.append(box);
  let list;
  try { list = (await Api.req('GET', '/admin/messages')).data; } catch (err) { box.innerHTML = `<p class="muted">${esc(err.message)}</p>`; return; }
  if (current !== 'messages') return;
  box.innerHTML = list.length ? '' : '<div class="card"><p class="muted">هنوز پیامی نرسیده است.</p></div>';
  list.forEach(m => {
    const c = h(`<article class="card msg${m.read_at ? '' : ' unread'}">
      <div class="head-row" style="margin:0"><div><b>${esc(m.name)}</b> <a class="muted" dir="ltr" href="mailto:${esc(m.email)}">${esc(m.email)}</a>
      <div class="muted">${new Date(m.created_at).toLocaleString('fa-IR')}${m.subject ? ' — ' + esc(m.subject) : ''}</div></div>
      <div class="acts"><button title="${m.read_at ? 'علامت نخوانده' : 'علامت خوانده‌شده'}">${m.read_at ? '○' : '✓'}</button><button class="del" title="حذف">🗑</button></div></div>
      <p class="msg-body"></p></article>`);
    c.querySelector('.msg-body').textContent = m.body;
    const [rd, del] = c.querySelectorAll('.acts button');
    rd.onclick = async () => { await Api.req('PATCH', `/admin/messages/${m.id}/read`); refreshUnread(); showTab('messages'); };
    del.onclick = async () => { if (confirm('پیام حذف شود؟')) { await Api.req('DELETE', `/admin/messages/${m.id}`); refreshUnread(); showTab('messages'); } };
    box.append(c);
  });
}
})();
