document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.pwd-wrap').forEach(function (wrap) {
    var input = wrap.querySelector('input');
    var btn = wrap.querySelector('.pwd-toggle');
    if (!input || !btn) return;
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.classList.toggle('is-visible', show);
      btn.setAttribute('aria-label', show ? 'הסתרת סיסמה' : 'הצגת סיסמה');
    });
  });
});
