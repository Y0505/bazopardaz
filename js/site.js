/* Bazopardaz — shared behaviour: mobile menu, reveal-on-scroll, FAQ, back-to-top, mobile CTA bar. */
(function () {
  'use strict';

  var root = document.documentElement;
  var toggle = document.getElementById('navToggle');
  var menu = document.getElementById('navMobile');
  var ctaBar = document.getElementById('ctaBar');
  var toTop = document.getElementById('backToTop');
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Theme: light / dark ----------
     The <head> script already chose the theme (saved choice, otherwise the system setting). This wires the button,
     keeps theme-color in sync and follows the system while the visitor has not chosen. */
  var themeBtn = document.getElementById('themeToggle');
  var THEME_KEY = 'bp-theme';
  var THEME_COLOR = { dark: '#0b0b14', light: '#f7f6fe' };
  function currentTheme() { return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark'; }
  function setTheme(t, persist) {
    root.setAttribute('data-theme', t);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', THEME_COLOR[t]);
    if (themeBtn) {
      var label = t === 'light' ? 'تغییر به حالت تیره' : 'تغییر به حالت روشن';
      themeBtn.setAttribute('aria-pressed', t === 'light' ? 'true' : 'false');
      themeBtn.setAttribute('aria-label', label);
      themeBtn.setAttribute('title', label);
    }
    if (persist) { try { localStorage.setItem(THEME_KEY, t); } catch (e) { /* storage blocked */ } }
  }
  setTheme(currentTheme(), false);
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      root.classList.add('theme-anim');
      setTheme(currentTheme() === 'dark' ? 'light' : 'dark', true);
      window.setTimeout(function () { root.classList.remove('theme-anim'); }, 400);
    });
  }
  var systemLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)');
  function onSystemChange(e) {
    var saved = null;
    try { saved = localStorage.getItem(THEME_KEY); } catch (err) { /* ignore */ }
    if (saved !== 'light' && saved !== 'dark') setTheme(e.matches ? 'light' : 'dark', false);
  }
  if (systemLight) {
    if (systemLight.addEventListener) systemLight.addEventListener('change', onSystemChange);
    else if (systemLight.addListener) systemLight.addListener(onSystemChange);
  }
  window.addEventListener('storage', function (e) {      // another tab changed the theme
    if (e.key === THEME_KEY && (e.newValue === 'light' || e.newValue === 'dark')) setTheme(e.newValue, false);
  });

  /* ---------- Mobile menu ---------- */
  function setMenu(open) {
    if (!toggle || !menu) return;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'بستن منو' : 'باز کردن منو');
    menu.hidden = !open;
    root.classList.toggle('nav-open', open);
  }

  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      setMenu(toggle.getAttribute('aria-expanded') !== 'true');
    });
    menu.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('a') : null;
      if (a) setMenu(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !menu.hidden) {
        setMenu(false);
        toggle.focus();
      }
    });
    // If the window grows past the mobile breakpoint, never leave the overlay stuck open.
    var wide = window.matchMedia('(min-width: 769px)');
    var onWide = function (q) { if (q.matches) setMenu(false); };
    if (wide.addEventListener) wide.addEventListener('change', onWide);
    else if (wide.addListener) wide.addListener(onWide);
  }

  /* ---------- Reveal on scroll ---------- */
  var revealEls = document.querySelectorAll('.reveal');
  if (revealEls.length) {
    if ('IntersectionObserver' in window && !reduceMotion) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) {
            en.target.classList.add('is-visible');
            io.unobserve(en.target);
          }
        });
      }, { threshold: 0.1, rootMargin: '0px 0px -24px 0px' });
      revealEls.forEach(function (el) { io.observe(el); });
    } else {
      revealEls.forEach(function (el) { el.classList.add('is-visible'); });
    }
  }

  /* ---------- FAQ: keep one answer open at a time ---------- */
  var faqs = document.querySelectorAll('details.faq-item');
  faqs.forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (!d.open) return;
      faqs.forEach(function (o) { if (o !== d) o.open = false; });
    });
  });

  /* ---------- Back to top + mobile CTA bar ---------- */
  var ticking = false;
  function onScroll() {
    ticking = false;
    var y = window.pageYOffset || root.scrollTop;
    if (toTop) toTop.classList.toggle('is-visible', y > 500);
    if (ctaBar) ctaBar.classList.toggle('is-visible', y > 320);
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; window.requestAnimationFrame(onScroll); }
  }, { passive: true });
  onScroll();

  if (toTop) {
    toTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
  }
})();
