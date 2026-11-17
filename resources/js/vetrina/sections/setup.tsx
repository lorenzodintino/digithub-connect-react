import { __ } from '@/lib/i18n';

export function Setup() {
    return (
        <section className="section rest" id="resto" aria-labelledby="h-resto" data-reveal>
            <div className="wrap">
                <div className="head">
                    <h2 className="h2" id="h-resto">
                        <span className="rise">
                            <span>
                                {__("Set it up once, then it runs by itself.")}
                            </span>
                        </span>
                    </h2>
                    <p className="lead fade" style={{ "--i": "1" }}>
                        {__("Services, hours, operators and reports: your everyday work, with no worries.")}
                    </p>
                </div>
                <ul className="rest__list fade" style={{ "--i": "2" }}>
                    <li>
                        <svg className="i">
                            <use href="#i-layers" />
                        </svg>
                        <h3>
                            {__("Services")}
                        </h3>
                        <p>
                            {__("Duration, breaks, price, minimum notice and manual confirmation, service by service.")}
                        </p>
                    </li>
                    <li>
                        <svg className="i">
                            <use href="#i-clock" />
                        </svg>
                        <h3>
                            {__("Hours")}
                        </h3>
                        <p>
                            {__("Availability in time bands, exceptions for special days and blocks for the days you're closed.")}
                        </p>
                    </li>
                    <li>
                        <svg className="i">
                            <use href="#i-users" />
                        </svg>
                        <h3>
                            {__("Operators")}
                        </h3>
                        <p>
                            {__("Each operator only sees their own diary. With \"any operator\" the customer books and Atrio assigns a free operator.")}
                        </p>
                    </li>
                    <li>
                        <svg className="i">
                            <use href="#i-check" />
                        </svg>
                        <h3>
                            {__("Outcomes and reports")}
                        </h3>
                        <p>
                            {__("Mark each appointment as completed or no-show, then read the reports.")}
                        </p>
                    </li>
                    <li>
                        <svg className="i">
                            <use href="#i-globe" />
                        </svg>
                        <h3>
                            {__("Languages and time zone")}
                        </h3>
                        <p>
                            {__("Portal in Italian and English. Customers see times in their own time zone.")}
                        </p>
                    </li>
                    <li>
                        <svg className="i">
                            <use href="#i-door" />
                        </svg>
                        <h3>
                            {__("Data and access")}
                        </h3>
                        <p>
                            {__("Download all of your portal's data. Access is protected with two-step verification (2FA).")}
                        </p>
                    </li>
                </ul>
            </div>
        </section>
    );
}
