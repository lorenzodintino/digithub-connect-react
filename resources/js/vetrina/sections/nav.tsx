import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { __, getLocale } from '@/lib/i18n';
import { dashboard, login } from '@/routes';
import { BrandMark } from './brand-mark';

const links = [
    { href: '#stile', label: 'Style' },
    { href: '#prova', label: 'Try it' },
    { href: '#funzioni', label: 'Features' },
    { href: '#email', label: 'Email' },
    { href: '#portale', label: 'Portal' },
    { href: '#piani', label: 'Plans' },
    { href: '#domande', label: 'FAQ' },
] as const;

/** Top bar: brand, section links, the sun/moon switch and, below 900px, the full-screen menu. */
export function Nav({ name }: { name: string }) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const { dark, toggle } = useAppearance();
    const otherLocale = getLocale() === 'it' ? 'en' : 'it';

    useEffect(() => {
        const close = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', close);

        return () => window.removeEventListener('keydown', close);
    }, []);

    return (
        <header className="nav" id="nav" data-open={open || undefined}>
            <div className="wrap">
                <a className="brand" href="#top" aria-label={__(':app, back to the top of the page', { app: name })}>
                    <BrandMark />
                    {name}
                </a>
                <div className="nav__end">
                    <nav className="nav__links" id="nav-links" aria-label={__('Main')}>
                        {links.map((link) => (
                            <a key={link.href} href={link.href} onClick={() => setOpen(false)}>
                                {__(link.label)}
                            </a>
                        ))}
                        <Link href={auth.user ? dashboard.url() : login.url()}>{auth.user ? __('Dashboard') : __('Log in')}</Link>
                        <a className="btn btn--primary" href="#demo" onClick={() => setOpen(false)}>
                            {__('Request a demo')}
                        </a>
                    </nav>
                    <a
                        className="theme-btn lang-btn"
                        href={`?lang=${otherLocale}`}
                        lang={otherLocale}
                        aria-label={otherLocale === 'en' ? 'English' : 'Italiano'}
                        title={otherLocale === 'en' ? 'English' : 'Italiano'}
                    >
                        {otherLocale.toUpperCase()}
                    </a>
                    <button
                        className="theme-btn"
                        type="button"
                        data-mode={dark ? 'dark' : 'light'}
                        aria-label={dark ? __('Switch to light theme') : __('Switch to dark theme')}
                        title={dark ? __('Switch to light theme') : __('Switch to dark theme')}
                        onClick={(event) => {
                            const box = event.currentTarget.getBoundingClientRect();
                            toggle({ x: box.left + box.width / 2, y: box.top + box.height / 2 });
                        }}
                    >
                        <svg className="i theme-btn__sun" aria-hidden="true" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="4" />
                            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
                        </svg>
                        <svg className="i theme-btn__moon" aria-hidden="true" viewBox="0 0 24 24">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                        </svg>
                    </button>
                    <button
                        className="nav__toggle"
                        type="button"
                        aria-expanded={open}
                        aria-controls="nav-links"
                        aria-label={open ? __('Close the menu') : __('Open the menu')}
                        onClick={() => setOpen((value) => !value)}
                    >
                        <svg>
                            <use href={open ? '#i-x' : '#i-menu'} />
                        </svg>
                    </button>
                </div>
            </div>
        </header>
    );
}
