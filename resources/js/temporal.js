const SUPPORTED_CALENDARS = new Set(['gregory', 'persian', 'islamic-umalqura']);

function calendarOrFallback(calendar) {
    return SUPPORTED_CALENDARS.has(calendar) ? calendar : 'gregory';
}

function isoToDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? '');

    if (!match) {
        return null;
    }

    return new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
}

function dateToIso(date) {
    return [
        date.getUTCFullYear(),
        String(date.getUTCMonth() + 1).padStart(2, '0'),
        String(date.getUTCDate()).padStart(2, '0'),
    ].join('-');
}

function addDays(date, days) {
    const next = new Date(date.getTime());
    next.setUTCDate(next.getUTCDate() + days);

    return next;
}

function logicalParts(date, locale, calendar) {
    const parts = new Intl.DateTimeFormat(locale, {
        calendar,
        numberingSystem: 'latn',
        timeZone: 'UTC',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
    }).formatToParts(date);

    return Object.fromEntries(parts.filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
}

function logicalDay(date, locale, calendar) {
    return Number(logicalParts(date, locale, calendar).day);
}

function monthKey(date, locale, calendar) {
    const parts = logicalParts(date, locale, calendar);

    return [
        parts.era ?? '',
        parts.year ?? '',
        parts.relatedYear ?? '',
        parts.yearName ?? '',
        parts.month ?? '',
    ].join('|');
}

function firstOfCalendarMonth(date, locale, calendar) {
    const day = logicalDay(date, locale, calendar);

    return Number.isFinite(day) && day > 0 ? addDays(date, -(day - 1)) : date;
}

function nextCalendarMonth(first, locale, calendar) {
    const current = monthKey(first, locale, calendar);

    for (let offset = 25; offset <= 40; offset++) {
        const candidate = addDays(first, offset);

        if (logicalDay(candidate, locale, calendar) === 1 && monthKey(candidate, locale, calendar) !== current) {
            return candidate;
        }
    }

    return addDays(first, 31);
}

function previousCalendarMonth(first, locale, calendar) {
    return firstOfCalendarMonth(addDays(first, -1), locale, calendar);
}

function todayIso(timezone) {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            calendar: 'gregory',
            numberingSystem: 'latn',
            timeZone: timezone || 'UTC',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        })
            .formatToParts(new Date())
            .filter((part) => part.type !== 'literal')
            .map((part) => [part.type, part.value]),
    );

    return `${parts.year}-${parts.month}-${parts.day}`;
}

function localeWeekday(locale, dayNumber) {
    const monday = new Date(Date.UTC(2024, 0, 1));
    const date = addDays(monday, dayNumber - 1);

    return new Intl.DateTimeFormat(locale, {
        timeZone: 'UTC',
        weekday: 'short',
    }).format(date);
}

function dateFormatOptions(dateFormat = 'long') {
    if (dateFormat === 'numeric') {
        return {year: 'numeric', month: '2-digit', day: '2-digit'};
    }

    if (dateFormat === 'medium') {
        return {year: 'numeric', month: 'short', day: 'numeric'};
    }

    return {year: 'numeric', month: 'long', day: 'numeric'};
}

function timeFormatOptions(timeFormat = '24h', seconds = false) {
    return {
        hour: 'numeric',
        minute: '2-digit',
        second: seconds ? '2-digit' : undefined,
        hour12: timeFormat === '12h',
    };
}

function localizedDate(value, locale, calendar, dateFormat = 'long') {
    const date = isoToDate(value);

    if (!date) {
        return '';
    }

    return new Intl.DateTimeFormat(locale, {
        calendar,
        timeZone: 'UTC',
        ...dateFormatOptions(dateFormat),
    }).format(date);
}

function localizedDateTime(value, locale, calendar, timezone, seconds = false, dateFormat = 'long', timeFormat = '24h') {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value ?? '';
    }

    return new Intl.DateTimeFormat(locale, {
        calendar,
        timeZone: timezone || 'UTC',
        ...dateFormatOptions(dateFormat),
        ...timeFormatOptions(timeFormat, seconds),
    }).format(date);
}

