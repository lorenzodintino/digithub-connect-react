import { __ } from '@/lib/i18n';

export function Features() {
    return (
        <section className="stack" id="funzioni" aria-label={__("Features")}>
            <article className="panel panel--1">
                <div className="wrap panel__in">
                    <div className="panel__text">
                        <h2 className="h2">
                            {__("Never two customers in the same room")}
                        </h2>
                        <p className="lead">
                            {__("Link to each service the operator, the room and the equipment it needs. If one is already taken, Atrio offers the alternative you chose.")}
                        </p>
                    </div>
                    <div className="mini cres" role="img" aria-label={__("The service asks for Giulia and Room 1, but Room 1 is busy: the booking moves to Room 2.")}>
                        <div className="cres__axis" aria-hidden="true">
                            <span>
                                {"09:00"}
                            </span>
                            <span>
                                {"10:00"}
                            </span>
                            <span>
                                {"11:00"}
                            </span>
                            <span>
                                {"12:00"}
                            </span>
                            <span>
                                {"13:00"}
                            </span>
                        </div>
                        <div className="cres__row">
                            <b>
                                {"Giulia"}
                            </b>
                            <div className="cres__track">
                                <i className="cres__busy" style={{ "--l": "0", "--w": ".16" }}></i>
                                <i className="cres__busy" style={{ "--l": ".7", "--w": ".2" }}></i>
                                <i className="cres__op"></i>
                            </div>
                        </div>
                        <div className="cres__row">
                            <b>
                                {__("Room 1")}
                            </b>
                            <div className="cres__track">
                                <i className="cres__busy" style={{ "--l": ".2", "--w": ".42" }}></i>
                                <i className="cres__room"></i>
                            </div>
                        </div>
                        <div className="cres__row">
                            <b>
                                {__("Room 2")}
                            </b>
                            <div className="cres__track">
                                <i className="cres__busy" style={{ "--l": ".66", "--w": ".26" }}></i>
                            </div>
                        </div>
                        <p className="cres__say">
                            <span className="s0">
                                {__("Looking for Giulia and a free room")}
                            </span>
                            <span className="s1">
                                {__("Room 1 is busy")}
                            </span>
                            <span className="s2">
                                {__("Booked with Room 2")}
                            </span>
                        </p>
                    </div>
                </div>
            </article>
            <article className="panel panel--2" data-theme="dark">
                <div className="wrap panel__in">
                    <div className="panel__text">
                        <h2 className="h2">
                            {__("Several services in one booking")}
                        </h2>
                        <p className="lead">
                            {__("A package chains services: choose the order, the breaks and the operator for each step. The customer books once.")}
                        </p>
                    </div>
                    <div className="mini cpkg" role="img" aria-label={__("A package: a 40-minute treatment with Giulia, a 10-minute break, a 30-minute massage with Luca. The customer makes a single 80-minute booking.")}>
                        <div className="cpkg__bar">
                            <i className="cpkg__seg cpkg__seg--a" style={{ "--w": "40" }}>
                                <b>
                                    {__("Treatment")}
                                </b>
                                <span>
                                    {"40 min"}
                                </span>
                            </i>
                            {' '}
                            <i className="cpkg__seg cpkg__seg--gap" style={{ "--w": "10" }}></i>
                            {' '}
                            <i className="cpkg__seg cpkg__seg--b" style={{ "--w": "30" }}>
                                <b>
                                    {__("Massage")}
                                </b>
                                <span>
                                    {"30 min"}
                                </span>
                            </i>
                        </div>
                        <div className="cpkg__who">
                            <span style={{ "--w": "40" }}>
                                {"Giulia"}
                            </span>
                            {' '}
                            <span style={{ "--w": "10" }}>
                                {__("Break")}
                                <br />
                                {"10 min"}
                            </span>
                            {' '}
                            <span style={{ "--w": "30" }}>
                                {"Luca"}
                            </span>
                        </div>
                        <p className="cpkg__sum">
                            <svg className="i">
                                <use href="#i-check" />
                            </svg>
                            {__("One booking for the customer: 80 minutes")}
                        </p>
                    </div>
                </div>
            </article>
            <article className="panel panel--3">
                <div className="wrap panel__in">
                    <div className="panel__text">
                        <h2 className="h2">
                            {__("Tell people waiting when a spot opens up")}
                        </h2>
                        <p className="lead">
                            {__("When someone cancels, whoever is on the waiting list gets an email straight away, in the language they booked in.")}
                        </p>
                    </div>
                    <div className="mini cwait" role="img" aria-label={__("Marco cancels the Thursday 10:30 appointment. The spot opens up and the waiting list gets an email.")}>
                        <div className="cwait__slot">
                            <span className="cs cs--book">
                                {__("Thu 10:30 · Marco Neri")}
                            </span>
                            {' '}
                            <span className="cs cs--cancel">
                                {__("Marco Neri cancels")}
                            </span>
                            {' '}
                            <span className="cs cs--free">
                                {__("Thu 10:30 · spot free")}
                            </span>
                        </div>
                        <p className="cwait__title">
                            {__("Waiting list")}
                        </p>
                        <ul className="cwait__list">
                            <li className="w1">
                                <span className="av">
                                    {"AF"}
                                </span>
                                <span className="cwait__n">
                                    {"Anna Ferrari"}
                                </span>
                                <span className="cwait__st">
                                    <em className="st0">
                                        {__("Waiting")}
                                    </em>
                                    <em className="st1">
                                        <svg className="i">
                                            <use href="#i-mail" />
                                        </svg>
                                        {__("Email sent")}
                                    </em>
                                </span>
                            </li>
                            <li className="w2">
                                <span className="av">
                                    {"PG"}
                                </span>
                                <span className="cwait__n">
                                    {"Paolo Greco"}
                                </span>
                                <span className="cwait__st">
                                    <em className="st0">
                                        {__("Waiting")}
                                    </em>
                                    <em className="st1">
                                        <svg className="i">
                                            <use href="#i-mail" />
                                        </svg>
                                        {__("Email sent")}
                                    </em>
                                </span>
                            </li>
                            <li className="w3">
                                <span className="av">
                                    {"SC"}
                                </span>
                                <span className="cwait__n">
                                    {"Sara Conti"}
                                </span>
                                <span className="cwait__st">
                                    <em className="st0">
                                        {__("Waiting")}
                                    </em>
                                    <em className="st1">
                                        <svg className="i">
                                            <use href="#i-mail" />
                                        </svg>
                                        {__("Email sent")}
                                    </em>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </article>
        </section>
    );
}
