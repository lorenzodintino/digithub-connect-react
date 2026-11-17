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
        /** What the plan of the business includes. */
        features: Record<string, boolean>;
        /** Managers only: the plan in force and the plans they can ask for. */
        plan: {
            name: string | null;
            /** The request for a bigger plan still waiting for an answer. */
            openRequest: { id: number; status: string; label: string; requested: string; color: string } | null;
            upgrades: { id: number; name: string; services: string; operators: string; bookings: string; waiting_list: string; packages: string; includes: string[] }[];
        } | null;
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
    vendor: { name: string; url: string };
    locale: string;
    auth: {
        user: AuthUser | null;
    };
    notices: LayoutNotice[];
    /** Administrators only: requests for a bigger plan waiting for an answer. */
    planRequestsOpen: number | null;
}
