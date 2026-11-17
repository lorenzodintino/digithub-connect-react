import { Head, useForm } from "@inertiajs/react";
import { CircleCheck, Info, Send, TriangleAlert } from "lucide-react";
import type { FormEvent } from "react";
import { FormField } from "@/components/form-field";
import { PageHeader } from "@/components/page-header";
import { PasswordInput } from "@/components/password-input";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { __ } from "@/lib/i18n";
import * as showcaseMailRoutes from "@/routes/admin/showcase-mail";

/** App\Support\ShowcaseMailData */
interface ShowcaseMail {
    state: "off" | "failing" | "own" | "configured" | "platform";
    hasMailerSettings: boolean;
    recipient: string | null;
    recipientFromEnvironment: boolean;
    failedAt: string | null;
    verifiedAt: string | null;
    sendingAs: string;
    platformFrom: string;
    appName: string;
    error: string | null;
    errorHint: string | null;
    testRecipient: string;
    form: {
        recipient: string;
        mail_host: string;
        mail_port: string;
        mail_username: string;
        mail_password: string;
        mail_encryption: string;
        mail_from_address: string;
    };
}

function domainOf(address: string): string {
    return address.split("@")[1] ?? "";
}

function ServerError({ showcase }: { showcase: ShowcaseMail }) {
    if (!showcase.error) {
        return null;
    }

    return (
        <>
            {showcase.errorHint && <p className="mt-1">{showcase.errorHint}</p>}
            <p className="mt-1 text-xs opacity-75">{showcase.error}</p>
        </>
    );
}

/** What the requests of the home page are doing right now. */
function StateCallout({ showcase }: { showcase: ShowcaseMail }) {
    switch (showcase.state) {
        case "off":
            return (
                <Alert className="border-amber-300 text-amber-900 dark:border-amber-800 dark:text-amber-100">
                    <TriangleAlert />
                    <AlertTitle>{__("The form is not active")}</AlertTitle>
                    <AlertDescription>
                        {__(
                            "No recipient yet: the form of the home page sends visitors to our site instead. Fill in the address below to turn it on.",
                        )}
                    </AlertDescription>
                </Alert>
            );
        case "failing":
            return (
                <Alert variant="destructive">
                    <TriangleAlert />
                    <AlertTitle>{__("A request did not go out")}</AlertTitle>
                    <AlertDescription>
                        <p>
                            {__(
                                "Failed on :date, so the requests went back to leaving through the platform. The one that failed waits in the queue, where you can retry it.",
                                { date: showcase.failedAt ?? "" },
                            )}
                        </p>
                        <ServerError showcase={showcase} />
                    </AlertDescription>
                </Alert>
            );
        case "own":
            return (
                <Alert className="border-emerald-300 text-emerald-900 dark:border-emerald-800 dark:text-emerald-100">
                    <CircleCheck />
                    <AlertDescription>
                        {__("Sending as :address since the test of :date.", {
                            address: showcase.sendingAs,
                            date: showcase.verifiedAt ?? "",
                        })}
                    </AlertDescription>
                </Alert>
            );
        case "configured":
            return (
                <Alert className="border-amber-300 text-amber-900 dark:border-amber-800 dark:text-amber-100">
                    <Send />
                    <AlertTitle>{__("Configured, not proved yet")}</AlertTitle>
                    <AlertDescription>
                        <p>
                            {__(
                                "The requests keep leaving through the platform until a test message goes through this server.",
                            )}
                        </p>
                        <ServerError showcase={showcase} />
                    </AlertDescription>
                </Alert>
            );
        default:
            return (
                <Alert>
                    <Info />
                    <AlertDescription>
                        {__(
                            "Requests leave through the platform mailer, from :address. Add your own server below if you want them to leave from another address.",
                            { address: showcase.platformFrom },
                        )}
                    </AlertDescription>
                </Alert>
            );
    }
}

