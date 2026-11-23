/* Decorative twinkling stars behind the chess demo. Lighter on phones, off for reduced-motion. */
(function () {
  'use strict';
  var host = document.querySelector('.star-bg-overlay');
  if (!host) return;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var count = window.innerWidth < 600 ? 40 : 110;
  var tints = ['#88ccff', '#aaddff', '#ffdd88', '#ffeedd', '#ffffff'];
  var frag = document.createDocumentFragment();

  for (var i = 0; i < count; i++) {
    var star = document.createElement('span');
    var bright = Math.random() > 0.85;
    var size = bright ? 2 + Math.random() * 2 : 1 + Math.random() * 2;
    star.className = 'star' + (bright ? ' star--bright' : '');
    star.style.cssText =
      'width:' + size.toFixed(1) + 'px;height:' + size.toFixed(1) + 'px;' +
      'top:' + (Math.random() * 100).toFixed(1) + '%;left:' + (Math.random() * 100).toFixed(1) + '%;' +
      'background:' + tints[Math.floor(Math.random() * tints.length)] + ';' +
      'animation-duration:' + (2 + Math.random() * 5).toFixed(1) + 's;' +
      'animation-delay:' + (Math.random() * 6).toFixed(1) + 's;';
    frag.appendChild(star);
  }
  host.appendChild(frag);
})();
