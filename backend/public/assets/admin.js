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
let data, meta, lang, current, ai = { enabled: false, providers: [], chain: [], chat: {} };
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
  await loadAi().catch(() => {});
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
const TABS = { team: 'اطلاعات تیم', services: 'تخصص‌ها و خدمات', members: 'اعضای تیم', projects: 'نمونه‌کارها', messages: 'پیام‌ها', chats: 'گفتگوهای چت', ai: 'هوش مصنوعی', tools: 'پشتیبان‌گیری و تنظیمات' };
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
  wrap.append(el);
  if (d.tr && ai.enabled && (!d.t || d.t === 'textarea')) wrap.querySelector('span').append(aiTools(obj, d, el, onChange));
  return wrap;
}

/* ---------- AI writing tools ---------- */
const GENERATE = { bio: 'bio', desc: 'description', about: 'about', tagline: 'tagline', role: 'role' };
async function assist(body) { return Api.req('POST', '/admin/ai/assist', body); }
function aiTools(obj, d, el, onChange) {
  const box = h('<span class="ai-tools"></span>');
  const btn = (label, title, fn) => {
    const b = h(`<button type="button" class="ai-btn" title="${esc(title)}">${label}</button>`);
    b.onclick = async e => {
      e.preventDefault(); e.stopPropagation();
      b.disabled = true; b.classList.add('busy'); el.classList.add('ai-busy');
      try {
        const text = await fn();
        if (text) { obj[d.k][lang] = text; el.value = text; el.dispatchEvent(new Event('input')); }
      } catch (err) { toast('هوش مصنوعی: ' + err.message, 'err'); }
      b.disabled = false; b.classList.remove('busy'); el.classList.remove('ai-busy');
    };
    box.append(b);
  };
  const src = () => obj[d.k]?.[def()];
  if (lang !== def()) btn('⇄ ترجمه', `ترجمه از ${locInfo(def()).native}`, async () => {
    if (!src()) throw new Error('متن زبان پیش‌فرض خالی است');
    return (await assist({ task: 'translate', text: src(), from: def(), to: lang })).text;
  });
  btn('✨ بهبود', 'بازنویسی حرفه‌ای همین متن', async () => {
    if (!el.value.trim()) throw new Error('ابتدا متنی بنویسید');
    return (await assist({ task: 'improve', text: el.value, lang, field: d.l })).text;
  });
  if (GENERATE[d.k]) btn('🪄 تولید', 'نوشتن خودکار بر اساس سایر اطلاعات همین بخش', async () =>
    (await assist({ task: 'generate', field: GENERATE[d.k], lang, context: plainContext(obj) })).text);
  return box;
}
/* Item data in the default language, for prompts */
const plainContext = obj => Object.fromEntries(Object.entries(obj).filter(([k]) => !['avatar', 'image', 'id', 'members'].includes(k))
  .map(([k, v]) => [k, v && typeof v === 'object' && !Array.isArray(v) ? tx(v, def()) : v]));
function toast(text, cls = '') {
  const t = h(`<div class="toast ${cls}"></div>`); t.textContent = text; document.body.append(t);
  setTimeout(() => t.classList.add('out'), 3500); setTimeout(() => t.remove(), 4000);
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
    const row = h('<div class="row-btns"></div>'); row.append(add);
    if (d.t === 'skills' && ai.enabled) {
      const sug = h('<button type="button" class="btn sm ai">✨ پیشنهاد مهارت با AI</button>');
      sug.onclick = async () => {
        sug.disabled = true; sug.textContent = 'در حال فکر کردن…';
        try {
          const { skills } = await assist({ task: 'skills', context: plainContext(obj) });
          const have = new Set(obj[d.k].map(s => (s.name || '').toLowerCase()));
          skills.filter(s => !have.has(s.name.toLowerCase())).forEach(s => obj[d.k].push(s));
          save(); draw(); toast(`${skills.length} مهارت پیشنهاد شد؛ موارد نامرتبط را حذف کنید.`);
        } catch (err) { toast('هوش مصنوعی: ' + err.message, 'err'); draw(); }
      };
      row.append(sug);
    }
    box.append(row);
  };
  draw(); return box;
}