function localizedInstantTime(value, locale, timezone, timeFormat = '24h') {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value ?? '';
    }

    return new Intl.DateTimeFormat(locale, {
        timeZone: timezone || 'UTC',
        ...timeFormatOptions(timeFormat),
    }).format(date);
}

function gregorianEquivalentDate(value, locale, dateFormat = 'long') {
    return localizedDate(value, locale, 'gregory', dateFormat);
}

function gregorianEquivalentDateTime(value, locale, timezone, seconds = false, dateFormat = 'long', timeFormat = '24h') {
    return localizedDateTime(value, locale, 'gregory', timezone, seconds, dateFormat, timeFormat);
}

function localizedTime(value, locale, timeFormat = '24h') {
    const match = /^(\d{2}):(\d{2})/.exec(value ?? '');

    if (!match) {
        return value ?? '';
    }

    const date = new Date(Date.UTC(2000, 0, 1, Number(match[1]), Number(match[2])));

    return new Intl.DateTimeFormat(locale, {
        timeZone: 'UTC',
        ...timeFormatOptions(timeFormat),
    }).format(date);
}

class IetDatePicker extends HTMLElement {
    connectedCallback() {
        if (this.initialized) {
            return;
        }

        this.initialized = true;
        this.locale = this.dataset.locale || document.documentElement.lang || 'en';
        this.calendar = calendarOrFallback(this.dataset.calendar || 'gregory');
        this.timezone = this.dataset.timezone || 'UTC';
        this.dateFormat = this.dataset.dateFormat || 'long';
        this.showEquivalent = this.dataset.showEquivalent !== 'false';
        this.firstDay = Number(this.dataset.firstDay || 1);
        this.input = this.querySelector('[data-date-value]');
        this.trigger = this.querySelector('[data-date-trigger]');
        this.display = this.querySelector('[data-date-display]');
        this.equivalentDisplay = this.querySelector('[data-date-equivalent]');
        this.popoverElement = this.querySelector('[data-date-popover]');
        this.titleElement = this.querySelector('[data-date-title]');
        this.weekdays = this.querySelector('[data-date-weekdays]');
        this.days = this.querySelector('[data-date-days]');

        if (!this.input || !this.trigger || !this.popoverElement) {
            return;
        }

        this.trigger.addEventListener('click', () => this.toggle());
        this.querySelector('[data-date-prev]')?.addEventListener('click', () => this.moveMonth(-1));
        this.querySelector('[data-date-next]')?.addEventListener('click', () => this.moveMonth(1));
        this.querySelector('[data-date-today]')?.addEventListener('click', () => this.select(todayIso(this.timezone)));
        this.querySelector('[data-date-clear]')?.addEventListener('click', () => this.select(''));
        this.input.addEventListener('change', () => this.refreshFromInput());
        this.addEventListener('keydown', (event) => this.handleKeydown(event));

        this.onDocumentClick = (event) => {
            if (!this.contains(event.target)) {
                this.close();
            }
        };

        document.addEventListener('click', this.onDocumentClick);
        this.refreshFromInput();
    }

    disconnectedCallback() {
        if (this.onDocumentClick) {
            document.removeEventListener('click', this.onDocumentClick);
        }
    }

    refreshFromInput() {
        const selected = isoToDate(this.input?.value ?? '');
        const fallback = isoToDate(todayIso(this.timezone));
        const focus = selected ?? fallback;

        if (!focus) {
            return;
        }

        this.focusIso = dateToIso(focus);
        this.monthStart = firstOfCalendarMonth(focus, this.locale, this.calendar);
        this.render();
    }

    toggle() {
        if (this.popoverElement.hidden) {
            this.open();
        } else {
            this.close();
        }
    }

    open() {
        this.popoverElement.hidden = false;
        this.trigger.setAttribute('aria-expanded', 'true');
        this.render();
    }

    close() {
        this.popoverElement.hidden = true;
        this.trigger.setAttribute('aria-expanded', 'false');
    }

