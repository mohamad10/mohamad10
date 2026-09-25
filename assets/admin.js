/* پنل مدیریت: ویرایش همه محتوای سایت بدون نیاز به کدنویسی */
let data = Store.load();
const $ = s => document.querySelector(s);
const h = (html) => { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; };

/* ---------- ورود ---------- */
const PASS_KEY = 'team-site-pass';
const sha = async s => [...new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(s)))].map(b => b.toString(16).padStart(2, '0')).join('');
const stored = localStorage.getItem(PASS_KEY);
const lockMsg = (t, err) => { $('#lockMsg').textContent = t; $('#lockMsg').style.color = err ? '#ef4444' : ''; };

if (Store.api) {
  $('#email').hidden = false; $('#email').required = true;
  lockMsg('با حساب مدیر وارد شوید');
  if (Api.token) Api.req('GET', '/auth/me').then(enterApi).catch(() => { Api.token = null; });
} else {
  lockMsg(stored ? 'رمز عبور خود را وارد کنید' : 'اولین ورود: یک رمز عبور برای پنل تعیین کنید');
  if (sessionStorage.getItem('admin-ok')) unlock();
}

$('#lockForm').onsubmit = async e => {
  e.preventDefault();
  const btn = e.target.querySelector('button'); btn.disabled = true;
  try {
    if (Store.api) {
      const r = await Api.req('POST', '/auth/login', { email: $('#email').value, password: $('#pass').value });
      Api.token = r.token; await enterApi(r.user);
    } else {
      const hash = await sha($('#pass').value);
      if (!stored) localStorage.setItem(PASS_KEY, hash);
      else if (hash !== stored) throw new Error('رمز عبور اشتباه است');
      sessionStorage.setItem('admin-ok', '1'); unlock();
    }
  } catch (err) { lockMsg(err.message === 'Failed to fetch' ? 'اتصال به سرور برقرار نشد' : err.message, true); }
  btn.disabled = false;
};

async function enterApi(user) {
  data = await Api.req('GET', '/site');
  $('#mode').textContent = '● متصل به سرور' + (user?.email ? ' — ' + user.email : '');
  unlock();
  refreshUnread();
}
function unlock() {
  $('#lock')?.remove(); $('#app').hidden = false;
  if (!Store.api) $('#mode').textContent = '● حالت محلی (بدون سرور)';
  showTab('team');
}
$('#logout').onclick = async () => {
  if (Store.api) { await Api.req('POST', '/auth/logout').catch(() => {}); Api.token = null; }
  sessionStorage.removeItem('admin-ok'); location.reload();
};

/* ---------- ذخیره خودکار ---------- */
let timer, syncTimer, syncing = false, pending = false;
const status = (t, cls = '') => { const el = $('#saved'); el.textContent = t; el.className = 'saved muted show ' + cls; clearTimeout(timer); if (cls === 'ok') timer = setTimeout(() => el.classList.remove('show'), 1500); };
function save() {
  if (!Store.api) { Store.save(data); status('ذخیره شد ✓', 'ok'); return; }
  status('در حال ذخیره…');
  clearTimeout(syncTimer); syncTimer = setTimeout(sync, 700);
}
async function sync() {
  if (syncing) { pending = true; return; }
  syncing = true;
  try { await Api.req('PUT', '/admin/site', data); status('روی سرور ذخیره شد ✓', 'ok'); }
  catch (err) { status('خطا: ' + err.message, 'err'); if (err.status === 401) location.reload(); }
  syncing = false;
  if (pending) { pending = false; sync(); }
}
window.addEventListener('beforeunload', e => { if (syncing || pending || $('#saved').textContent === 'در حال ذخیره…') e.preventDefault(); });

