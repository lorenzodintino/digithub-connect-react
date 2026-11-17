import { __ } from '@/lib/i18n';
import { formatPrice, lowestPrice, type VetrinaPlan } from '../plans';

export function Hero({ name, plans }: { name: string; plans: VetrinaPlan[] }) {
    const lowest = lowestPrice(plans);

    return (
        <section className="hero" aria-labelledby="h-hero">
            <div className="wrap hero__grid">
                <div className="hero__text">
                    <h1 className="h1 hero__h1" id="h-hero">
                        <span className="rise">
                            <span>
                                {__("Appointments book themselves.")}
                            </span>
                        </span>
                    </h1>
                    <p className="lead fade" style={{ "--i": "2" }}>
                        {__(":app gives your business an online booking page and an always-tidy diary. You work, your customers book.", { app: name })}
                    </p>
                    <div className="hero__cta fade" style={{ "--i": "3" }}>
                        <a className="btn btn--primary" href="#demo">
                            {__("Request a demo")}
                            <svg className="i">
                                <use href="#i-arrow" />
                            </svg>
                        </a>
                        {' '}
                        <a className="btn btn--ghost" href="#piani">
                            {__("See the plans")}
                        </a>
                    </div>
                    <p className="small muted fade" style={{ "--i": "4" }}>
                        {lowest && `${__('Plans from :price a month', { price: formatPrice(lowest.cents, lowest.currency) })}. `}
                        {__('Portal in Italian and English.')}
                    </p>
                </div>
                <div className="g3 c2 fade" style={{ "--i": "3" }} id="grid3d">
                    <div className="g3__bar">
                        <div className="g3__title">
                            <p className="g3__h">
                                {__("Your business's calendar")}
                            </p>
                            <p className="g3__what">
                                {__("Every stack is a booking.")}
                                <span className="c2__more">
                                    {' '}
                                    {__("Open a day and book a free slot.")}
                                </span>
                            </p>
                        </div>
                        <ul className="g3__legend c2__legend" aria-label={__("Calendar legend")}>
                            <li>
                                <i className="op0"></i>
                                <span>
                                    {"Giulia"}
                                    <span className="c2__more">
                                        {' '}
                                        {"Bianchi"}
                                    </span>
                                </span>
                            </li>
                            <li>
                                <i className="op1"></i>
                                <span>
                                    {"Luca"}
                                    <span className="c2__more">
                                        {' '}
                                        {"Verdi"}
                                    </span>
                                </span>
                            </li>
                            <li>
                                <i className="c2__lg-pile"></i>
                                {__("Stack: the day's bookings")}
                            </li>
                            <li>
                                <i className="c2__lg-today"></i>
                                {__("Today")}
                            </li>
                        </ul>
                    </div>
                    <div className="c2__wrap">
                        <div className="g3__stage c2__stage" id="g3-stage">
                            <canvas id="gl" aria-hidden="true"></canvas>
                            <div className="c2__pills" id="c2-pills" aria-hidden="true"></div>
                            <div className="g3__nudge" id="g3-nudge" aria-hidden="true">
                                {__("Tap a day")}
                            </div>
                            <div className="c2__ui" id="c2-ui"></div>
                            <div className="c2__cal" id="c2-cal">
                                <div className="c2__head" id="c2-head">
                                    <button className="c2__nav" type="button" id="c2-prev" aria-label={__("Previous month")} title={__("Previous month")}>
                                        <svg className="i" aria-hidden="true" viewBox="0 0 24 24">
                                            <path d="m15 18-6-6 6-6" />
                                        </svg>
                                    </button>
                                    <h3 className="c2__month" id="c2-month">
                                        {"Novembre 2026"}
                                    </h3>
                                    <button className="c2__nav" type="button" id="c2-next" aria-label={__("Next month")} title={__("Next month")}>
                                        <svg className="i" aria-hidden="true" viewBox="0 0 24 24">
                                            <path d="m9 18 6-6-6-6" />
                                        </svg>
                                    </button>
                                </div>
                                <table className="c2__tbl" id="c2-tbl" aria-labelledby="c2-month"></table>
                            </div>
                        </div>
                        <div className="c2__dayclip" id="c2-dayclip">
                            <section className="c2__day" id="c2-day" aria-labelledby="c2-day-h">
                                <div className="c2__dayin">
                                    <header className="c2__dayhead">
                                        <div>
                                            <h3 className="c2__dayh" id="c2-day-h" tabIndex={-1}>
                                                {"Mer 18 novembre"}
                                            </h3>
                                            <p className="c2__daymeta" id="c2-day-meta"></p>
                                        </div>
                                        <button className="c2__close" type="button" id="c2-close" aria-label={__("Close the day")} title={__("Close the day")}>
                                            <svg className="i" aria-hidden="true">
                                                <use href="#i-x" />
                                            </svg>
                                        </button>
                                    </header>
                                    <div className="c2__body" id="c2-body"></div>
                                </div>
                            </section>
                        </div>
                    </div>
                    <div className="g3__foot">
                        <p className="g3__hint" id="g3-hint"></p>
                        <div className="g3__acts">
                            <p className="g3__count">
                                <b id="c2-count">
                                    {"57"}
                                </b>
                                {' '}
                                <span id="c2-count-l">
                                    {__("bookings in November")}
                                </span>
                            </p>
                            <button className="btn btn--ghost btn--sm" type="button" id="g3-reset" disabled>
                                {__("Clear my tests")}
                            </button>
                        </div>
                        <p className="sr" role="status" id="g3-live"></p>
                    </div>
                </div>
            </div>
        </section>
    );
}
