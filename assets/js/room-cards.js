(function () {
  if (!window.matchMedia || !window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
    var cards = document.querySelectorAll('.room-card');
    cards.forEach(function (card) {
      card.addEventListener('click', function (e) {
        if (!card.classList.contains('is-revealed')) {
          e.preventDefault();
          cards.forEach(function (c) { if (c !== card) c.classList.remove('is-revealed'); });
          card.classList.add('is-revealed');
        }
      });
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.room-card')) {
        cards.forEach(function (c) { c.classList.remove('is-revealed'); });
      }
    });
  }
})();
