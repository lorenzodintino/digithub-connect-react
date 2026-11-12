/** App\Support\PortalPage::layout(): what every public page of a portal shares. */
export interface PortalLayoutProps {
    name: string;
    home_url: string;
    logo: string | null;
    initials: string;
    brand: string;
    brand_contrast: string;
    privacy_url: string | null;
    terms_url: string | null;
    embed: boolean;
    year: number;
}

/** App\Support\SlotCalendar::month() */
export interface SlotMonth {
    value: string;
    heading: string;
    previous: string | null;
    next: string;
    weekdays: string[];
    days: {
        date: string;
        number: number;
        label: string;
        in_month: boolean;
        is_today: boolean;
        available: boolean;
        seats_left: number | null;
    }[];
    notes: string[];
    empty: boolean;
    nearly_full: boolean;
}

/** App\Support\SlotCalendar::day() */
export interface SlotDay {
    date: string;
    heading: string;
    slots: Slot[];
}

export interface Slot {
    value: string;
    time: string;
    other_day: string | null;
    seats: { label: string; low: boolean } | null;
    date_label: string;
    hours: string;
    portal_hours: string | null;
}
