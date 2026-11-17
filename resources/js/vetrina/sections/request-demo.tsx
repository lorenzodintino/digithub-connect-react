import { type FormEvent, useState } from 'react';
import { __, trans_choice } from '@/lib/i18n';

/** Where the demo request goes. Provisional: with no sending service yet, the form opens the visitor's mail program. */
const DEMO_MAILTO = 'hello@digithub.example';

import type { VetrinaPlan } from '../plans';

type Errors = { name?: string; email?: string };

export function RequestDemo({ plan, plans, onPlanChange }: { plan: string; plans: VetrinaPlan[]; onPlanChange: (plan: string) => void }) {
    const [errors, setErrors] = useState<Errors>({});
    const [status, setStatus] = useState('');

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = new FormData(form);
        const name = String(data.get('name') ?? '').trim();
        const email = String(data.get('email') ?? '').trim();
        const business = String(data.get('business') ?? '').trim();

        const found: Errors = {
            name: name ? undefined : __('Write your name.'),
            email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? undefined : __('Write an email address with the @ sign.'),
        };
        setErrors(found);

        const missing = [found.name, found.email].filter(Boolean).length;

        if (missing > 0) {
            form.querySelector<HTMLInputElement>(found.name ? '#f-name' : '#f-mail')?.focus();
            setStatus(trans_choice('One field is missing.|:count fields are missing.', missing));

            return;
        }

        const body = [
            `${__('Your name')}: ${name}`,
            `${__('Email')}: ${email}`,
            `${__('Your business')}: ${business || '-'}`,
            `${__("The plan you're interested in")}: ${plan === 'unsure' ? __('Not sure yet') : plan}`,
        ].join('\n');

        setStatus(__('Your mail program opens with the request ready to send.'));
        window.location.href = `mailto:${DEMO_MAILTO}?subject=${encodeURIComponent(__('Demo request'))}&body=${encodeURIComponent(body)}`;
    };

    return (
        <section className="demo" id="demo" aria-labelledby="h-demo" data-reveal>
            <div className="wrap demo__grid">
                <div className="demo__text">
                    <h2 className="h2" id="h-demo">
                        <span className="rise">
                            <span>{__('Request a demo')}</span>
                        </span>{' '}
                        <span className="rise">
                            <span style={{ '--i': '1' }}>{__('for your portal')}</span>
                        </span>
                    </h2>
                    <p className="lead fade" style={{ '--i': '2' }}>
                        {__("Tell us what business you run and we'll show you what your portal looks like.")}
                    </p>
                </div>
                <div className="demo__card fade" style={{ '--i': '3' }}>
                    <form className="form" id="demo-form" noValidate onSubmit={submit}>
                        <div className="field">
                            <label htmlFor="f-name">{__('Your name')}</label>
                            <input id="f-name" name="name" type="text" autoComplete="name" required aria-describedby="e-name" aria-invalid={errors.name ? 'true' : 'false'} />
                            <p className="field__error" id="e-name">
                                {errors.name}
                            </p>
                        </div>
                        <div className="field">
                            <label htmlFor="f-mail">{__('Email')}</label>
                            <input id="f-mail" name="email" type="email" autoComplete="email" required aria-describedby="e-mail" aria-invalid={errors.email ? 'true' : 'false'} />
                            <p className="field__error" id="e-mail">
                                {errors.email}
                            </p>
                        </div>
                        <div className="field">
                            <label htmlFor="f-biz">{__('Your business')}</label>
                            <input id="f-biz" name="business" type="text" autoComplete="organization" />
                        </div>
                        <div className="field">
                            <label htmlFor="f-plan">{__("The plan you're interested in")}</label>
                            <select id="f-plan" name="plan" value={plan} onChange={(event) => onPlanChange(event.target.value)}>
                                <option value="unsure">{__('Not sure yet')}</option>
                                {plans.map((option) => (
                                    <option key={option.id} value={option.name}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <button className="btn btn--primary" type="submit">
                            {__('Request a demo')}
                            <svg className="i">
                                <use href="#i-arrow" />
                            </svg>
                        </button>
                        <p className="form__status" role="status">
                            {status}
                        </p>
                    </form>
                </div>
            </div>
        </section>
    );
}
