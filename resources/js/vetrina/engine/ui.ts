// @ts-nocheck -- ported from the closed showcase draft (vanilla DOM + three.js); the typed boundary is `mountAtrio`.
import { __, getLocale } from '@/lib/i18n';

/* Demo viva: il telefono del cliente e l'agenda del titolare parlano davvero fra loro.
   Il calcolo è quello del prodotto in piccolo: nessuna sovrapposizione, e con
   «operatore indifferente» vince chi ha meno prenotazioni nella settimana. */

const dayName = (offset) => new Intl.DateTimeFormat(getLocale(), { weekday: 'short' }).format(new Date(2026, 10, 16 + offset)).replace(/^./, (c) => c.toUpperCase());
const DAYS = [0, 1, 2, 3, 4].map((i) => [dayName(i), 16 + i]);
const monthShort = () => new Intl.DateTimeFormat(getLocale(), { month: 'short' }).format(new Date(2026, 10, 1)).replace('.', '');
const monthLong = () => new Intl.DateTimeFormat(getLocale(), { month: 'long' }).format(new Date(2026, 10, 1));
const OPS = ['Giulia Bianchi', 'Luca Verdi'];
const SERVICES = [
    { name: __('First consultation'), min: 30, price: __('Free') },
    { name: __('Tax consultation'), min: 60, price: __('€120') },
    { name: __('VAT registration'), min: 45, price: __('€90') },
];
const STARTS = Array.from({ length: 9 }, (_, i) => i * 30); // 09:00 … 13:00
const ROWS = 10; // 09:00 … 14:00
const SEED = [
    { day: 0, s: 0, d: 30, lane: 0, who: 'Anna Ferrari', svc: __('First consultation'), state: 'ok' },
    { day: 0, s: 90, d: 60, lane: 1, who: 'Paolo Greco', svc: __('Tax consultation'), state: 'ok' },
    { day: 1, s: 30, d: 45, lane: 0, who: 'Sara Conti', svc: __('VAT reg.'), state: 'pending' },
    { day: 1, s: 150, d: 30, lane: 1, who: 'Marco Neri', svc: __('First consultation'), state: 'ok' },
    { day: 2, s: 60, d: 60, lane: 0, who: 'Elisa Gatti', svc: __('Tax consultation'), state: 'ok' },
    { day: 2, s: 0, d: 30, lane: 1, who: 'Luca Fabbri', svc: __('First consultation'), state: 'ok' },
    { day: 3, s: 120, d: 45, lane: 1, who: 'Irene Sala', svc: __('VAT reg.'), state: 'pending' },
    { day: 4, s: 30, d: 30, lane: 0, who: 'Dario Longo', svc: __('First consultation'), state: 'ok' },
];

const hhmm = (m) => {
    const t = 9 * 60 + m;

    return String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0');
};
const el = (tag, cls, html) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html !== undefined) n.innerHTML = html;

    return n;
};
const esc = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

export class SyncDemo {
    constructor({ phone, agenda, autoplay = false }) {
        this.listeners = [];
        this.phone = phone;
        this.agendaRoot = agenda;
        this.bookings = SEED.map((b) => ({ ...b }));
        this.added = 0;
        this.choice = { svc: 0, day: 0, s: null };
        this.stopped = false;
        this.timers = [];

        if (agenda) this.buildAgenda();
        if (phone) this.bindPhone();
        if (phone && autoplay) this.watch();
    }

    /* ---- motore ---- */

    freeLanes(day, s, d) {
        return [0, 1].filter((lane) => !this.bookings.some((b) => b.day === day && b.lane === lane && s < b.s + b.d && b.s < s + d));
    }

    pickLane(day, s, d) {
        const free = this.freeLanes(day, s, d);
        if (!free.length) return null;
        const load = (lane) => this.bookings.filter((b) => b.lane === lane).length;

        return free.sort((a, b) => load(a) - load(b) || a - b)[0];
    }

    available(day, svc) {
        return STARTS.filter((s) => s + SERVICES[svc].min <= ROWS * 30 && this.pickLane(day, s, SERVICES[svc].min) !== null);
    }

    /* ---- agenda ---- */

