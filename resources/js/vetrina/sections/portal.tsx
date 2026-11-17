import { __ } from '@/lib/i18n';
import qrUrl from '../qr.svg';

export function Portal() {
    return (
        <section className="section portal" id="portale" aria-labelledby="h-portale" data-reveal>
            <div className="wrap portal__grid">
                <div className="portal__text">
                    <h2 className="h2" id="h-portale">
                        <span className="rise">
                            <span>
                                {__("Your portal,")}
                            </span>
                        </span>
                        {' '}
                        <span className="rise">
                            <span style={{ "--i": "1" }}>
                                {__("with your brand")}
                            </span>
                        </span>
                    </h2>
                    <p className="lead fade" style={{ "--i": "2" }}>
                        {__("The portal carries your business's name and colour on every page. Share it with a link, embed it in your site, and every booking comes with its own QR code.")}
                    </p>
                    <div className="brandbox fade" style={{ "--i": "3" }}>
                        <p className="brandbox__hint">
                            {__("Try it: type your business name and pick a colour, and the phone changes at once. In your real portal you add your logo too.")}
                        </p>
                        <div className="field">
                            <label htmlFor="b-name">
                                {__("Business name")}
                            </label>
                            <input id="b-name" type="text" defaultValue="Studio Rossi" maxLength={26} autoComplete="off" />
                        </div>
                        <fieldset className="swatches">
                            <legend>
                                {__("Colour")}
                            </legend>
                            <div>
                                <label>
                                    <input type="radio" name="brand" defaultValue="#3a5cff" />
                                    <span style={{ "--c": "#3a5cff" }}>
                                        <span className="sr">
                                            {__("Cobalt")}
                                        </span>
                                    </span>
                                </label>
                                {' '}
                                <label>
                                    <input type="radio" name="brand" defaultValue="#0f766e" />
                                    <span style={{ "--c": "#0f766e" }}>
                                        <span className="sr">
                                            {__("Petrol green")}
                                        </span>
                                    </span>
                                </label>
                                {' '}
                                <label>
                                    <input type="radio" name="brand" defaultValue="#e11d48" />
                                    <span style={{ "--c": "#e11d48" }}>
                                        <span className="sr">
                                            {__("Raspberry red")}
                                        </span>
                                    </span>
                                </label>
                                {' '}
                                <label>
                                    <input type="radio" name="brand" defaultValue="#d97706" />
                                    <span style={{ "--c": "#d97706" }}>
                                        <span className="sr">
                                            {__("Amber")}
                                        </span>
                                    </span>
                                </label>
                                {' '}
                                <label>
                                    <input type="radio" name="brand" defaultValue="#16a34a" />
                                    <span style={{ "--c": "#16a34a" }}>
                                        <span className="sr">
                                            {__("Green")}
                                        </span>
                                    </span>
                                </label>
                                {' '}
                                <label>
                                    <input type="radio" name="brand" defaultValue="#191c22" />
                                    <span style={{ "--c": "#191c22" }}>
                                        <span className="sr">
                                            {__("Graphite")}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>
                    </div>
                </div>
                <div className="portal__scene" id="portal-scene">
                    <div className="portal__in">
                        <div className="portal__ph" id="portal-phone">
                            <div className="ph" data-sync-phone>
                                <div className="ph__bezel">
                                    <div className="ph__screen">
                                        <header className="pt-head">
                                            <span className="pt-logo" data-brand-initial>
                                                {"S"}
                                            </span>
                                            <div>
                                                <strong data-brand-name>
                                                    {"Studio Rossi"}
                                                </strong>
                                                <small>
                                                    {__("Book online")}
                                                </small>
                                            </div>
                                        </header>
                                        <div className="ph__views">
                                            <section className="ph__view is-active" data-view="services" aria-label={__("Choose a service")}>
                                                <h3 className="pt-title">
                                                    {__("Choose a service")}
                                                </h3>
                                                <button type="button" className="pt-svc" data-service="0">
                                                    <strong>
                                                        {__("First consultation")}
                                                    </strong>
                                                    <span>
                                                        {"30 min"}
                                                    </span>
                                                    <b>
                                                        {__("Free")}
                                                    </b>
                                                </button>
                                                {' '}
                                                <button type="button" className="pt-svc" data-service="1">
                                                    <strong>
                                                        {__("Tax consultation")}
                                                    </strong>
                                                    <span>
                                                        {"60 min"}
                                                    </span>
                                                    <b>
                                                        {__("€120")}
                                                    </b>
                                                </button>
                                                {' '}
                                                <button type="button" className="pt-svc" data-service="2">
                                                    <strong>
                                                        {__("VAT registration")}
                                                    </strong>
                                                    <span>
                                                        {"45 min"}
                                                    </span>
                                                    <b>
                                                        {__("€90")}
                                                    </b>
                                                </button>
                                                <div className="pt-team">
                                                    <span className="pt-av">
                                                        {"GB"}
                                                    </span>
                                                    <span className="pt-av pt-av--2">
                                                        {"LV"}
                                                    </span>
                                                    <p>
                                                        <b>
                                                            {__("The team")}
                                                        </b>
                                                        {__("Giulia Bianchi and Luca Verdi")}
                                                    </p>
                                                </div>
                                                <p className="pt-power">
                                                    {"Powered by DigitHub"}
                                                </p>
                                            </section>
                                            <section className="ph__view" data-view="time" aria-label={__("Choose a day and time")}>
                                                <h3 className="pt-title">
                                                    {__("When would you like to come?")}
                                                </h3>
                                                <div className="pt-days" data-days></div>
                                                <div className="pt-slots" data-slots></div>
                                                <p className="pt-who">
                                                    <i></i>
                                                    <span data-who>
                                                        {__("Any operator: we'll assign one")}
                                                    </span>
                                                </p>
                                            </section>
                                            <section className="ph__view" data-view="confirm" aria-label={__("Confirm your booking")}>
                                                <h3 className="pt-title">
                                                    {__("Confirm your booking")}
                                                </h3>
                                                <dl className="pt-recap" data-recap></dl>
                                                <input className="pt-input" data-name type="text" placeholder={__("Your name")} aria-label={__("Your name")} autoComplete="off" />
                                                <div className="pt-foot">
                                                    <button type="button" className="pt-btn" data-confirm>
                                                        {__("Confirm booking")}
                                                    </button>
                                                    {' '}
                                                    <button type="button" className="pt-link" data-back>
                                                        {__("Change time")}
                                                    </button>
                                                </div>
                                            </section>
                                            <section className="ph__view pt-done" data-view="done" aria-label={__("Booking confirmed")}>
                                                <span className="pt-tick">
                                                    <svg viewBox="0 0 24 24">
                                                        <path d="M20 6 9 17l-5-5" />
                                                    </svg>
                                                </span>
                                                <h3 className="pt-title">
                                                    {__("Booking confirmed")}
                                                </h3>
                                                <img className="pt-qr" src={qrUrl} alt={__("QR code to manage your booking")} width={118} height={118} />
                                                <p className="pt-mail">
                                                    <svg viewBox="0 0 24 24">
                                                        <rect x="2" y="4" width={20} height={16} rx="2" />
                                                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                                                    </svg>
                                                    {' '}
                                                    <span>
                                                        {__("We've emailed you: from there you can move or cancel.")}
                                                    </span>
                                                </p>
                                                <div className="pt-foot">
                                                    <button type="button" className="pt-btn pt-btn--soft" data-reset>
                                                        {__("Book again")}
                                                    </button>
                                                </div>
                                            </section>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="chip chip--link">
                            <svg className="i">
                                <use href="#i-link" />
                            </svg>
                            <span>
                                <small>
                                    {__("Link to share")}
                                </small>
                                <span className="mono">
                                    {"…/p/"}
                                    <b id="slug">
                                        {"studio-rossi"}
                                    </b>
                                </span>
                            </span>
                        </div>
                        <div className="chip chip--frame">
                            <svg className="i">
                                <use href="#i-globe" />
                            </svg>
                            <span>
                                <small>
                                    {__("To embed in your site")}
                                </small>
                                <span className="mono">
                                    {"<iframe src=\"…?embed=1\">"}
                                </span>
                            </span>
                        </div>
                        <div className="chip chip--qr">
                            <img src={qrUrl} alt={""} width={72} height={72} />
                            <small>
                                {__("QR code for every booking")}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
