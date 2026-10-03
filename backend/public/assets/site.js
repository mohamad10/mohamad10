(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const root = document.documentElement;
  root.classList.add('js');

  /* Theme */
  $('#themeBtn')?.addEventListener('click', () => {
    const t = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = t;
    try { localStorage.setItem('theme', t); } catch (e) {}
  });

  /* Header state, scroll progress, active link */
  const nav = $('.nav'), bar = $('.progress');
  const sections = $$('main section[id]');
  const links = $$('#navLinks a');
  const onScroll = () => {
    const y = scrollY, h = document.body.scrollHeight - innerHeight;
    nav.classList.toggle('scrolled', y > 10);
    bar.style.setProperty('--p', h > 0 ? y / h : 0);
    let cur = '';
    for (const s of sections) if (s.offsetTop - 140 <= y) cur = s.id;
    links.forEach(a => a.classList.toggle('active', a.hash === '#' + cur));
  };
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* Mobile menu */
  const menuBtn = $('#menuBtn'), menu = $('#navLinks');
  const setMenu = open => { menu.classList.toggle('open', open); nav.classList.toggle('menu-open', open); menuBtn.setAttribute('aria-expanded', open); };
  menuBtn?.addEventListener('click', () => setMenu(!menu.classList.contains('open')));
  menu?.addEventListener('click', e => { if (e.target.closest('a')) setMenu(false); });

  /* Close the language menu when clicking elsewhere */
  document.addEventListener('click', e => $$('details.lang[open]').forEach(d => { if (!d.contains(e.target)) d.open = false; }));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { $$('details.lang[open]').forEach(d => d.open = false); setMenu(false); } });

  /* Reveal on scroll + skill bars */
  const io = new IntersectionObserver(entries => entries.forEach(e => {
    if (!e.isIntersecting) return;
    e.target.classList.add('in');
    io.unobserve(e.target);
  }), { threshold: .1, rootMargin: '0px 0px -40px 0px' });
  $$('.reveal').forEach(el => io.observe(el));

  /* Filters (team by level, projects by category) */
  $$('[data-filter]').forEach(group => {
    const grid = group.dataset.filter === 'team' ? $('#teamGrid') : $('#projectsGrid');
    group.addEventListener('click', e => {
      const btn = e.target.closest('.chip'); if (!btn) return;
      $$('.chip', group).forEach(c => { c.classList.toggle('on', c === btn); c.setAttribute('aria-pressed', c === btn); });
      const v = btn.dataset.v;
      $$('[data-v]', grid).forEach(card => {
        const show = !v || card.dataset.v === v;
        card.classList.toggle('hidden-by-filter', !show);
        if (show) card.classList.add('in');
      });
    });
  });

  /* Member résumé dialog */
  const modal = $('#memberModal');
  const openMember = id => {
    const tpl = document.getElementById('member-' + id);
    if (!modal || !tpl) return;
    const body = $('.modal-body', modal);
    body.replaceChildren(tpl.content.cloneNode(true));
    modal.showModal();
    requestAnimationFrame(() => requestAnimationFrame(() => $$('.fill', body).forEach(f => f.classList.add('go'))));
  };
  document.addEventListener('click', e => {
    const t = e.target.closest('[data-member]');
    if (t) { e.preventDefault(); openMember(t.dataset.member); }
  });
  document.addEventListener('keydown', e => {
    const t = e.target.closest?.('.member[data-member]');
    if (t && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openMember(t.dataset.member); }
  });
  modal?.addEventListener('click', e => { if (e.target === modal || e.target.closest('[data-close]')) modal.close(); });

  /* Contact form */
  const form = $('#contactForm');
  form?.addEventListener('submit', async e => {
    e.preventDefault();
    const msg = $('.cf-msg', form), btn = $('button', form), d = form.dataset;
    let valid = true;
    $$('input[required], textarea[required]', form).forEach(i => { const ok = i.checkValidity(); i.classList.toggle('invalid', !ok); valid &&= ok; });
    if (!valid) { $('.invalid', form)?.focus(); return; }
    btn.disabled = true; msg.className = 'cf-msg wide'; msg.textContent = d.sending;
    try {
      const res = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(form))) });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw Object.assign(new Error(json.errors ? Object.values(json.errors).flat()[0] : d.error), { status: res.status });
      form.reset(); msg.classList.add('ok'); msg.textContent = d.sent;
    } catch (err) {
      msg.classList.add('err'); msg.textContent = err.status === 429 ? d.throttled : d.error;
    }
    btn.disabled = false;
  });
  form?.addEventListener('input', e => e.target.classList.remove('invalid'));
})();