/* Translate every missing field of the whole site into the current language */
function translateAllBar() {
  if (!ai.enabled || lang === def()) return document.createTextNode('');
  const bar = h(`<div class="ai-bar"><span>✨ ترجمه خودکار همه متن‌های ترجمه‌نشده به <b>${esc(locInfo(lang).native)}</b> با هوش مصنوعی</span><button class="btn sm ai">ترجمه همه</button></div>`);
  const b = bar.querySelector('button');
  b.onclick = async () => {
    if (!confirm('همه متن‌های خالی این زبان با ترجمه ماشینی پر شوند؟ (بعداً قابل ویرایش هستند)')) return;
    b.disabled = true; b.textContent = 'در حال ترجمه…';
    try {
      await sync();
      const r = await Api.req('POST', '/admin/ai/translate-missing', { locale: lang });
      delete r.site.meta; data = r.site;
      toast(r.translated ? `${r.translated} متن ترجمه و ذخیره شد ✓` : 'متن ترجمه‌نشده‌ای وجود ندارد', 'ok');
      showTab(current);
    } catch (err) { toast('هوش مصنوعی: ' + err.message, 'err'); b.disabled = false; b.textContent = 'ترجمه همه'; }
  };
  return bar;
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
    p.append(h(`<div class="head-row"><h2>${TABS[k]}</h2></div>`), langHint(), translateAllBar());
    const c = h('<div class="card"></div>'); c.append(form(data.team, S.team)); p.append(c);
  } else if (k === 'tools') tools(p);
  else if (k === 'messages') messages(p);
  else if (k === 'chats') chats(p);
  else if (k === 'ai') aiTab(p);
  else collection(p, k);
}
const langHint = () => h(`<p class="hint">در حال ویرایش محتوای زبان <b>${esc(locInfo(lang).native)}</b>. برای ویرایش زبان‌های دیگر از دکمه‌های بالای صفحه استفاده کنید${lang !== def() ? '؛ فیلدهای خالی با متن زبان پیش‌فرض نمایش داده می‌شوند' : ''}.</p>`);

