import { __ } from '@/lib/i18n';
import qrUrl from '../qr.svg';

export function TryBooking() {
    return (
        <section className="section prova" id="prova" aria-labelledby="h-prova" data-reveal>
            <div className="wrap prova__grid">
                <div className="prova__head">
                    <h2 className="h2" id="h-prova">
                        <span className="rise">
                            <span>
                                {__("Try a booking")}
                            </span>
                        </span>
                    </h2>
                    <p className="lead fade" style={{ "--i": "1" }}>
                        {__("The phone is the customer's portal, the diary is the owner's. Book a service and watch the appointment appear.")}
                    </p>
                    <ol className="steps fade" style={{ "--i": "2" }}>
                        <li>
                            {__("Tap a service on the phone.")}
                        </li>
                        <li>
                            {__("Pick a day and time, then confirm.")}
                        </li>
                        <li>
                            {__("Look at the diary: the booking is already there.")}
                        </li>
                    </ol>
                </div>
                <figure className="prova__agenda fade" style={{ "--i": "3" }}>
                    <figcaption>
                        {__("The owner's diary")}
                    </figcaption>
                    <div data-sync-agenda id="agenda2"></div>
                    <p className="prova__legend">
                        <i></i>
                        {__("Striped block: request waiting for manual confirmation.")}
                    </p>
                </figure>
                <figure className="prova__phone fade" style={{ "--i": "3" }}>
                    <figcaption>
                        {__("The customer's portal")}
                    </figcaption>
                    <p className="prova__mode" id="prova-mode" data-mode="manual">
                        {__("Tap a service to begin.")}
                    </p>
                    <div className="prova__ph">
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
                    <p className="prova__note" id="prova-note" role="status"></p>
                </figure>
            </div>
        </section>
    );
}
