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

  /* Back to top: the header is sticky, so anchoring to it never scrolls — scroll the window instead */
  const toTop = $('#toTop');
  const goTop = e => { e.preventDefault(); scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); };
  $$('a[href="#top"]').forEach(a => a.addEventListener('click', goTop));
  toTop?.addEventListener('click', goTop);
  addEventListener('scroll', () => { if (toTop) toTop.hidden = scrollY < 700; }, { passive: true });

  /* Suggest the visitor's own language when the site has it */
  const banner = $('#langBanner');
  (() => {
    if (!banner) return;
    let dismissed; try { dismissed = localStorage.getItem('lang-banner'); } catch (e) {}
    if (dismissed) return;
    const wanted = (navigator.languages || [navigator.language]).map(l => (l || '').slice(0, 2).toLowerCase());
    if (wanted[0] === root.lang) return;
    const tpl = wanted.map(c => banner.querySelector(`template[data-lang="${c}"]`)).find(Boolean);
    if (!tpl) return;
    $('.lb-body', banner).replaceChildren(tpl.content.cloneNode(true));
    banner.hidden = false;
    $('[data-dismiss]', banner).onclick = () => { banner.hidden = true; try { localStorage.setItem('lang-banner', '1'); } catch (e) {} };
  })();

  /* AI chat assistant */
  const chat = $('#chat');
  if (chat) {
    const panel = $('#chatPanel'), log = $('#chatLog'), form = $('#chatForm'), input = $('#chatInput'), fab = $('#chatFab');
    const KEY = 'chat-session-' + chat.dataset.locale;
    let sid; try { sid = localStorage.getItem(KEY); } catch (e) {}
    let busy = false, loaded = false;
    const esc = s => s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    /* Minimal, safe markdown: escape first, then bold, links and bullet lists */
    const md = t => esc(t)
      .replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
      .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
      .replace(/(^|\s)(https?:\/\/[^\s<]+)/g, '$1<a href="$2" target="_blank" rel="noopener">$2</a>')
      .replace(/(?:^|\n)((?:[-•*] .+(?:\n|$))+)/g, (m, list) => '<ul>' + list.trim().split('\n').map(l => '<li>' + l.replace(/^[-•*] /, '') + '</li>').join('') + '</ul>');
    const bubble = (cls, html) => { const b = document.createElement('div'); b.className = 'bubble ' + cls; b.innerHTML = html; log.append(b); log.scrollTop = log.scrollHeight; return b; };
    const setOpen = open => {
      chat.classList.toggle('open', open); panel.hidden = !open; fab.setAttribute('aria-expanded', open);
      if (open) { input.focus(); if (!loaded) restore(); }
    };
    const restore = async () => {
      loaded = true;
      if (!sid) return;
      try {
        const r = await fetch(chat.dataset.api + '/' + sid, { headers: { Accept: 'application/json' } });
        if (!r.ok) throw 0;
        const { messages } = await r.json();
        messages.forEach(m => bubble(m.role === 'user' ? 'me' : 'bot', m.role === 'user' ? esc(m.content) : md(m.content)));
        if (messages.length) $('#chatSuggest').hidden = true;
      } catch (e) { sid = null; }
    };
    const send = async text => {
      text = text.trim(); if (!text || busy) return;
      busy = true; $('#chatSuggest').hidden = true;
      bubble('me', esc(text)); input.value = ''; input.style.height = '';
      const typing = bubble('bot', '<span class="typing"><i></i><i></i><i></i></span>');
      $('.send', form).disabled = true;
      try {
        const r = await fetch(chat.dataset.api, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ session_id: sid, message: text, locale: chat.dataset.locale }) });
        const j = await r.json().catch(() => ({}));
        if (j.session_id) { sid = j.session_id; try { localStorage.setItem(KEY, sid); } catch (e) {} }
        typing.remove();
        if (!r.ok) bubble('bot err', esc(r.status === 429 ? chat.dataset.limit : chat.dataset.error));
        else { bubble('bot', md(j.reply)); if (j.lead) bubble('note', esc(chat.dataset.lead)); }
      } catch (e) { typing.remove(); bubble('bot err', esc(chat.dataset.error)); }
      busy = false; $('.send', form).disabled = false; input.focus();
    };
    fab.addEventListener('click', () => setOpen(panel.hidden));
    form.addEventListener('submit', e => { e.preventDefault(); send(input.value); });
    input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(input.value); } });
    input.addEventListener('input', () => { input.style.height = ''; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; });
    $('#chatSuggest').addEventListener('click', e => { const c = e.target.closest('.chip'); if (c) send(c.textContent); });
    $('#chatReset').addEventListener('click', () => {
      sid = null; try { localStorage.removeItem(KEY); } catch (e) {}
      [...log.children].slice(1).forEach(n => n.remove()); $('#chatSuggest').hidden = false; input.focus();
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden && !$('#memberModal')?.open) setOpen(false); });
  }
})();
