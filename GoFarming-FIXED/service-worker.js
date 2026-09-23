const CACHE = 'gofarming-v5';

const ARQUIVOS = [
  'FRONT/dashboard.html',
  'FRONT/scan.html',
  'FRONT/planta.html',
  'FRONT/login.html',
  'FRONT/cadastro.html',
  'FRONT/css/style.css',
  'FRONT/js/api.js',
  'FRONT/js/dashboard.js',
  'FRONT/js/scan.js',
  'FRONT/js/planta.js',
  'FRONT/js/auth.js',
  'FRONT/js/notificacoes.js',
  'FRONT/css/Logo.png'
].map(p => new URL(p, self.registration.scope).href);

self.addEventListener('install', e => {
  e.waitUntil((async () => {
    const cache = await caches.open(CACHE);
    await Promise.allSettled(ARQUIVOS.map(async a => {
      const r = await fetch(a, { cache: 'reload' });
      if (r.ok) await cache.put(a, r);
    }));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', e => {
  e.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== location.origin) return;          // ignora CDN/externos
  if (!url.protocol.startsWith('http')) return;        // ignora chrome-extension etc.

  const isApi = url.pathname.includes('/PUBLIC/') || url.pathname.endsWith('.php');
  const isHtml = req.mode === 'navigate' || url.pathname.endsWith('.html');

  // API e páginas: rede primeiro, cache só como reserva
  if (isApi || isHtml) {
    e.respondWith((async () => {
      try {
        const res = await fetch(req);
        if (!isApi && res.ok) (await caches.open(CACHE)).put(req, res.clone());
        return res;
      } catch {
        return (await caches.match(req)) || Response.error();
      }
    })());
    return;
  }

  // CSS/JS/imagens: cache primeiro
  e.respondWith((async () => {
    const cached = await caches.match(req);
    if (cached) return cached;
    const res = await fetch(req);
    if (res.ok) (await caches.open(CACHE)).put(req, res.clone());
    return res;
  })());
});


/* ===================== Notificações ===================== */

// Clique na notificação: foca uma aba já aberta do app em vez de abrir
// uma nova a cada toque, e navega direto para a planta quando houver ID.
self.addEventListener('notificationclick', e => {
  e.notification.close();

  const idPlanta = e.notification.data && e.notification.data.id_planta;
  const destino = new URL(
    idPlanta ? `FRONT/planta.html?id=${idPlanta}` : 'FRONT/dashboard.html',
    self.registration.scope
  ).href;

  e.waitUntil((async () => {
    const clientes = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

    for (const c of clientes) {
      if (c.url.startsWith(self.registration.scope) && 'focus' in c) {
        await c.focus();
        if ('navigate' in c) await c.navigate(destino);
        return;
      }
    }

    await self.clients.openWindow(destino);
  })());
});

// Push real (app fechado). Só dispara se o servidor enviar Web Push com VAPID —
// veja LEIA-ME-CORRECOES.md. Sem isso, o handler simplesmente nunca é chamado.
self.addEventListener('push', e => {
  let dados = { titulo: 'GoFarming', mensagem: 'Suas plantas precisam de atenção.' };

  try {
    if (e.data) dados = Object.assign(dados, e.data.json());
  } catch (err) {
    if (e.data) dados.mensagem = e.data.text();
  }

  e.waitUntil(self.registration.showNotification(dados.titulo, {
    body: dados.mensagem,
    icon: new URL('FRONT/css/Logo.png', self.registration.scope).href,
    badge: new URL('FRONT/css/Logo.png', self.registration.scope).href,
    tag: 'gofarming-push-' + (dados.id || Date.now()),
    data: { id_planta: dados.id_planta || null }
  }));
});
