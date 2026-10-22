/*
 * "Notify me on this device": registers /sw.js, asks the browser for
 * permission and sends the subscription to the server. Used by the
 * x-app.device-notifications component (Alpine data: devicePush).
 */
const keyToBytes = base64 => {
    const padded = (base64 + '='.repeat((4 - base64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/')
    return Uint8Array.from(atob(padded), char => char.charCodeAt(0))
}
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || ''
const send = (method, url, body) => fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
    body: body ? JSON.stringify(body) : undefined,
})

document.addEventListener('alpine:init', () => {
    window.Alpine.data('devicePush', config => ({
        state: 'checking', // unsupported | blocked | off | on | working | ios
        message: '',
        async init() {
            if (!config.publicKey) { this.state = 'unavailable'; return }
            const ios = /iphone|ipad|ipod/i.test(navigator.userAgent)
            const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                this.state = ios && !standalone ? 'ios' : 'unsupported'
                return
            }
            if (Notification.permission === 'denied') { this.state = 'blocked'; return }
            try {
                const registration = await navigator.serviceWorker.getRegistration('/')
                const subscription = await registration?.pushManager.getSubscription()
                this.state = subscription ? 'on' : 'off'
                if (subscription) send('POST', config.storeUrl, subscription.toJSON()) // keep the server copy fresh
            } catch (_) { this.state = 'off' }
        },
        async enable() {
            this.state = 'working'; this.message = ''
            try {
                const permission = await Notification.requestPermission()
                if (permission !== 'granted') { this.state = permission === 'denied' ? 'blocked' : 'off'; return }
                const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' })
                await navigator.serviceWorker.ready
                const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(config.publicKey) })
                const response = await send('POST', config.storeUrl, subscription.toJSON())
                if (!response.ok) throw new Error(String(response.status))
                this.state = 'on'
                this.message = config.labels.enabled
            } catch (error) {
                this.state = 'off'
                this.message = config.labels.failed
            }
        },
        async disable() {
            this.state = 'working'
            try {
                const registration = await navigator.serviceWorker.getRegistration('/')
                const subscription = await registration?.pushManager.getSubscription()
                if (subscription) {
                    await send('DELETE', config.storeUrl, { endpoint: subscription.endpoint })
                    await subscription.unsubscribe()
                }
            } finally { this.state = 'off'; this.message = '' }
        },
        async test() {
            this.message = config.labels.sending
            const response = await send('POST', config.testUrl)
            const result = await response.json().catch(() => ({}))
            this.message = result.ok ? config.labels.test_sent : config.labels.test_failed
        },
    }))
})
