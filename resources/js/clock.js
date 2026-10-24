/*
 * Live clocks and the moving "text of the day".
 *
 * Every [data-iet-clock] element shows the current time in the viewer's
 * language, calendar (Persian, Gregorian, ...) and time zone, with the
 * Gregorian date and time for the hover card. One timer serves all clocks,
 * aligned to the start of each second and stopped while the tab is hidden.
 * No network requests: the time comes from the device.
 */

const SUPPORTED_CALENDARS = new Set(['gregory', 'persian', 'islamic-umalqura']);
const formatters = new Map();
const clocks = new Set();
let timer = null;

function formatter(locale, options) {
    const key = locale + JSON.stringify(options);
    let value = formatters.get(key);
    if (!value) {
        try {
            value = new Intl.DateTimeFormat(locale, options);
        } catch {
            const { timeZone, ...rest } = options;
            value = new Intl.DateTimeFormat(locale, rest);
        }
        formatters.set(key, value);
    }

    return value;
}

function settings(root) {
    const calendar = SUPPORTED_CALENDARS.has(root.dataset.calendar) ? root.dataset.calendar : 'gregory';
    const zone = root.dataset.timezone || undefined;

    return {
        locale: root.dataset.locale || document.documentElement.lang || 'en',
        calendar,
        zone,
        hourCycle: root.dataset.hourCycle === 'h12' ? 'h12' : 'h23',
    };
}

function partsOf(fmt, date) {
    const parts = {};
    for (const part of fmt.formatToParts(date)) {
        if (part.type !== 'literal') {
            parts[part.type] = part.value;
        }
    }

    return parts;
}

/** Turns a hand without ever running backwards at the top of the dial. */
function turn(hand, degrees) {
    if (!hand) {
        return;
    }
    const last = hand._ietAngle;
    let next = degrees + (hand._ietTurns || 0) * 360;
    if (last !== undefined && next < last - 180) {
        hand._ietTurns = (hand._ietTurns || 0) + 1;
        next += 360;
    }
    hand._ietAngle = next;
    hand.style.transform = `rotate(${next}deg)`;
}

/**
 * Weekday, day, month and (on the big clock) year in the viewer's calendar.
 * Persian is assembled from its parts: some browsers put the year first
 * with a Latin comma ("۱۴۰۵ مهر ۳۰, پنجشنبه").
 */
function dateLabel(s, now, withYear) {
    const options = { calendar: s.calendar, timeZone: s.zone, weekday: 'long', day: 'numeric', month: 'long', ...(withYear ? { year: 'numeric' } : {}) };
    if (!s.locale.toLowerCase().startsWith('fa')) {
        return formatter(s.locale, options).format(now);
    }
    const p = partsOf(formatter(s.locale, options), now);

    return `${p.weekday}، ${p.day} ${p.month}${withYear && p.year ? ` ${p.year}` : ''}`;
}

