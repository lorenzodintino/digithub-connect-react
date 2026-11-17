import { useEffect, useRef, useState } from 'react';
import '../../css/vetrina.css';
import { __ } from '@/lib/i18n';
import { Emails } from './sections/emails';
import { Faq } from './sections/faq';
import { Features } from './sections/features';
import { Footer } from './sections/footer';
import { Hero } from './sections/hero';
import { Icons } from './sections/icons';
import { Nav } from './sections/nav';
import { Plans } from './sections/plans';
import { Portal } from './sections/portal';
import { type DemoPlan, RequestDemo } from './sections/request-demo';
import { Setup } from './sections/setup';
import { StylePicker } from './sections/style-picker';
import { TryBooking } from './sections/try-booking';

/**
 * The single-page showcase of the product. Markup and copy are React; the 3D calendar, the booking demo, the
 * email viewer and the branded portal are drawn by the engine in `./engine`.
 * Everything lives under `.vetrina`, so none of its CSS reaches the rest of the app.
 */
export function Vetrina({ name }: { name: string }) {
    const root = useRef<HTMLDivElement>(null);
    const [plan, setPlan] = useState<DemoPlan>('unsure');

    useEffect(() => {
        const element = root.current;

        if (!element) {
            return;
        }

        /* The words show up once the fonts are there (or after 0.7s): no flash of the fallback face. */
        let frame = 0;
        const reveal = () => (frame = requestAnimationFrame(() => element.classList.add('ready')));
        const fonts = document.fonts?.ready;

        if (fonts) {
            Promise.race([fonts, new Promise((resolve) => setTimeout(resolve, 700))]).then(reveal);
        } else {
            reveal();
        }

        /* The 3D calendar, the demos and the email viewer come in their own chunk, after the page is on screen. */
        let stopEngine: (() => void) | undefined;
        let unmounted = false;
        import('./engine/atrio').then(({ mountAtrio }) => {
            if (!unmounted) {
                stopEngine = mountAtrio(element);
            }
        });
        const targets = element.querySelectorAll('[data-reveal]');
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduced || !('IntersectionObserver' in window)) {
            targets.forEach((target) => target.classList.add('is-in'));

            return () => {
                unmounted = true;
                stopEngine?.();
                cancelAnimationFrame(frame);
            };
        }

        /* Sections show themselves once, when they come into view. The content is there without this. */
        const observer = new IntersectionObserver(
            (entries) =>
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-in');
                        observer.unobserve(entry.target);
                    }
                }),
            { rootMargin: '0px 0px -10% 0px', threshold: 0.05 },
        );
        targets.forEach((target) => observer.observe(target));

        return () => {
            unmounted = true;
            stopEngine?.();
            cancelAnimationFrame(frame);
            observer.disconnect();
        };
    }, []);

    return (
        <div ref={root} className="vetrina js" id="top">
            <Icons />
            <a className="skip" href="#main">
                {__('Skip to content')}
            </a>
            <Nav name={name} />
            <main id="main">
                <Hero name={name} />
                <StylePicker />
                <TryBooking />
                <Features />
                <Setup />
                <Emails />
                <Portal />
                <Plans onChoose={setPlan} />
                <Faq />
                <RequestDemo plan={plan} onPlanChange={setPlan} />
            </main>
            <Footer name={name} />
        </div>
    );
}