/* ---------- تعریف فیلدها ---------- */
const S = {
  team: [
    { k: 'name', l: 'نام تیم' }, { k: 'tagline', l: 'شعار', wide: 1 }, { k: 'about', l: 'درباره تیم', t: 'textarea', wide: 1 },
    { k: 'email', l: 'ایمیل' }, { k: 'phone', l: 'تلفن' }, { k: 'location', l: 'آدرس' },
    { k: 'stats', l: 'آمار صفحه اصلی', t: 'list', sub: [{ k: 'value', l: 'مقدار' }, { k: 'label', l: 'عنوان' }] },
    { k: 'socials', l: 'شبکه‌های اجتماعی', t: 'list', sub: [{ k: 'label', l: 'نام' }, { k: 'url', l: 'لینک' }] }
  ],
  services: [{ k: 'icon', l: 'آیکون (ایموجی)' }, { k: 'title', l: 'عنوان' }, { k: 'desc', l: 'توضیح', t: 'textarea', wide: 1 }],
  members: [
    { k: 'name', l: 'نام و نام خانوادگی' }, { k: 'role', l: 'سمت' },
    { k: 'level', l: 'سطح تخصص', t: 'select', opts: LEVELS }, { k: 'years', l: 'سال‌های تجربه', t: 'number' },
    { k: 'location', l: 'شهر' }, { k: 'education', l: 'تحصیلات' }, { k: 'languages', l: 'زبان‌ها' },
    { k: 'available', l: 'آماده همکاری', t: 'bool' },
    { k: 'avatar', l: 'عکس پروفایل', t: 'image', wide: 1 },
    { k: 'bio', l: 'بیوگرافی', t: 'textarea', wide: 1 },
    { k: 'skills', l: 'مهارت‌ها و میزان تسلط', t: 'skills' },
    { k: 'links', l: 'لینک‌ها', t: 'list', sub: [{ k: 'label', l: 'نام' }, { k: 'url', l: 'لینک' }] }
  ],
  projects: [
    { k: 'title', l: 'عنوان پروژه' }, { k: 'category', l: 'دسته‌بندی' }, { k: 'year', l: 'سال' }, { k: 'link', l: 'لینک' },
    { k: 'desc', l: 'توضیح', t: 'textarea', wide: 1 },
    { k: 'tech', l: 'تکنولوژی‌ها (با کاما جدا کنید)', t: 'tags', wide: 1 },
    { k: 'image', l: 'تصویر کاور', t: 'image', wide: 1 },
    { k: 'members', l: 'اعضای درگیر در پروژه', t: 'members', wide: 1 }
  ]
};
const TABS = { team: 'اطلاعات تیم', services: 'تخصص‌ها و خدمات', members: 'اعضای تیم', projects: 'نمونه‌کارها', messages: 'پیام‌ها', tools: 'پشتیبان‌گیری و تنظیمات' };
const NEW = {
  services: () => ({ icon: '✨', title: 'خدمت جدید', desc: '' }),
  members: () => ({ id: 'm' + Date.now().toString(36), name: 'عضو جدید', role: '', level: 'Mid', years: 1, avatar: '', available: true, bio: '', skills: [], links: [], location: '', education: '', languages: '' }),
  projects: () => ({ title: 'پروژه جدید', category: '', year: '', image: '', link: '', desc: '', tech: [], members: [] })
};