function collection(p, k) {
  const list = data[k];
  const head = h(`<div class="head-row"><h2>${TABS[k]} <span class="muted">(${list.length})</span></h2><button class="btn primary sm">+ افزودن</button></div>`);
  head.querySelector('button').onclick = () => { list.unshift(NEW[k]()); save(); showTab(k); $('#panel details')?.setAttribute('open', ''); };
  p.append(head, langHint(), translateAllBar());
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
  const set = (k, n) => { const b = $(`#tabs [data-k=${k}]`); if (b) b.innerHTML = TABS[k] + (n ? ` <span class="badge">${n}</span>` : ''); };
  try { set('messages', (await Api.req('GET', '/admin/messages')).unread); } catch (e) {}
  try { set('chats', (await Api.req('GET', '/admin/chats')).unread); } catch (e) {}
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
      <p class="msg-body"></p>${ai.enabled ? '<div class="row-btns"><button class="btn sm ai draft">✨ پیش‌نویس پاسخ با AI</button></div>' : ''}</article>`);
    c.querySelector('.msg-body').textContent = m.body;
    c.querySelector('.draft')?.addEventListener('click', async e => {
      const b = e.currentTarget; b.disabled = true; b.textContent = 'در حال نوشتن…';
      try {
        const { text } = await assist({ task: 'reply', message_id: m.id });
        const box = h(`<div class="draft-box"><textarea rows="8" dir="auto"></textarea><div class="row-btns"><a class="btn sm primary" target="_blank">باز کردن در ایمیل</a><button class="btn sm copy">کپی</button></div></div>`);
        const ta = box.querySelector('textarea'); ta.value = text;
        const mail = box.querySelector('a');
        const upd = () => mail.href = `mailto:${encodeURIComponent(m.email)}?subject=${encodeURIComponent('Re: ' + (m.subject || ''))}&body=${encodeURIComponent(ta.value)}`;
        ta.oninput = upd; upd();
        box.querySelector('.copy').onclick = () => navigator.clipboard?.writeText(ta.value).then(() => toast('کپی شد ✓', 'ok'));
        b.parentElement.replaceWith(box);
      } catch (err) { toast('هوش مصنوعی: ' + err.message, 'err'); b.disabled = false; b.textContent = '✨ پیش‌نویس پاسخ با AI'; }
    });
    const [rd, del] = c.querySelectorAll('.acts button');
    rd.onclick = async () => { await Api.req('PATCH', `/admin/messages/${m.id}/read`); refreshUnread(); showTab('messages'); };
    del.onclick = async () => { if (confirm('پیام حذف شود؟')) { await Api.req('DELETE', `/admin/messages/${m.id}`); refreshUnread(); showTab('messages'); } };
    box.append(c);
  });
}

/* ---------- Chat transcripts ---------- */
async function chats(p) {
  p.append(h(`<div class="head-row"><h2>${TABS.chats}</h2></div>`));
  if (!ai.chat.enabled) p.append(h('<p class="hint">دستیار چت سایت خاموش است؛ از تب «هوش مصنوعی» روشنش کنید.</p>'));
  const box = h('<div class="msgs"><p class="muted">در حال بارگذاری…</p></div>'); p.append(box);
  let list;
  try { list = (await Api.req('GET', '/admin/chats')).data; } catch (err) { box.innerHTML = `<p class="muted">${esc(err.message)}</p>`; return; }
  if (current !== 'chats') return;
  box.innerHTML = list.length ? '' : '<div class="card"><p class="muted">هنوز گفتگویی انجام نشده است.</p></div>';
  list.forEach(s => {
    const c = h(`<details class="card item msg${s.read_at ? '' : ' unread'}"><summary>
      <span class="sum-thumb">💬</span><span class="ttl"></span>
      ${s.lead ? `<span class="miss lead">مشتری: ${esc(s.lead.name)}</span>` : ''}
      <span class="muted" style="font-weight:400;font-size:.82rem">${s.messages_count} پیام · ${esc(s.locale.toUpperCase())} · ${new Date(s.updated_at).toLocaleString('fa-IR')}</span>
      <span class="acts"><button class="del" title="حذف">🗑</button></span></summary><div class="transcript"><p class="muted">…</p></div></details>`);
    c.querySelector('.ttl').textContent = (s.preview || '').slice(0, 70);
    c.querySelector('.del').onclick = async e => { e.preventDefault(); if (confirm('گفتگو حذف شود؟')) { await Api.req('DELETE', `/admin/chats/${s.id}`); showTab('chats'); refreshUnread(); } };
    c.addEventListener('toggle', async () => {
      if (!c.open || c.dataset.loaded) return;
      c.dataset.loaded = 1; c.classList.remove('unread');
      const { messages } = await Api.req('GET', `/admin/chats/${s.id}`);
      const t = c.querySelector('.transcript'); t.innerHTML = '';
      messages.forEach(m => { const b = h(`<div class="tline ${m.role}"><b>${m.role === 'user' ? 'بازدیدکننده' : 'دستیار'}${m.provider ? ` <i>${esc(m.provider)}</i>` : ''}</b><p dir="auto"></p></div>`); b.querySelector('p').textContent = m.content; t.append(b); });
      refreshUnread();
    });
    box.append(c);
  });
}

/* ---------- AI settings ---------- */
async function loadAi() { ai = await Api.req('GET', '/admin/ai'); }
function aiTab(p) {
  const providers = ai.providers, chainSet = () => new Set(ai.chain);
  p.append(h(`<div class="head-row"><h2>${TABS.ai}</h2><span class="saved ${ai.enabled ? 'ok' : ''}">${ai.enabled ? '● فعال' : '○ هنوز ارائه‌دهنده‌ای فعال نیست'}</span></div>`));
  p.append(h(`<p class="hint">یک یا چند ارائه‌دهنده را فعال کنید. ترتیب «زنجیره» مهم است: اگر اولی خطا بدهد (تمام شدن سهمیه رایگان، قطعی، …) خودکار سراغ بعدی می‌رود.
    ارائه‌دهنده‌های <b>رایگان</b> با برچسب سبز مشخص شده‌اند؛ برای فارسی Gemini و مدل‌های بزرگ (مثل Llama 70B) نتیجه بهتری می‌دهند. سرویس‌های ایرانی سازگار با OpenAI را هم می‌توانید در «سفارشی» وارد کنید.</p>`));

  /* Chain */
  const chainCard = h('<div class="card ai-chain"><h3>زنجیره ارائه‌دهنده‌ها</h3><div class="chain-list"></div></div>');
  const drawChain = () => {
    const list = chainCard.querySelector('.chain-list'); list.innerHTML = '';
    if (!ai.chain.length) list.innerHTML = '<p class="muted">هنوز ارائه‌دهنده‌ای اضافه نشده؛ از کارت‌های زیر «افزودن به زنجیره» را بزنید.</p>';
    ai.chain.forEach((code, i) => {
      const pr = providers.find(x => x.code === code);
      const r = h(`<div class="chain-row"><b>${i + 1}.</b> <span>${esc(pr?.label || code)}</span> ${pr?.ready ? '<span class="ok-tag">آماده</span>' : '<span class="warn-tag">کلید/مدل لازم است</span>'}
        <span class="acts"><button title="بالا">↑</button><button title="پایین">↓</button><button class="del" title="حذف">✕</button></span></div>`);
      const [u, dn, x] = r.querySelectorAll('button');
      const mv = dir => { const j = i + dir; if (j < 0 || j >= ai.chain.length) return; [ai.chain[i], ai.chain[j]] = [ai.chain[j], ai.chain[i]]; persist({ chain: ai.chain }); };
      u.onclick = () => mv(-1); dn.onclick = () => mv(1);
      x.onclick = () => persist({ chain: ai.chain.filter(c => c !== code) });
      list.append(r);
    });
  };
  drawChain(); p.append(chainCard);

  /* Chat assistant */
  const chat = ai.chat;
  ['name', 'greeting'].forEach(k => { if (!chat[k] || Array.isArray(chat[k])) chat[k] = {}; });
  const cc = h(`<div class="card"><h3>دستیار چت سایت</h3><p class="muted">به سؤالات بازدیدکنندگان فقط بر اساس اطلاعات همین سایت پاسخ می‌دهد، به زبان خودشان. اگر کسی پروژه بخواهد، نام و ایمیل و توضیح را می‌گیرد و در «پیام‌ها» ثبت می‌کند.</p><div class="form"></div><div class="row-btns"><button class="btn primary sm">ذخیره تنظیمات چت</button></div></div>`);
  const f = cc.querySelector('.form');
  f.append(field(chat, { k: 'enabled', l: 'نمایش دستیار چت در سایت', t: 'bool' }), field(chat, { k: 'leads', l: 'ثبت خودکار مشتری‌های بالقوه', t: 'bool' }),
    field(chat, { k: 'name', l: 'نام دستیار', tr: 1 }), field(chat, { k: 'greeting', l: 'پیام خوش‌آمد', tr: 1, wide: 1 }),
    field(chat, { k: 'instructions', l: 'دستورالعمل اضافه (مثلاً لحن، ساعات کاری، سیاست قیمت‌گذاری)', t: 'textarea', wide: 1 }));
  cc.querySelector('.btn.primary').onclick = () => persist({ chat: { enabled: !!chat.enabled, leads: !!chat.leads, name: chat.name, greeting: chat.greeting, instructions: chat.instructions || '' } }, 'تنظیمات چت ذخیره شد ✓');
  p.append(cc);

  /* Provider cards */
  const grid = h('<div class="prov-grid"></div>');
  providers.forEach(pr => {
    const inChain = chainSet().has(pr.code);
    const c = h(`<div class="card prov${inChain ? ' on' : ''}">
      <div class="prov-head"><b>${esc(pr.label)}</b>${pr.free === true ? '<span class="free">رایگان</span>' : pr.free === false ? '<span class="paid">پولی</span>' : ''}
        ${pr.ready ? '<span class="ok-tag">آماده</span>' : ''}</div>
      ${pr.custom || pr.code === 'ollama' ? `<label class="field"><span>آدرس API (Base URL)</span><input class="bu" dir="ltr" placeholder="${esc(pr.default_base_url || 'https://…/v1')}"></label>` : ''}
      ${pr.key_unreadable ? '<p class="prov-out err">کلید ذخیره‌شده قابل خواندن نیست (APP_KEY تغییر کرده)؛ کلید را دوباره وارد و ذخیره کنید.</p>' : ''}
      ${pr.keyless ? '' : `<label class="field"><span>کلید API ${pr.has_key ? `<i class="muted">(ذخیره‌شده ${esc(pr.key_hint || '')}${pr.key_from_env ? ' از .env' : ''})</i>` : ''}</span><input class="key" type="password" dir="ltr" autocomplete="off" placeholder="${pr.has_key ? 'برای تغییر، کلید جدید وارد کنید' : 'کلید API'}"></label>`}
      <label class="field"><span>مدل</span><input class="model" dir="ltr" list="ml-${pr.code}" placeholder="${esc(pr.default_model || 'model-name')}"><datalist id="ml-${pr.code}"></datalist></label>
      <div class="row-btns">
        <button class="btn sm primary sv">ذخیره</button>
        <button class="btn sm tst">آزمایش اتصال</button>
        <button class="btn sm lm">دریافت لیست مدل‌ها</button>
        <button class="btn sm ch">${inChain ? 'حذف از زنجیره' : 'افزودن به زنجیره'}</button>
        ${pr.has_key && !pr.key_from_env ? '<button class="btn sm danger rk">حذف کلید</button>' : ''}
        ${pr.key_url ? `<a class="btn sm ghost" href="${esc(pr.key_url)}" target="_blank" rel="noopener">دریافت کلید ↗</a>` : ''}
      </div><p class="prov-out muted"></p></div>`);
    const $c = s => c.querySelector(s), out = $c('.prov-out');
    $c('.model').value = pr.model || '';
    if ($c('.bu')) $c('.bu').value = pr.base_url || '';
    const values = () => ({ model: $c('.model').value.trim(), ...($c('.bu') ? { base_url: $c('.bu').value.trim() } : {}), ...($c('.key')?.value.trim() ? { api_key: $c('.key').value.trim() } : {}) });
    const hint = m => /\b401\b|auth|api key/i.test(m) ? m + ' — کلید API را بررسی کنید (بدون فاصله اضافه) و دوباره «ذخیره» بزنید.' : m;
    const run = async (b, fn) => { b.disabled = true; out.className = 'prov-out muted'; out.textContent = '…'; try { await fn(); } catch (err) { out.className = 'prov-out err'; out.textContent = hint(err.message); } b.disabled = false; };
    $c('.sv').onclick = e => run(e.target, () => persist({ providers: { [pr.code]: values() } }, `${pr.label} ذخیره شد ✓`));
    $c('.tst').onclick = e => run(e.target, async () => {
      await persist({ providers: { [pr.code]: values() } }, null, false);
      const r = await Api.req('POST', '/admin/ai/test', { provider: pr.code });
      out.className = 'prov-out ok'; out.textContent = `✓ ${r.ms}ms — ${r.reply}`;
    });
    $c('.lm').onclick = e => run(e.target, async () => {
      await persist({ providers: { [pr.code]: values() } }, null, false);
      const { models } = await Api.req('GET', `/admin/ai/models/${pr.code}`);
      $c('datalist').innerHTML = models.map(m => `<option value="${esc(m)}">`).join('');
      out.textContent = `${models.length} مدل پیدا شد؛ در فیلد «مدل» تایپ کنید تا لیست را ببینید.`;
    });
    $c('.ch').onclick = () => persist({ chain: inChain ? ai.chain.filter(x => x !== pr.code) : [...ai.chain, pr.code], providers: { [pr.code]: values() } });
    $c('.rk')?.addEventListener('click', () => confirm('کلید حذف شود؟') && persist({ providers: { [pr.code]: { remove_key: true } } }));
    grid.append(c);
  });
  p.append(h('<h3 class="sub-title">ارائه‌دهنده‌ها</h3>'), grid);
}
async function persist(body, msg = 'ذخیره شد ✓', redraw = true) {
  ai = await Api.req('PUT', '/admin/ai', body);
  if (msg) toast(msg, 'ok');
  if (redraw && current === 'ai') { const y = scrollY; showTab('ai'); scrollTo(0, y); }
}
})();
