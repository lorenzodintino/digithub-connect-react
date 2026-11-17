import { useHttp, usePage } from "@inertiajs/react";
import { type FormEvent, useState } from "react";
import { __, trans_choice } from "@/lib/i18n";
import * as demoRequestRoutes from "@/routes/demo-request";
import type { VetrinaPlan } from "../plans";

type Errors = { name?: string; email?: string; privacy?: string };

/** The longest message the server takes (App\Http\Requests\DemoRequestRequest::MESSAGE_LIMIT). */
const MESSAGE_LIMIT = 2000;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * The demo request. With a recipient configured (admin page "Showcase email") it is sent to the server,
 * which queues an email to the administrators. Without one, the form sends the visitor to the vendor's
 * site, so a request never goes to nobody.
 */
export function RequestDemo({
    plan,
    plans,
    demoEnabled,
    onPlanChange,
}: {
    plan: string;
    plans: VetrinaPlan[];
    demoEnabled: boolean;
    onPlanChange: (plan: string) => void;
}) {
    const vendor = usePage().props.vendor;
    const http = useHttp({
        name: "",
        email: "",
        business: "",
        plan: "",
        message: "",
        privacy: false,
        website: "",
    });
    const [errors, setErrors] = useState<Errors>({});
    const [status, setStatus] = useState("");
    const [sent, setSent] = useState(false);

    const data = http.data;
    // What the visitor has to fix: the checks of this page first, then what the server answered.
    const shown: Errors = {
        name: http.errors.name,
        email: http.errors.email,
        privacy: http.errors.privacy,
        ...errors,
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = event.currentTarget;

        const found: Errors = {
            name: data.name.trim() ? undefined : __("Write your name."),
            email: EMAIL_PATTERN.test(data.email.trim())
                ? undefined
                : __("Write an email address with the @ sign."),
            privacy:
                data.privacy || !demoEnabled
                    ? undefined
                    : __("To send the request we need your consent."),
        };
        setErrors(found);
        http.clearErrors();

        const missing = [found.name, found.email, found.privacy].filter(
            Boolean,
        ).length;

        if (missing > 0) {
            form.querySelector<HTMLInputElement>(
                found.name ? "#f-name" : found.email ? "#f-mail" : "#f-privacy",
            )?.focus();
            setStatus(
                trans_choice(
                    "One field is missing.|:count fields are missing.",
                    missing,
                ),
            );

            return;
        }

        if (!demoEnabled) {
            setStatus(__("Our site opens: ask for the demo there."));
            window.open(vendor.url, "_blank", "noopener");

            return;
        }

        setStatus("");
        http.transform((current) => ({
            ...current,
            name: current.name.trim(),
            email: current.email.trim(),
            plan: plan === "unsure" ? "" : plan,
        }));
        http.post(demoRequestRoutes.store.url(), {
            onSuccess: () => {
                setSent(true);
                setStatus(__("Request sent. We'll get back to you soon."));
                http.reset();
            },
            onError: () =>
                setStatus(__("Something in the form needs another look.")),
            onHttpException: () => {
                setStatus(
                    __(
                        "We could not send your request. Try again in a few minutes.",
                    ),
                );

                return false;
            },
            onNetworkError: () => {
                setStatus(
                    __(
                        "We could not send your request. Try again in a few minutes.",
                    ),
                );

                return false;
            },
        }).catch(() => undefined);
    };

    return (
        <section
            className="demo"
            id="demo"
            aria-labelledby="h-demo"
            data-reveal
        >
            <div className="wrap demo__grid">
                <div className="demo__text">
                    <h2 className="h2" id="h-demo">
                        <span className="rise">
                            <span>{__("Request a demo")}</span>
                        </span>{" "}
                        <span className="rise">
                            <span style={{ "--i": "1" }}>
                                {__("for your portal")}
                            </span>
                        </span>
                    </h2>
                    <p className="lead fade" style={{ "--i": "2" }}>
                        {__(
                            "Tell us what business you run and we'll show you what your portal looks like.",
                        )}
                    </p>
                </div>
                <div className="demo__card fade" style={{ "--i": "3" }}>
                    <form
                        className="form"
                        id="demo-form"
                        noValidate
                        onSubmit={submit}
                    >
                        <div className="field">
                            <label htmlFor="f-name">{__("Your name")}</label>
                            <input
                                id="f-name"
                                name="name"
                                type="text"
                                autoComplete="name"
                                required
                                value={data.name}
                                onChange={(event) =>
                                    http.setData("name", event.target.value)
                                }
                                aria-describedby="e-name"
                                aria-invalid={shown.name ? "true" : "false"}
                            />
                            <p className="field__error" id="e-name">
                                {shown.name}
                            </p>
                        </div>
                        <div className="field">
                            <label htmlFor="f-mail">{__("Email")}</label>
                            <input
                                id="f-mail"
                                name="email"
                                type="email"
                                autoComplete="email"
                                required
                                value={data.email}
                                onChange={(event) =>
                                    http.setData("email", event.target.value)
                                }
                                aria-describedby="e-mail"
                                aria-invalid={shown.email ? "true" : "false"}
                            />
                            <p className="field__error" id="e-mail">
                                {shown.email}
                            </p>
                        </div>
                        <div className="field">
                            <label htmlFor="f-biz">{__("Your business")}</label>
                            <input
                                id="f-biz"
                                name="business"
                                type="text"
                                autoComplete="organization"
                                value={data.business}
                                onChange={(event) =>
                                    http.setData("business", event.target.value)
                                }
                            />
                        </div>
                        <div className="field">
                            <label htmlFor="f-plan">
                                {__("The plan you're interested in")}
                            </label>
                            <select
                                id="f-plan"
                                name="plan"
                                value={plan}
                                onChange={(event) =>
                                    onPlanChange(event.target.value)
                                }
                            >
                                <option value="unsure">
                                    {__("Not sure yet")}
                                </option>
                                {plans.map((option) => (
                                    <option key={option.id} value={option.name}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="field">
                            <label htmlFor="f-message">
                                {__("Message (optional)")}
                            </label>
                            <textarea
                                id="f-message"
                                name="message"
                                rows={4}
                                maxLength={MESSAGE_LIMIT}
                                placeholder={__(
                                    "Questions, details about your business, anything that helps us show you the right thing.",
                                )}
                                value={data.message}
                                onChange={(event) =>
                                    http.setData("message", event.target.value)
                                }
                            />
                        </div>
                        {/* The trap for robots: nobody sees it, so nobody fills it. */}
                        <div className="field field--trap" aria-hidden="true">
                            <label htmlFor="f-website">
                                {__("Leave this field empty")}
                            </label>
                            <input
                                id="f-website"
                                name="website"
                                type="text"
                                tabIndex={-1}
                                autoComplete="off"
                                value={data.website}
                                onChange={(event) =>
                                    http.setData("website", event.target.value)
                                }
                            />
                        </div>
                        {demoEnabled && (
                            <div className="field field--check">
                                <label htmlFor="f-privacy">
                                    <input
                                        id="f-privacy"
                                        name="privacy"
                                        type="checkbox"
                                        checked={data.privacy}
                                        onChange={(event) =>
                                            http.setData(
                                                "privacy",
                                                event.target.checked,
                                            )
                                        }
                                        aria-describedby="e-privacy"
                                        aria-invalid={
                                            shown.privacy ? "true" : "false"
                                        }
                                    />
                                    <span>
                                        {__(
                                            "I agree to the processing of my personal data to receive an answer to this request.",
                                        )}
                                    </span>
                                </label>
                                <p className="field__error" id="e-privacy">
                                    {shown.privacy}
                                </p>
                            </div>
                        )}
                        <button
                            className="btn btn--primary"
                            type="submit"
                            disabled={http.processing}
                        >
                            {http.processing
                                ? __("Sending…")
                                : __("Request a demo")}
                            <svg className="i">
                                <use href="#i-arrow" />
                            </svg>
                        </button>
                        <p
                            className={`form__status${sent ? " form__status--ok" : ""}`}
                            role="status"
                        >
                            {status}
                        </p>
                    </form>
                </div>
            </div>
        </section>
    );
}
