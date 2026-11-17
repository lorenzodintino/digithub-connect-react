import { useState } from "react";
import { __ } from "@/lib/i18n";

/**
 * The questions as an accordion: one answer open at a time, opening and closing animated by CSS.
 * Closed answers are inert, so they stay out of the tab order and away from screen readers.
 */
export function Faq() {
    const [openIndex, setOpenIndex] = useState<number | null>(0);

    const questions = [
        {
            question: __("Does the customer have to create an account?"),
            answer: __(
                "No. They book by leaving name, email and phone, and get a personal link to move or cancel the appointment.",
            ),
        },
        {
            question: __("Do I need to install anything?"),
            answer: __(
                "No. It runs in the browser, on computer, tablet and phone, for you and for your customers.",
            ),
        },
        {
            question: __("Can I approve bookings myself?"),
            answer: __(
                "Yes. Service by service, you choose whether a booking is confirmed straight away or waits for your approval. The customer gets an email as soon as you decide.",
            ),
        },
        {
            question: __("What if two customers pick the same time?"),
            answer: __(
                "The time is checked again when the booking is confirmed. Whoever comes second sees it's no longer free and picks another time.",
            ),
        },
        {
            question: __("Can operators see everything?"),
            answer: __(
                "No. Each operator only sees their own diary; you see everyone's.",
            ),
        },
        {
            question: __("Can I put booking on my own website?"),
            answer: __(
                "Yes. You can share your portal's link and, from the Pro plan, embed it in your site by copying a short snippet of code.",
            ),
        },
        {
            question: __("What address do the emails come from?"),
            answer: __(
                "They go out from the platform, with your business's name as the sender. On the Pro and Studio plans you can also use your own mail server.",
            ),
        },
        {
            question: __("Which languages does it work in?"),
            answer: __(
                "Italian and English. Customers get emails in the language they booked in and see times in their own time zone.",
            ),
        },
        {
            question: __("Can I take my data with me?"),
            answer: __(
                "Yes, they're yours. From the portal settings you can download all your data whenever you want.",
            ),
        },
        {
            question: __("How do I protect access?"),
            answer: __(
                "You turn on two-step verification (2FA) on your account: besides the password, a code from your phone is required.",
            ),
        },
    ];

    return (
        <section
            className="section section--tight"
            id="domande"
            aria-labelledby="h-faq"
            data-reveal
        >
            <div className="wrap faqs">
                <div className="head">
                    <h2 className="h2" id="h-faq">
                        <span className="rise">
                            <span>{__("Frequently asked questions")}</span>
                        </span>
                    </h2>
                    <p className="lead fade" style={{ "--i": "1" }}>
                        <a className="link" href="#demo">
                            {__(
                                "Can't find your answer? Ask it in the demo form.",
                            )}
                        </a>
                    </p>
                </div>
                <div className="faq">
                    {questions.map(({ question, answer }, index) => {
                        const isOpen = openIndex === index;

                        return (
                            <div
                                key={index}
                                className={`faq__item fade${isOpen ? " is-open" : ""}`}
                                style={{
                                    "--i": String(Math.min(index, 5) + 1),
                                }}
                            >
                                <h3>
                                    <button
                                        type="button"
                                        id={`faq-q-${index}`}
                                        aria-expanded={isOpen}
                                        aria-controls={`faq-a-${index}`}
                                        onClick={() =>
                                            setOpenIndex(isOpen ? null : index)
                                        }
                                    >
                                        <span>{question}</span>
                                        <span
                                            className="faq__icon"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </h3>
                                <div
                                    className="faq__panel"
                                    id={`faq-a-${index}`}
                                    role="region"
                                    aria-labelledby={`faq-q-${index}`}
                                    inert={!isOpen}
                                >
                                    <div>
                                        <p>{answer}</p>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
