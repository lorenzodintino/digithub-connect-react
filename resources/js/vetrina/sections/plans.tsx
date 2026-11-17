import { __ } from '@/lib/i18n';
import type { DemoPlan } from './request-demo';

export function Plans({ onChoose }: { onChoose: (plan: DemoPlan) => void }) {
    return (
        <section className="section plans-s" id="piani" aria-labelledby="h-piani" data-reveal>
            <div className="wrap">
                <div className="head">
                    <h2 className="h2" id="h-piani">
                        <span className="rise">
                            <span>
                                {__("Three plans, from")}
                                {' '}
                                <span data-assumed>
                                    {__("€19")}
                                </span>
                                {' '}
                                {__("a month")}
                            </span>
                        </span>
                    </h2>
                    <p className="lead fade" style={{ "--i": "1" }}>
                        {__("Changes with your business: more services, more operators, more bookings and, from the Pro plan, email from your own server.")}
                    </p>
                </div>
                <div className="plans">
                    <article className="plan" aria-labelledby="p-base">
                        <h3 className="plan__name" id="p-base">
                            {__("Basic")}
                        </h3>
                        <p className="plan__price">
                            <span data-assumed>
                                {__("€19")}
                            </span>
                            <small>
                                {__("a month")}
                            </small>
                        </p>
                        <p className="plan__desc">
                            {__("For getting started with a single operator.")}
                        </p>
                        <ul>
                            <li>
                                <span>
                                    {__("Services")}
                                </span>
                                <span>
                                    {"3"}
                                </span>
                            </li>
                            <li>
                                <span>
                                    {__("Operators")}
                                </span>
                                <span>
                                    {"1"}
                                </span>
                            </li>
                            <li>
                                <span>
                                    {__("Bookings per month")}
                                </span>
                                <span>
                                    {"100"}
                                </span>
                            </li>
                            <li>
                                <span>
                                    {__("Own mail server")}
                                </span>
                                <span>
                                    {__("no")}
                                </span>
                            </li>
                        </ul>
                        <a className="btn btn--ghost" href="#demo" onClick={() => onChoose('Base')}>
                            {__("Choose Basic")}
                        </a>
                    </article>
                    <article className="plan plan--main" aria-labelledby="p-pro">
                        <h3 className="plan__name" id="p-pro">
                            {"Pro"}
                        </h3>
                        <p className="plan__price">
                            <span data-assumed>
                                {__("€39")}
                            </span>
                            <small>
                                {__("a month")}
                            </small>
                        </p>
                        <p className="plan__desc">
                            {__("For a practice with several operators and a steady flow of bookings.")}
                        </p>
                        <ul>
                            <li>
                                <span>
                                    {__("Services")}
                                </span>
                                <span>
                                    {"15"}
                                </span>
                            </li>
                            <li>
                                <span>
                                    {__("Operators")}
                                </span>
                                <span>
                                    {"5"}
                                </span>
                            </li>
                            <li>
                                <span>
                                    {__("Bookings per month")}
                                </span>
                                <span>
                                    {__("1,000")}
                                </span>
                            </li>
                            <li>
                                <span>
                                    {__("Own mail server")}
                                </span>
                                <span>
                                    {__("yes")}
                                </span>
                            </li>
                        </ul>
                        <a className="btn btn--primary" href="#demo" onClick={() => onChoose('Pro')}>
                            {__("Choose Pro")}
                        </a>
                    </article>
                    <article className="plan" aria-labelledby="p-studio">
                        <h3 className="plan__name" id="p-studio">
                            {"Studio"}
                        </h3>
                        <p className="plan__price">
                            <span data-assumed>
                                {__("€79")}
                            </span>
                            <small>
                                {__("a month")}
                            </small>
                        </p>
                        <p className="plan__desc">
                            {__("For those who want no limits: everything unlimited and email from your own server.")}
                        </p>
                        <ul>
                            <li>
                                <span>
                                    {__("Services")}
                                </span>
                                <span>
                                    {__("unlimited")}
                                </span>
                            </li>
                            <li>
                                <span>
                                    {__("Operators")}
                                </span>
                                <span>
                                    {__("unlimited")}
                                </span>
                            </li>
                            <li>
                                <span>
                                    {__("Bookings per month")}
                                </span>
                                <span>
                                    {__("unlimited")}
                                </span>
                            </li>
                            <li>
                                <span>
                                    {__("Own mail server")}
                                </span>
                                <span>
                                    {__("yes")}
                                </span>
                            </li>
                        </ul>
                        <a className="btn btn--ghost" href="#demo" onClick={() => onChoose('Studio')}>
                            {__("Choose Studio")}
                        </a>
                    </article>
                </div>
            </div>
        </section>
    );
}
