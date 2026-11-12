import type { FlashData } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Toaster as Sonner, toast } from 'sonner';

/**
 * The toasts of the React pages. The server sends them as Inertia flash data
 * through App\Support\Toast.
 */
export function Toaster() {
    const page = usePage();
    const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'));
    const shown = useRef(new WeakSet<object>());

    useEffect(() => {
        function show(flash: FlashData): void {
            if (flash.toast && !shown.current.has(flash)) {
                shown.current.add(flash);
                toast[flash.toast.type](flash.toast.message);
            }
        }

        // A full page load can carry a toast too, and its flash event may fire
        // before this component is listening.
        show(page.flash);

        const stopListening = router.on('flash', (event) => show(event.detail.flash));

        const observer = new MutationObserver(() => setDark(document.documentElement.classList.contains('dark')));
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        return () => {
            stopListening();
            observer.disconnect();
        };
        // Only once: later toasts arrive through the flash event.
    }, []);

    return <Sonner theme={dark ? 'dark' : 'light'} position="bottom-right" richColors closeButton />;
}
