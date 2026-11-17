/** The title and the line under it on every sign-in page. */
export function AuthHeader({ title, description }: { title: string; description: string }) {
    return (
        <div className="flex w-full flex-col gap-1 text-center">
            <h1 className="text-xl font-extrabold tracking-tight">{title}</h1>
            <p className="text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

/** What Fortify put in the session after the last step: a link sent, a password changed. */
export function AuthStatus({ status }: { status?: string | null }) {
    return status ? <div className="text-center text-sm font-medium text-success">{status}</div> : null;
}
