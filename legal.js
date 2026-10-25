/* Shared behaviour for the legal pages: theme switch (same "theme" key as the homepage),
   a contents list that highlights the section in view, and the footer year. No tracking, no network calls. */
(function () {
  var root = document.documentElement;
  var btn = document.getElementById('theme-btn');
  var meta = document.querySelector('meta[name="theme-color"]');

  function show(t) {
    if (meta) meta.setAttribute('content', t === 'dark' ? '#080808' : '#F6F6F6');
    if (btn) btn.setAttribute('aria-label', t === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
  }
  show(root.dataset.theme);
  if (btn) btn.addEventListener('click', function () {
    var t = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = t;
    try { localStorage.setItem('theme', t); } catch (e) {}
    show(t);
  });

  // contents: open on wide screens, collapsible on small ones; close after picking a section on mobile
  var details = document.querySelector('.toc details');
  var wide = window.matchMedia('(min-width: 961px)');
  function syncToc() { if (details) details.open = wide.matches; }
  syncToc();
  if (wide.addEventListener) wide.addEventListener('change', syncToc);
  var links = Array.prototype.slice.call(document.querySelectorAll('.toc a[href^="#"]'));
  links.forEach(function (a) {
    a.addEventListener('click', function () { if (!wide.matches && details) details.open = false; });
  });

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // one highlight bar that slides to the active contents item
  var toc = document.querySelector('.toc');
  var list = toc && toc.querySelector('ol');
  var ind = null;
  if (list) {
    ind = document.createElement('span');
    ind.className = 'toc-ind'; ind.setAttribute('aria-hidden', 'true');
    list.appendChild(ind);
    toc.classList.add('has-ind');
  }
  function moveInd(a) {
    if (!ind || !a || !a.offsetHeight) return;
    ind.style.height = a.offsetHeight + 'px';
    ind.style.transform = 'translateY(' + a.offsetTop + 'px)';
    ind.style.opacity = '1';
  }

  // highlight the section currently being read
  if ('IntersectionObserver' in window && links.length) {
    var byId = {};
    links.forEach(function (a) { byId[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        links.forEach(function (a) { a.removeAttribute('aria-current'); });
        var hit = byId[en.target.id];
        if (hit) { hit.setAttribute('aria-current', 'true'); moveInd(hit); }
      });
    }, { rootMargin: '-25% 0px -65% 0px' });
    Object.keys(byId).forEach(function (id) { var s = document.getElementById(id); if (s) io.observe(s); });
    addEventListener('resize', function () { moveInd(document.querySelector('.toc a[aria-current="true"]')); });
    if (details) details.addEventListener('toggle', function () { moveInd(document.querySelector('.toc a[aria-current="true"]')); });
  }

  // gentle section reveals: only sections that start below the fold wait, each fades in once as it arrives
  if (!reduce && 'IntersectionObserver' in window) {
    var target = location.hash ? document.getElementById(location.hash.slice(1)) : null;
    var below = Array.prototype.slice.call(document.querySelectorAll('.doc-body section')).filter(function (s) {
      return s.getBoundingClientRect().top > window.innerHeight && s !== target;
    });
    var ro = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add('in');
        ro.unobserve(en.target);
      });
    }, { rootMargin: '0px 0px -8% 0px' });
    below.forEach(function (s) { s.classList.add('await'); ro.observe(s); });
  }

  var yr = document.getElementById('yr');
  if (yr) yr.textContent = new Date().getFullYear();
})();