    moveMonth(direction) {
        this.monthStart = direction > 0
            ? nextCalendarMonth(this.monthStart, this.locale, this.calendar)
            : previousCalendarMonth(this.monthStart, this.locale, this.calendar);
        this.focusIso = dateToIso(this.monthStart);
        this.render();
    }

    select(value) {
        this.input.value = value;
        this.input.dispatchEvent(new Event('input', {bubbles: true}));
        this.input.dispatchEvent(new Event('change', {bubbles: true}));
        this.close();
        this.refreshFromInput();
        this.trigger.focus();
    }

    handleKeydown(event) {
        if (this.popoverElement.hidden) {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                this.open();
            }

            return;
        }

        const deltas = {
            ArrowLeft: document.documentElement.dir === 'rtl' ? 1 : -1,
            ArrowRight: document.documentElement.dir === 'rtl' ? -1 : 1,
            ArrowUp: -7,
            ArrowDown: 7,
        };

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            this.trigger.focus();

            return;
        }

        if (!(event.key in deltas)) {
            return;
        }

        event.preventDefault();
        const current = isoToDate(this.focusIso) ?? this.monthStart;
        const next = addDays(current, deltas[event.key]);
        this.focusIso = dateToIso(next);
        this.monthStart = firstOfCalendarMonth(next, this.locale, this.calendar);
        this.render();

        requestAnimationFrame(() => {
            this.querySelector(`[data-date-iso="${this.focusIso}"]`)?.focus();
        });
    }

    render() {
        this.renderDisplay();
        this.renderWeekdays();
        this.renderDays();
    }

    renderDisplay() {
        const value = this.input?.value ?? '';
        this.display.textContent = value
            ? localizedDate(value, this.locale, this.calendar, this.dateFormat)
            : (this.dataset.emptyLabel || '');

        if (this.equivalentDisplay) {
            const showEquivalent = this.showEquivalent && Boolean(value) && this.calendar !== 'gregory';
            this.equivalentDisplay.hidden = !showEquivalent;
            this.equivalentDisplay.textContent = showEquivalent
                ? `${this.dataset.equivalentLabel || 'Gregorian'} · ${gregorianEquivalentDate(value, this.locale, this.dateFormat)}`
                : '';
        }
    }

    renderWeekdays() {
        this.weekdays.replaceChildren();

        for (let offset = 0; offset < 7; offset++) {
            const day = ((this.firstDay - 1 + offset) % 7) + 1;
            const node = document.createElement('span');
            node.textContent = localeWeekday(this.locale, day);
            node.className = 'py-1';
            this.weekdays.append(node);
        }
    }

    renderDays() {
        const titleFormatter = new Intl.DateTimeFormat(this.locale, {
            calendar: this.calendar,
            timeZone: 'UTC',
            year: 'numeric',
            month: 'long',
        });
        const dayFormatter = new Intl.DateTimeFormat(this.locale, {
            calendar: this.calendar,
            timeZone: 'UTC',
            day: 'numeric',
        });
        const ariaFormatter = new Intl.DateTimeFormat(this.locale, {
            calendar: this.calendar,
            timeZone: 'UTC',
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });

        this.titleElement.textContent = titleFormatter.format(this.monthStart);
        this.days.replaceChildren();

        const jsDay = this.monthStart.getUTCDay() === 0 ? 7 : this.monthStart.getUTCDay();
        const offset = (jsDay - this.firstDay + 7) % 7;
        const gridStart = addDays(this.monthStart, -offset);
        const currentMonth = monthKey(this.monthStart, this.locale, this.calendar);
        const selectedIso = this.input?.value ?? '';
        const today = todayIso(this.timezone);

        for (let index = 0; index < 42; index++) {
            const date = addDays(gridStart, index);
            const iso = dateToIso(date);
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.dateIso = iso;
            button.textContent = dayFormatter.format(date);
            button.setAttribute('aria-label', ariaFormatter.format(date));
            button.setAttribute('aria-selected', iso === selectedIso ? 'true' : 'false');
            button.tabIndex = iso === this.focusIso ? 0 : -1;

            const outside = monthKey(date, this.locale, this.calendar) !== currentMonth;
            const selected = iso === selectedIso;
            const isToday = iso === today;

            button.className = [
                'aspect-square rounded-lg text-sm transition',
                'focus:outline-none focus:ring-2 focus:ring-zinc-400',
                outside ? 'text-zinc-300 dark:text-zinc-600' : 'text-zinc-800 dark:text-zinc-100',
                selected ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800',
                isToday && !selected ? 'ring-1 ring-zinc-400' : '',
            ].join(' ');

            button.addEventListener('click', () => this.select(iso));
            this.days.append(button);
        }
    }
}

