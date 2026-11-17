import { __ } from '@/lib/i18n';

export function Emails() {
    return (
        <section className="section mails" id="email" aria-labelledby="h-email" data-reveal>
            <div className="wrap">
                <div className="head">
                    <h2 className="h2" id="h-email">
                        <span className="rise">
                            <span>
                                {__("Nine emails send themselves")}
                            </span>
                        </span>
                    </h2>
                    <p className="lead fade" style={{ "--i": "1" }}>
                        {__("The sender carries your business's name and customers get the emails in the language they booked in. Pick one to see it.")}
                    </p>
                </div>
                <div className="mails__grid">
                    <div className="mails__pick fade" style={{ "--i": "2" }}>
                        <div role="group" aria-labelledby="mg-c">
                            <p className="mails__gh" id="mg-c">
                                {__("To the customer")}
                            </p>
                            <div className="mails__tiles">
                                <button type="button" className="mtile is-on" data-mail="conferma" aria-pressed="true">
                                    <svg className="i">
                                        <use href="#i-check" />
                                    </svg>
                                    {__("Confirmation")}
                                </button>
                                {' '}
                                <button type="button" className="mtile" data-mail="richiesta" aria-pressed="false">
                                    <svg className="i">
                                        <use href="#i-clock" />
                                    </svg>
                                    {__("Request received")}
                                </button>
                                {' '}
                                <button type="button" className="mtile" data-mail="promemoria" aria-pressed="false">
                                    <svg className="i">
                                        <use href="#i-cal" />
                                    </svg>
                                    {__("Reminder")}
                                </button>
                                {' '}
                                <button type="button" className="mtile" data-mail="sposta" aria-pressed="false">
                                    <svg className="i">
                                        <use href="#i-arrow" />
                                    </svg>
                                    {__("Rescheduling")}
                                </button>
                                {' '}
                                <button type="button" className="mtile" data-mail="annulla" aria-pressed="false">
                                    <svg className="i">
                                        <use href="#i-x" />
                                    </svg>
                                    {__("Cancellation")}
                                </button>
                                {' '}
                                <button type="button" className="mtile" data-mail="libero" aria-pressed="false">
                                    <svg className="i">
                                        <use href="#i-users" />
                                    </svg>
                                    {__("Spot freed up")}
                                </button>
                            </div>
                        </div>
                        <div role="group" aria-labelledby="mg-o">
                            <p className="mails__gh" id="mg-o">
                                {__("To the operator")}
                            </p>
                            <div className="mails__tiles">
                                <button type="button" className="mtile mtile--op" data-mail="o-nuova" aria-pressed="false">
                                    <svg className="i">
                                        <use href="#i-check" />
                                    </svg>
                                    {__("New booking")}
                                </button>
                                {' '}
                                <button type="button" className="mtile mtile--op" data-mail="o-annulla" aria-pressed="false">
                                    <svg className="i">
                                        <use href="#i-x" />
                                    </svg>
                                    {__("Cancellation")}
                                </button>
                                {' '}
                                <button type="button" className="mtile mtile--op" data-mail="o-sposta" aria-pressed="false">
                                    <svg className="i">
                                        <use href="#i-arrow" />
                                    </svg>
                                    {__("Rescheduling")}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div className="mail fade" style={{ "--i": "3" }}>
                        <div className="mail__top">
                            <span className="mail__logo" data-mail-logo>
                                {"S"}
                            </span>
                            <dl className="mail__meta">
                                <div>
                                    <dt>
                                        {__("From:")}
                                    </dt>
                                    <dd data-mail-from>
                                        {"Studio Rossi"}
                                    </dd>
                                </div>
                                <div>
                                    <dt>
                                        {__("To:")}
                                    </dt>
                                    <dd data-mail-to>
                                        {"Anna Ferrari"}
                                    </dd>
                                </div>
                                <div>
                                    <dt>
                                        {__("Subject:")}
                                    </dt>
                                    <dd>
                                        <b data-mail-subject>
                                            {__("Booking confirmed: Wed 18 Nov, 12:00")}
                                        </b>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div className="mail__lang" id="mail-lang" role="group" aria-label={__("Customer's language")}>
                            <span>
                                {__("Customer's language")}
                            </span>
                            {' '}
                            <button type="button" data-lang="it" aria-pressed="true">
                                {"Italiano"}
                            </button>
                            {' '}
                            <button type="button" data-lang="en" aria-pressed="false">
                                {"English"}
                            </button>
                        </div>
                        <div className="mail__body" aria-live="polite">
                            <p data-mail-hello>
                                {__("Hi Anna,")}
                            </p>
                            <p data-mail-text>
                                {__("your booking with Giulia Bianchi is confirmed. The email includes the booking's QR code.")}
                            </p>
                            <p className="mail__link" data-mail-cta>
                                {__("Move or cancel")}
                            </p>
                        </div>
                        <p className="mail__foot" data-mail-foot>
                            {__("The customer doesn't create an account: they move or cancel from a personal link.")}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    );
}
