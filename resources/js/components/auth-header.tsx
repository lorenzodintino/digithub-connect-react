/** The title and the line under it on every sign-in page. */
export function AuthHeader({ title, description }: { title: string; description: string }) {
    return (
        <div className="flex w-full flex-col gap-1 text-center">
            <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
            <p className="text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

/** What Fortify put in the session after the last step: a link sent, a password changed. */
export function AuthStatus({ status }: { status?: string | null }) {
    return status ? <div className="text-center text-sm font-medium text-green-600 dark:text-green-400">{status}</div> : null;
}
