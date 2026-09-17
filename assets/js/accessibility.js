(function(){
  var root = document.documentElement;
  var STORE_KEY = 'sb_a11y_v1';

  function load(){
    try{ return JSON.parse(localStorage.getItem(STORE_KEY) || '{}'); }
    catch(e){ return {}; }
  }
  function save(state){
    try{ localStorage.setItem(STORE_KEY, JSON.stringify(state)); }catch(e){}
  }

  var state = Object.assign({ scale: 100, contrast: false, underline: false, noMotion: false }, load());

  function apply(){
    root.style.fontSize = state.scale + '%';
    root.classList.toggle('a11y-contrast', !!state.contrast);
    root.classList.toggle('a11y-underline-links', !!state.underline);
    root.classList.toggle('a11y-no-motion', !!state.noMotion);

    var contrastBtn = document.getElementById('a11yContrast');
    var underlineBtn = document.getElementById('a11yUnderline');
    var motionBtn = document.getElementById('a11yMotion');
    if (contrastBtn) contrastBtn.classList.toggle('is-on', !!state.contrast);
    if (underlineBtn) underlineBtn.classList.toggle('is-on', !!state.underline);
    if (motionBtn) motionBtn.classList.toggle('is-on', !!state.noMotion);
  }

  apply();

  document.addEventListener('DOMContentLoaded', function(){
    var toggleBtn = document.getElementById('a11yToggle');
    var panel = document.getElementById('a11yPanel');
    if (!toggleBtn || !panel) return;

    toggleBtn.addEventListener('click', function(){
      panel.hidden = !panel.hidden;
    });
    document.addEventListener('click', function(e){
      if (!panel.hidden && !panel.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
        panel.hidden = true;
      }
    });

    var incBtn = document.getElementById('a11yInc');
    var decBtn = document.getElementById('a11yDec');
    if (incBtn) incBtn.addEventListener('click', function(){
      state.scale = Math.min(150, state.scale + 12.5);
      apply(); save(state);
    });
    if (decBtn) decBtn.addEventListener('click', function(){
      state.scale = Math.max(87.5, state.scale - 12.5);
      apply(); save(state);
    });

    var contrastBtn = document.getElementById('a11yContrast');
    if (contrastBtn) contrastBtn.addEventListener('click', function(){
      state.contrast = !state.contrast; apply(); save(state);
    });
    var underlineBtn = document.getElementById('a11yUnderline');
    if (underlineBtn) underlineBtn.addEventListener('click', function(){
      state.underline = !state.underline; apply(); save(state);
    });
    var motionBtn = document.getElementById('a11yMotion');
    if (motionBtn) motionBtn.addEventListener('click', function(){
      state.noMotion = !state.noMotion; apply(); save(state);
    });
    var resetBtn = document.getElementById('a11yReset');
    if (resetBtn) resetBtn.addEventListener('click', function(){
      state = { scale: 100, contrast: false, underline: false, noMotion: false };
      apply(); save(state);
    });
  });
})();