/* ---------- سازنده فرم ---------- */
function form(obj, fields, onTitle) {
  const f = h('<div class="form"></div>');
  fields.forEach(d => f.append(field(obj, d, onTitle)));
  return f;
}
function field(obj, d, onTitle) {
  const wrap = h(`<label class="field${d.wide || ['list', 'skills', 'members', 'tags'].includes(d.t) ? ' wide' : ''}"><span>${esc(d.l)}</span></label>`);
  const set = v => { obj[d.k] = v; save(); if (d.k === 'name' || d.k === 'title') onTitle?.(v); };
  let el;
  switch (d.t) {
    case 'textarea': el = h('<textarea></textarea>'); el.value = obj[d.k] ?? ''; el.oninput = () => set(el.value); break;
    case 'number': el = h('<input type="number" min="0">'); el.value = obj[d.k] ?? 0; el.oninput = () => set(+el.value); break;
    case 'select': el = h(`<select>${Object.entries(d.opts).map(([v, l]) => `<option value="${v}">${l}</option>`).join('')}</select>`); el.value = obj[d.k]; el.onchange = () => set(el.value); break;
    case 'bool': wrap.classList.add('check'); el = h('<input type="checkbox">'); el.checked = !!obj[d.k]; el.onchange = () => set(el.checked); wrap.prepend(el); return wrap;
    case 'tags': el = h('<input>'); el.value = (obj[d.k] || []).join('، '); el.oninput = () => set(el.value.split(/[,،]/).map(s => s.trim()).filter(Boolean)); break;
    case 'image': el = imageField(obj, d.k); break;
    case 'members': el = h(`<div class="checks">${data.members.map(m => `<label><input type="checkbox" value="${esc(m.id)}" ${(obj[d.k] || []).includes(m.id) ? 'checked' : ''}>${esc(m.name)}</label>`).join('') || '<span>ابتدا عضو اضافه کنید</span>'}</div>`);
      el.onchange = () => set([...el.querySelectorAll('input:checked')].map(i => i.value)); break;
    case 'list': case 'skills': el = subList(obj, d); break;
    default: el = h('<input>'); el.value = obj[d.k] ?? ''; el.oninput = () => set(el.value);
  }
  wrap.append(el); return wrap;
}
function imageField(obj, k) {
  const el = h(`<div class="img-f"><div class="prev"></div><input placeholder="آدرس تصویر (URL) یا آپلود"><input type="file" accept="image/*" hidden><button type="button" class="btn ghost sm">آپلود</button><button type="button" class="btn danger sm">حذف</button></div>`);
  const [prev, url, file, up, del] = el.children;
  const show = () => { prev.style.backgroundImage = obj[k] ? `url("${obj[k]}")` : ''; url.value = obj[k]?.startsWith('data:') ? '(تصویر آپلود شده)' : (obj[k] || ''); };
  url.oninput = () => { obj[k] = url.value; save(); prev.style.backgroundImage = `url("${url.value}")`; };
  up.onclick = () => file.click();
  del.onclick = () => { obj[k] = ''; save(); show(); };
  file.onchange = async () => {
    const f = file.files[0]; if (!f) return;
    up.disabled = true; up.textContent = '…';
    try {
      if (Store.api) { const fd = new FormData(); fd.append('file', await shrinkBlob(f), 'image.jpg'); obj[k] = (await Api.req('POST', '/admin/uploads', fd)).url; }
      else obj[k] = await shrink(f);
      save(); show();
    } catch (err) { alert('آپلود ناموفق: ' + err.message); }
    up.disabled = false; up.textContent = 'آپلود'; file.value = '';
  };
  show(); return el;
}
/* فشرده‌سازی تصویر آپلودی تا حجم داده‌ها کم بماند */
const shrinkBlob = async f => (await fetch(await shrink(f))).blob();
function shrink(f, max = 800) {
  return new Promise(res => {
    const img = new Image(); img.onload = () => {
      const r = Math.min(1, max / Math.max(img.width, img.height)), c = document.createElement('canvas');
      c.width = img.width * r; c.height = img.height * r; c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      res(c.toDataURL('image/jpeg', .82)); URL.revokeObjectURL(img.src);
    }; img.src = URL.createObjectURL(f);
  });
}
function subList(obj, d) {
  obj[d.k] ||= [];
  const box = h('<div class="sub"></div>');
  const draw = () => {
    box.innerHTML = '';
    obj[d.k].forEach((it, i) => {
      let row;
      if (d.t === 'skills') {
        row = h(`<div class="sub-row skill"><input placeholder="نام مهارت"><div class="rng"><input type="range" min="0" max="100"><output></output></div><div class="acts"><button type="button" class="del">✕</button></div></div>`);
        const [n, rng] = row.querySelectorAll('input'), out = row.querySelector('output');
        n.value = it.name; rng.value = it.level; out.textContent = it.level + '٪';
        n.oninput = () => { it.name = n.value; save(); };
        rng.oninput = () => { it.level = +rng.value; out.textContent = rng.value + '٪'; save(); };
      } else {
        row = h(`<div class="sub-row">${d.sub.map(s => `<input placeholder="${esc(s.l)}">`).join('')}<div class="acts"><button type="button" class="del">✕</button></div></div>`);
        row.querySelectorAll('input').forEach((inp, j) => { const k = d.sub[j].k; inp.value = it[k] ?? ''; inp.oninput = () => { it[k] = inp.value; save(); }; });
      }
      row.querySelector('.del').onclick = () => { obj[d.k].splice(i, 1); save(); draw(); };
      box.append(row);
    });
    const add = h('<button type="button" class="btn ghost sm">+ افزودن</button>');
    add.onclick = () => { obj[d.k].push(d.t === 'skills' ? { name: '', level: 70 } : Object.fromEntries(d.sub.map(s => [s.k, '']))); save(); draw(); };
    box.append(add);
  };
  draw(); return box;
}

