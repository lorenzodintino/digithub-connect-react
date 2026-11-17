// @ts-nocheck -- ported from the closed showcase draft (vanilla DOM + three.js); the typed boundary is `mountAtrio`.
import * as THREE from 'three';
import { __, getLocale, trans_choice } from '@/lib/i18n';
import { initBrand } from './brand';
import { finePointer, reduced } from './site';
import { SyncDemo } from './ui';

/**
 * Brings the showcase to life: the 3D month calendar, the booking demo, the email viewer and the branded portal.
 * Everything is found inside `host` (the `.vetrina` element); the returned function undoes it all.
 */
export function mountAtrio(host: HTMLElement): () => void {
let destroyed = false;
const cleanups = [];
const on = (target, type, fn, options) => {
    target.addEventListener(type, fn, options);
    cleanups.push(() => target.removeEventListener(type, fn, options));
};
const observe = (observer, target) => {
    observer.observe(target);
    cleanups.push(() => observer.disconnect());

    return observer;
};
const root = host;
const $ = (s, r = host) => r.querySelector(s);
const $$ = (s, r = host) => [...r.querySelectorAll(s)];
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

/* ---------- Entrata ---------- */

const go = () => requestAnimationFrame(() => root.classList.add('ready'));
if (document.fonts?.ready) Promise.race([document.fonts.ready, new Promise((r) => setTimeout(r, 700))]).then(go);
else go();

/* ---------- Prova una prenotazione ---------- */

const OPS = ['Giulia Bianchi', 'Luca Verdi'];
const phones = $$('[data-sync-phone]');
const demo = new SyncDemo({ phone: phones[0], agenda: $('#agenda2'), autoplay: true });
const modeEl = $('#prova-mode');
const noteEl = $('#prova-note');
const setMode = (mode, text) => {
    modeEl.dataset.mode = mode;
    modeEl.textContent = text;
};
const stopDemo = demo.stop.bind(demo);
demo.stop = () => {
    stopDemo();
    if (modeEl.dataset.mode === 'auto') setMode('manual', __('Your turn: pick a service.'));
};
const playDemo = demo.play.bind(demo);
demo.play = () => {
    if (!reduced) setMode('auto', __('Automatic demo. Tap the phone to carry on yourself.'));
    playDemo();
    if (!reduced) demo.later(12600, () => setMode('manual', __('Your turn: pick a service.')));
};
const resetDemo = demo.reset.bind(demo);
demo.reset = () => {
    resetDemo();
    noteEl.textContent = '';
};
phones[0].addEventListener('booked', (e) => {
    noteEl.replaceChildren();
    noteEl.append(__('Assigned to :operator with "any operator". It is now in the owner\'s diary.', { operator: OPS[e.detail.lane] }));
    const a = document.createElement('a');
    a.className = 'prova__go';
    a.href = '#agenda2';
    a.textContent = __('Go to the diary');
    noteEl.append(a);
});

/* ---------- Portale col marchio ---------- */

const portalPhone = phones[1];
const portal = initBrand(portalPhone);
const luminance = (hex) => {
    const n = parseInt(hex.slice(1), 16);
    const lin = (v) => {
        const x = v / 255;

        return x <= 0.04045 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * lin((n >> 16) & 255) + 0.7152 * lin((n >> 8) & 255) + 0.0722 * lin(n & 255);
};
// Il testo sul colore scelto deve restare leggibile: bianco solo se il contrasto arriva a 4,5.
$$('input[name="brand"]').forEach((i) =>
    i.addEventListener('change', () => {
        portalPhone.style.setProperty('--brand-ink', 1.05 / (luminance(i.value) + 0.05) >= 4.5 ? 'var(--surface)' : 'var(--ink)');
    }),
);

/* ---------- Le email ---------- */

const MAILS = {
    conferma: {
        who: 'c',
        it: { s: 'Prenotazione confermata: mer 18 nov, 12:00', t: "la tua prenotazione con Giulia Bianchi è confermata. Nell'email trovi il QR della prenotazione.", cta: 'Sposta o annulla' },
        en: { s: 'Booking confirmed: Wed 18 Nov, 12:00', t: 'your booking with Giulia Bianchi is confirmed. This email includes the QR code of your booking.', cta: 'Reschedule or cancel' },
    },
    richiesta: {
        who: 'c',
        it: { s: 'Richiesta ricevuta: gio 19 nov, 10:30', t: 'abbiamo ricevuto la tua richiesta. {brand} deve confermarla: ti scriviamo appena lo fa.', cta: 'Gestisci la richiesta' },
        en: { s: 'Request received: Thu 19 Nov, 10:30', t: "we've received your request. {brand} needs to confirm it: we'll email you as soon as they do.", cta: 'Manage your request' },
    },
    promemoria: {
        who: 'c',
        it: { s: 'Promemoria: domani alle 12:00', t: "ti ricordiamo l'appuntamento di domani con Giulia Bianchi. Se non puoi venire, spostalo o annullalo dal link.", cta: 'Sposta o annulla' },
        en: { s: 'Reminder: tomorrow at 12:00', t: "a reminder of your appointment tomorrow with Giulia Bianchi. If you can't make it, reschedule or cancel from the link.", cta: 'Reschedule or cancel' },
    },
    sposta: {
        who: 'c',
        it: { s: 'Appuntamento spostato: gio 19 nov, 11:30', t: 'il tuo appuntamento con Giulia Bianchi è stato spostato a giovedì 19 novembre alle 11:30.', cta: 'Sposta o annulla' },
        en: { s: 'Appointment rescheduled: Thu 19 Nov, 11:30', t: 'your appointment with Giulia Bianchi has been moved to Thursday 19 November at 11:30.', cta: 'Reschedule or cancel' },
    },
    annulla: {
        who: 'c',
        it: { s: 'Prenotazione annullata: mer 18 nov, 12:00', t: 'la prenotazione di mercoledì 18 novembre alle 12:00 è stata annullata. Puoi prenotare un altro orario dal portale.', cta: 'Prenota un altro orario' },
        en: { s: 'Booking cancelled: Wed 18 Nov, 12:00', t: 'your booking for Wednesday 18 November at 12:00 has been cancelled. You can book another time from the portal.', cta: 'Book another time' },
    },
    libero: {
        who: 'c',
        it: { s: 'Si è liberato un posto: gio 19 nov, 10:30', t: "eri in lista d'attesa e si è liberato un posto per giovedì 19 novembre alle 10:30.", cta: 'Prenota il posto' },
        en: { s: 'A spot has opened: Thu 19 Nov, 10:30', t: 'you were on the waiting list and a spot has opened for Thursday 19 November at 10:30.', cta: 'Book the spot' },
    },
    'o-nuova': {
        who: 'o',
        it: { s: 'Nuova prenotazione: Anna Ferrari, mer 18 nov, 12:00', t: 'Anna Ferrari ha prenotato Consulenza fiscale con te per mercoledì 18 novembre alle 12:00.', cta: "Apri l'agenda" },
        en: { s: 'New booking: Anna Ferrari, Wed 18 Nov, 12:00', t: 'Anna Ferrari booked Tax consultation with you for Wednesday 18 November at 12:00.', cta: 'Open the diary' },
    },
    'o-annulla': {
        who: 'o',
        it: { s: 'Prenotazione annullata: Anna Ferrari, mer 18 nov, 12:00', t: "Anna Ferrari ha annullato l'appuntamento di mercoledì 18 novembre alle 12:00. L'orario è di nuovo libero.", cta: "Apri l'agenda" },
        en: { s: 'Booking cancelled: Anna Ferrari, Wed 18 Nov, 12:00', t: 'Anna Ferrari cancelled the appointment on Wednesday 18 November at 12:00. The slot is free again.', cta: 'Open the diary' },
    },
    'o-sposta': {
        who: 'o',
        it: { s: 'Prenotazione spostata: Anna Ferrari', t: "Anna Ferrari ha spostato l'appuntamento da mercoledì 18 novembre, 12:00, a giovedì 19 novembre, 11:30.", cta: "Apri l'agenda" },
        en: { s: 'Booking rescheduled: Anna Ferrari', t: 'Anna Ferrari moved the appointment from Wednesday 18 November, 12:00, to Thursday 19 November, 11:30.', cta: 'Open the diary' },
    },
};
{
    const card = $('.mail');
    const nameInput = $('#b-name');
    const ui = {
        from: $('[data-mail-from]', card),
        to: $('[data-mail-to]', card),
        logo: $('[data-mail-logo]', card),
        subject: $('[data-mail-subject]', card),
        hello: $('[data-mail-hello]', card),
        text: $('[data-mail-text]', card),
        cta: $('[data-mail-cta]', card),
        foot: $('[data-mail-foot]', card),
        lang: $('#mail-lang'),
    };
    let current = 'conferma';
    let lang = getLocale() === 'en' ? 'en' : 'it';
    const uiLang = lang;
    const brand = () => nameInput.value.trim() || __('Your business');
    const draw = () => {
        const m = MAILS[current];
        const isClient = m.who === 'c';
        const l = isClient ? lang : uiLang;
        const c = m[l];
        const first = isClient ? 'Anna' : 'Giulia';
        ui.from.textContent = brand();
        ui.logo.textContent = brand().charAt(0).toUpperCase();
        ui.to.textContent = isClient ? 'Anna Ferrari' : 'Giulia Bianchi';
        ui.subject.textContent = c.s;
        ui.hello.textContent = l === 'it' ? `Ciao ${first},` : `Hi ${first},`;
        ui.text.textContent = c.t.replaceAll('{brand}', brand());
        ui.cta.textContent = c.cta;
        ui.lang.hidden = !isClient;
        ui.foot.textContent = isClient ? (l === 'it' ? 'Il cliente non crea un account: sposta o annulla da un link personale.' : 'No account needed: the customer reschedules or cancels from a personal link.') : __('It goes to the operator concerned, not to the customer.');
        $$('[data-mail]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.mail === current)));
        $$('[data-lang]', ui.lang).forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.lang === lang)));
    };
    $$('[data-mail]').forEach((b) =>
        b.addEventListener('click', () => {
            current = b.dataset.mail;
            draw();
            // su tablet e telefono l'anteprima sta sotto i pulsanti: la porto in vista
            if (matchMedia('(max-width: 900px)').matches) card.scrollIntoView({ block: 'nearest', behavior: reduced ? 'auto' : 'smooth' });
        }),
    );
    $$('[data-lang]', ui.lang).forEach((b) =>
        b.addEventListener('click', () => {
            lang = b.dataset.lang;
            draw();
        }),
    );
    nameInput.addEventListener('input', draw);
    draw();
}


/* ---------- Il calendario: dati condivisi da 3D e 2D ---------- */

