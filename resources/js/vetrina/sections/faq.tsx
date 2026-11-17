import { __ } from '@/lib/i18n';

export function Faq() {
    return (
        <section className="section section--tight" id="domande" aria-labelledby="h-faq" data-reveal>
            <div className="wrap">
                <div className="head">
                    <h2 className="h2" id="h-faq">
                        <span className="rise">
                            <span>
                                {__("Frequently asked questions")}
                            </span>
                        </span>
                    </h2>
                </div>
                <div className="faq">
                    <div>
                        <h3>
                            {__("Does the customer have to create an account?")}
                        </h3>
                        <p>
                            {__("No. They book by leaving name, email and phone, and get a personal link to move or cancel the appointment.")}
                        </p>
                    </div>
                    <div>
                        <h3>
                            {__("Can I put booking on my own website?")}
                        </h3>
                        <p>
                            {__("Yes. The portal has a link to share and an iframe to embed in any site.")}
                        </p>
                    </div>
                    <div>
                        <h3>
                            {__("What if two customers pick the same time?")}
                        </h3>
                        <p>
                            {__("Every write to the diary is double-checked before it's saved: the second customer sees the time is no longer free.")}
                        </p>
                    </div>
                    <div>
                        <h3>
                            {__("What address do the emails come from?")}
                        </h3>
                        <p>
                            {__("They come from the platform, with your business's name as the sender. The Pro and Studio plans can use their own mail server.")}
                        </p>
                    </div>
                    <div>
                        <h3>
                            {__("Can operators see everything?")}
                        </h3>
                        <p>
                            {__("No. Each operator only sees their own diary; you see everyone's.")}
                        </p>
                    </div>
                    <div>
                        <h3>
                            {__("Which languages does it work in?")}
                        </h3>
                        <p>
                            {__("Italian and English. Customers get emails in the language they booked in and see times in their own time zone.")}
                        </p>
                    </div>
                    <div>
                        <h3>
                            {__("Can I take my data with me?")}
                        </h3>
                        <p>
                            {__("Yes. From the portal settings you download all of your data.")}
                        </p>
                    </div>
                    <div>
                        <h3>
                            {__("How do I protect access?")}
                        </h3>
                        <p>
                            {__("With two-step verification (2FA) on your account.")}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    );
}