/* ---------- تب‌ها ---------- */
let current;
$('#tabs').innerHTML = Object.entries(TABS).filter(([k]) => k !== 'messages' || Store.api).map(([k, l]) => `<button data-k="${k}">${l}</button>`).join('');
$('#tabs').onclick = e => { const b = e.target.closest('button'); if (b) showTab(b.dataset.k); };

function showTab(k) {
  current = k;
  $('#tabs').querySelectorAll('button').forEach(b => b.classList.toggle('on', b.dataset.k === k));
  const p = $('#panel'); p.innerHTML = '';
  if (k === 'team') { p.append(h(`<div class="head-row"><h2>${TABS[k]}</h2></div>`)); const c = h('<div class="card"></div>'); c.append(form(data.team, S.team)); p.append(c); }
  else if (k === 'tools') tools(p);
  else if (k === 'messages') messages(p);
  else collection(p, k);
}

function collection(p, k) {
  const list = data[k];
  const head = h(`<div class="head-row"><h2>${TABS[k]} <span class="muted">(${list.length})</span></h2><button class="btn primary sm">+ افزودن</button></div>`);
  head.querySelector('button').onclick = () => { list.unshift(NEW[k]()); save(); showTab(k); $('#panel details')?.setAttribute('open', ''); };
  p.append(head);
  list.forEach((it, i) => {
    const d = h(`<details class="card item"><summary><span class="ttl"></span><span class="acts"><button title="بالا">↑</button><button title="پایین">↓</button><button class="del" title="حذف">🗑</button></span></summary></details>`);
    const ttl = d.querySelector('.ttl'); const setT = v => ttl.textContent = (it.icon ? it.icon + ' ' : '') + (v || '(بدون عنوان)');
    setT(it.name || it.title);
    const [upB, dnB, delB] = d.querySelectorAll('.acts button');
    const move = (e, dir) => { e.preventDefault(); const j = i + dir; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; save(); showTab(k); };
    upB.onclick = e => move(e, -1); dnB.onclick = e => move(e, 1);
    delB.onclick = e => {
      e.preventDefault(); if (!confirm('این مورد حذف شود؟')) return;
      list.splice(i, 1);
      if (k === 'members') data.projects.forEach(pr => pr.members = (pr.members || []).filter(id => id !== it.id));
      save(); showTab(k);
    };
    d.append(form(it, S[k], setT)); p.append(d);
  });
}

