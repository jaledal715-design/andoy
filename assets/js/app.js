// Small enhancements. Every page still works as a normal form without JavaScript.
(function () {
  var EYE = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYEOFF = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.06-5.94M9.9 4.24A10.9 10.9 0 0 1 12 5c7 0 11 7 11 7a19.9 19.9 0 0 1-3.17 4.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';
  var script = document.currentScript;

  document.addEventListener('click', function (e) {
    var eye = e.target.closest('[data-eye]');
    if (eye) {
      var inp = eye.parentElement.querySelector('input'), show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      eye.innerHTML = show ? EYEOFF : EYE;
      eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      eye.setAttribute('aria-pressed', String(show));
    }
    var cp = e.target.closest('[data-copy]');
    if (cp) {
      var v = cp.dataset.copy, done = function () { cp.textContent = 'Copied'; setTimeout(function () { cp.textContent = cp.dataset.label; }, 1500); };
      cp.dataset.label = cp.dataset.label || cp.textContent;
      if (navigator.clipboard) navigator.clipboard.writeText(v).then(done, function () { window.prompt('Copy this:', v); });
      else window.prompt('Copy this:', v);
    }
    var cf = e.target.closest('[data-confirm]');
    if (cf && !window.confirm(cf.dataset.confirm)) e.preventDefault();
  });
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });

  // Checkout: address only for delivery, GCash steps only for GCash, live total
  var form = document.getElementById('checkout-form');
  if (form) {
    var fee = parseFloat(form.dataset.fee || '0'), sub = parseFloat(form.dataset.subtotal || '0');
    var sync = function () {
      var ful = form.querySelector('input[name=fulfillment]:checked').value, pay = form.querySelector('input[name=payment_method]:checked').value;
      form.querySelector('#address-box').hidden = ful !== 'delivery';
      form.querySelector('#address').required = ful === 'delivery';
      form.querySelector('#gcash-box').hidden = pay !== 'gcash';
      form.querySelector('#proof').required = pay === 'gcash';
      var f = ful === 'delivery' ? fee : 0, total = sub + f;
      document.getElementById('sum-fee').textContent = '₱' + f.toFixed(2);
      document.getElementById('sum-total').textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      var amt = document.getElementById('gcash-amt'); if (amt) amt.textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      var ca = document.getElementById('copy-amt'); if (ca) ca.dataset.copy = total.toFixed(2);
      var hint = document.getElementById('cash-hint'); if (hint) hint.textContent = ful === 'delivery' ? 'Pay the rider when your order arrives.' : 'Pay at the counter when you pick up.';
      var dl = document.getElementById('date-label'); if (dl) dl.textContent = ful === 'delivery' ? 'Delivery date' : 'Pickup date';
    };
    form.addEventListener('change', sync); sync();
  }

  // Keep red dots and chat fresh
  var pollUrl = script && script.dataset.poll;
  if (pollUrl) setInterval(function () {
    fetch(pollUrl, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      document.querySelectorAll('[data-dot]').forEach(function (el) { var n = d[el.dataset.dot] || 0; el.hidden = !n; if (el.classList.contains('cnt')) el.textContent = n; });
    }).catch(function () {});
  }, 15000);
  var cb = document.getElementById('chb');
  if (cb) {
    cb.scrollTop = cb.scrollHeight;
    setInterval(function () {
      fetch(cb.dataset.src, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (h) {
        if (h.trim() && h !== cb.dataset.last) { var first = cb.dataset.last === undefined; cb.dataset.last = h; if (!first) { cb.innerHTML = h; cb.scrollTop = cb.scrollHeight; } }
      }).catch(function () {});
    }, 8000);
  }
})();

// Shrink big phone photos before upload so they pass the host's upload limit
document.addEventListener('change', function (e) {
  var inp = e.target, f = inp.type === 'file' && inp.files && inp.files[0];
  if (!f || !/^image\/(jpeg|png|webp)$/.test(f.type) || f.size < 1.2 * 1024 * 1024 || typeof DataTransfer === 'undefined') return;
  var img = new Image(), url = URL.createObjectURL(f);
  img.onload = function () {
    var k = Math.min(1, 1600 / Math.max(img.width, img.height)), c = document.createElement('canvas');
    c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    c.toBlob(function (b) {
      URL.revokeObjectURL(url);
      if (!b || b.size >= f.size) return;
      try { var dt = new DataTransfer(); dt.items.add(new File([b], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' })); inp.files = dt.files; } catch (x) {}
    }, 'image/jpeg', 0.82);
  };
  img.onerror = function () { URL.revokeObjectURL(url); };
  img.src = url;
});