if (!customElements.get('iet-date-picker')) {
    customElements.define('iet-date-picker', IetDatePicker);
}

class IetDateTimePicker extends HTMLElement {
    connectedCallback() {
        if (this.initialized) {
            return;
        }

        this.initialized = true;
        this.valueInput = this.querySelector('[data-datetime-value]');
        this.dateInput = this.querySelector('[data-datetime-date]');
        this.timeInput = this.querySelector('[data-datetime-time]');

        if (!this.valueInput || !this.dateInput || !this.timeInput) {
            return;
        }

        this.onPartChange = () => this.syncToValue();
        this.dateInput.addEventListener('input', this.onPartChange);
        this.dateInput.addEventListener('change', this.onPartChange);
        this.timeInput.addEventListener('input', this.onPartChange);
        this.timeInput.addEventListener('change', this.onPartChange);
        this.valueInput.addEventListener('change', () => this.syncFromValue());

        this.syncFromValue();
    }

    disconnectedCallback() {
        if (!this.onPartChange) {
            return;
        }

        this.dateInput?.removeEventListener('input', this.onPartChange);
        this.dateInput?.removeEventListener('change', this.onPartChange);
        this.timeInput?.removeEventListener('input', this.onPartChange);
        this.timeInput?.removeEventListener('change', this.onPartChange);
    }

    syncFromValue() {
        const match = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/.exec(this.valueInput?.value ?? '');

        if (!match) {
            return;
        }

        this.dateInput.value = match[1];
        this.timeInput.value = match[2];
        this.dateInput.dispatchEvent(new Event('change', {bubbles: true}));
    }

    syncToValue() {
        const date = this.dateInput?.value ?? '';
        const time = this.timeInput?.value ?? '';

        this.valueInput.value = date && time ? `${date}T${time}` : '';
        this.valueInput.dispatchEvent(new Event('input', {bubbles: true}));
        this.valueInput.dispatchEvent(new Event('change', {bubbles: true}));
    }
}

if (!customElements.get('iet-datetime-picker')) {
    customElements.define('iet-datetime-picker', IetDateTimePicker);
}

