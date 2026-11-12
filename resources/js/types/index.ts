export type UserRole = 'admin' | 'manager' | 'default';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    initials: string;
    avatar: string | null;
    role: UserRole;
    tenant: {
        name: string;
        public_url: string;
    } | null;
}

/** One strip above the page, built by App\Support\LayoutNotices. */
export interface LayoutNotice {
    key: string;
    tone: 'amber' | 'red' | 'sky';
    icon: string;
    message: string;
    detail: string | null;
    link: { label: string; href: string } | null;
}

/** The props HandleInertiaRequests shares with every page. */
export interface SharedData {
    name: string;
    locale: string;
    auth: {
        user: AuthUser | null;
    };
    notices: LayoutNotice[];
}
