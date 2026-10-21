/* Service worker: shows notifications sent by the server even when the site is closed. */
self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()))

self.addEventListener('push', event => {
    let data = {}
    try { data = event.data ? event.data.json() : {} } catch (_) { data = { title: event.data?.text() } }
    const title = data.title || 'Everything'
    event.waitUntil(self.registration.showNotification(title, {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-96.png',
        tag: data.tag || undefined,
        renotify: Boolean(data.tag),
        requireInteraction: Boolean(data.important),
        data: { url: data.url || '/' },
        dir: 'auto',
    }))
})

self.addEventListener('notificationclick', event => {
    event.notification.close()
    const url = new URL(event.notification.data?.url || '/', self.location.origin).href
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
        for (const client of windows) {
            if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                await client.focus()
                if ('navigate' in client) return client.navigate(url)
                return
            }
        }
        return self.clients.openWindow(url)
    })())
})