const MONTHS = [
    { y: 2026, m: 10 },
    { y: 2026, m: 11 },
    { y: 2027, m: 0 },
];
const MONTH_IT = Array.from({ length: 12 }, (_, m) => new Intl.DateTimeFormat(getLocale(), { month: 'long' }).format(new Date(2026, m, 1)));
const DOW = Array.from({ length: 7 }, (_, i) => cap(new Intl.DateTimeFormat(getLocale(), { weekday: 'short' }).format(new Date(2026, 10, 2 + i))));
const TODAY_TAG = ` (${__('today')})`;
const FULL = __('fully booked');
const TODAY = { y: 2026, m: 10, d: 16 };
const HOURS = [9, 10, 11, 12, 13];
const MAX_ADDED = 10;
const SERVICES = [
    { name: __('First consultation'), min: 30 },
    { name: __('Tax consultation'), min: 60 },
    { name: __('VAT registration'), min: 45 },
];
const NAMES = ['Elisa G.', 'Marco R.', 'Sara C.', 'Paolo N.', 'Davide F.', 'Chiara M.', 'Luigi C.', 'Marta V.', 'Franco B.', 'Irene L.'];
/* giorno di novembre -> [ora, operatore, servizio, cliente?]: una prenotazione per ora, al massimo cinque al giorno */
const SEED_NOV = {
    2: [[9, 0, 0], [11, 1, 1]],
    3: [[10, 0, 1], [12, 1, 0], [13, 0, 2]],
    4: [[11, 1, 1]],
    5: [[9, 0, 0], [10, 1, 1], [11, 0, 2], [13, 1, 0]],
    6: [[9, 1, 1], [12, 0, 0]],
    9: [[10, 0, 1], [11, 1, 2], [13, 0, 0]],
    10: [[9, 0, 2], [12, 1, 1]],
    11: [[9, 1, 0], [10, 0, 1], [11, 0, 2], [12, 1, 1]],
    12: [[9, 0, 1], [10, 1, 2], [11, 0, 0], [12, 1, 0], [13, 0, 1]],
    13: [[10, 0, 0], [11, 1, 1], [12, 0, 2]],
    16: [[9, 0, 1, 'Elisa G.'], [10, 1, 0, 'Davide F.'], [12, 0, 0, 'Chiara M.']],
    17: [[9, 1, 2], [10, 0, 1], [11, 1, 0], [13, 0, 0]],
    18: [[10, 1, 0, 'Marco R.'], [11, 0, 2, 'Sara C.'], [12, 0, 1, 'Anna Ferrari']],
    19: [[9, 0, 0], [10, 1, 2], [11, 0, 1], [12, 1, 0], [13, 0, 0]],
    20: [[10, 0, 1], [13, 1, 0]],
    23: [[9, 0, 0], [11, 1, 2], [12, 0, 1]],
    24: [[10, 1, 1]],
    25: [[9, 0, 2], [10, 0, 0], [11, 1, 1], [13, 0, 0]],
    26: [[11, 0, 1], [12, 1, 0]],
    30: [[10, 0, 0]],
};
const HOLIDAYS = new Set(['2026-11-8', '2026-11-25', '2026-11-26', '2027-0-1', '2027-0-6']);

const hash = (n) => {
    let x = Math.imul(n | 0, 2654435761) >>> 0;
    x ^= x >>> 15;
    x = Math.imul(x, 2246822519) >>> 0;
    x ^= x >>> 13;

    return x >>> 0;
};
const dkey = (y, m, d) => `${y}-${m}-${d}`;
const dowOf = (y, m, d) => (new Date(y, m, d).getDay() + 6) % 7;
const daysIn = (y, m) => new Date(y, m + 1, 0).getDate();
const todayMs = new Date(TODAY.y, TODAY.m, TODAY.d).getTime();
const isToday = (y, m, d) => y === TODAY.y && m === TODAY.m && d === TODAY.d;
const dayStatus = (y, m, d) => {
    if (dowOf(y, m, d) >= 5) return 'weekend';
    if (HOLIDAYS.has(dkey(y, m, d))) return 'holiday';

    return new Date(y, m, d).getTime() < todayMs ? 'past' : 'open';
};
const dayLabel = (y, m, d) => `${DOW[dowOf(y, m, d)]} ${d} ${MONTH_IT[m]}`;
const hhmm = (h) => String(h).padStart(2, '0') + ':00';
const nBooks = (n) => trans_choice(':count booking|:count bookings', n);
const nFree = (n) => trans_choice(':count free slot|:count free slots', n);
const monthTitle = (mi) => `${cap(MONTH_IT[MONTHS[mi].m])} ${MONTHS[mi].y}`;

function monthCells(mi) {
    const { y, m } = MONTHS[mi];
    const first = dowOf(y, m, 1);
    const len = daysIn(y, m);
    const rows = Math.ceil((first + len) / 7);
    const prevLen = daysIn(y, m - 1);
    const cells = [];
    for (let i = 0; i < rows * 7; i++) {
        const n = i - first + 1;
        cells.push(n < 1 ? { d: prevLen + n, in: false } : n > len ? { d: n - len, in: false } : { d: n, in: true });
    }

    return { y, m, rows, len, cells };
}

const views = [];
const store = {
    map: new Map(),
    added: [],
    list(y, m, d) {
        return this.map.get(dkey(y, m, d)) ?? [];
    },
    at(y, m, d, h) {
        return this.list(y, m, d).find((b) => b.h === h);
    },
    freeHours(y, m, d) {
        return dayStatus(y, m, d) === 'open' ? HOURS.filter((h) => !this.at(y, m, d, h)) : [];
    },
    pickLane(y, m, d) {
        const l = this.list(y, m, d);

        return l.filter((b) => b.lane === 0).length <= l.filter((b) => b.lane === 1).length ? 0 : 1;
    },
    put(y, m, d, b) {
        const k = dkey(y, m, d);
        if (!this.map.has(k)) this.map.set(k, []);
        this.map.get(k).push(b);
    },
    monthCount(mi) {
        const { y, m } = MONTHS[mi];
        let n = 0;
        for (let d = 1; d <= daysIn(y, m); d++) n += this.list(y, m, d).length;

        return n;
    },
    load() {
        this.map.clear();
        this.added = [];
        MONTHS.forEach(({ y, m }, mi) => {
            for (let d = 1; d <= daysIn(y, m); d++) {
                const st = dayStatus(y, m, d);
                if (st === 'weekend' || st === 'holiday') continue;
                let rows = [];
                if (mi === 0) rows = SEED_NOV[d] ?? [];
                else {
                    const h0 = hash(y * 512 + m * 32 + d);
                    const n = [0, 1, 2, 2, 3, 4][h0 % 6];
                    for (let i = 0; i < n; i++) {
                        const x = hash(h0 + i * 7919 + 13);
                        let hr = HOURS[x % HOURS.length];
                        while (rows.some((r) => r[0] === hr)) hr = HOURS[(HOURS.indexOf(hr) + 1) % HOURS.length];
                        rows.push([hr, (x >>> 5) & 1, (x >>> 7) % 3]);
                    }
                }
                rows.forEach(([h, lane, svc, who]) => this.put(y, m, d, { h, lane, svc, who: who ?? NAMES[(d * 3 + h) % NAMES.length], added: false }));
            }
        });
    },
    add(y, m, d, h, svc) {
        const b = { h, lane: this.pickLane(y, m, d), svc, who: __('You'), added: true };
        this.put(y, m, d, b);
        this.added.push({ y, m, d, b });
        views.forEach((v) => v.add?.(y, m, d, b));
        sync();

        return b;
    },
    reset() {
        for (const { y, m, d, b } of this.added) {
            const arr = this.map.get(dkey(y, m, d));
            arr.splice(arr.indexOf(b), 1);
        }
        this.added = [];
        views.forEach((v) => v.reset?.());
        sync();
    },
};
store.load();

/* ---------- Elementi, stato e messaggi ---------- */

const ui = {
    card: $('#grid3d'),
    stage: $('#g3-stage'),
    hint: $('#g3-hint'),
    live: $('#g3-live'),
    count: $('#c2-count'),
    countLabel: $('#c2-count-l'),
    reset: $('#g3-reset'),
    month: $('#c2-month'),
    prev: $('#c2-prev'),
    next: $('#c2-next'),
    head: $('#c2-head'),
    tbl: $('#c2-tbl'),
    uiLayer: $('#c2-ui'),
    clip: $('#c2-dayclip'),
    day: $('#c2-day'),
    dayH: $('#c2-day-h'),
    dayMeta: $('#c2-day-meta'),
    body: $('#c2-body'),
    close: $('#c2-close'),
};
const state = { mi: 0, open: null, step: 'slots', hour: null, busy: false, queued: null };
const overlayMQ = matchMedia('(min-width: 1181px)');
const verb = finePointer ? __('Click') : __('Tap');
let gl = null;
let nudgeOff = () => {};
const cur = () => MONTHS[state.mi];

const idleHint = () => {
    if (root.classList.contains('no-gl')) return __(':action a day to open it: you see the free slots and book.', { action: verb });

    return finePointer ? __('Hover a day to lift it, click to open it and book a time.') : __('Tap a day to open it and book a time.');
};
let hintLocked = false;
const setHint = (text) => {
    ui.hint.textContent = text || idleHint();
};
const announce = (text) => {
    ui.live.textContent = '';
    requestAnimationFrame(() => (ui.live.textContent = text));
    setHint(text);
    hintLocked = true;
    clearTimeout(announce.t);
    announce.t = setTimeout(() => (hintLocked = false), 3600);
};
const hover = (text) => {
    if (!hintLocked) setHint(text);
};

function dayInfo(y, m, d, tail = false) {
    const st = dayStatus(y, m, d);
    const when = dayLabel(y, m, d) + (isToday(y, m, d) ? TODAY_TAG : '');
    const n = store.list(y, m, d).length;
    if (st === 'weekend') return __(':day: closed at the weekend.', { day: when });
    if (st === 'holiday') return __(':day: closed for the holiday.', { day: when });
    if (st === 'past') return __(':day: past day, :bookings.', { day: when, bookings: nBooks(n) });
    const free = store.freeHours(y, m, d).length;

    return `${when}: ${nBooks(n)}, ${free ? nFree(free) : FULL}.${tail ? ` ${__(':action to open it.', { action: verb })}` : ''}`;
}