function tools(p) {
  p.append(h(`<div class="head-row"><h2>${TABS.tools}</h2></div>`));
  const w = h(`<div class="tools">
    <div class="card"><h3>انتشار تغییرات روی سایت</h3>
      <p class="muted">${Store.api ? 'سایت به سرور متصل است و تغییرات بلافاصله برای همه منتشر می‌شوند. فایل data.js فقط نسخه پشتیبان/حالت آفلاین است.' : 'تغییرات فقط در همین مرورگر ذخیره می‌شوند. برای اینکه همه بازدیدکنندگان ببینند، فایل <b>data.js</b> را دانلود و جایگزین <code>assets/data.js</code> در هاست/مخزن کنید.'}</p>
      <div class="row"><button class="btn primary sm" id="expJs">دانلود data.js</button><button class="btn ghost sm" id="expJson">پشتیبان JSON</button></div></div>
    <div class="card"><h3>بازیابی از فایل پشتیبان</h3><p class="muted">یک فایل JSON پشتیبان را بارگذاری کنید.</p>
      <div class="row"><input type="file" accept=".json,application/json" id="imp" style="max-width:320px"></div></div>
    <div class="card"><h3>تغییر رمز عبور</h3><p class="muted">${Store.api ? 'رمز حساب مدیر روی سرور.' : 'رمز فقط برای همین مرورگر است (امنیت واقعی نیاز به سرور دارد).'}</p>
      <div class="row">${Store.api ? '<input type="password" id="op" placeholder="رمز فعلی" style="max-width:200px">' : ''}<input type="password" id="np" placeholder="رمز جدید" style="max-width:240px"><button class="btn ghost sm" id="cp">ذخیره رمز</button></div></div>
    ${Store.api ? '' : `<div class="card"><h3>بازگشت به داده‌های پیش‌فرض</h3><p class="muted">تغییرات ذخیره‌شده در این مرورگر پاک می‌شود.</p>
      <button class="btn danger sm" id="rst">بازنشانی</button></div>`}</div>`);
  p.append(w);
  const dl = (name, text, type) => { const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([text], { type })); a.download = name; a.click(); URL.revokeObjectURL(a.href); };
  w.querySelector('#expJs').onclick = () => dl('data.js', '/* داده‌های سایت — خروجی پنل مدیریت */\nwindow.DEFAULT_DATA = ' + JSON.stringify(data, null, 2) + ';\n', 'text/javascript');
  w.querySelector('#expJson').onclick = () => dl('team-backup.json', JSON.stringify(data, null, 2), 'application/json');
  w.querySelector('#imp').onchange = async e => {
    try { const d = JSON.parse(await e.target.files[0].text()); if (!d.team || !Array.isArray(d.members)) throw 0; data = d; save(); alert('بازیابی شد ✓'); showTab('tools'); }
    catch { alert('فایل نامعتبر است'); }
  };
  w.querySelector('#cp').onclick = async () => {
    const v = w.querySelector('#np').value;
    try {
      if (Store.api) await Api.req('PUT', '/auth/password', { current_password: w.querySelector('#op').value, password: v });
      else { if (v.length < 4) throw new Error('حداقل ۴ کاراکتر'); localStorage.setItem(PASS_KEY, await sha(v)); }
      alert('رمز تغییر کرد ✓');
    } catch (err) { alert(err.message); }
  };
  if (!Store.api) w.querySelector('#rst').onclick = () => { if (confirm('همه تغییرات پاک شود؟')) { Store.reset(); data = Store.load(); alert('بازنشانی شد'); } };
}

/* ---------- پیام‌های فرم تماس ---------- */
async function refreshUnread() {
  try {
    const { unread } = await Api.req('GET', '/admin/messages');
    const b = $('#tabs [data-k=messages]'); if (b) b.innerHTML = TABS.messages + (unread ? ` <span class="badge">${unread}</span>` : '');
    return unread;
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
      <div class="head-row"><div><b>${esc(m.name)}</b> <a class="muted" dir="ltr" href="mailto:${esc(m.email)}">${esc(m.email)}</a>
      <div class="muted">${new Date(m.created_at).toLocaleString('fa-IR')}${m.subject ? ' — ' + esc(m.subject) : ''}</div></div>
      <div class="acts"><button title="خوانده/نخوانده">${m.read_at ? '○' : '✓'}</button><button class="del" title="حذف">🗑</button></div></div>
      <p class="msg-body">${esc(m.body)}</p></article>`);
    const [rd, del] = c.querySelectorAll('.acts button');
    rd.onclick = async () => { await Api.req('PATCH', `/admin/messages/${m.id}/read`); refreshUnread(); showTab('messages'); };
    del.onclick = async () => { if (confirm('پیام حذف شود؟')) { await Api.req('DELETE', `/admin/messages/${m.id}`); refreshUnread(); showTab('messages'); } };
    box.append(c);
  });
}