class IetAmbientStatus extends HTMLElement {
    connectedCallback() {
        if (this.initialized) {
            return;
        }

        this.initialized = true;
        this.lifecycle = new AbortController();
        const { signal } = this.lifecycle;
        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.visible = true;
        this.messageIndex = 0;
        this.clock = this.querySelector('[data-ambient-clock]');
        this.equivalent = this.querySelector('[data-ambient-equivalent]');
        this.message = this.querySelector('[data-ambient-message]');
        this.messages = [...this.querySelectorAll('[data-ambient-source]')]
            .map((element) => element.textContent?.trim())
            .filter(Boolean);
        this.renderClock();
        this.renderMessage();
        this.clockTimer = window.setInterval(() => {
            if (!document.hidden) this.renderClock();
            if (this.frame == null && !this.motion.matches) this.updateAnimation();
        }, 1000);
        this.messageTimer = window.setInterval(() => {
            if (document.hidden || this.paused) return;
            this.messageIndex = (this.messageIndex + 1) % Math.max(this.messages.length, 1);
            this.renderMessage();
        }, 12000);
        this.querySelector('[data-ambient-pause]')?.addEventListener('click', () => {
            this.paused = !this.paused;
            this.renderPauseControl();
        }, { signal });
        this.renderPauseControl();
        const fullscreen = this.querySelector('[data-ambient-fullscreen]');
        if (fullscreen && document.fullscreenEnabled) {
            fullscreen.hidden = false;
            fullscreen.addEventListener('click', async () => {
                try {
                    if (document.fullscreenElement) await document.exitFullscreen();
                    else {
                        await document.documentElement.requestFullscreen();
                        window.scrollTo(0, 0);
                    }
                } catch {
                    this.querySelector('[data-clock-feedback]').textContent = this.dataset.fullscreenUnavailable;
                }
            }, { signal });
            document.addEventListener('fullscreenchange', () => {
                fullscreen.textContent = document.fullscreenElement ? this.dataset.exitFullscreenLabel : this.dataset.fullscreenLabel;
            }, { signal });
        }
        this.hourHand = this.querySelector('[data-clock-hour]');
        this.minuteHand = this.querySelector('[data-clock-minute]');
        this.secondHand = this.querySelector('[data-clock-second]');
        this.renderHands();
        this.motion.addEventListener('change', () => this.updateAnimation(), { signal });
        document.addEventListener('visibilitychange', () => {
            this.renderClock();
            this.updateAnimation();
        }, { signal });
        if (this.hourHand) {
            this.intersection = new IntersectionObserver(([entry]) => {
                this.visible = entry.isIntersecting;
                this.updateAnimation();
            });
            this.intersection.observe(this);
        }
        const bar = this.closest('[data-ambient-bar]');
        if (bar) {
            this.resize = new ResizeObserver(() => {
                document.documentElement.style.setProperty('--ambient-bar-height', `${bar.getBoundingClientRect().height}px`);
            });
            this.resize.observe(bar);
        }
        this.updateAnimation();
    }

    disconnectedCallback() {
        window.clearInterval(this.clockTimer);
        window.clearInterval(this.messageTimer);
        window.cancelAnimationFrame(this.frame);
        this.frame = null;
        this.lifecycle?.abort();
        this.intersection?.disconnect();
        this.resize?.disconnect();
        this.initialized = false;
    }

    renderPauseControl() {
        const button = this.querySelector('[data-ambient-pause]');
        if (!button) return;
        button.hidden = false;
        button.textContent = this.paused ? this.dataset.resumeLabel : this.dataset.pauseLabel;
        button.setAttribute('aria-pressed', String(Boolean(this.paused)));
    }

    updateAnimation() {
        window.cancelAnimationFrame(this.frame);
        this.frame = null;
        if (!this.hourHand || !this.isConnected || document.hidden || !this.visible || this.motion.matches) return;
        const animate = () => {
            if (!this.isConnected || document.hidden || !this.visible || this.motion.matches) {
                this.frame = null;
                return;
            }
            this.renderHands();
            this.frame = window.requestAnimationFrame(animate);
        };
        this.frame = window.requestAnimationFrame(animate);
    }

    renderHands(now = new Date()) {
        if (!this.hourHand) return;
        const second = Math.floor(now.getTime() / 1000);
        if (this.handSecond !== second || this.handTimezone !== this.dataset.timezone) {
            this.handTimezone = this.dataset.timezone;
            this.handSecond = second;
            this.handParts = Object.fromEntries(new Intl.DateTimeFormat('en-US', {
                timeZone: this.dataset.timezone || 'UTC', numberingSystem: 'latn', hourCycle: 'h23',
                hour: 'numeric', minute: 'numeric', second: 'numeric',
            }).formatToParts(now).filter(part => part.type !== 'literal').map(part => [part.type, Number(part.value)]));
        }
        const seconds = this.handParts.second + (this.motion.matches ? 0 : now.getMilliseconds() / 1000);
        const minutes = this.handParts.minute + seconds / 60;
        const hours = this.handParts.hour % 12 + minutes / 60;
        this.hourHand.setAttribute('transform', `rotate(${hours * 30} 120 120)`);
        this.minuteHand.setAttribute('transform', `rotate(${minutes * 6} 120 120)`);
        this.secondHand.setAttribute('transform', `rotate(${seconds * 6} 120 120)`);
    }

