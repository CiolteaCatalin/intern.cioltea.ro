const CACHE_PREFIX='cioltea-hub-';
const VERSION='v23.2.1';

self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    try {
      const keys = await caches.keys();
      await Promise.all(keys.filter(k => k.startsWith(CACHE_PREFIX)).map(k => caches.delete(k)));
    } catch (_) {}
    await self.clients.claim();
  })());
});

// IMPORTANT: no fetch handler here.
// Pages, CSS, JS and gallery media always use the browser/network directly.
// This avoids stale/corrupted PWA caches on iOS while preserving Web Push.

self.addEventListener('push', event => {
  let data={};
  try { data=event.data?event.data.json():{}; }
  catch (_) { data={body:event.data?event.data.text():'Ai o actualizare în Cioltea Hub.'}; }

  const title=data.title||'Cioltea Hub';
  const options={
    body:data.body||'',
    icon:data.icon||'/assets/icons/icon-192.png?v=23.2.1',
    badge:data.badge||'/assets/icons/favicon-32.png?v=23.2.1',
    tag:data.tag||'cioltea-hub',
    data:{url:data.url||'/dashboard.php'},
    renotify:false
  };

  event.waitUntil(self.registration.showNotification(title,options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target=event.notification?.data?.url||'/dashboard.php';
  event.waitUntil((async()=>{
    const all=await clients.matchAll({type:'window',includeUncontrolled:true});
    for(const client of all){
      if('focus' in client){
        try{await client.navigate(target);}catch(_){}
        await client.focus();
        return;
      }
    }
    if(clients.openWindow) return clients.openWindow(target);
  })());
});
