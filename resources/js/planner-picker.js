/*
 * Visual time picker for the Planner.
 *
 * Choose a span of time the way people talk about it (today, tomorrow, this
 * week, next week, this month, next month → a week → a day), then drag on
 * the day's timeline from start to end. Existing plans are shown as busy
 * bars. Values are written to the Livewire component: startsOn, startTime,
 * durationMinutes and selectedDays (several days at once).
 */
const pad = value => String(value).padStart(2, '0')
const toTime = minutes => `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`
const fromTime = value => {
    const match = /^(\d{1,2}):(\d{2})/.exec(value ?? '')
    return match ? Number(match[1]) * 60 + Number(match[2]) : null
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('plannerTimePicker', config => ({
        horizons: config.horizons,
        days: config.days,
        busy: config.busy ?? {},
        locale: config.locale || document.documentElement.lang || 'en',
        multi: false,
        slotMode: Boolean(config.slotMode),
        step: config.step ?? 15,
        horizon: 'today',
        week: 0,
        selected: [],
        start: 9 * 60,
        end: 10 * 60,
        drag: null,
        presets: [15, 30, 45, 60, 90, 120],
        // The visible part of the day (minutes). Phones show half a day at a
        // time and move it with the arrows instead of scrolling sideways.
        viewFrom: 0,
        viewSpan: 1440,

        init() {
            const startsOn = this.$wire.get('startsOn')
            const chosen = (this.$wire.get('selectedDays') ?? []).filter(date => this.days[date])
            this.selected = chosen.length ? chosen : (this.days[startsOn] ? [startsOn] : [config.today])
            this.multi = this.selected.length > 1
            const start = fromTime(this.$wire.get('startTime'))
            if (start !== null) this.start = start
            this.end = Math.min(1440, this.start + Math.max(this.step, Number(this.$wire.get('durationMinutes')) || 60))
            this.horizon = Object.keys(this.horizons).find(key => this.horizons[key].days.includes(this.selected[0])) ?? 'this_month'
            this.week = Math.max(0, this.weeks().findIndex(week => week.includes(this.selected[0])))
            this.viewSpan = window.matchMedia('(max-width: 639px)').matches ? 720 : 1440
            this.centerView()
        },

        weeks() {
            return this.horizons[this.horizon]?.weeks ?? []
        },
        horizonDays() {
            const weeks = this.weeks()
            return weeks.length > 1 ? (weeks[this.week] ?? weeks[0] ?? []) : (this.horizons[this.horizon]?.days ?? [])
        },
        chooseHorizon(key) {
            this.horizon = key
            this.week = 0
            const days = this.horizonDays()
            if (days.length && !days.some(date => this.selected.includes(date))) {
                this.selected = [days[0]]
                this.multi = false
                this.commit()
            }
        },
        chooseWeek(index) {
            this.week = index
            const days = this.horizonDays()
            if (days.length && !days.some(date => this.selected.includes(date))) {
                this.selected = [days[0]]
                this.commit()
            }
        },
        toggleDay(date) {
            if (this.multi) {
                this.selected = this.selected.includes(date)
                    ? (this.selected.length > 1 ? this.selected.filter(item => item !== date) : this.selected)
                    : [...this.selected, date].sort()
            } else {
                this.selected = [date]
            }
            this.commit()
        },
        toggleMulti() {
            this.multi = !this.multi
            if (!this.multi) this.selected = [this.selected[0]]
            this.commit()
        },

        // ── Timeline ─────────────────────────────────────────────────────
        minuteAt(event) {
            const rect = this.$refs.track.getBoundingClientRect()
            const ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width))
            return Math.round((this.viewFrom + ratio * this.viewSpan) / this.step) * this.step
        },
        centerView() {
            const middle = (this.start + this.end) / 2
            this.viewFrom = Math.min(1440 - this.viewSpan, Math.max(0, Math.round((middle - this.viewSpan / 2) / 60) * 60))
        },
        shiftView(direction) {
            const step = this.viewSpan / 2
            this.viewFrom = Math.min(1440 - this.viewSpan, Math.max(0, this.viewFrom + direction * step))
        },
        ensureVisible() {
            if (this.start < this.viewFrom || this.end > this.viewFrom + this.viewSpan) this.centerView()
        },
        zoom(span) {
            this.viewSpan = span
            this.centerView()
        },
        visibleHours() {
            const hours = []
            const every = this.viewSpan > 720 ? 1 : (this.viewSpan > 360 ? 1 : 0.5)
            for (let minute = Math.ceil(this.viewFrom / 60) * 60; minute < this.viewFrom + this.viewSpan; minute += every * 60) hours.push(minute)
            return hours
        },
        pointerDown(event) {
            if (event.button !== undefined && event.button > 0) return
            event.preventDefault()
            this.$refs.track.setPointerCapture?.(event.pointerId)
            const handle = event.target.closest('[data-handle]')?.dataset.handle
            const minute = this.minuteAt(event)
            if (handle) {
                this.drag = { mode: handle }
            } else if (minute > this.start && minute < this.end) {
                this.drag = { mode: 'move', offset: minute - this.start, length: this.end - this.start }
            } else {
                // A tap moves the current length there; dragging draws a new range.
                const length = this.end - this.start
                this.drag = { mode: 'new', anchor: minute, x: event.clientX, length, moved: false }
                this.start = Math.min(minute, 1440 - length)
                this.end = this.start + length
            }
        },
        pointerMove(event) {
            if (!this.drag) return
            const minute = this.minuteAt(event)
            if (this.drag.mode === 'new') {
                if (!this.drag.moved && Math.abs(event.clientX - this.drag.x) < 8) return
                this.drag.moved = true
                this.start = Math.min(this.drag.anchor, minute)
                this.end = Math.max(this.drag.anchor + this.step, minute)
            } else if (this.drag.mode === 'start') {
                this.start = Math.min(minute, this.end - this.step)
            } else if (this.drag.mode === 'end') {
                this.end = Math.max(minute, this.start + this.step)
            } else {
                this.start = Math.min(Math.max(0, minute - this.drag.offset), 1440 - this.drag.length)
                this.end = this.start + this.drag.length
            }
            this.start = Math.max(0, this.start)
            this.end = Math.min(1440, this.end)
        },
        pointerUp() {
            if (!this.drag) return
            this.drag = null
            this.commit()
        },
        nudge(edge, delta) {
            if (edge === 'start') this.start = Math.min(Math.max(0, this.start + delta), this.end - this.step)
            else this.end = Math.max(Math.min(1440, this.end + delta), this.start + this.step)
            this.ensureVisible()
            this.commit()
        },
        preset(minutes) {
            this.end = Math.min(1440, this.start + minutes)
            this.ensureVisible()
            this.commit()
        },

        commit() {
            this.$wire.set('selectedDays', [...this.selected], false)
            this.$wire.set('startsOn', this.selected[0], false)
            this.$wire.set('startTime', toTime(Math.min(this.start, 1439)), false)
            this.$wire.set('durationMinutes', Math.max(this.step, this.end - this.start), false)
        },
        addSlot() {
            this.commit()
            this.$wire.call('addSlotsFromPicker', [...this.selected], toTime(this.start), this.end - this.start)
        },

        // ── Display helpers ──────────────────────────────────────────────
        percent(minutes) {
            return `${(minutes / 1440) * 100}%`
        },
        span(from, to) {
            const a = Math.max(from, this.viewFrom)
            const b = Math.min(to, this.viewFrom + this.viewSpan)
            if (b <= a) return 'display:none'
            return `left:${((a - this.viewFrom) / this.viewSpan) * 100}%;width:${((b - a) / this.viewSpan) * 100}%`
        },
        at(minute) {
            return `left:${((minute - this.viewFrom) / this.viewSpan) * 100}%`
        },
        clock(minutes) {
            try {
                return new Intl.DateTimeFormat(this.locale, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: 'UTC' })
                    .format(new Date(Date.UTC(2000, 0, 1, 0, Math.min(minutes, 1439))))
                    + (minutes >= 1440 ? '+' : '')
            } catch (_) {
                return toTime(minutes)
            }
        },
        hourLabel(hour) {
            try {
                return new Intl.NumberFormat(this.locale).format(hour)
            } catch (_) {
                return String(hour)
            }
        },
        length() {
            const minutes = this.end - this.start
            const hours = Math.floor(minutes / 60)
            const rest = minutes % 60
            return [hours ? config.labels.hours.replace(':count', this.hourLabel(hours)) : null, rest ? config.labels.minutes.replace(':count', this.hourLabel(rest)) : null].filter(Boolean).join(' ')
        },
        busyFor(date) {
            return this.busy[date] ?? []
        },
        conflicts() {
            return this.selected.some(date => this.busyFor(date).some(([from, to]) => from < this.end && to > this.start))
        },
        summary() {
            const names = this.selected.map(date => `${this.days[date]?.weekday ?? ''} ${this.days[date]?.label ?? date}`)
            return `${names.join(config.labels.separator ?? ', ')} · ${this.clock(this.start)}–${this.clock(this.end)} (${this.length()})`
        },
    }))
})
