const data = Store.load();
let io;
const $ = s => document.querySelector(s);
const t = data.team;

/* تم روشن/تاریک */
const setTheme = m => { document.documentElement.dataset.theme = m; try { localStorage.setItem('theme', m); } catch (e) {} };
setTheme((() => { try { return localStorage.getItem('theme'); } catch (e) {} })() || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'));
$('#themeBtn').onclick = () => setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');

document.title = t.name;
$('#brand').textContent = t.name;
$('#heroName').textContent = t.name;
$('#heroTagline').textContent = t.tagline;
$('#about').textContent = t.about;
$('#copy').textContent = `© ${new Date().getFullYear()} ${t.name}`;
$('#stats').innerHTML = (t.stats || []).map(s => `<div class="stat"><b>${esc(s.value)}</b><span>${esc(s.label)}</span></div>`).join('');

$('#servicesGrid').innerHTML = data.services.map(s => `
  <article class="card service reveal"><div class="s-icon">${esc(s.icon)}</div>
  <h3>${esc(s.title)}</h3><p>${esc(s.desc)}</p></article>`).join('');

const initials = n => n.trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('‌');
const avatar = (m, cls = 'avatar') => m.avatar
  ? `<img class="${cls}" src="${esc(m.avatar)}" alt="${esc(m.name)}">`
  : `<div class="${cls} ph">${esc(initials(m.name))}</div>`;
const levelBadge = l => `<span class="lvl lvl-${esc(l)}">${esc(LEVELS[l] || l)}</span>`;

/* فیلترها */
function filters(el, items, onPick) {
  const all = ['همه', ...items];
  el.innerHTML = all.map((x, i) => `<button class="chip${i ? '' : ' on'}" data-v="${esc(x)}">${esc(x)}</button>`).join('');
  el.onclick = e => {
    const b = e.target.closest('.chip'); if (!b) return;
    el.querySelectorAll('.chip').forEach(c => c.classList.toggle('on', c === b));
    onPick(b.dataset.v === 'همه' ? null : b.dataset.v);
  };
}

function renderMembers(level) {
  const list = data.members.filter(m => !level || LEVELS[m.level] === level);
  $('#membersGrid').innerHTML = list.map(m => `
    <article class="card member reveal" data-id="${esc(m.id)}" tabindex="0">
      <div class="m-head">${avatar(m)}<div><h3>${esc(m.name)}</h3><p class="muted">${esc(m.role)}</p></div></div>
      <div class="m-meta">${levelBadge(m.level)}<span class="muted">${esc(m.years)} سال تجربه</span>
        ${m.available ? '<span class="avail">● آماده همکاری</span>' : ''}</div>
      <div class="bars">${(m.skills || []).slice(0, 3).map(bar).join('')}</div>
      <span class="more">مشاهده رزومه کامل ←</span>
    </article>`).join('');
  observe();
}
const bar = s => `<div class="bar"><div class="bar-l"><span>${esc(s.name)}</span><span>${esc(s.level)}٪</span></div>
  <div class="track"><div class="fill" style="--w:${Math.min(100, +s.level || 0)}%"></div></div></div>`;

filters($('#levelFilters'), Object.keys(LEVELS).filter(k => data.members.some(m => m.level === k)).map(k => LEVELS[k]), renderMembers);
renderMembers(null);

$('#membersGrid').addEventListener('click', e => { const c = e.target.closest('.member'); if (c) openMember(c.dataset.id); });
$('#membersGrid').addEventListener('keydown', e => { const c = e.target.closest('.member'); if (c && e.key === 'Enter') openMember(c.dataset.id); });

function openMember(id) {
  const m = data.members.find(x => x.id === id); if (!m) return;
  const projs = data.projects.filter(p => (p.members || []).includes(id));
  $('#modalBody').innerHTML = `
    <button class="icon-btn close" onclick="memberModal.close()">✕</button>
    <div class="m-head big">${avatar(m, 'avatar lg')}<div><h2>${esc(m.name)}</h2><p class="muted">${esc(m.role)}</p>
      <div class="m-meta">${levelBadge(m.level)}<span class="muted">${esc(m.years)} سال تجربه</span>
      ${m.available ? '<span class="avail">● آماده همکاری</span>' : '<span class="muted">مشغول پروژه</span>'}</div></div></div>
    <p>${esc(m.bio)}</p>
    <dl class="facts">
      ${m.location ? `<div><dt>موقعیت</dt><dd>${esc(m.location)}</dd></div>` : ''}
      ${m.education ? `<div><dt>تحصیلات</dt><dd>${esc(m.education)}</dd></div>` : ''}
      ${m.languages ? `<div><dt>زبان‌ها</dt><dd>${esc(m.languages)}</dd></div>` : ''}
    </dl>
    <h4>مهارت‌ها</h4><div class="bars">${(m.skills || []).map(bar).join('')}</div>
    ${projs.length ? `<h4>پروژه‌ها</h4><div class="tags">${projs.map(p => `<span class="tag">${esc(p.title)}</span>`).join('')}</div>` : ''}
    ${(m.links || []).length ? `<div class="socials">${m.links.map(l => `<a href="${esc(l.url)}" target="_blank" rel="noopener">${esc(l.label)}</a>`).join('')}</div>` : ''}`;
  $('#memberModal').showModal();
  requestAnimationFrame(() => $('#modalBody').querySelectorAll('.fill').forEach(f => f.classList.add('go')));
}
$('#memberModal').addEventListener('click', e => { if (e.target.id === 'memberModal') e.target.close(); });

function renderProjects(cat) {
  const list = data.projects.filter(p => !cat || p.category === cat);
  $('#projectsGrid').innerHTML = list.map((p, i) => `
    <article class="card project reveal">
      <div class="cover" style="${p.image ? `background-image:url('${esc(p.image)}')` : `--h:${(i * 67) % 360}`}">
        ${p.image ? '' : `<span>${esc(p.title[0] || '')}</span>`}</div>
      <div class="p-body">
        <div class="p-top"><span class="tag">${esc(p.category)}</span><span class="muted">${esc(p.year)}</span></div>
        <h3>${esc(p.title)}</h3><p>${esc(p.desc)}</p>
        <div class="tags">${(p.tech || []).map(x => `<span class="tech">${esc(x)}</span>`).join('')}</div>
        <div class="p-foot"><div class="stack">${(p.members || []).map(id => data.members.find(m => m.id === id)).filter(Boolean).map(m => avatar(m, 'avatar xs')).join('')}</div>
        ${p.link && p.link !== '#' ? `<a href="${esc(p.link)}" target="_blank" rel="noopener">مشاهده ↗</a>` : ''}</div>
      </div>
    </article>`).join('');
  observe();
}
filters($('#projFilters'), [...new Set(data.projects.map(p => p.category).filter(Boolean))], renderProjects);
renderProjects(null);

$('#contactInfo').innerHTML = [
  t.email && `<a href="mailto:${esc(t.email)}">✉ ${esc(t.email)}</a>`,
  t.phone && `<a href="tel:${esc(t.phone)}">☏ ${esc(t.phone)}</a>`,
  t.location && `<span>⌖ ${esc(t.location)}</span>`].filter(Boolean).join('');
$('#socials').innerHTML = (t.socials || []).map(s => `<a href="${esc(s.url)}" target="_blank" rel="noopener">${esc(s.label)}</a>`).join('');

/* انیمیشن ظاهر شدن */

function observe() {
  io = io || new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('in'); e.target.querySelectorAll('.fill').forEach(f => f.classList.add('go')); io.unobserve(e.target); }
  }), { threshold: .12 });
  document.querySelectorAll('.reveal:not(.in)').forEach(el => io.observe(el));
}
observe();
