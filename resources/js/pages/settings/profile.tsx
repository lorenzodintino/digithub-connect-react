import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Info, Trash2 } from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';
import { FormField } from '@/components/form-field';
import { PasswordInput } from '@/components/password-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import SettingsLayout from '@/layouts/settings-layout';
import { __ } from '@/lib/i18n';
import { destroy, update } from '@/routes/profile';
import * as photo from '@/routes/profile/photo';
import { send } from '@/routes/verification';

interface Props {
    profile: {
        name: string;
        email: string;
        timezone: string;
        locale: string;
        phone: string;
        job_title: string;
        bio: string;
    };
    timezones: string[];
    hasPublicProfile: boolean;
    isBookable: boolean;
    mustVerifyEmail: boolean;
    status: string | null;
}

function SectionDivider({ label }: { label: string }) {
    return (
        <div className="flex items-center gap-3">
            <Separator className="flex-1" />
            <span className="text-sm text-muted-foreground">{label}</span>
            <Separator className="flex-1" />
        </div>
    );
}

/** Picked photos go up straight away, like the portal logo. */
function PhotoField() {
    const { auth, errors } = usePage().props;
    const user = auth.user!;
    const [uploading, setUploading] = useState(false);

    function upload(file: File | undefined): void {
        if (!file) {
            return;
        }

        router.post(photo.store.url(), { photo: file }, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setUploading(true),
            onFinish: () => setUploading(false),
        });
    }

    return (
        <FormField
            id="photo"
            label={__('Photo')}
            description={__('Shown to your customers when they pick who to meet. JPG, PNG or WebP, up to 1 MB.')}
            error={errors.photo}
        >
            <div className="mt-2 flex flex-wrap items-center gap-4">
                <Avatar className="size-16">
                    {user.avatar && <AvatarImage src={user.avatar} alt={user.name} />}
                    <AvatarFallback className="text-lg">{user.initials}</AvatarFallback>
                </Avatar>

                <div className="flex items-center gap-2">
                    <Input
                        id="photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="max-w-xs"
                        disabled={uploading}
                        onChange={(event) => {
                            upload(event.target.files?.[0]);
                            event.target.value = '';
                        }}
                    />

                    {user.avatar && (
                        <Button type="button" size="sm" variant="ghost" onClick={() => router.delete(photo.destroy.url(), { preserveScroll: true })}>
                            <Trash2 />
                            {__('Remove')}
                        </Button>
                    )}
                </div>
            </div>
        </FormField>
    );
}

function DeleteAccount() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const form = useForm({ password: '' });

    function deleteAccount(event: FormEvent): void {
        event.preventDefault();

        form.delete(destroy.url(), {
            preserveScroll: true,
            onError: () => passwordInput.current?.focus(),
            onFinish: () => form.reset(),
        });
    }

    return (
        <section className="mt-10 space-y-6">
            <div>
                <h3 className="font-medium">{__('Delete account')}</h3>
                <p className="text-sm text-muted-foreground">{__('Delete your account and all of its resources')}</p>
            </div>

            <Dialog onOpenChange={() => form.clearErrors()}>
                <DialogTrigger asChild>
                    <Button variant="destructive" data-test="delete-user-button">
                        {__('Delete account')}
                    </Button>
                </DialogTrigger>

                <DialogContent>
                    <form onSubmit={deleteAccount} className="space-y-6">
                        <div className="space-y-2">
                            <DialogTitle>{__('Are you sure you want to delete your account?')}</DialogTitle>
                            <DialogDescription>
                                {__('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.')}
                            </DialogDescription>
                        </div>

                        <FormField id="delete-password" label={__('Password')} error={form.errors.password}>
                            <PasswordInput
                                id="delete-password"
                                ref={passwordInput}
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                autoComplete="current-password"
                            />
                        </FormField>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    {__('Cancel')}
                                </Button>
                            </DialogClose>

                            <Button type="submit" variant="destructive" disabled={form.processing} data-test="confirm-delete-user-button">
                                {__('Delete account')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}

export default function ProfileSettings({ profile, timezones, hasPublicProfile, isBookable, mustVerifyEmail, status }: Props) {
    const form = useForm(profile);

    function save(event: FormEvent): void {
        event.preventDefault();

        form.patch(update.url(), { preserveScroll: true });
    }

    return (
        <>
            <Head title={__('Profile settings')} />

            <SettingsLayout heading={__('Profile')} subheading={__('Update your name and email address')}>
                <div className="my-6 space-y-6">
                    <PhotoField />

                    <form onSubmit={save} className="space-y-6">
                        <FormField id="name" label={__('Name')} error={form.errors.name}>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                required
                                autoFocus
                                autoComplete="name"
                            />
                        </FormField>

                        <div>
                            <FormField id="email" label={__('Email')} error={form.errors.email}>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    required
                                    autoComplete="email"
                                />
                            </FormField>

                            {mustVerifyEmail && (
                                <div className="mt-4 text-sm">
                                    <p>
                                        {__('Your email address is unverified.')}{' '}
                                        <Link href={send()} as="button" className="underline underline-offset-4">
                                            {__('Click here to re-send the verification email.')}
                                        </Link>
                                    </p>

                                    {status === 'verification-link-sent' && (
                                        <p className="mt-2 font-medium text-green-600 dark:text-green-400">
                                            {__('A new verification link has been sent to your email address.')}
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>

                        <SectionDivider label={__('Preferences')} />

                        <FormField
                            id="timezone"
                            label={__('Timezone')}
                            description={__('Times across the app are shown in this timezone.')}
                            error={form.errors.timezone}
                        >
                            <Select value={form.data.timezone} onValueChange={(value) => form.setData('timezone', value)}>
                                <SelectTrigger id="timezone" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {timezones.map((timezone) => (
                                        <SelectItem key={timezone} value={timezone}>
                                            {timezone}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            id="locale"
                            label={__('Language')}
                            description={__('Saved on your account, so it follows you on every device.')}
                            error={form.errors.locale}
                        >
                            <Select value={form.data.locale} onValueChange={(value) => form.setData('locale', value)}>
                                <SelectTrigger id="locale" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="it">Italiano</SelectItem>
                                    <SelectItem value="en">English</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        {hasPublicProfile && (
                            <>
                                <SectionDivider label={__('Public profile')} />

                                <p className="text-sm text-muted-foreground">{__('How customers see you on the booking page.')}</p>

                                {!isBookable && (
                                    <Alert>
                                        <Info />
                                        <AlertDescription>
                                            {__('You are not bookable at the moment, so these details are not shown publicly. Your manager can change this.')}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <FormField id="phone" label={__('Phone')} error={form.errors.phone}>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={form.data.phone}
                                        onChange={(event) => form.setData('phone', event.target.value)}
                                        autoComplete="tel"
                                    />
                                </FormField>

                                <FormField id="job_title" label={__('Role shown to customers')} error={form.errors.job_title}>
                                    <Input
                                        id="job_title"
                                        value={form.data.job_title}
                                        onChange={(event) => form.setData('job_title', event.target.value)}
                                        placeholder={__('e.g. Accountant, Consultant')}
                                    />
                                </FormField>

                                <FormField id="bio" label={__('Short bio')} error={form.errors.bio}>
                                    <Textarea id="bio" rows={3} value={form.data.bio} onChange={(event) => form.setData('bio', event.target.value)} />
                                </FormField>
                            </>
                        )}

                        <Button type="submit" disabled={form.processing} data-test="update-profile-button">
                            {__('Save')}
                        </Button>
                    </form>
                </div>

                {!mustVerifyEmail && <DeleteAccount />}
            </SettingsLayout>
        </>
    );
}
