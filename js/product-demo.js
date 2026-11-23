/* Size picker for the WordPress → Bale product demo. Updates the "buy on Bale" deep link. */
(function () {
  'use strict';
  var buy = document.getElementById('buyOnBale');
  var buyRubika = document.getElementById('buyOnRubika');
  var options = document.querySelectorAll('.wp-store-page .size-option');
  if (!buy || !options.length) return;

  var BOT = 'https://ble.ir/WordPressPartnerBot?start=product4_size';
  var BOT_RUBIKA = 'https://rubika.ir/WordPressPartnerBot?start=product4_size';

  function select(btn) {
    var size = btn.getAttribute('data-size');
    options.forEach(function (o) { o.setAttribute('aria-pressed', o === btn ? 'true' : 'false'); });
    buy.href = BOT + encodeURIComponent(size);
    buy.setAttribute('aria-label', 'ادامه خرید کتونی سایز ' + btn.textContent.trim() + ' در بازوی بله');
    if (buyRubika) {
      buyRubika.href = BOT_RUBIKA + encodeURIComponent(size);
      buyRubika.setAttribute('aria-label', 'ادامه خرید کتونی سایز ' + btn.textContent.trim() + ' در بازوی روبیکا');
    }
  }

  options.forEach(function (o) { o.addEventListener('click', function () { select(o); }); });

  var current = document.querySelector('.wp-store-page .size-option[aria-pressed="true"]');
  if (current) select(current);
})();
