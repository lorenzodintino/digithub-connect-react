import { createInertiaApp, router } from '@inertiajs/react';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PortalLayout from '@/layouts/portal-layout';
import { setLocale } from '@/lib/i18n';

createInertiaApp({
    title: (title, page) => (title ? `${title} - ${page.props.name}` : String(page.props.name)),
    // The public pages of a portal are the business's, the sign-in pages nobody's yet.
    layout: (name) => {
        // The home page draws its own header and footer.
        if (name === 'welcome') {
            return undefined;
        }

        if (name.startsWith('portal/')) {
            return PortalLayout;
        }

        // The sign-in pages and the licence block: one narrow column, no navigation to lead back to a closed back-office.
        return name.startsWith('auth/') || name === 'license/blocked' ? AuthLayout : AppLayout;
    },
    withApp(app, { page }) {
        setLocale(page.props.locale);

        return <TooltipProvider delayDuration={0}>{app}</TooltipProvider>;
    },
    progress: {
        color: '#737373',
    },
});

// The language changes with a full reload today, but a visit carrying ?lang=
// would change it too: keep __() in step with what the server rendered.
router.on('navigate', (event) => setLocale(event.detail.page.props.locale));
