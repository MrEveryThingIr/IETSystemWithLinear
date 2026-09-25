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

function localizedDate(value, locale, calendar) {
    const date = isoToDate(value);

    if (!date) {
        return '';
    }

    return new Intl.DateTimeFormat(locale, {
        calendar,
        timeZone: 'UTC',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(date);
}

function localizedTime(value, locale) {
    const match = /^(\d{2}):(\d{2})/.exec(value ?? '');

    if (!match) {
        return value ?? '';
    }

    const date = new Date(Date.UTC(2000, 0, 1, Number(match[1]), Number(match[2])));

    return new Intl.DateTimeFormat(locale, {
        timeZone: 'UTC',
        hour: 'numeric',
        minute: '2-digit',
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
        this.firstDay = Number(this.dataset.firstDay || 1);
        this.input = this.querySelector('[data-date-value]');
        this.trigger = this.querySelector('[data-date-trigger]');
        this.display = this.querySelector('[data-date-display]');
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
            ? localizedDate(value, this.locale, this.calendar)
            : (this.dataset.emptyLabel || '');
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

function localizeTemporal(root = document) {
    root.querySelectorAll?.('[data-localized-date]').forEach((element) => {
        const value = element.dataset.localizedDate;

        if (!value) {
            return;
        }

        element.textContent = localizedDate(
            value,
            element.dataset.locale || document.documentElement.lang || 'en',
            calendarOrFallback(element.dataset.calendar || 'gregory'),
        );
    });

    root.querySelectorAll?.('[data-localized-time]').forEach((element) => {
        element.textContent = localizedTime(
            element.dataset.localizedTime,
            element.dataset.locale || document.documentElement.lang || 'en',
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

        target.textContent = new Intl.DateTimeFormat(locale, {
            calendar,
            timeZone: timezone,
            dateStyle: 'full',
            timeStyle: 'short',
        }).format(new Date());
    });

    root.querySelectorAll?.('iet-date-picker').forEach((picker) => picker.refreshFromInput?.());
}


function updateAmbientStatus(root = document) {
    root.querySelectorAll?.('[data-ambient-status]').forEach((element) => {
        const locale = element.dataset.locale || document.documentElement.lang || 'en';
        const calendar = calendarOrFallback(element.dataset.calendar || 'gregory');
        const timezone = element.dataset.timezone || 'UTC';
        const clock = element.querySelector('[data-ambient-clock]');
        const tip = element.querySelector('[data-ambient-tip]');

        if (clock) {
            clock.textContent = new Intl.DateTimeFormat(locale, {
                calendar,
                timeZone: timezone,
                dateStyle: 'medium',
                timeStyle: 'medium',
            }).format(new Date());
        }

        if (tip) {
            let tips = [];

            try {
                tips = JSON.parse(element.dataset.tips || '[]');
            } catch {
                tips = [];
            }

            if (Array.isArray(tips) && tips.length > 0) {
                const slot = Math.floor(Date.now() / 12000) % tips.length;
                tip.textContent = tips[slot] ?? '';
            }
        }
    });
}

function ensureAmbientTicker() {
    updateAmbientStatus();

    if (window.__ietAmbientTimer) {
        return;
    }

    window.__ietAmbientTimer = window.setInterval(() => updateAmbientStatus(), 1000);
}

document.addEventListener('DOMContentLoaded', () => { localizeTemporal(); ensureAmbientTicker(); });
document.addEventListener('livewire:navigated', () => { localizeTemporal(); ensureAmbientTicker(); });
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook('morph.updated', ({el}) => requestAnimationFrame(() => localizeTemporal(el)));
});