    buildAgenda() {
        const root = this.agendaRoot;
        root.innerHTML = '';
        const ag = el('div', 'ag');
        ag.innerHTML = `
            <div class="ag__bar"><b>${__('Diary · :from–:to :month', { from: 16, to: 20, month: monthLong() })}</b>
              <div class="ag__who"><span><i></i>${OPS[0]}</span><span><i></i>${OPS[1]}</span></div>
              <div class="ag__count"><b data-count>0</b> ${__('bookings')}</div></div>
            <div class="ag__body">
              <div class="ag__head"><div></div>${DAYS.map(([n, d]) => `<div>${n}<b>${d}</b></div>`).join('')}</div>
              <div class="ag__times">${Array.from({ length: ROWS }, (_, i) => `<span>${i % 2 === 0 ? hhmm(i * 30) : ''}</span>`).join('')}</div>
              ${DAYS.map((_, i) => `<div class="ag__col" data-col="${i}" style="height:calc(var(--row)*${ROWS})"></div>`).join('')}
            </div>
            <div class="ag__toast" data-toast role="status"><i></i><span></span></div>`;
        root.appendChild(ag);
        this.ag = ag;
        this.toast = ag.querySelector('[data-toast]');
        this.count = ag.querySelector('[data-count]');
        this.bookings.forEach((b) => this.drawBlock(b, false));
        this.updateCount(false);
    }

    drawBlock(b, isNew) {
        const col = this.ag.querySelector(`[data-col="${b.day}"]`);
        const n = el('div', 'ag__blk' + (isNew ? ' is-new' : ''), `<b>${esc(b.who.split(' ')[0])}</b><span>${esc(b.svc)}</span>`);
        n.dataset.lane = b.lane;
        n.dataset.state = b.state;
        n.style.setProperty('--s', b.s);
        n.style.setProperty('--d', b.d);
        col.appendChild(n);
        if (isNew) setTimeout(() => n.classList.remove('is-new'), 4200);
    }

    updateCount(bump = true) {
        this.count.textContent = this.bookings.length;
        if (bump) {
            this.count.classList.remove('bump');
            void this.count.offsetWidth;
            this.count.classList.add('bump');
        }
    }

    say(text) {
        if (!this.toast) return;
        this.toast.querySelector('span').textContent = text;
        this.toast.classList.add('is-on');
        clearTimeout(this._toast);
        this._toast = setTimeout(() => this.toast.classList.remove('is-on'), 3600);
    }

    /* ---- telefono ---- */

    bindPhone() {
        const p = this.phone;
        this.views = [...p.querySelectorAll('[data-view]')];
        this.daysEl = p.querySelector('[data-days]');
        this.slotsEl = p.querySelector('[data-slots]');

        const onClick = (e) => {
            const t = e.target.closest('button');
            if (!t) return;
            if (e.isTrusted) this.stop();
            if (t.dataset.service !== undefined) this.pickService(+t.dataset.service);
            else if (t.classList.contains('pt-day')) this.pickDay(+t.dataset.day);
            else if (t.classList.contains('pt-slot')) this.pickSlot(+t.dataset.s);
            else if (t.hasAttribute('data-confirm')) this.confirm();
            else if (t.hasAttribute('data-back')) this.show('time', true);
            else if (t.hasAttribute('data-reset')) this.reset();
        };
        p.addEventListener('click', onClick);
        const onKey = () => this.stop();
        p.addEventListener('keydown', onKey);
        this.listeners.push(() => p.removeEventListener('click', onClick), () => p.removeEventListener('keydown', onKey));
    }

    show(name) {
        const idx = this.views.findIndex((v) => v.dataset.view === name);
        this.views.forEach((v, i) => {
            v.classList.toggle('is-active', i === idx);
            v.classList.toggle('is-past', i < idx);
        });
    }

    pickService(i) {
        this.choice.svc = i;
        const first = DAYS.findIndex((_, d) => this.available(d, i).length);
        this.pickDay(first < 0 ? 0 : first, true);
        this.show('time');
    }

    pickDay(d, silent) {
        this.choice.day = d;
        this.choice.s = null;
        this.daysEl.innerHTML = DAYS.map(([n, num], i) => `<button type="button" class="pt-day${i === d ? ' is-on' : ''}" data-day="${i}"><small>${n}</small><b>${num}</b></button>`).join('');
        const free = this.available(d, this.choice.svc);
        this.slotsEl.innerHTML = STARTS.filter((s) => s + SERVICES[this.choice.svc].min <= ROWS * 30)
            .map((s) => `<button type="button" class="pt-slot" data-s="${s}"${free.includes(s) ? '' : ' disabled'}>${hhmm(s)}</button>`)
            .join('');
        void silent;
    }

