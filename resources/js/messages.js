/*
 * Messaging on hosts without WebSockets.
 *
 * messageThread: asks the server a tiny "anything new?" question and only then
 * refreshes the conversation. Fast while the person is looking and active,
 * slower when idle, paused while the tab is hidden; backs off after errors.
 * Also scrolls to the newest message, records voice notes with the browser's
 * MediaRecorder, shares a location only on an explicit tap, and gives every
 * send a client id so a repeated tap or retry never posts twice.
 */
const uuid = () => (window.crypto && window.crypto.randomUUID)
    ? window.crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = Math.random() * 16 | 0
        return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16)
    })

document.addEventListener('alpine:init', () => {
    // Everything that changes between renders (the thread version) is read from data-* attributes once, never
    // from the x-data expression: a changed x-data expression makes Alpine rebuild the component, which used to
    // drop a recording in progress whenever a message arrived.
    window.Alpine.data('messageThread', () => ({
        stateUrl: '',
        version: '',
        timer: null,
        failures: 0,
        lastActivity: Date.now(),
        recording: false,
        recorder: null,
        chunks: [],
        recordStarted: 0,
        recordSeconds: 0,
        recordTimer: null,
        locating: false,
        clientId: uuid(),

        init() {
            this.stateUrl = this.$el.dataset.stateUrl
            this.version = this.$el.dataset.version
            this.scrollToEnd()
            const mark = () => { this.lastActivity = Date.now() }
            ;['keydown', 'pointerdown', 'scroll', 'focus'].forEach(name => window.addEventListener(name, mark, { passive: true }))
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') { this.lastActivity = Date.now(); this.check() } else { this.stop() }
            })
            this.$wire.on('message-sent', () => { this.clientId = uuid(); this.$nextTick(() => this.scrollToEnd()) })
            this.$wire.on('thread-updated', ({ version }) => {
                const atEnd = this.nearEnd()
                this.version = version
                this.$nextTick(() => { if (atEnd) this.scrollToEnd() })
            })
            this.schedule()
        },

        destroy() { this.stop(); this.stopRecording(true) },

        interval() {
            if (this.failures > 0) return Math.min(60000, 4000 * 2 ** this.failures)
            return Date.now() - this.lastActivity < 120000 ? 3000 : 10000
        },
        schedule() {
            this.stop()
            if (document.visibilityState === 'hidden') return
            this.timer = setTimeout(() => this.check(), this.interval())
        },
        stop() { if (this.timer) { clearTimeout(this.timer); this.timer = null } },
        async check() {
            try {
                const response = await fetch(this.stateUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                if (!response.ok) throw new Error(String(response.status))
                const state = await response.json()
                this.failures = 0
                if (state.version !== this.version) {
                    this.version = state.version
                    await this.$wire.refreshThread()
                }
            } catch (error) {
                this.failures = Math.min(this.failures + 1, 4)
            }
            this.schedule()
        },

        nearEnd() {
            const box = this.$refs.scroller
            return !box || box.scrollHeight - box.scrollTop - box.clientHeight < 160
        },
        scrollToEnd() {
            const box = this.$refs.scroller
            if (box) box.scrollTop = box.scrollHeight
        },

        send() { this.$wire.send(this.clientId) },
        sendOnEnter(event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && window.matchMedia('(pointer: fine)').matches) {
                event.preventDefault()
                this.send()
            }
        },

        async startRecording() {
            if (!navigator.mediaDevices || !window.MediaRecorder) { this.$wire.voiceUnsupported(); return }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
                const type = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'].find(t => window.MediaRecorder.isTypeSupported(t)) || ''
                this.recorder = new window.MediaRecorder(stream, type ? { mimeType: type } : undefined)
                this.chunks = []
                this.recorder.ondataavailable = e => { if (e.data && e.data.size) this.chunks.push(e.data) }
                this.recorder.onstop = () => stream.getTracks().forEach(track => track.stop())
                this.recorder.start()
                this.recording = true
                this.recordStarted = Date.now()
                this.recordSeconds = 0
                this.recordTimer = setInterval(() => {
                    this.recordSeconds = Math.round((Date.now() - this.recordStarted) / 1000)
                    if (this.recordSeconds >= 300) this.finishRecording()
                }, 500)
            } catch (error) {
                this.$wire.voiceUnsupported()
            }
        },
        stopRecording(discard = false) {
            if (this.recordTimer) { clearInterval(this.recordTimer); this.recordTimer = null }
            if (this.recorder && this.recorder.state !== 'inactive') {
                if (discard) this.recorder.onstop = () => this.recorder.stream.getTracks().forEach(t => t.stop())
                this.recorder.stop()
            }
            this.recording = false
        },
        finishRecording() {
            if (!this.recorder) return
            const recorder = this.recorder
            const seconds = Math.max(1, Math.round((Date.now() - this.recordStarted) / 1000))
            const stop = recorder.onstop
            recorder.onstop = event => {
                if (stop) stop(event)
                const type = (recorder.mimeType || 'audio/webm').split(';')[0]
                const extension = type.includes('mp4') ? 'm4a' : (type.includes('ogg') ? 'ogg' : 'webm')
                const file = new File(this.chunks, `voice-${Date.now()}.${extension}`, { type })
                this.$wire.upload(
                    'voice',
                    file,
                    () => Promise.resolve(this.$wire.sendVoice(seconds, uuid())).catch(() => this.$wire.voiceFailed()),
                    () => this.$wire.voiceFailed(),
                )
            }
            this.stopRecording()
        },

        shareLocation() {
            if (!navigator.geolocation) { this.$wire.locationUnavailable(); return }
            this.locating = true
            navigator.geolocation.getCurrentPosition(
                position => { this.locating = false; this.$wire.sendLocation(position.coords.latitude, position.coords.longitude, uuid()) },
                () => { this.locating = false; this.$wire.locationUnavailable() },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
            )
        },
    }))

    // The Deal workspace lenses (Conversation | Agreement | Work | Money). The choice is remembered per Deal in the
    // browser; a Livewire re-render never changes it because nothing about it lives in the server HTML.
    window.Alpine.data('dealTabs', () => ({
        tab: 'conversation',
        init() {
            const key = 'deal-tab-' + this.$el.dataset.key
            let saved = null
            try { saved = sessionStorage.getItem(key) } catch (error) { /* storage can be blocked */ }
            this.tab = saved || this.$el.dataset.start || 'conversation'
            this.$watch('tab', value => { try { sessionStorage.setItem(key, value) } catch (error) { /* ignore */ } })
        },
    }))

    window.Alpine.data('messageBadge', config => ({
        url: config.url,
        count: config.count,
        timer: null,
        init() {
            const tick = async () => {
                if (document.visibilityState === 'visible') {
                    try {
                        const response = await fetch(this.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        if (response.ok) this.count = (await response.json()).unread
                    } catch (error) { /* try again later */ }
                }
                this.timer = setTimeout(tick, 30000)
            }
            this.timer = setTimeout(tick, 1500)
        },
        destroy() { clearTimeout(this.timer) },
    }))
})