let lastCount = store.monthCount(0);
let lastMi = 0;
function sync() {
    const n = store.monthCount(state.mi);
    ui.count.textContent = n;
    ui.countLabel.textContent = trans_choice('booking in :month|bookings in :month', n, { month: MONTH_IT[cur().m] });
    if (n !== lastCount && state.mi === lastMi) {
        ui.count.classList.remove('bump');
        void ui.count.offsetWidth;
        ui.count.classList.add('bump');
    }
    lastCount = n;
    lastMi = state.mi;
    ui.reset.disabled = store.added.length === 0;
    ui.card.classList.toggle('is-full', store.added.length >= MAX_ADDED);
    ui.prev.setAttribute('aria-disabled', String(state.mi === 0));
    ui.next.setAttribute('aria-disabled', String(state.mi === MONTHS.length - 1));
}

/* ---------- Calendario HTML: è il fallback visibile e, con il 3D, la via da tastiera ---------- */

const cellBtn = (d) => ui.tbl.querySelector(`.c2__d[data-d="${d}"]`);
const setRoving = (d) => $$('.c2__d', ui.tbl).forEach((b) => (b.tabIndex = +b.dataset.d === d ? 0 : -1));

function dayBtn(y, m, d) {
    const st = dayStatus(y, m, d);
    const list = store.list(y, m, d);
    const today = isToday(y, m, d);
    const cls = ['c2__d', st === 'weekend' || st === 'holiday' ? 'is-closed' : '', st === 'past' ? 'is-past' : '', today ? 'is-today' : '', state.open === d ? 'is-open' : ''].filter(Boolean).join(' ');
    const bars = list.map((b) => `<i data-lane="${b.lane}"></i>`).join('');
    const label = `${DOW_FULL_IT[dowOf(y, m, d)]} ${d} ${MONTH_IT[m]}${today ? TODAY_TAG : ''}: ${dayInfo(y, m, d).replace(/^[^:]+: /, '')}`;

    return `<button type="button" class="${cls}" data-d="${d}" tabindex="-1" aria-controls="c2-day" aria-expanded="${state.open === d}"${today ? ' aria-current="date"' : ''} aria-label="${label}"><span class="c2__num">${d}</span>${list.length ? `<span class="c2__pile" aria-hidden="true">${bars}</span><span class="c2__n" aria-hidden="true">${list.length}</span>` : ''}</button>`;
}
const DOW_FULL_IT = Array.from({ length: 7 }, (_, i) => new Intl.DateTimeFormat(getLocale(), { weekday: 'long' }).format(new Date(2026, 10, 2 + i)));

function renderTable() {
    const mc = monthCells(state.mi);
    ui.month.textContent = monthTitle(state.mi);
    let html = `<thead><tr>${DOW.map((n, i) => `<th scope="col" abbr="${DOW_FULL_IT[i]}">${n}</th>`).join('')}</tr></thead><tbody>`;
    for (let r = 0; r < mc.rows; r++) {
        html += '<tr>';
        for (let c = 0; c < 7; c++) {
            const cell = mc.cells[r * 7 + c];
            html += cell.in ? `<td>${dayBtn(mc.y, mc.m, cell.d)}</td>` : `<td class="is-out"><span aria-hidden="true">${cell.d}</span></td>`;
        }
        html += '</tr>';
    }
    ui.tbl.innerHTML = html + '</tbody>';
    const first = isToday(mc.y, mc.m, TODAY.d) && state.mi === 0 ? TODAY.d : [...Array(mc.len).keys()].map((i) => i + 1).find((d) => dayStatus(mc.y, mc.m, d) === 'open') ?? 1;
    setRoving(state.open ?? first);
}
function refreshBtn(d) {
    const { y, m } = cur();
    const btn = cellBtn(d);
    if (!btn) return;
    const had = document.activeElement === btn;
    const tab = btn.tabIndex;
    btn.parentElement.innerHTML = dayBtn(y, m, d);
    const nb = cellBtn(d);
    nb.tabIndex = tab;
    if (had) nb.focus({ preventScroll: true });
}

views.push({
    add(y, m, d) {
        if (cur().y === y && cur().m === m) refreshBtn(d);
    },
    reset() {
        renderTable();
    },
});

ui.tbl.addEventListener('click', (e) => {
    const b = e.target.closest('.c2__d');
    if (b) openDay(+b.dataset.d, e.detail === 0);
});
ui.tbl.addEventListener('pointerover', (e) => {
    const b = e.target.closest('.c2__d');
    if (b && !gl) hover(dayInfo(cur().y, cur().m, +b.dataset.d, true));
});
ui.tbl.addEventListener('pointerleave', () => !gl && hover(''));
ui.tbl.addEventListener('focusin', (e) => {
    const b = e.target.closest('.c2__d');
    if (!b) return;
    const d = +b.dataset.d;
    setRoving(d);
    nudgeOff();
    hover(dayInfo(cur().y, cur().m, d, true));
    gl?.hotDay(d);
    ui.stage.classList.toggle('is-kbd', !!gl && b.matches(':focus-visible'));
});
ui.tbl.addEventListener('focusout', () => {
    gl?.hotDay(null);
    ui.stage.classList.remove('is-kbd');
    hover('');
});
ui.tbl.addEventListener('keydown', (e) => {
    const b = e.target.closest('.c2__d');
    if (!b) return;
    const { y, m } = cur();
    const d = +b.dataset.d;
    const len = daysIn(y, m);
    const w = dowOf(y, m, d);
    const to = { ArrowLeft: d - 1, ArrowRight: d + 1, ArrowUp: d - 7, ArrowDown: d + 7, Home: Math.max(1, d - w), End: Math.min(len, d + 6 - w) }[e.key];
    if (e.key === 'PageUp' || e.key === 'PageDown') {
        e.preventDefault();
        changeMonth(e.key === 'PageUp' ? -1 : 1, d);

        return;
    }
    if (to === undefined) return;
    e.preventDefault();
    if (to >= 1 && to <= len) cellBtn(to)?.focus();
});

/* ---------- Pannello del giorno ---------- */

const markOpen = () =>
    $$('.c2__d', ui.tbl).forEach((b) => {
        const on = +b.dataset.d === state.open;
        b.classList.toggle('is-open', on);
        b.setAttribute('aria-expanded', String(on));
    });
const measureDay = () => gl?.measure(state.open !== null && overlayMQ.matches ? ui.day.offsetHeight + 12 : 0);
const bookingChip = (b) => `<div class="c2__bk" data-lane="${b.lane}" title="${b.who}"><b>${OPS[b.lane].split(' ')[0]}</b><span>${SERVICES[b.svc].name}</span></div>`;

function renderDay(highlight = null) {
    const { y, m } = cur();
    const d = state.open;
    if (d === null) return;
    const st = dayStatus(y, m, d);
    const list = store.list(y, m, d);
    const free = store.freeHours(y, m, d);
    const full = store.added.length >= MAX_ADDED;
    ui.dayH.textContent = dayLabel(y, m, d) + (isToday(y, m, d) ? `, ${__('today')}` : '');
    if (st === 'weekend') ui.dayMeta.textContent = __('Closed at the weekend');
    else if (st === 'holiday') ui.dayMeta.textContent = __('Closed for the holiday');
    else if (st === 'past') ui.dayMeta.textContent = `${__('Past day')} · ${nBooks(list.length)}`;
    else ui.dayMeta.textContent = `${nBooks(list.length)} · ${free.length ? nFree(free.length) : FULL}`;

    if (state.step === 'svc') {
        ui.body.innerHTML = `<div class="c2__svc"><p class="c2__q">${__('At :time: which service?', { time: hhmm(state.hour) })}</p><div class="c2__opts">${SERVICES.map((s, i) => `<button type="button" class="c2__opt" data-svc="${i}"><b>${s.name}</b><span>${s.min} min</span></button>`).join('')}</div><button type="button" class="c2__back">${__('Change time')}</button></div>`;
    } else if (st === 'weekend' || st === 'holiday') {
        ui.body.innerHTML = `<p class="c2__note">${st === 'weekend' ? __('The studio is closed at the weekend: the times stay closed.') : __('The studio is closed for the holiday: the times stay closed.')}</p>`;
    } else {
        ui.body.innerHTML = `<ul class="c2__slots">${HOURS.map((h) => {
            const t = hhmm(h);
            const b = store.at(y, m, d, h);
            if (b) return `<li class="c2__slot${highlight === h ? ' is-new' : ''}"><time>${t}</time>${bookingChip(b)}</li>`;
            if (st === 'open') return `<li class="c2__slot"><time>${t}</time><button type="button" class="c2__pick" data-h="${h}" aria-label="${__('Book at :time', { time: t })}"${full ? ' aria-disabled="true"' : ''}><small>${__('Available')}</small>${__('Book')}</button></li>`;

            return `<li class="c2__slot"><time>${t}</time><span class="c2__none">${__('Available')}</span></li>`;
        }).join('')}</ul>`;
    }
}

ui.body.addEventListener('click', (e) => {
    const pick = e.target.closest('.c2__pick');
    const opt = e.target.closest('.c2__opt');
    const back = e.target.closest('.c2__back');
    const { y, m } = cur();
    const d = state.open;
    if (d === null) return;
    if (pick) {
        if (store.added.length >= MAX_ADDED) {
            announce(__('In this trial you can add ten bookings: press "Clear my tests" to start over.'));

            return;
        }
        state.step = 'svc';
        state.hour = +pick.dataset.h;
        renderDay();
        measureDay();
        $('.c2__opt', ui.body).focus({ preventScroll: true });
    } else if (opt) {
        const h = state.hour;
        if (store.at(y, m, d, h)) return;
        const svc = +opt.dataset.svc;
        const b = store.add(y, m, d, h, svc);
        state.step = 'slots';
        state.hour = null;
        renderDay(h);
        measureDay();
        ui.dayH.focus({ preventScroll: true });
        const n = store.list(y, m, d).length;
        announce(`${__('Booked: :day at :time, :service with :operator. The day\'s stack goes up to :count.', { day: dayLabel(y, m, d), time: hhmm(h), service: SERVICES[svc].name, operator: OPS[b.lane], count: n })}${store.added.length >= MAX_ADDED ? ` ${__('The trial is full: press "Clear my tests" to start over.')}` : ''}`);
    } else if (back) {
        const h = state.hour;
        state.step = 'slots';
        state.hour = null;
        renderDay();
        measureDay();
        $(`.c2__pick[data-h="${h}"]`, ui.body)?.focus({ preventScroll: true });
    }
});

