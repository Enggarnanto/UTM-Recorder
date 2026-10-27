(function () {
  'use strict';

  function fallbackCopy(value) {
    var field = document.createElement('textarea');
    field.value = value;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    var copied = document.execCommand('copy');
    document.body.removeChild(field);
    return copied;
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.utr-copy-shortcode');
    if (!button) return;
    var shortcode = button.getAttribute('data-shortcode');
    var originalLabel = button.textContent;
    var status = document.getElementById('utr-copy-status');
    var copy = navigator.clipboard && navigator.clipboard.writeText
      ? navigator.clipboard.writeText(shortcode).then(function () { return true; }, function () { return fallbackCopy(shortcode); })
      : Promise.resolve(fallbackCopy(shortcode));
    copy.then(function (copied) {
      button.textContent = copied ? 'Tersalin' : 'Gagal menyalin';
      if (status) status.textContent = copied ? 'Shortcode tersalin: ' + shortcode : 'Gagal menyalin shortcode.';
      window.setTimeout(function () { button.textContent = originalLabel; }, 2000);
    });
  });
}());