    renderClock() {
        if (!this.clock) {
            return;
        }

        const locale = this.dataset.locale || 'en';
        const calendar = calendarOrFallback(this.dataset.calendar || 'gregory');
        const timezone = this.dataset.timezone || 'UTC';
        const dateFormat = this.dataset.dateFormat || 'long';
        const timeFormat = this.dataset.timeFormat || '24h';
        const showEquivalentPreference = this.dataset.showEquivalent !== 'false';
        const now = new Date();

        this.clock.textContent = new Intl.DateTimeFormat(locale, {
            calendar,
            timeZone: timezone,
            ...dateFormatOptions(dateFormat),
            ...timeFormatOptions(timeFormat, true),
        }).format(now);
        this.clock.dateTime = now.toISOString();
        this.renderHands(now);

        if (this.equivalent) {
            const showEquivalent = showEquivalentPreference && calendar !== 'gregory';
            this.equivalent.hidden = !showEquivalent;
            this.equivalent.textContent = showEquivalent
                ? `${this.dataset.equivalentLabel || 'Gregorian'} · ${new Intl.DateTimeFormat(locale, {
                    calendar: 'gregory',
                    timeZone: timezone,
                    ...dateFormatOptions(dateFormat),
                    ...timeFormatOptions(timeFormat),
                }).format(now)}`
                : '';
            if (showEquivalent && this.dataset.equivalentMode === 'hover') {
                this.equivalent.classList.add('ambient-equivalent-popover');
                this.equivalent.setAttribute('role', 'tooltip');
                this.equivalent.id ||= `ambient-equivalent-${crypto.randomUUID()}`;
                this.equivalent.parentElement.setAttribute('aria-describedby', this.equivalent.id);
            }
        }
    }

    renderMessage() {
        if (this.message) {
            this.message.textContent = this.messages[this.messageIndex] || '';
        }
    }
}

if (!customElements.get('iet-ambient-status')) {
    customElements.define('iet-ambient-status', IetAmbientStatus);
}

function renderProfileTemporal(root = document) {
    root.querySelectorAll?.('[data-profile-date]').forEach((element) => {
        const value = element.dataset.profileDate;
        const locale = element.dataset.locale || document.documentElement.lang || 'en';
        const calendar = calendarOrFallback(element.dataset.calendar || 'gregory');
        const dateFormat = element.dataset.dateFormat || 'long';
        const primary = element.querySelector('[data-temporal-primary]');
        const equivalent = element.querySelector('[data-temporal-equivalent]');

        if (primary) {
            primary.textContent = localizedDate(value, locale, calendar, dateFormat);
        }

        if (equivalent) {
            const show = element.dataset.showEquivalent === 'true' && calendar !== 'gregory';
            equivalent.hidden = !show;
            equivalent.textContent = show
                ? `${element.dataset.equivalentLabel || 'Gregorian'} · ${gregorianEquivalentDate(value, locale, dateFormat)}`
                : '';
        }
    });

    root.querySelectorAll?.('[data-profile-datetime]').forEach((element) => {
        const value = element.dataset.profileDatetime;
        const locale = element.dataset.locale || document.documentElement.lang || 'en';
        const calendar = calendarOrFallback(element.dataset.calendar || 'gregory');
        const timezone = element.dataset.timezone || 'UTC';
        const dateFormat = element.dataset.dateFormat || 'long';
        const timeFormat = element.dataset.timeFormat || '24h';
        const seconds = element.dataset.seconds === 'true';
        const primary = element.querySelector('[data-temporal-primary]');
        const equivalent = element.querySelector('[data-temporal-equivalent]');

        if (primary) {
            primary.textContent = localizedDateTime(value, locale, calendar, timezone, seconds, dateFormat, timeFormat);
        }

        if (equivalent) {
            const show = element.dataset.showEquivalent === 'true' && calendar !== 'gregory';
            equivalent.hidden = !show;
            equivalent.textContent = show
                ? `${element.dataset.equivalentLabel || 'Gregorian'} · ${gregorianEquivalentDateTime(value, locale, timezone, seconds, dateFormat, timeFormat)}`
                : '';
        }
    });

    root.querySelectorAll?.('[data-profile-time]').forEach((element) => {
        element.textContent = localizedInstantTime(
            element.dataset.profileTime,
            element.dataset.locale || document.documentElement.lang || 'en',
            element.dataset.timezone || 'UTC',
            element.dataset.timeFormat || '24h',
        );
    });
}