export default function ShowcaseMailPage({
    showcase,
}: {
    showcase: ShowcaseMail;
}) {
    const form = useForm(showcase.form);
    const test = useForm({ test_recipient: showcase.testRecipient });
    const data = form.data;
    const testErrors = test.errors as Record<string, string | undefined>;

    function save(event: FormEvent): void {
        event.preventDefault();
        form.put(showcaseMailRoutes.update.url(), {
            preserveScroll: true,
            onSuccess: () => form.setData("mail_password", ""),
        });
    }

    function sendTest(event: FormEvent): void {
        event.preventDefault();
        test.post(showcaseMailRoutes.test.url(), { preserveScroll: true });
    }

    // How a request will look once it is in the inbox: the server decides one header, the visitor the other.
    const own =
        data.mail_host !== "" && data.mail_username !== ""
            ? data.mail_from_address || data.mail_username
            : null;
    const sending = own !== null && showcase.state === "own";
    const from = sending ? own : showcase.platformFrom;
    const afterTest = sending ? null : own;
    const senderLooksForeign =
        data.mail_from_address !== "" &&
        data.mail_username !== "" &&
        domainOf(data.mail_from_address) !== domainOf(data.mail_username);

    return (
        <>
            <Head title={__("Showcase email")} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader
                    heading={__("Showcase email")}
                    subheading={__(
                        "Where the demo requests of the home page go, and which server sends them.",
                    )}
                />

                <section className="space-y-6 rounded-xl border p-5">
                    <div>
                        <h2 className="text-lg font-semibold">
                            {__("Email delivery")}
                        </h2>
                        <p className="mt-1 text-muted-foreground">
                            {__(
                                "Every request is queued and sent to the recipient with everything the visitor wrote. Without a server of its own, it leaves through the platform mailer.",
                            )}
                        </p>
                    </div>

                    <StateCallout showcase={showcase} />

                    <form onSubmit={save} className="space-y-4">
                        <FormField
                            id="showcase-recipient"
                            label={__("Recipient")}
                            description={
                                showcase.recipientFromEnvironment
                                    ? __(
                                          "Taken from the environment (DEMO_REQUEST_EMAIL) until you save one here.",
                                      )
                                    : __(
                                          "The address that receives every demo request. Replies go to the visitor.",
                                      )
                            }
                            error={form.errors.recipient}
                            className="sm:max-w-96"
                        >
                            <Input
                                id="showcase-recipient"
                                type="email"
                                value={data.recipient}
                                placeholder={showcase.recipient ?? undefined}
                                onChange={(event) =>
                                    form.setData(
                                        "recipient",
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <Separator />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="showcase-host"
                                label={__("SMTP server")}
                                description={__(
                                    "The outgoing server of your provider. Not your email address.",
                                )}
                                error={
                                    form.errors.mail_host ??
                                    testErrors.mail_host
                                }
                            >
                                <Input
                                    id="showcase-host"
                                    value={data.mail_host}
                                    placeholder="smtp.example.com"
                                    onChange={(event) =>
                                        form.setData(
                                            "mail_host",
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="showcase-port"
                                label={__("Port")}
                                error={form.errors.mail_port}
                            >
                                <Input
                                    id="showcase-port"
                                    type="number"
                                    value={data.mail_port}
                                    placeholder="587"
                                    onChange={(event) =>
                                        form.setData(
                                            "mail_port",
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="showcase-username"
                                label={__("Username")}
                                description={__(
                                    "The full email address, not just the part before the @.",
                                )}
                                error={form.errors.mail_username}
                            >
                                <Input
                                    id="showcase-username"
                                    autoComplete="off"
                                    value={data.mail_username}
                                    onChange={(event) =>
                                        form.setData(
                                            "mail_username",
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="showcase-password"
                                label={__("Password")}
                                description={
                                    showcase.hasMailerSettings
                                        ? __(
                                              "Leave empty to keep the current one.",
                                          )
                                        : undefined
                                }
                                error={form.errors.mail_password}
                            >
                                <PasswordInput
                                    id="showcase-password"
                                    autoComplete="new-password"
                                    value={data.mail_password}
                                    placeholder={
                                        showcase.hasMailerSettings
                                            ? __("Unchanged")
                                            : ""
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            "mail_password",
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="showcase-encryption"
                                label={__("Encryption")}
                                description={__(
                                    "Port 465 always uses SSL, whatever is chosen here.",
                                )}
                                error={form.errors.mail_encryption}
                            >
                                <Select
                                    value={data.mail_encryption}
                                    onValueChange={(value) =>
                                        form.setData("mail_encryption", value)
                                    }
                                >
                                    <SelectTrigger
                                        id="showcase-encryption"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="tls">
                                            STARTTLS
                                        </SelectItem>
                                        <SelectItem value="ssl">SSL</SelectItem>
                                        <SelectItem value="none">
                                            {__("None")}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                id="showcase-from"
                                label={__("Sender address")}
                                description={__(
                                    "Who the request appears to come from. It must be an address the SMTP account is allowed to send from — usually the username itself.",
                                )}
                                error={form.errors.mail_from_address}
                            >
                                <Input
                                    id="showcase-from"
                                    type="email"
                                    value={data.mail_from_address}
                                    placeholder={data.mail_username}
                                    onChange={(event) =>
                                        form.setData(
                                            "mail_from_address",
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>

                        {senderLooksForeign && (
                            <Alert className="border-amber-300 text-amber-900 dark:border-amber-800 dark:text-amber-100">
                                <TriangleAlert />
                                <AlertTitle>
                                    {__("The sender belongs to another domain")}
                                </AlertTitle>
                                <AlertDescription>
                                    {__(
                                        "You authenticate as :username but send as :sender. Most servers refuse that, and the error they return explains little. Use the same address, or leave the field empty.",
                                        {
                                            username: data.mail_username,
                                            sender: data.mail_from_address,
                                        },
                                    )}
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="rounded-xl bg-muted p-4">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {__("Requests will look like this")}
                            </p>
                            <dl className="mt-2 space-y-1 font-mono text-sm">
                                <div className="flex gap-2">
                                    <dt className="text-muted-foreground">
                                        {__("From:")}
                                    </dt>
                                    <dd className="min-w-0 truncate">
                                        {showcase.appName} &lt;{from}&gt;
                                    </dd>
                                </div>
                                {afterTest !== null && (
                                    <div className="flex gap-2">
                                        <dt className="text-muted-foreground">
                                            {__("After the test:")}
                                        </dt>
                                        <dd className="min-w-0 truncate">
                                            {showcase.appName} &lt;{afterTest}
                                            &gt;
                                        </dd>
                                    </div>
                                )}
                                <div className="flex gap-2">
                                    <dt className="text-muted-foreground">
                                        {__("To:")}
                                    </dt>
                                    <dd className="min-w-0 truncate">
                                        {data.recipient ||
                                            showcase.recipient ||
                                            "—"}
                                    </dd>
                                </div>
                                <div className="flex gap-2">
                                    <dt className="text-muted-foreground">
                                        {__("Reply-To:")}
                                    </dt>
                                    <dd className="min-w-0 truncate">
                                        {__("the address the visitor wrote")}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                {__("Save settings")}
                            </Button>
                        </div>
                    </form>

                    {showcase.hasMailerSettings && (
                        <>
                            <div className="flex items-center gap-3">
                                <Separator className="flex-1" />
                                <span className="text-sm text-muted-foreground">
                                    {__("Test it")}
                                </span>
                                <Separator className="flex-1" />
                            </div>

                            <form onSubmit={sendTest} className="space-y-2">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <FormField
                                        id="showcase-test"
                                        label={__("Send a test email to")}
                                        error={test.errors.test_recipient}
                                        className="flex-1"
                                    >
                                        <Input
                                            id="showcase-test"
                                            type="email"
                                            value={test.data.test_recipient}
                                            onChange={(event) =>
                                                test.setData(
                                                    "test_recipient",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={test.processing}
                                    >
                                        <Send />
                                        {__("Send test")}
                                    </Button>
                                </div>
                            </form>
                        </>
                    )}
                </section>
            </div>
        </>
    );
}
