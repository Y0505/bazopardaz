/* Blog behaviour: category filter, reading progress, table of contents, sharing. */
(function () {
  'use strict';

  /* ---------- Category filter on /blog/ ---------- */
  var grid = document.getElementById('blogGrid');
  if (grid) {
    var chips = document.querySelectorAll('.blog-filter .chip-btn');
    var cards = grid.querySelectorAll('.post-card');
    var empty = document.getElementById('blogEmpty');
    var apply = function (key) {
      var shown = 0;
      chips.forEach(function (c) { c.setAttribute('aria-pressed', c.getAttribute('data-filter') === key ? 'true' : 'false'); });
      cards.forEach(function (card) {
        var match = key === 'all' || card.getAttribute('data-cat') === key;
        card.hidden = !match;
        if (match) shown++;
      });
      if (empty) empty.hidden = shown !== 0;
    };
    chips.forEach(function (c) {
      c.addEventListener('click', function () { apply(c.getAttribute('data-filter')); });
    });
  }

  /* ---------- Article page ---------- */
  var body = document.querySelector('.post-body');
  if (!body) return;

  // reading progress bar
  var bar = document.querySelector('#readingProgress span');
  var ticking = false;
  function progress() {
    ticking = false;
    var rect = body.getBoundingClientRect();
    var total = rect.height - window.innerHeight * 0.6;
    var done = Math.min(1, Math.max(0, (-rect.top + window.innerHeight * 0.2) / Math.max(total, 1)));
    if (bar) bar.style.transform = 'scaleX(' + done.toFixed(3) + ')';
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; window.requestAnimationFrame(progress); }
  }, { passive: true });
  window.addEventListener('resize', progress);
  progress();

  // table of contents: collapsed on phones, highlight the section being read
  var toc = document.getElementById('tocBox');
  if (toc && window.matchMedia('(max-width: 1099px)').matches) toc.removeAttribute('open');
  if (toc && 'IntersectionObserver' in window) {
    var links = {};
    toc.querySelectorAll('a[href^="#"]').forEach(function (a) { links[decodeURIComponent(a.getAttribute('href').slice(1))] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        Object.keys(links).forEach(function (k) { links[k].classList.remove('is-active'); });
        var a = links[en.target.id];
        if (a) a.classList.add('is-active');
      });
    }, { rootMargin: '-80px 0px -70% 0px' });
    body.querySelectorAll('h2[id]').forEach(function (h) { io.observe(h); });
  }

  // sharing
  var copy = document.getElementById('copyLink');
  if (copy) {
    var original = copy.textContent;
    copy.addEventListener('click', function () {
      var done = function () {
        copy.textContent = copy.getAttribute('data-copied');
        setTimeout(function () { copy.textContent = original; }, 2200);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(window.location.href.split('#')[0]).then(done, function () { window.prompt('لینک مقاله:', window.location.href); });
      } else {
        window.prompt('لینک مقاله:', window.location.href);
      }
    });
  }
  var share = document.getElementById('nativeShare');
  if (share && navigator.share) {
    share.hidden = false;
    share.addEventListener('click', function () {
      navigator.share({ title: document.title, url: window.location.href.split('#')[0] }).catch(function () { /* cancelled */ });
    });
  }
})();
