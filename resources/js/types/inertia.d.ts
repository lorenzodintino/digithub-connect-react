import type { SharedData } from '@/types';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedData;
        flashDataType: {
            toast?: { type: 'success' | 'warning' | 'error'; message: string };
            /** A password generated for somebody else, shown once. */
            credentials?: { email: string; password: string };
        };
    }
}