function openDay(d, byKey = false) {
    if (state.busy) return;
    nudgeOff();
    if (state.open === d) {
        closeDay(byKey);

        return;
    }
    state.open = d;
    state.step = 'slots';
    state.hour = null;
    renderDay();
    ui.clip.classList.add('is-open');
    markOpen();
    gl?.open(d);
    measureDay();
    const { y, m } = cur();
    announce(dayInfo(y, m, d));
    if (byKey) ui.dayH.focus({ preventScroll: true });
    else if (!gl || !overlayMQ.matches) setTimeout(() => ui.clip.scrollIntoView({ block: 'nearest', behavior: reduced ? 'auto' : 'smooth' }), 260);
}
function closeDay(refocus = false) {
    if (state.open === null) return;
    const d = state.open;
    state.open = null;
    ui.clip.classList.remove('is-open');
    markOpen();
    gl?.close();
    gl?.measure(0);
    setHint();
    if (refocus) cellBtn(d)?.focus();
}
ui.close.addEventListener('click', (e) => closeDay(e.detail === 0));
ui.day.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    e.stopPropagation();
    if (state.step === 'svc') {
        const h = state.hour;
        state.step = 'slots';
        state.hour = null;
        renderDay();
        measureDay();
        $(`.c2__pick[data-h="${h}"]`, ui.body)?.focus({ preventScroll: true });

        return;
    }
    closeDay(true);
});

/* ---------- Cambio mese ---------- */

/* un clic durante l'animazione non si perde: il cambio mese successivo parte appena finisce quello in corso */
function flipDone() {
    state.busy = false;
    const q = state.queued;
    state.queued = null;
    if (q) changeMonth(q.dir, q.keepDay);
}
function changeMonth(dir, keepDay = null) {
    nudgeOff();
    if (state.busy) {
        state.queued = { dir, keepDay };

        return;
    }
    const ni = state.mi + dir;
    if (ni < 0 || ni >= MONTHS.length) {
        announce(ni < 0 ? __('You can\'t book in the past: the calendar starts in November 2026.') : __('The trial goes up to January 2027.'));

        return;
    }
    const hadFocus = ui.day.contains(document.activeElement);
    closeDay(false);
    if (hadFocus) (dir > 0 ? ui.next : ui.prev).focus({ preventScroll: true });
    state.mi = ni;
    renderTable();
    sync();
    announce(`${monthTitle(ni)}: ${nBooks(store.monthCount(ni))}.`);
    if (gl) gl.month(dir, ni);
    else if (!reduced) {
        ui.tbl.classList.remove('is-turn');
        void ui.tbl.offsetWidth;
        ui.tbl.classList.add('is-turn');
    }
    if (keepDay !== null) requestAnimationFrame(() => cellBtn(Math.min(keepDay, monthCells(ni).len))?.focus({ preventScroll: true }));
}
ui.prev.addEventListener('click', () => changeMonth(-1));
ui.next.addEventListener('click', () => changeMonth(1));
ui.reset.addEventListener('click', () => {
    nudgeOff();
    store.reset();
    if (state.open !== null) {
        state.step = 'slots';
        state.hour = null;
        renderDay();
        measureDay();
    }
    announce(__('Tests cleared: the starting bookings are back.'));
});
on(window, 'keydown', (e) => {
    if (e.key === 'Escape' && state.open !== null && !ui.day.contains(document.activeElement)) closeDay(false);
});

/* il calendario HTML mostra questi elementi solo senza 3D: con il 3D le frecce stanno sull'intestazione */
function mountNav(mode) {
    if (mode === 'gl') {
        ui.uiLayer.append(ui.prev, ui.next);
        ui.prev.classList.add('c2__nav--gl');
        ui.next.classList.add('c2__nav--gl');
    } else {
        ui.head.prepend(ui.prev);
        ui.head.append(ui.next);
        for (const b of [ui.prev, ui.next]) {
            b.classList.remove('c2__nav--gl');
            b.style.transform = '';
        }
    }
}

/* ---------- Colori della scena: vengono dai token CSS della palette ---------- */

/* Il browser risolve var(), oklch() e color-mix(); una canvas 1x1 li converte in sRGB a 8 bit ("#rrggbb"). */
const tokenProbe = document.createElement('i');
tokenProbe.style.cssText = 'position:absolute;width:0;height:0;visibility:hidden;pointer-events:none';
const tokenPixel = document.createElement('canvas');
tokenPixel.width = 1;
tokenPixel.height = 1;
const tokenCtx = tokenPixel.getContext('2d', { willReadFrequently: true });
const tokenHex = (name, fallback) => {
    if (!tokenProbe.isConnected) host.append(tokenProbe);
    tokenProbe.style.color = fallback ? `var(${name}, var(${fallback}))` : `var(${name})`;
    tokenCtx.clearRect(0, 0, 1, 1);
    tokenCtx.fillStyle = '#000';
    tokenCtx.fillStyle = getComputedStyle(tokenProbe).color;
    tokenCtx.fillRect(0, 0, 1, 1);
    const [r, g, b] = tokenCtx.getImageData(0, 0, 1, 1).data;

    return `#${[r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('')}`;
};

/* ---------- 3D ---------- */

let renderer = null;
if (!reduced) {
    try {
        renderer = new THREE.WebGLRenderer({ canvas: $('#gl'), antialias: true, alpha: true });
    } catch {
        renderer = null;
    }
}

