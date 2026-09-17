(function(){
  var thumbs = document.querySelectorAll('.thumb');
  var mainGallery = document.getElementById('galleryMain');
  thumbs.forEach(function(th){
    th.addEventListener('click', function(){
      thumbs.forEach(function(t){ t.classList.remove('is-active'); });
      th.classList.add('is-active');
      var html = th.getAttribute('data-scene-html');
      var current = mainGallery.querySelector('.scene');
      current.classList.add('leaving');
      setTimeout(function(){
        mainGallery.innerHTML = html + mainGallery.querySelector('.tag').outerHTML;
      }, 260);
    });
  });

  var thumbsTrack = document.getElementById('thumbs');
  var thumbsPrev = document.getElementById('thumbsPrev');
  var thumbsNext = document.getElementById('thumbsNext');
  if (thumbsTrack && thumbsPrev && thumbsNext) {
    var isRTL = getComputedStyle(thumbsTrack).direction === 'rtl';
    var rtlSign = isRTL ? -1 : 1;
    var scrollStep = function () { return Math.round(thumbsTrack.clientWidth * 0.8); };
    // Native `scrollBy({behavior:'smooth'})` silently no-ops on RTL negative scrollLeft in some
    // browsers, so jump directly instead of depending on smooth-scroll animation support.
    var updateArrows = function () {
      var atStart, atEnd;
      if (isRTL) {
        atStart = thumbsTrack.scrollLeft >= -1;
        atEnd = thumbsTrack.scrollLeft <= -(thumbsTrack.scrollWidth - thumbsTrack.clientWidth) + 1;
      } else {
        atStart = thumbsTrack.scrollLeft <= 0;
        atEnd = thumbsTrack.scrollLeft >= thumbsTrack.scrollWidth - thumbsTrack.clientWidth - 1;
      }
      thumbsPrev.hidden = atStart;
      thumbsNext.hidden = atEnd;
    };
    var jumpScroll = function (delta) { thumbsTrack.scrollLeft = thumbsTrack.scrollLeft + delta; updateArrows(); };
    thumbsPrev.addEventListener('click', function () { jumpScroll(-scrollStep() * rtlSign); });
    thumbsNext.addEventListener('click', function () { jumpScroll(scrollStep() * rtlSign); });
    thumbsTrack.addEventListener('scroll', updateArrows);
    window.addEventListener('resize', updateArrows);
    updateArrows();
  }

  var days = document.querySelectorAll('.day:not(.is-disabled)');
  var selectedDate = document.querySelector('.day.is-selected');
  selectedDate = selectedDate ? selectedDate.getAttribute('data-date') : null;

  days.forEach(function(d){
    d.addEventListener('click', function(){
      window.location = updateQuery('date', d.getAttribute('data-date'));
    });
  });

  document.querySelectorAll('.hour:not(.is-taken)').forEach(function(hEl){
    hEl.addEventListener('click', function(){
      window.location = updateQuery('start', hEl.getAttribute('data-h'));
    });
  });

  function updateQuery(key, value){
    var url = new URL(window.location.href);
    url.searchParams.set(key, value);
    return url.toString();
  }

  var minusBtn = document.getElementById('minus');
  var plusBtn = document.getElementById('plus');
  if (minusBtn && plusBtn) {
    minusBtn.addEventListener('click', function(){
      window.location = updateQuery('hours', Math.max(parseInt(minusBtn.dataset.min, 10), parseInt(minusBtn.dataset.current, 10) - 1));
    });
    plusBtn.addEventListener('click', function(){
      window.location = updateQuery('hours', Math.min(parseInt(plusBtn.dataset.max, 10), parseInt(plusBtn.dataset.current, 10) + 1));
    });
  }

  var checkinInput = document.getElementById('checkinInput');
  var checkoutInput = document.getElementById('checkoutInput');
  if (checkinInput && checkoutInput) {
    checkinInput.addEventListener('change', function(){
      var url = new URL(window.location.href);
      url.searchParams.set('checkin', checkinInput.value);
      url.searchParams.delete('checkout');
      window.location = url.toString();
    });
    checkoutInput.addEventListener('change', function(){
      window.location = updateQuery('checkout', checkoutInput.value);
    });
  }

  var form = document.getElementById('bookingForm');
  if (form) {
    form.addEventListener('submit', function(){
      var submitBtn = document.getElementById('submitBtn');
      submitBtn.disabled = true;
      submitBtn.textContent = submitBtn.getAttribute('data-loading-text') || submitBtn.textContent;
    });
  }
})();