function render(root, now) {
    const s = root._ietClock;
    const hms = partsOf(formatter('en-US', { timeZone: s.zone, hourCycle: 'h23', hour: 'numeric', minute: 'numeric', second: 'numeric' }), now);
    const h = Number(hms.hour) % 24;
    const m = Number(hms.minute);
    const sec = Number(hms.second);

    turn(s.hands.hour, (h % 12) * 30 + m * 0.5 + sec / 120);
    turn(s.hands.minute, m * 6 + sec * 0.1);
    turn(s.hands.second, sec * 6);

    for (const el of s.times) {
        el.textContent = formatter(s.locale, {
            timeZone: s.zone,
            hour: '2-digit',
            minute: '2-digit',
            ...(el.hasAttribute('data-seconds') ? { second: '2-digit' } : {}),
            hourCycle: s.hourCycle,
        }).format(now);
        el.setAttribute('datetime', now.toISOString());
    }

    // Dates, the date window and the zone change at most once a minute.
    const minuteKey = Math.floor(now.getTime() / 60000);
    if (s.minuteKey === minuteKey) {
        return;
    }
    s.minuteKey = minuteKey;

    for (const el of s.dates) {
        el.textContent = dateLabel(s, now, el.dataset.dateStyle !== 'bar');
    }
    for (const el of s.days) {
        el.textContent = partsOf(formatter(s.locale, { calendar: s.calendar, timeZone: s.zone, day: 'numeric' }), now).day || '';
    }
    const gregorianDate = formatter(s.locale, { calendar: 'gregory', timeZone: s.zone, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now);
    const gregorianTime = formatter(s.locale, { calendar: 'gregory', timeZone: s.zone, hour: '2-digit', minute: '2-digit', hourCycle: s.hourCycle }).format(now);
    for (const el of s.gregorian) {
        el.textContent = `${gregorianDate} · ${gregorianTime}`;
    }
    for (const el of s.gregorianShort) {
        el.textContent = s.calendar === 'gregory'
            ? ''
            : formatter(s.locale, { calendar: 'gregory', timeZone: s.zone, day: 'numeric', month: 'short', year: 'numeric' }).format(now);
    }
    if (s.zones.length) {
        const zoneName = partsOf(formatter(s.locale, { timeZone: s.zone, timeZoneName: 'long', hour: 'numeric' }), now).timeZoneName || '';
        let offset = '';
        try {
            offset = partsOf(formatter('en-US', { timeZone: s.zone, timeZoneName: 'longOffset', hour: 'numeric' }), now).timeZoneName || '';
        } catch {
            offset = '';
        }
        const id = s.zone || Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        const label = [zoneName, offset && offset !== zoneName ? offset : '', id && id !== zoneName ? id : ''].filter(Boolean).join(' · ');
        for (const el of s.zones) {
            el.textContent = label;
        }
    }
}

function tick() {
    timer = null;
    if (document.hidden) {
        return;
    }
    const now = new Date();
    for (const root of clocks) {
        if (!root.isConnected) {
            clocks.delete(root);
            continue;
        }
        render(root, now);
    }
    if (clocks.size) {
        timer = window.setTimeout(tick, 1000 - (Date.now() % 1000) + 8);
    }
}

function schedule() {
    if (timer === null && clocks.size && !document.hidden) {
        tick();
    }
}

function setupClock(root) {
    if (root._ietClock) {
        clocks.add(root);

        return;
    }
    const s = settings(root);
    const own = (selector) => [...root.querySelectorAll(selector)].filter((el) => el.closest('[data-iet-clock]') === root);
    root._ietClock = {
        ...s,
        hands: {
            hour: own('[data-hand="hour"]')[0],
            minute: own('[data-hand="minute"]')[0],
            second: own('[data-hand="second"]')[0],
        },
        times: own('[data-clock-time]'),
        dates: own('[data-clock-date]'),
        days: own('[data-clock-day]'),
        gregorian: own('[data-clock-gregorian]'),
        gregorianShort: own('[data-clock-gregorian-short]'),
        zones: own('[data-clock-zone]'),
        minuteKey: null,
    };
    const numerals = new Intl.NumberFormat(s.locale, { useGrouping: false });
    for (const el of own('[data-numeral]')) {
        el.textContent = numerals.format(Number(el.dataset.numeral));
    }
    render(root, new Date());
    // Transitions only after the first position, so hands do not sweep in.
    requestAnimationFrame(() => requestAnimationFrame(() => root.setAttribute('data-ready', '')));
    clocks.add(root);
}

/* ---------- Full screen (home page clock) ---------- */

let wakeLock = null;

async function holdScreen(on) {
    try {
        if (on && 'wakeLock' in navigator) {
            wakeLock = await navigator.wakeLock.request('screen');
        } else if (!on && wakeLock) {
            await wakeLock.release();
            wakeLock = null;
        }
    } catch {
        wakeLock = null;
    }
}

function setupFullscreen(button) {
    if (button._ietFullscreen) {
        return;
    }
    button._ietFullscreen = true;
    const stage = button.closest('[data-clock-stage]');
    if (!stage || !document.fullscreenEnabled) {
        button.hidden = true;

        return;
    }
    button.addEventListener('click', () => {
        if (document.fullscreenElement) {
            document.exitFullscreen?.();
        } else {
            stage.requestFullscreen?.().catch(() => {});
        }
    });
}

document.addEventListener('fullscreenchange', () => {
    const active = Boolean(document.fullscreenElement);
    for (const button of document.querySelectorAll('[data-clock-fullscreen]')) {
        const label = active ? button.dataset.labelExit : button.dataset.labelEnter;
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        button.querySelector('[data-icon-enter]')?.toggleAttribute('hidden', active);
        button.querySelector('[data-icon-exit]')?.toggleAttribute('hidden', !active);
    }
    holdScreen(active);
});

/* ---------- Moving text of the day ---------- */

const reducedMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
const PAUSE_KEY = 'iet.timeBar.paused';

function storedPause() {
    try {
        return window.localStorage.getItem(PAUSE_KEY) === '1';
    } catch {
        return false;
    }
}

function storePause(paused) {
    try {
        window.localStorage.setItem(PAUSE_KEY, paused ? '1' : '0');
    } catch {
        // Private mode or storage blocked: the choice lasts for this page only.
    }
}

function measureMarquee(marquee) {
    const viewport = marquee.querySelector('[data-marquee-viewport]');
    const track = marquee.querySelector('[data-marquee-track]');
    const group = track?.querySelector('[data-marquee-group]');
    if (!viewport || !track || !group) {
        return;
    }
    track.querySelectorAll('[data-marquee-clone]').forEach((clone) => clone.remove());
    marquee.removeAttribute('data-moving');

    const width = group.scrollWidth;
    const fits = width <= viewport.clientWidth + 1;
    if (fits || reducedMotion?.matches) {
        return;
    }
    const clone = group.cloneNode(true);
    clone.setAttribute('data-marquee-clone', '');
    clone.setAttribute('aria-hidden', 'true');
    clone.querySelectorAll('a, button').forEach((el) => el.setAttribute('tabindex', '-1'));
    track.appendChild(clone);
    // About 48 pixels a second, whatever the length.
    marquee.style.setProperty('--iet-marquee-duration', `${Math.max(14, Math.round(group.scrollWidth / 48))}s`);
    marquee.setAttribute('data-moving', '');
}

function setPaused(bar, paused) {
    const marquee = bar.querySelector('[data-marquee]');
    const button = bar.querySelector('[data-marquee-toggle]');
    marquee?.toggleAttribute('data-paused', paused);
    if (button) {
        const label = paused ? button.dataset.labelPlay : button.dataset.labelPause;
        button.setAttribute('aria-pressed', paused ? 'true' : 'false');
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.querySelector('[data-icon-pause]')?.toggleAttribute('hidden', paused);
        button.querySelector('[data-icon-play]')?.toggleAttribute('hidden', !paused);
    }
}

function setupBar(bar) {
    const marquee = bar.querySelector('[data-marquee]');
    if (!marquee) {
        return;
    }
    if (!bar._ietBar) {
        bar._ietBar = true;
        bar.querySelector('[data-marquee-toggle]')?.addEventListener('click', () => {
            const paused = !marquee.hasAttribute('data-paused');
            setPaused(bar, paused);
            storePause(paused);
        });
        const expand = bar.querySelector('[data-marquee-expand]');
        const panel = bar.querySelector('[data-marquee-panel]');
        expand?.addEventListener('click', () => {
            const open = panel?.hidden ?? false;
            if (panel) {
                panel.hidden = !open;
            }
            const label = open ? expand.dataset.labelCollapse : expand.dataset.labelExpand;
            expand.setAttribute('aria-expanded', open ? 'true' : 'false');
            expand.setAttribute('aria-label', label);
            expand.setAttribute('title', label);
            expand.querySelector('[data-icon-expand]')?.classList.toggle('rotate-180', open);
        });
        if ('ResizeObserver' in window) {
            let last = 0;
            new ResizeObserver((entries) => {
                const width = Math.round(entries[0]?.contentRect.width || 0);
                if (Math.abs(width - last) > 8) {
                    last = width;
                    measureMarquee(marquee);
                }
            }).observe(marquee);
        }
        setPaused(bar, storedPause());
    }
    measureMarquee(marquee);
}

reducedMotion?.addEventListener?.('change', () => {
    document.querySelectorAll('[data-time-bar] [data-marquee]').forEach(measureMarquee);
});

/* ---------- Wiring ---------- */

function init(root = document) {
    root.querySelectorAll('[data-iet-clock]').forEach(setupClock);
    root.querySelectorAll('[data-clock-fullscreen]').forEach(setupFullscreen);
    root.querySelectorAll('[data-time-bar]').forEach(setupBar);
    schedule();
}

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        schedule();
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
} else {
    init();
}
document.addEventListener('livewire:navigated', () => init());
// Fonts change text widths; measure the moving line again once they are in.
document.fonts?.ready?.then(() => document.querySelectorAll('[data-time-bar] [data-marquee]').forEach(measureMarquee));