function initGL() {
    const stage = ui.stage;
    const cv = $('#gl');
    const pillsEl = $('#c2-pills');
    const nudge = $('#g3-nudge');
    const V = THREE.Vector3;
    const clamp = THREE.MathUtils.clamp;
    const lerp = THREE.MathUtils.lerp;

    const scene = new THREE.Scene();
    const cam = new THREE.OrthographicCamera(-1, 1, 1, -1, -80, 80);
    renderer.setClearColor(0x000000, 0);
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.shadowMap.autoUpdate = false;
    scene.add(new THREE.AmbientLight(0xffffff, 1.25));
    const sun = new THREE.DirectionalLight(0xffffff, 2.1);
    sun.position.set(-3, 12, 5);
    sun.castShadow = true;
    sun.shadow.mapSize.setScalar(matchMedia('(max-width: 900px)').matches ? 1024 : 2048);
    Object.assign(sun.shadow.camera, { left: -11, right: 11, top: 11, bottom: -11, near: 1, far: 40 });
    sun.shadow.bias = -0.0006;
    sun.shadow.radius = 4;
    scene.add(sun);

    /* misure del calendario, in unità di scena: 1 giorno = 1 unità */
    const TILE_H = 0.1;
    const PAGE_H = 0.07;
    const HEAD_H = 0.1;
    const CHIP_H = 0.115;
    const STEP = 0.145;
    const PAGE_W = 7.7;
    const pageDepth = (rows) => rows + 2.1;
    const baseDepth = (rows) => pageDepth(rows) + 0.25;
    const desk = new THREE.Group();
    desk.position.z = -4;
    scene.add(desk);

    /* Ogni colore è un token CSS (shared/palettes/<id>.css). Gli oggetti Color e i materiali registrati in LIVE
       si riallineano da soli quando cambia palette o tema (recolor, in fondo). */
    const LIVE = [];
    const live = (color, name) => {
        color.set(tokenHex(name));
        LIVE.push([color, name]);

        return color;
    };
    const col = (name) => live(new THREE.Color(), name);
    const C = { base: col('--board'), paper: col('--paper'), tile: col('--tile'), weekend: col('--tile-off'), out: col('--tile-out'), today: col('--tile-today'), hot: col('--tile-hot'), open: col('--tile-open') };
    const RING = [col('--op0'), col('--op1-ring')];
    /* colori delle trame disegnate a canvas (testi stampati) */
    const INK = {};
    const readInk = () => {
        INK.num = tokenHex('--num-ink');
        INK.muted = tokenHex('--muted');
        INK.today = tokenHex('--today');
        INK.todayInk = tokenHex('--today-ink', '--num-ink');
        INK.onPrimary = tokenHex('--primary-ink');
    };
    readInk();

    /* forme con spigoli arrotondati */
    const roundedShape = (w, d, r) => {
        const s = new THREE.Shape();
        const x = -w / 2;
        const y = -d / 2;
        s.moveTo(x + r, y);
        s.lineTo(x + w - r, y);
        s.quadraticCurveTo(x + w, y, x + w, y + r);
        s.lineTo(x + w, y + d - r);
        s.quadraticCurveTo(x + w, y + d, x + w - r, y + d);
        s.lineTo(x + r, y + d);
        s.quadraticCurveTo(x, y + d, x, y + d - r);
        s.lineTo(x, y + r);
        s.quadraticCurveTo(x, y, x + r, y);

        return s;
    };
    const slabGeo = (w, d, h, r) => {
        const g = new THREE.ExtrudeGeometry(roundedShape(w, d, r), { depth: h, bevelEnabled: false, curveSegments: 5 });
        g.rotateX(-Math.PI / 2);

        return g;
    };
    const flatPlane = (w, d, ox = 0, oz = 0) => {
        const g = new THREE.PlaneGeometry(w, d);
        g.rotateX(-Math.PI / 2);
        g.translate(ox, 0, oz);

        return g;
    };

    /* testi stampati: trame disegnate a canvas */
    const maxAniso = Math.min(8, renderer.capabilities.getMaxAnisotropy());
    const mkTex = (w, h) => {
        const c = document.createElement('canvas');
        c.width = w;
        c.height = h;
        const t = new THREE.CanvasTexture(c);
        t.colorSpace = THREE.SRGBColorSpace;
        t.anisotropy = maxAniso;

        return { c, ctx: c.getContext('2d'), t, w, h };
    };
    const FONT = '"Atkinson Hyperlegible Next", ui-sans-serif, system-ui, sans-serif';
    const CELL = 96;
    const atlas = mkTex(CELL * 8, CELL * 4);
    const dowTex = mkTex(1024, 128);
    const todayTex = mkTex(128, 128);
    let labelScale = 1;
    const paintAtlas = () => {
        const { ctx, t, w, h } = atlas;
        ctx.clearRect(0, 0, w, h);
        ctx.fillStyle = '#fff'; // maschera: il colore vero lo dà il materiale (NUM)
        ctx.font = `700 ${Math.round(CELL * 0.6)}px ${FONT}`;
        ctx.textAlign = 'left';
        ctx.textBaseline = 'top';
        for (let n = 1; n <= 31; n++) ctx.fillText(String(n), ((n - 1) % 8) * CELL + 4, Math.floor((n - 1) / 8) * CELL + 2);
        t.needsUpdate = true;
    };
    const paintDow = () => {
        const { ctx, t, w, h } = dowTex;
        ctx.clearRect(0, 0, w, h);
        ctx.font = `700 ${Math.round(h * 0.38 * labelScale)}px ${FONT}`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        DOW.forEach((n, i) => {
            ctx.fillStyle = i >= 5 ? INK.muted : INK.num;
            ctx.fillText(n, ((i + 0.5) * w) / 7, h * 0.54);
        });
        t.needsUpdate = true;
    };
    const paintToday = () => {
        const { ctx, t, w, h } = todayTex;
        ctx.clearRect(0, 0, w, h);
        ctx.fillStyle = INK.today;
        ctx.beginPath();
        ctx.arc(w / 2, h / 2, w * 0.47, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = INK.todayInk;
        ctx.font = `800 ${Math.round(h * 0.52)}px ${FONT}`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(String(TODAY.d), w / 2, h * 0.54);
        t.needsUpdate = true;
    };
    const paintTitle = (tex, mi) => {
        const { ctx, t, w, h } = tex;
        const { m, y } = MONTHS[mi];
        ctx.clearRect(0, 0, w, h);
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        const name = cap(MONTH_IT[m]);
        ctx.font = `800 ${Math.round(h * 0.66)}px ${FONT}`;
        const w1 = ctx.measureText(name + ' ').width;
        ctx.font = `500 ${Math.round(h * 0.66)}px ${FONT}`;
        const w2 = ctx.measureText(String(y)).width;
        const x0 = (w - w1 - w2) / 2;
        ctx.fillStyle = INK.onPrimary;
        ctx.font = `800 ${Math.round(h * 0.66)}px ${FONT}`;
        ctx.fillText(name, x0, h * 0.54);
        ctx.globalAlpha = 0.82;
        ctx.font = `500 ${Math.round(h * 0.66)}px ${FONT}`;
        ctx.fillText(String(y), x0 + w1, h * 0.54);
        ctx.globalAlpha = 1;
        t.needsUpdate = true;
    };
    /* un piano per numero: gli UV puntano alla cella giusta dell'atlante */
    const numGeos = [];
    for (let n = 1; n <= 31; n++) {
        const g = flatPlane(0.5, 0.5, 0.25, 0.25);
        const c0 = (n - 1) % 8;
        const r0 = Math.floor((n - 1) / 8);
        const u0 = (c0 * CELL) / atlas.w;
        const u1 = ((c0 + 1) * CELL) / atlas.w;
        const v1 = 1 - (r0 * CELL) / atlas.h;
        const v0 = 1 - ((r0 + 1) * CELL) / atlas.h;
        const uv = g.attributes.uv;
        uv.setXY(0, u0, v1);
        uv.setXY(1, u1, v1);
        uv.setXY(2, u0, v0);
        uv.setXY(3, u1, v0);
        numGeos[n] = g;
    }
    const todayGeo = flatPlane(0.5, 0.5, 0.25, 0.25);
    /* senza token il materiale non tinge la trama (i suoi colori sono già disegnati); con un token la tinge */
    const printMat = (map, token) => {
        const m = new THREE.MeshBasicMaterial({ map, color: 0xffffff, transparent: true, depthWrite: false, polygonOffset: true, polygonOffsetFactor: -2 });
        if (token) live(m.color, token);

        return m;
    };
    const NUM = { ink: printMat(atlas.t, '--num-ink'), mute: printMat(atlas.t, '--muted'), dim: printMat(atlas.t, '--faint'), today: printMat(todayTex.t) };
    const dowMat = printMat(dowTex.t);

    /* materiali e geometrie condivise */
    const stdMat = (token, params) => {
        const m = new THREE.MeshStandardMaterial(params);
        live(m.color, token);

        return m;
    };
    const baseMat = stdMat('--board', { roughness: 0.9 });
    const paperMat = stdMat('--paper', { roughness: 0.92 });
    const headMat = stdMat('--primary', { roughness: 0.55 });
    const ringMat = stdMat('--board-ring', { roughness: 0.4, metalness: 0.25 });
    const chipMats = ['--op0', '--op1'].map((token) => stdMat(token, { roughness: 0.4 }));
    const tileGeo = slabGeo(0.9, 0.9, TILE_H, 0.12);
    const chipGeo = slabGeo(0.4, 0.4, CHIP_H, 0.07);
    const pickGeo = new THREE.BoxGeometry(1, 1, 1);
    const pickMat = new THREE.MeshBasicMaterial({ visible: false });
    const bandGeo = slabGeo(7.3, 1.2, HEAD_H, 0.12);
    bandGeo.translate(0, PAGE_H, 0.75);
    const titleGeo = flatPlane(5, 0.78);
    const dowGeo = flatPlane(7, 0.875);
    const pageGeos = {};
    const pageGeo = (rows) => {
        if (!pageGeos[rows]) {
            pageGeos[rows] = slabGeo(PAGE_W, pageDepth(rows), PAGE_H, 0.16);
            pageGeos[rows].translate(0, 0, pageDepth(rows) / 2);
        }

        return pageGeos[rows];
    };

    /* base fissa con le spirali, ombra a terra */
    const baseGeo = slabGeo(PAGE_W + 0.25, baseDepth(6), 0.3, 0.2);
    baseGeo.translate(0, -0.3, baseDepth(6) / 2 - 0.12);
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.castShadow = true;
    base.receiveShadow = true;
    desk.add(base);
    const ground = new THREE.Mesh(new THREE.PlaneGeometry(40, 40), new THREE.ShadowMaterial({ opacity: 0.17 }));
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.31;
    ground.receiveShadow = true;
    desk.add(ground);
    const ringGeo = new THREE.TorusGeometry(0.2, 0.034, 8, 28);
    for (let i = 0; i < 8; i++) {
        const r = new THREE.Mesh(ringGeo, ringMat);
        r.rotation.y = Math.PI / 2;
        r.position.set(-3.15 + i * 0.9, 0.2, 0);
        r.castShadow = true;
        desk.add(r);
    }

    /* ola al cambio palette: le pile saltellano a onda dal giorno corrente (un solo arco morbido, nessun rimbalzo) */
    const wave = { on: false, t: 0, list: [] };
    const WAVE_HOP = 0.3; // durata del saltello di una pila, in secondi
    const WAVE_STEP = 0.062; // ritardo per ogni casella di distanza dal giorno corrente
    const WAVE_LIFT = 0.26; // quota massima, in unità di scena

    /* animazioni attive */
    const drops = [];
    const dying = [];
    const rings = [];
    let moving = true;
    let time = 0;
    let entering = true;
    let rowsAnim = monthCells(0).rows;
    let hotCell = null;
    let openCell = null;
    let spot = 0;
    let spotT = 0;
    let overlayPx = 0;
    let pan = 0;
    let flip = null;
    let nudgeOn = true;
    const easeOut = (x) => 1 - (1 - x) ** 3;
    const easeInOut = (x) => (x < 0.5 ? 4 * x ** 3 : 1 - (-2 * x + 2) ** 3 / 2);
    const easeOutBack = (x) => 1 + 2.4 * (x - 1) ** 3 + 1.4 * (x - 1) ** 2;
    const bounce = (x) => {
        const n = 7.5625;
        const d = 2.75;
        if (x < 1 / d) return n * x * x;
        if (x < 2 / d) return n * (x -= 1.5 / d) * x + 0.75;
        if (x < 2.5 / d) return n * (x -= 2.25 / d) * x + 0.9375;

        return n * (x -= 2.625 / d) * x + 0.984375;
    };
    const tmp = new V();

    /* ---- pagine: una per mese, con intestazione, nomi dei giorni e caselle ---- */

    const pages = [];
    const titleTexs = [];
    function updatePick(c) {
        if (!c.pick) return;
        const n = c.chips.filter((x) => x.dying < 0).length;
        const h = TILE_H + n * STEP + 0.06;
        c.pickStack.visible = n > 0;
        c.pickStack.scale.set(0.42, h, 0.42);
        c.pickStack.position.y = PAGE_H + h / 2;
    }
    /* il numero cambia con un crossfade: il vecchio sale di qualche pixel e sfuma, il nuovo arriva dal basso */
    function setPillNumber(pill, n, animate) {
        const cur = pill.el.lastElementChild;
        if (cur && cur.textContent === String(n)) return;
        const next = document.createElement('span');
        next.className = animate && cur ? 'c2__pn is-in' : 'c2__pn';
        next.textContent = n;
        if (cur) {
            if (animate) {
                cur.classList.add('is-out');
                setTimeout(() => cur.remove(), 260);
            } else cur.remove();
        }
        pill.el.append(next);
    }
    function updatePill(c, animate = false) {
        const n = c.chips.filter((x) => x.dying < 0).length;
        if (!n) {
            if (c.pill) c.pill.el.hidden = true;

            return;
        }
        if (!c.pill) {
            const el = document.createElement('span');
            el.className = 'c2__pill';
            // all'ingresso e dopo il cambio mese un leggerissimo scaglionamento, 18 ms a casella
            if (!c.page.built) el.style.setProperty('--d', `${Math.min(c.page.pillSeq++ * 18, 420)}ms`);
            c.page.pillsEl.append(el);
            c.pill = { el, n: 0 };
        }
        c.pill.el.hidden = false;
        setPillNumber(c.pill, n, animate && c.pill.n > 0);
        c.pill.n = n;
    }
    function addChip(c, b, delay, drop) {
        const idx = c.chips.length;
        const mesh = new THREE.Mesh(chipGeo, chipMats[b.lane]);
        mesh.castShadow = true;
        mesh.receiveShadow = true;
        const to = TILE_H + idx * STEP;
        mesh.position.set(0.24, drop ? to + 9 : to, 0.02);
        mesh.visible = !drop || delay <= time;
        c.body.add(mesh);
        const ch = { mesh, b, to, from: to + 9, t: drop ? 0 : 1, delay, dying: -1, ring: drop && b.added, cell: c };
        c.chips.push(ch);
        if (drop) drops.push(ch);
        updatePick(c);
        updatePill(c, c.page.built === true);
    }
    function ring(ch) {
        const mat = new THREE.MeshBasicMaterial({ color: RING[ch.b.lane], transparent: true, opacity: 0.7, depthWrite: false });
        const mesh = new THREE.Mesh(new THREE.RingGeometry(0.28, 0.34, 40), mat);
        mesh.rotation.x = -Math.PI / 2;
        mesh.position.set(0.24, TILE_H + 0.012, 0.02);
        ch.cell.body.add(mesh);
        rings.push({ mesh, t: 0 });
    }

    function startWave() {
        if (reduced || flip || !current || !visible || document.hidden) return; // fuori vista l'ola non parte: non si accoda
        const origin = current.cells.find((c) => c.in && c.today) ?? current.cells[10];
        wave.list = [];
        for (const c of current.cells) {
            if (!c.in || !c.chips.length) continue;
            wave.list.push({ c, delay: Math.hypot(c.col - origin.col, c.row - origin.row) * WAVE_STEP });
        }
        wave.t = 0;
        wave.on = wave.list.length > 0;
        moving = true;
        kick();
    }
    function stopWave() {
        if (!wave.on) return;
        wave.on = false;
        for (const w of wave.list) for (const ch of w.c.chips) if (ch.t >= 1 && ch.dying < 0) ch.mesh.position.y = ch.to;
        moving = true;
    }

    function buildPage(mi) {
        const mc = monthCells(mi);
        const page = { mi, mc, rows: mc.rows, group: new THREE.Group(), cells: [], pick: [], pillsEl: document.createElement('div'), pillSeq: 0, built: false };
        page.pillsEl.className = 'c2__pillbox';
        page.pillsEl.hidden = true;
        pillsEl.append(page.pillsEl);
        const g = page.group;
        const slab = new THREE.Mesh(pageGeo(mc.rows), paperMat);
        slab.castShadow = true;
        slab.receiveShadow = true;
        g.add(slab);
        const band = new THREE.Mesh(bandGeo, headMat);
        band.castShadow = true;
        band.receiveShadow = true;
        g.add(band);
        const tex = mkTex(1024, 160);
        titleTexs[mi] = tex;
        paintTitle(tex, mi);
        const title = new THREE.Mesh(titleGeo, printMat(tex.t));
        title.position.set(0, PAGE_H + HEAD_H + 0.004, 0.76);
        g.add(title);
        const dow = new THREE.Mesh(dowGeo, dowMat);
        dow.position.set(0, PAGE_H + 0.004, 1.6);
        g.add(dow);

        mc.cells.forEach((cell, i) => {
            const cc = i % 7;
            const rr = Math.floor(i / 7);
            const inM = cell.in;
            const st = inM ? dayStatus(mc.y, mc.m, cell.d) : 'out';
            const closed = st === 'weekend' || st === 'holiday';
            const today = inM && isToday(mc.y, mc.m, cell.d);
            const pivot = new THREE.Group();
            pivot.position.set(cc - 3, PAGE_H, 2.5 + rr + 0.45);
            const body = new THREE.Group();
            body.position.z = -0.45;
            pivot.add(body);
            const baseCol = !inM ? C.out : today ? C.today : closed ? C.weekend : C.tile;
            const tile = new THREE.Mesh(tileGeo, new THREE.MeshStandardMaterial({ color: baseCol.clone(), roughness: 0.88 }));
            tile.scale.y = inM ? 1 : 0.5;
            tile.castShadow = inM;
            tile.receiveShadow = true;
            body.add(tile);
            const numMat = today ? NUM.today : !inM ? NUM.dim : closed || st === 'past' ? NUM.mute : NUM.ink;
            const num = new THREE.Mesh(today ? todayGeo : numGeos[cell.d], numMat);
            num.position.set(-0.42, (inM ? TILE_H : TILE_H * 0.5) + 0.004, -0.43);
            num.renderOrder = 2;
            body.add(num);
            const c = { page, d: cell.d, in: inM, st, closed, today, col: cc, row: rr, pivot, body, tile, num, base: baseCol, chips: [], pill: null, lift: 0, liftV: 0, tilt: 0, tiltV: 0, t: 1, pick: null };
            tile.userData.cell = c;
            if (inM) {
                const pick = new THREE.Mesh(pickGeo, pickMat);
                pick.scale.set(0.92, 0.16, 0.92);
                pick.position.set(cc - 3, PAGE_H + 0.08, 2.5 + rr);
                pick.userData.cell = c;
                g.add(pick);
                const stack = new THREE.Mesh(pickGeo, pickMat);
                stack.position.set(cc - 3 + 0.24, 0, 2.5 + rr + 0.02);
                stack.userData.cell = c;
                g.add(stack);
                c.pick = pick;
                c.pickStack = stack;
                page.pick.push(pick, stack);
            }
            g.add(pivot);
            page.cells.push(c);
        });
        page.cells.forEach((c) => {
            if (!c.in) return;
            store.list(mc.y, mc.m, c.d).forEach((b, k) => {
                const first = mi === 0 && entering;
                addChip(c, b, first ? 0.55 + (c.row * 7 + c.col) * 0.025 + k * 0.07 : 0, first);
            });
            updatePick(c);
        });
        if (mi === 0) {
            page.cells.forEach((c) => {
                c.t = 0;
                c.body.visible = false;
            });
        }
        page.built = true;
        g.visible = false;
        g.matrixWorldAutoUpdate = false;
        desk.add(g);
        pages[mi] = page;

        return page;
    }
    const getPage = (mi) => pages[mi] ?? buildPage(mi);
    let current = getPage(0);
    showPage(current, true);
    current.pillsEl.hidden = true;

    const wed = current.cells.find((c) => c.in && c.d === 27);

    /* ---- misure, inquadratura ---- */

    let W = 1;
    let H = 1;
    let maxDpr = 1.75;
    let lost = false;
    let started = false;
    let visible = true;
    let rafId = 0;
    let pressCell = null;
    let wasActive = true;
    let idleAnim = true;
    let dirty = true;
    let lastTs = 0;
    let slowMs = 16.7;
    let slowFrames = 0;
    let dprDrops = 0;
    let tipDirty = false;
    let tipW = 0;

    function showPage(p, on) {
        p.group.visible = on;
        p.group.matrixWorldAutoUpdate = on;
        if (on) p.group.updateMatrixWorld(true);
    }
    function kick() {
        if (!started || rafId || lost) return;
        clock.getDelta();
        rafId = requestAnimationFrame(frame);
    }

    const size = () => {
        const w = stage.clientWidth;
        const h = stage.clientHeight;
        if (!w || !h) return;
        W = w;
        H = h;
        renderer.setPixelRatio(Math.min(devicePixelRatio, w < 560 ? 2 : 1.75, maxDpr));
        renderer.setSize(w, h, false);
        moving = true;
    };
    size();
    observe(new ResizeObserver(size), stage);
    observe(new ResizeObserver(() => measureDay()), ui.day);
    on(overlayMQ, 'change', () => measureDay());
    on(window, 'scroll', () => (dirty = true), { passive: true });

    /* inquadratura: si ricalcola solo quando cambiano misure, righe o inclinazione; il resto del tempo si riusa */
    const HW = 3.98;
    const pts = Array.from({ length: 8 }, () => new V());
    const probe = new V();
    const aim = (yaw, elev) => {
        cam.position.set(Math.sin(yaw) * Math.cos(elev) * 14, Math.sin(elev) * 14, Math.cos(yaw) * Math.cos(elev) * 14);
        cam.lookAt(0, 0.2, 0);
        cam.updateMatrixWorld();
    };
    const fr = { W: 0, H: 0, rows: 0, elev: 0, l: -1, r: 1, t: 1, b: -1, k: 1 };
    const refit = (yaw0, elev) => {
        if (fr.W === W && fr.H === H && Math.abs(fr.rows - rowsAnim) < 1e-4 && Math.abs(fr.elev - elev) < 1e-4) return;
        fr.W = W;
        fr.H = H;
        fr.rows = rowsAnim;
        fr.elev = elev;
        aim(yaw0, elev);
        const zb = desk.position.z - 0.12;
        const zf = desk.position.z + baseDepth(rowsAnim) - 0.12;
        pts[0].set(-HW, 0.45, zb);
        pts[1].set(-HW, -0.32, zb);
        pts[2].set(-HW, 0.05, zf);
        pts[3].set(-HW, -0.32, zf);
        pts[4].set(HW, 0.45, zb);
        pts[5].set(HW, -0.32, zb);
        pts[6].set(HW, 0.05, zf);
        pts[7].set(HW, -0.32, zf);
        let minX = Infinity;
        let maxX = -Infinity;
        let minY = Infinity;
        let maxY = -Infinity;
        for (let i = 0; i < 8; i++) {
            probe.copy(pts[i]).applyMatrix4(cam.matrixWorldInverse);
            if (probe.x < minX) minX = probe.x;
            if (probe.x > maxX) maxX = probe.x;
            if (probe.y < minY) minY = probe.y;
            if (probe.y > maxY) maxY = probe.y;
        }
        const narrow = W < 560;
        const ml = narrow ? 8 : 16;
        const mt = narrow ? 14 : 20;
        const mb = narrow ? 12 : 20;
        const cw = maxX - minX;
        const ch = maxY - minY;
        const k = Math.max(1, Math.min((W - ml * 2) / cw, (H - mt - mb) / ch));
        const padX = (W - cw * k - ml * 2) / 2;
        const padY = (H - ch * k - mt - mb) / 2;
        fr.l = minX - (ml + padX) / k;
        fr.r = maxX + (ml + padX) / k;
        fr.t = maxY + (mt + padY) / k;
        fr.b = minY - (mb + padY) / k;
        fr.k = k;
    };
    const applyCam = (dt, elev) => {
        // con la scheda sopra la scena il calendario si rimpicciolisce un poco, così resta più intestazione in vista
        const zoomOut = 1 + (overlayPx > 0 ? 0.12 : 0) * spot;
        const cx = (fr.l + fr.r) / 2;
        const cy = (fr.t + fr.b) / 2;
        const l = cx + (fr.l - cx) * zoomOut;
        const r = cx + (fr.r - cx) * zoomOut;
        let t = cy + (fr.t - cy) * zoomOut;
        let b = cy + (fr.b - cy) * zoomOut;

        /* giorno aperto con la scheda sopra la scena: il calendario scorre finché la casella resta visibile sopra la scheda */
        let want = 0;
        if (openCell && overlayPx > 0) {
            openCell.body.getWorldPosition(probe);
            probe.applyMatrix4(cam.matrixWorldInverse);
            want = Math.max(0, ((t - probe.y) / (t - b)) * H - (H - overlayPx - 52));
        }
        pan += (want - pan) * (1 - Math.exp(-dt * 7));
        if (Math.abs(pan - want) < 0.2) pan = want;
        else moving = true;
        if (pan !== 0) {
            const dy = (pan / fr.k) * zoomOut;
            t -= dy;
            b -= dy;
        }
        if (l !== cam.left || r !== cam.right || t !== cam.top || b !== cam.bottom) {
            cam.left = l;
            cam.right = r;
            cam.top = t;
            cam.bottom = b;
            cam.updateProjectionMatrix();
        }

        /* testi stampati: mai sotto circa 14 px, anche quando la scena è stretta */
        const ls = clamp(Math.round((14 / (0.35 * (fr.k / zoomOut) * Math.sin(elev))) * 20) / 20, 1, 1.45);
        if (ls !== labelScale) {
            labelScale = ls;
            for (const p of pages) if (p) for (const c of p.cells) c.num.scale.set(ls, 1, ls);
            paintDow();
        }
    };

    /* ---- puntatore ---- */

    const ray = new THREE.Raycaster();
    const ndc = new THREE.Vector2();
    let inside = false;
    let needPick = false;
    let px = 0;
    let py = 0;
    const tip = document.createElement('span');
    tip.className = 'c2__tip';
    tip.setAttribute('aria-hidden', 'true');
    pillsEl.append(tip);
    const tipText = (c) => {
        const { y, m } = cur();
        const st = dayStatus(y, m, c.d);
        const n = store.list(y, m, c.d).length;
        const base = dayLabel(y, m, c.d) + (isToday(y, m, c.d) ? TODAY_TAG : '');
        if (st === 'weekend' || st === 'holiday') return `${base} · ${__('closed')}`;
        const free = store.freeHours(y, m, c.d).length;

        return `${base} · ${nBooks(n)}${st === 'open' ? ` · ${free ? nFree(free) : FULL}` : ''}`;
    };
    const pickAt = (x, y) => {
        const b = stage.getBoundingClientRect();
        ndc.set(((x - b.left) / b.width) * 2 - 1, -((y - b.top) / b.height) * 2 + 1);
        ray.setFromCamera(ndc, cam);
        const hits = ray.intersectObjects(current.pick, false);
        for (let i = 0; i < hits.length; i++) if (hits[i].object.visible) return hits[i].object.userData.cell;

        return null;
    };
    const setHot = (c, silent = false) => {
        if (c === hotCell) return;
        hotCell = c;
        moving = true;
        tipDirty = true;
        cv.style.cursor = c ? 'pointer' : 'default';
        if (!silent) hover(c ? dayInfo(cur().y, cur().m, c.d, true) : '');
    };
    const release = () => {
        if (pressCell) {
            pressCell = null;
            moving = true;
        }
    };
    stage.addEventListener('pointermove', (e) => {
        if (lost || e.pointerType === 'touch') return;
        inside = true;
        px = e.clientX;
        py = e.clientY;
        needPick = true;
        nudgeOff();
    });
    stage.addEventListener('pointerleave', () => {
        inside = false;
        release();
        if (!lost) setHot(null);
    });
    stage.addEventListener('pointerdown', (e) => {
        if (lost || flip || e.target.closest('.c2__nav, .c2__cal')) return;
        pressCell = pickAt(e.clientX, e.clientY);
        if (pressCell) moving = true;
    });
    stage.addEventListener('pointerup', release);
    stage.addEventListener('pointercancel', release);
    stage.addEventListener('click', (e) => {
        if (lost || e.target.closest('.c2__nav, .c2__cal')) return;
        nudgeOff();
        if (flip) return;
        const c = pickAt(e.clientX, e.clientY);
        if (!c) return;
        openDay(c.d, false);
        if (e.pointerType === 'touch' || !finePointer) setHot(null);
    });

    /* ---- ciclo di animazione ---- */

    const clock = new THREE.Clock();
    let sway = 1;
    const tmpCol = new THREE.Color();
    const SUB = 1 / 120;
    /* molla a passi fissi: lo stesso movimento a 30, 60 o 144 fps */
    const springTo = (c, key, target, dt) => {
        const kv = key + 'V';
        let x = c[key];
        let v = c[kv];
        let rem = dt;
        while (rem > 1e-6) {
            const h = rem > SUB ? SUB : rem;
            rem -= h;
            v += ((target - x) * 240 - v * 19) * h;
            x += v * h;
        }
        if (Math.abs(target - x) < 0.0006 && Math.abs(v) < 0.004) {
            x = target;
            v = 0;
        }
        c[key] = x;
        c[kv] = v;
    };
    const stepCell = (c, dt) => {
        if (!c.in) return false;
        let tinting = false;
        const liftT = c === openCell ? 0.3 : c === pressCell ? 0.03 : c === hotCell ? (c.closed ? 0.06 : 0.22) : 0;
        const tiltT = c === openCell ? 0.32 : 0;
        if (c.lift !== liftT || c.liftV !== 0 || c.tilt !== tiltT || c.tiltV !== 0) {
            springTo(c, 'lift', liftT, dt);
            springTo(c, 'tilt', tiltT, dt);
            c.pivot.position.y = PAGE_H + Math.max(0, c.lift);
            c.pivot.rotation.x = c.tilt;
            moving = true;
        }
        let target = c.base;
        if (c === openCell) target = C.open;
        else if ((c === hotCell || c === pressCell) && !c.closed) target = C.hot;
        tmpCol.copy(target);
        const nudging = nudgeOn && c === wed && c !== hotCell && c !== openCell && time > 2.4;
        if (nudging) tmpCol.lerpColors(c.base, C.hot, 0.5 + 0.5 * Math.sin(time * 3.4));
        if (spot > 0.001 && c !== openCell) tmpCol.multiplyScalar(1 - 0.07 * spot);
        const col = c.tile.material.color;
        if (nudging) col.copy(tmpCol);
        else {
            const diff = Math.abs(col.r - tmpCol.r) + Math.abs(col.g - tmpCol.g) + Math.abs(col.b - tmpCol.b);
            if (diff > 0.0015) {
                col.lerp(tmpCol, 1 - Math.exp(-dt * 14));
                tinting = true;
            } else if (diff > 0) col.copy(tmpCol);
        }

        return tinting;
    };
    /* le etichette HTML si riposizionano solo se si sono mosse di almeno mezzo decimo di pixel */
    const put = (el, x, y, anchor) => {
        if (Math.abs(x - el._x) < 0.05 && Math.abs(y - el._y) < 0.05) return;
        el._x = x;
        el._y = y;
        el.style.transform = `translate3d(${x.toFixed(1)}px, ${y.toFixed(1)}px, 0) translate(${anchor})`;
    };
    const place = (el, p, anchor) => {
        tmp.copy(p).project(cam);
        put(el, (tmp.x * 0.5 + 0.5) * W, (-tmp.y * 0.5 + 0.5) * H, anchor);
    };
    /* le frecce restano in vista anche quando il calendario scorre sotto la scheda;
       seguono la scena con un leggero smorzamento e stanno ferme mentre il puntatore è sopra di loro */
    let navSettling = false;
    const placeNav = (el, p, dt) => {
        tmp.copy(p).project(cam);
        const tx = (tmp.x * 0.5 + 0.5) * W;
        const ty = Math.min(Math.max((-tmp.y * 0.5 + 0.5) * H, 30), H - 30);
        if (el._cx === undefined) {
            el._cx = tx;
            el._cy = ty;
        } else if (!el._hold) {
            const k = 1 - Math.exp(-dt * 18);
            el._cx = Math.abs(tx - el._cx) < 0.05 ? tx : el._cx + (tx - el._cx) * k;
            el._cy = Math.abs(ty - el._cy) < 0.05 ? ty : el._cy + (ty - el._cy) * k;
        }
        if (el._cx !== tx || el._cy !== ty) navSettling = true;
        put(el, el._cx, el._cy, '-50%, -50%');
    };
    for (const b of [ui.prev, ui.next]) {
        b.addEventListener('pointerenter', () => (b._hold = true));
        for (const t of ['pointerleave', 'pointercancel']) {
            b.addEventListener(t, () => {
                b._hold = false;
                dirty = true;
            });
        }
    }
    const navL = new V(-3.3, 0.26, desk.position.z + 0.75);
    const navR = new V(3.3, 0.26, desk.position.z + 0.75);
    const pillPos = new V();

    function finishFlip() {
        const f = flip;
        showPage(f.from, false);
        f.from.group.rotation.x = 0;
        f.from.group.position.y = 0;
        f.to.group.rotation.x = 0;
        f.to.group.position.y = 0;
        current = f.to;
        current.pillsEl.hidden = false;
        rowsAnim = current.rows;
        flip = null;
        needPick = true;
        dirty = true;
        moving = true;
        flipDone();
    }

    let warmStep = 0;
    const warm = () => {
        if (lost || warmStep >= MONTHS.length) return;
        const mi = warmStep++;
        getPage(mi);
        renderer.initTexture(titleTexs[mi].t);
        (window.requestIdleCallback ?? ((f) => setTimeout(f, 60)))(warm);
    };

    const frame = (ts) => {
        rafId = 0;
        if (destroyed || lost || !visible || document.hidden) return;
        rafId = requestAnimationFrame(frame);

        // niente da disegnare: la scena dorme; con solo l'oscillazione lenta si disegna a 30 fps
        const idleOnly = !wasActive && !moving && !dirty && !needPick;
        if (idleOnly && (!idleAnim || ts - lastTs < 30)) return;
        const dtMs = ts - lastTs;
        lastTs = ts;
        const dt = Math.min(0.1, clock.getDelta());
        if (root.classList.contains('ready')) time += dt;

        // risoluzione adattiva: se i frame attivi restano lenti, si scende di un quarto di pixel per volta
        if (wasActive && dtMs < 120) {
            slowMs += (dtMs - slowMs) * 0.08;
            if (slowMs > 30) {
                if (++slowFrames > 60 && dprDrops < 3 && renderer.getPixelRatio() > 1) {
                    dprDrops++;
                    maxDpr = Math.max(1, renderer.getPixelRatio() - 0.25);
                    slowFrames = 0;
                    slowMs = 16.7;
                    size();
                }
            } else slowFrames = 0;
        }

        let any = false;
        let tinting = false;

        spot += (spotT - spot) * (1 - Math.exp(-dt * 8));
        if (Math.abs(spotT - spot) < 0.002) spot = spotT;
        else any = true;

        // ingresso: le caselle salgono a onda, poi cadono le pile
        if (entering) {
            let done = true;
            for (const c of pages[0].cells) {
                if (c.t < 1) {
                    c.t = clamp((time - 0.1 - (c.row + c.col) * 0.05) / 0.75, 0, 1);
                    c.body.scale.setScalar(Math.max(easeOutBack(c.t), 0.001));
                    c.body.visible = c.t > 0;
                    if (c.t < 1) done = false;
                }
            }
            if (done && time > 0.2) entering = false;
            any = true;
        }
        if (!entering && drops.length === 0 && current === pages[0] && current.pillsEl.hidden && !flip) current.pillsEl.hidden = false;
        if (!entering && warmStep === 0 && drops.length === 0) warm();

        for (let i = drops.length - 1; i >= 0; i--) {
            const ch = drops[i];
            any = true;
            if (time < ch.delay) {
                ch.mesh.visible = false;
                continue;
            }
            ch.mesh.visible = true;
            const was = ch.t;
            ch.t = Math.min(1, ch.t + dt / 0.95);
            ch.mesh.position.y = ch.from + (ch.to - ch.from) * bounce(ch.t);
            if (ch.ring && was < 0.36 && ch.t >= 0.36) ring(ch);
            if (ch.t >= 1) drops.splice(i, 1);
        }
        for (let i = dying.length - 1; i >= 0; i--) {
            const ch = dying[i];
            any = true;
            ch.dying += dt / 0.25;
            ch.mesh.scale.y = Math.max(0.001, 1 - ch.dying);
            if (ch.dying >= 1) {
                ch.cell.body.remove(ch.mesh);
                ch.cell.chips.splice(ch.cell.chips.indexOf(ch), 1);
                dying.splice(i, 1);
                updatePick(ch.cell);
            }
        }
        for (let i = rings.length - 1; i >= 0; i--) {
            const r = rings[i];
            any = true;
            r.t += dt / 0.8;
            r.mesh.scale.setScalar(1 + r.t * 3);
            r.mesh.material.opacity = 0.7 * (1 - r.t);
            if (r.t >= 1) {
                r.mesh.parent.remove(r.mesh);
                r.mesh.geometry.dispose();
                r.mesh.material.dispose();
                rings.splice(i, 1);
            }
        }

        if (wave.on) {
            any = true;
            wave.t += dt;
            let alive = false;
            for (const w of wave.list) {
                w.c.chips.forEach((ch, idx) => {
                    if (ch.t < 1 || ch.dying >= 0) return;
                    const u = (wave.t - w.delay - idx * 0.02) / WAVE_HOP;
                    if (u < 1) alive = true;
                    const s = u <= 0 || u >= 1 ? 0 : Math.sin(Math.PI * u);
                    ch.mesh.position.y = ch.to + s * s * (3 - 2 * s) * WAVE_LIFT;
                });
            }
            if (!alive) wave.on = false;
        }

        // cambio mese: la pagina corrente gira sopra la spirale e rivela la successiva (o torna giù)
        if (flip) {
            any = true;
            flip.t = Math.min(1, flip.t + dt / 0.95);
            const e = easeInOut(flip.t);
            rowsAnim = lerp(flip.rowsFrom, flip.rowsTo, e);
            if (flip.dir > 0) {
                flip.from.group.rotation.x = -Math.PI * e;
                flip.to.group.position.y = -0.2 * (1 - e);
            } else {
                flip.to.group.rotation.x = -Math.PI * (1 - e);
                flip.from.group.position.y = -0.2 * e;
            }
            if (flip.t >= 1) finishFlip();
        }
        const baseScale = baseDepth(rowsAnim) / baseDepth(6);
        if (base.scale.z !== baseScale) base.scale.z = baseScale;

        if (flip) {
            for (const c of flip.from.cells) tinting = stepCell(c, dt) || tinting;
            for (const c of flip.to.cells) tinting = stepCell(c, dt) || tinting;
        } else {
            for (const c of current.cells) tinting = stepCell(c, dt) || tinting;
        }

        // inquadratura: più dall'alto quando si scorre la pagina
        const s = easeOut(clamp(scrollY / (innerHeight * 0.9), 0, 1));
        // sopra le frecce la scena si ferma in fretta, così nulla si muove sotto il puntatore
        const hold = ui.prev._hold || ui.next._hold;
        const still = inside || hold;
        sway += ((still ? 0 : 1) - sway) * (1 - Math.exp(-dt * (hold ? 8 : 3)));
        if (sway < 0.002 && still) sway = 0;
        const narrow = W < 560;
        const elev = lerp(narrow ? 1.04 : 0.94, narrow ? 1.14 : 1.08, s);
        const yaw0 = narrow ? 0.04 : 0.09;
        refit(yaw0, elev);
        aim(yaw0 + Math.sin(time * 0.45) * 0.07 * sway * (1 - s), elev);
        applyCam(dt, elev);

        if (inside && !flip && (needPick || sway > 0.004)) {
            setHot(pickAt(px, py));
            needPick = false;
        }
        wasActive = any || moving || tinting;
        idleAnim = sway > 0.002 || (nudgeOn && time > 2.4 && !openCell);
        if (any || moving) renderer.shadowMap.needsUpdate = true;
        moving = false;
        dirty = false;
        renderer.render(scene, cam);

        // etichette HTML ancorate alla scena (dopo il rendering: le matrici sono aggiornate)
        navSettling = false;
        placeNav(ui.prev, navL, dt);
        placeNav(ui.next, navR, dt);
        if (navSettling) dirty = true;
        if (!flip) {
            for (const c of current.cells) {
                if (!c.pill || c.pill.el.hidden) continue;
                if (W < 560) pillPos.set(0.3, TILE_H, -0.3);
                else pillPos.set(-0.26, TILE_H, 0.3);
                c.body.localToWorld(pillPos);
                place(c.pill.el, pillPos, '-50%, -50%');
            }
        }
        if (nudgeOn && wed) {
            if (time > 2.6 && !flip && current === pages[0]) nudge.classList.add('is-on');
            pillPos.set(0, TILE_H, 0.1);
            wed.body.localToWorld(pillPos);
            place(nudge, pillPos, '-50%, 25%');
        }

        // etichetta sul giorno sotto il puntatore o con il focus
        const tipOn = !!hotCell && hotCell !== openCell && !flip;
        if (tipDirty) {
            tipDirty = false;
            if (hotCell) {
                tip.textContent = tipText(hotCell);
                tipW = tip.offsetWidth;
            }
            tip.classList.toggle('is-on', tipOn);
        }
        if (tipOn) {
            const n = hotCell.chips.length;
            pillPos.set(0.05, TILE_H + n * STEP + 0.12, -0.1);
            hotCell.body.localToWorld(pillPos);
            tmp.copy(pillPos).project(cam);
            const half = tipW / 2 + 6;
            put(tip, clamp((tmp.x * 0.5 + 0.5) * W, half, W - half), (-tmp.y * 0.5 + 0.5) * H, '-50%, -135%');
        }
    };

    nudgeOff = () => {
        nudgeOn = false;
        nudge.classList.remove('is-on');
        dirty = true;
    };
    nudge.textContent = __(':action a day', { action: verb });

    observe(
        new IntersectionObserver((e) => {
            visible = e[0].isIntersecting;
            if (visible) kick();
        }),
        stage,
    );
    on(document, 'visibilitychange', () => !document.hidden && kick());

    /* perdita del contesto WebGL: si passa al calendario HTML senza perdere lo stato */
    cv.addEventListener('webglcontextlost', (e) => {
        e.preventDefault();
        if (destroyed) return;
        lost = true;
        cancelAnimationFrame(rafId);
        rafId = 0;
        root.classList.add('no-gl');
        const i = views.indexOf(view);
        if (i >= 0) views.splice(i, 1);
        gl = null;
        state.busy = false;
        state.queued = null;
        mountNav('2d');
        renderTable();
        ui.month.textContent = monthTitle(state.mi);
        setHint();
    });

    /* i testi si ridisegnano appena il font è pronto */
    const repaint = () => {
        paintAtlas();
        paintDow();
        paintToday();
        titleTexs.forEach((t, i) => t && paintTitle(t, i));
        dirty = true;
    };
    repaint();
    document.fonts?.ready.then(() => !destroyed && repaint());

    /* se cambia palette o tema i colori si rileggono dai token e la scena si ridisegna */
    const recolor = () => {
        for (const [color, name] of LIVE) color.set(tokenHex(name));
        readInk();
        for (const p of pages) {
            if (p) for (const c of p.cells) c.tile.material.color.copy(c.base);
        }
        repaint();
        kick();
    };
    const restyle = new MutationObserver(() => !destroyed && recolor());
    restyle.observe(document.documentElement, { attributes: true, attributeFilter: ['data-palette', 'class'] });
    cleanups.push(() => restyle.disconnect());
    /* Evento della vetrina (shared/tema.js), lanciato DENTRO la callback della view transition: i colori si rileggono e il
       fotogramma nuovo si disegna subito, così lo snapshot "nuovo" ha già i colori giusti. detail.palette: è cambiata la palette. */
    on(window, 'palettechange', (e) => {
        recolor();
        if (visible && !lost && !document.hidden) {
            renderer.shadowMap.needsUpdate = true;
            renderer.render(scene, cam);
        }
        if (e.detail?.palette) startWave();
    });
    Promise.all(['800 40px "Atkinson Hyperlegible Next"', '700 40px "Atkinson Hyperlegible Next"', '500 40px "Atkinson Hyperlegible Next"'].map((f) => document.fonts?.load(f).catch(() => null) ?? null)).then(() => !destroyed && repaint());

    const view = {
        add(y, m, d, b) {
            const mi = MONTHS.findIndex((x) => x.y === y && x.m === m);
            const p = pages[mi];
            if (!p) return;
            const c = p.cells.find((x) => x.in && x.d === d);
            if (c) addChip(c, b, 0, true);
            tipDirty = true;
        },
        reset() {
            for (const p of pages) {
                if (!p) continue;
                for (const c of p.cells) {
                    for (const ch of c.chips) {
                        if (ch.b.added && ch.dying < 0) {
                            ch.dying = 0;
                            dying.push(ch);
                        }
                    }
                    // il contatore scende subito, la pila si accorcia in 0,25 s
                    const left = c.chips.filter((x) => !x.b.added).length;
                    if (c.pill) {
                        c.pill.el.hidden = !left;
                        if (left) setPillNumber(c.pill, left, true);
                        c.pill.n = left;
                    }
                }
            }
            tipDirty = true;
            moving = true;
        },
    };
    views.push(view);

    started = true;
    kick();

    return {
        view,
        open(d) {
            openCell = current.cells.find((c) => c.in && c.d === d) ?? null;
            spotT = 1;
            moving = true;
            tipDirty = true;
        },
        close() {
            openCell = null;
            spotT = 0;
            moving = true;
            tipDirty = true;
        },
        measure(px_) {
            if (px_ !== overlayPx) {
                overlayPx = px_;
                moving = true;
            }
        },
        hotDay(d) {
            setHot(d === null ? null : (current.cells.find((c) => c.in && c.d === d) ?? null), true);
        },
        month(dir, ni) {
            stopWave();
            const to = getPage(ni);
            const from = current;
            state.busy = true;
            hotCell = null;
            pressCell = null;
            tipDirty = true;
            showPage(to, true);
            to.group.rotation.x = dir > 0 ? 0 : -Math.PI;
            to.group.position.y = dir > 0 ? -0.2 : 0;
            from.pillsEl.hidden = true;
            to.pillsEl.hidden = true;
            flip = { dir, from, to, t: 0, rowsFrom: rowsAnim, rowsTo: to.rows };
            moving = true;
            // scena fuori vista o scheda in secondo piano: si arriva subito al mese nuovo, senza animazione
            if (!visible || document.hidden) {
                rowsAnim = to.rows;
                finishFlip();
            }
        },
    };
}

if (renderer) {
    mountNav('gl');
    gl = initGL();
} else {
    root.classList.add('no-gl');
    mountNav('2d');
}
renderTable();
sync();
setHint();

return () => {
    destroyed = true;
    cleanups.forEach((off) => off());
    cleanups.length = 0;
    demo.destroy();
    portal.destroy();
    if (renderer) {
        renderer.dispose();
        renderer.forceContextLoss();
    }
    tokenProbe.remove();
};
}