    pickSlot(s) {
        this.choice.s = s;
        const { svc, day } = this.choice;
        const lane = this.pickLane(day, s, SERVICES[svc].min);
        const recap = this.phone.querySelector('[data-recap]');
        recap.innerHTML = `
            <div><dt>${__('Service')}</dt><dd>${SERVICES[svc].name}</dd></div>
            <div><dt>${__('When')}</dt><dd>${DAYS[day][0]} ${DAYS[day][1]} ${monthShort()}, ${hhmm(s)}</dd></div>
            <div><dt>${__('With')}</dt><dd>${OPS[lane]}</dd></div>
            <div><dt>${__('Price')}</dt><dd>${SERVICES[svc].price}</dd></div>`;
        this.show('confirm');
    }

    confirm() {
        const { svc, day, s } = this.choice;
        const d = SERVICES[svc].min;
        const lane = this.pickLane(day, s, d);
        if (lane === null) {
            this.pickDay(day);
            this.show('time', true);

            return;
        }
        const who = (this.phone.querySelector('[data-name]').value || '').trim() || __('New customer');
        const b = { day, s, d, lane, who, svc: SERVICES[svc].name, state: 'ok' };
        this.bookings.push(b);
        this.added++;
        if (this.ag) {
            this.drawBlock(b, true);
            this.updateCount();
            this.say(__('New booking: :name, :service, :day :time with :operator', { name: who, service: SERVICES[svc].name, day: DAYS[day][0], time: hhmm(s), operator: OPS[lane] }));
        }
        this.show('done');
        this.phone.dispatchEvent(new CustomEvent('booked', { bubbles: true, detail: b }));
    }

    reset() {
        if (this.added >= 5) {
            this.bookings = SEED.map((b) => ({ ...b }));
            this.added = 0;
            if (this.ag) this.buildAgenda();
        }
        this.phone.querySelector('[data-name]').value = '';
        this.show('services', true);
    }

    /* ---- marchio (per la bozza con il colore scelto dal cliente) ---- */

    setBrand({ name, color }) {
        const p = this.phone;
        if (name !== undefined) {
            p.querySelector('[data-brand-name]').textContent = name || __('Your business');
            p.querySelector('[data-brand-initial]').textContent = (name || 'A').trim().charAt(0).toUpperCase() || 'A';
        }
        if (color) {
            p.style.setProperty('--brand', color);
            const n = parseInt(color.slice(1), 16);
            const lin = (v) => {
                const c = v / 255;

                return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
            };
            const L = 0.2126 * lin((n >> 16) & 255) + 0.7152 * lin((n >> 8) & 255) + 0.0722 * lin(n & 255);
            // testo bianco o grafite: vince quello con il contrasto più alto sul colore scelto
            const white = 1.05 / (L + 0.05);
            const dark = (L + 0.05) / 0.056;
            p.style.setProperty('--brand-ink', white >= dark ? 'var(--surface)' : 'var(--ink)');
        }
    }

    /* ---- il giro da solo, finché il visitatore non tocca ---- */

    destroy() {
        this.stop();
        this.listeners.forEach((off) => off());
        this.listeners = [];
        clearTimeout(this._toast);
    }

    stop() {
        this.stopped = true;
        this.timers.forEach(clearTimeout);
        this.timers = [];
        this.phone.querySelectorAll('.is-pressed').forEach((n) => n.classList.remove('is-pressed'));
    }

    later(ms, fn) {
        this.timers.push(setTimeout(() => !this.stopped && fn(), ms));
    }

    press(node, ms = 380) {
        if (!node) return;
        node.classList.add('is-pressed');
        setTimeout(() => node.classList.remove('is-pressed'), ms);
    }

    watch() {
        const io = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting) && !this.started) {
                    this.started = true;
                    io.disconnect();
                    this.play();
                }
            },
            { threshold: 0.35 },
        );
        io.observe(this.phone);
        this.listeners.push(() => io.disconnect());
    }

    play() {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const p = this.phone;
        const svc = 1;
        this.later(1400, () => this.press(p.querySelector(`[data-service="${svc}"]`)));
        this.later(1900, () => this.pickService(svc));
        this.later(3000, () => {
            const day = DAYS.findIndex((_, i) => i === 2);
            this.press(p.querySelector(`[data-day="${day}"]`));
            this.pickDay(day);
        });
        this.later(4000, () => {
            const free = this.available(2, svc);
            const s = free.find((v) => v >= 120) ?? free[0];
            this.press(p.querySelector(`[data-s="${s}"]`));
            this.later(450, () => this.pickSlot(s));
        });
        this.later(6200, () => {
            p.querySelector('[data-name]').value = 'Elena Marchi';
            this.press(p.querySelector('[data-confirm]'));
            this.later(450, () => this.confirm());
        });
        this.later(11500, () => this.reset());
    }
}
