(function(){
  var cfg = document.getElementById('pushConfig');
  var btn = document.getElementById('pushToggle');
  if (!cfg || !btn) return;
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

  var site = cfg.getAttribute('data-site');
  var vapidKey = cfg.getAttribute('data-vapid-key');
  var csrf = cfg.getAttribute('data-csrf');
  var baseUrl = cfg.getAttribute('data-base-url');

  function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - base64String.length % 4) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = window.atob(base64);
    var outputArray = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
  }

  function postJson(path, body) {
    return fetch(baseUrl + '/admin/' + path + '?site=' + encodeURIComponent(site), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(body)
    });
  }

  function render(subscribed) {
    btn.hidden = false;
    btn.textContent = subscribed ? 'כיבוי התראות' : 'הפעלת התראות';
    btn.classList.toggle('ok', subscribed);
  }

  var registration = null;
  navigator.serviceWorker.register('sw.js').then(function(reg){
    registration = reg;
    return reg.pushManager.getSubscription();
  }).then(function(sub){
    render(!!sub);
  }).catch(function(){ /* service worker unavailable — leave button hidden */ });

  btn.addEventListener('click', function(){
    if (!registration) return;
    registration.pushManager.getSubscription().then(function(existing){
      if (existing) {
        var endpoint = existing.endpoint;
        return existing.unsubscribe().then(function(){
          return postJson('push_unsubscribe.php', { endpoint: endpoint });
        }).then(function(){ render(false); });
      }
      return Notification.requestPermission().then(function(perm){
        if (perm !== 'granted') return;
        return registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(vapidKey)
        }).then(function(sub){
          return postJson('push_subscribe.php', sub.toJSON()).then(function(){ render(true); });
        });
      });
    });
  });
})();