function localizeTemporal(root = document) {
    renderProfileTemporal(root);

    root.querySelectorAll?.('[data-localized-date]').forEach((element) => {
        const value = element.dataset.localizedDate;

        if (!value) {
            return;
        }

        element.textContent = localizedDate(
            value,
            element.dataset.locale || document.documentElement.lang || 'en',
            calendarOrFallback(element.dataset.calendar || 'gregory'),
            element.dataset.dateFormat || 'long',
        );
    });

    root.querySelectorAll?.('[data-localized-time]').forEach((element) => {
        element.textContent = localizedTime(
            element.dataset.localizedTime,
            element.dataset.locale || document.documentElement.lang || 'en',
            element.dataset.timeFormat || '24h',
        );
    });

    root.querySelectorAll?.('[data-temporal-preview]').forEach((element) => {
        const target = element.querySelector('[data-temporal-preview-value]');

        if (!target) {
            return;
        }

        const locale = element.dataset.locale || document.documentElement.lang || 'en';
        const calendar = calendarOrFallback(element.dataset.calendar || 'gregory');
        const timezone = element.dataset.timezone || 'UTC';
        const dateFormat = element.dataset.dateFormat || 'long';
        const timeFormat = element.dataset.timeFormat || '24h';
        const showEquivalent = element.dataset.showEquivalent !== 'false';

        const now = new Date();
        target.textContent = new Intl.DateTimeFormat(locale, {
            calendar,
            timeZone: timezone,
            ...dateFormatOptions(dateFormat),
            ...timeFormatOptions(timeFormat),
        }).format(now);

        const equivalent = element.querySelector('[data-temporal-preview-equivalent]');
        if (equivalent) {
            const visible = showEquivalent && calendar !== 'gregory';
            equivalent.hidden = !visible;
            equivalent.textContent = visible
                ? `${element.dataset.equivalentLabel || 'Gregorian'} · ${new Intl.DateTimeFormat(locale, {
                    calendar: 'gregory',
                    timeZone: timezone,
                    ...dateFormatOptions(dateFormat),
                    ...timeFormatOptions(timeFormat),
                }).format(now)}`
                : '';
        }
    });

    root.querySelectorAll?.('iet-date-picker').forEach((picker) => picker.refreshFromInput?.());
}

document.addEventListener('DOMContentLoaded', () => localizeTemporal());
document.addEventListener('livewire:navigated', () => localizeTemporal());
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook('morph.updated', ({el}) => requestAnimationFrame(() => localizeTemporal(el)));
    window.Livewire?.on('temporal-preferences-updated', () => window.location.reload());
});

// Gregorian/timezone popovers open on hover and keyboard focus via CSS. Touch
// screens have no hover, so a tap toggles the popover explicitly; tapping
// elsewhere or pressing Escape closes it. Delegated, so it survives morphs.
function closeTemporalPopovers(except = null) {
    document.querySelectorAll('[data-temporal-popover-trigger][data-temporal-open]').forEach((trigger) => {
        if (trigger !== except) {
            trigger.removeAttribute('data-temporal-open');
        }
    });
}

document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element
        ? event.target.closest('[data-temporal-popover-trigger]')
        : null;

    if (!trigger) {
        closeTemporalPopovers();

        return;
    }

    // Inside a link the first tap reveals the details instead of navigating.
    if (!trigger.hasAttribute('data-temporal-open') && trigger.closest('a') && window.matchMedia('(hover: none)').matches) {
        event.preventDefault();
    }

    closeTemporalPopovers(trigger);
    trigger.toggleAttribute('data-temporal-open');
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeTemporalPopovers();
    }
});
