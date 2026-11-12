import { FormField } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** The billing section of the portal form: who pays for the portal, as the server validates it. */
export type BillingForm = {
    kind: '' | 'PERSON' | 'COMPANY';
    company_name: string;
    first_name: string;
    last_name: string;
    contact_name: string;
    tax_code: string;
    vat_number: string;
    sdi_code: string;
    pec: string;
    street: string;
    postal_code: string;
    city: string;
    province: string;
    country: string;
    email: string;
    billing_email: string;
    phone: string;
};

export const blankBilling: BillingForm = {
    kind: '',
    company_name: '',
    first_name: '',
    last_name: '',
    contact_name: '',
    tax_code: '',
    vat_number: '',
    sdi_code: '',
    pec: '',
    street: '',
    postal_code: '',
    city: '',
    province: '',
    country: 'IT',
    email: '',
    billing_email: '',
    phone: '',
};

interface Props {
    value: BillingForm;
    onChange: (field: keyof BillingForm, value: string) => void;
    /** The error of a field of the section, as the server wrote it. */
    error: (field: keyof BillingForm) => string | undefined;
}

/** The id of the control of a field, which the form uses to move the cursor to the first mistake. */
export function billingFieldId(field: string): string {
    return `portal-billing-${field.replace(/_/g, '-')}`;
}

/**
 * Who pays for the portal. The fields that apply depend on the kind: a private
 * person has a name and a tax code, a business a name, a VAT number and an SDI
 * code or a PEC. The address and the emails are the same for both.
 */
export function BillingFields({ value, onChange, error }: Props) {
    function text(field: keyof BillingForm, label: string, options: { type?: string; placeholder?: string; className?: string; maxLength?: number; uppercase?: boolean } = {}) {
        const id = billingFieldId(field);

        return (
            <FormField id={id} label={label} error={error(field)} className={cn('content-start', options.className)}>
                <Input
                    id={id}
                    type={options.type ?? 'text'}
                    maxLength={options.maxLength}
                    placeholder={options.placeholder}
                    className={options.uppercase ? 'uppercase' : undefined}
                    aria-invalid={error(field) ? true : undefined}
                    value={value[field]}
                    onChange={(event) => onChange(field, event.target.value)}
                />
            </FormField>
        );
    }

    return (
        <section className="space-y-4" aria-labelledby="portal-billing-title">
            <div className="flex items-center gap-3">
                <Separator className="flex-1" />
                <span id="portal-billing-title" className="text-sm text-muted-foreground">
                    {__('Billing details')}
                </span>
                <Separator className="flex-1" />
            </div>

            <p className="text-sm text-muted-foreground">{__('Who pays for this portal. They are not the public contact details, and only administrators see them.')}</p>

            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id={billingFieldId('kind')} label={__('Who pays')} error={error('kind')} className="content-start">
                    <Select value={value.kind === '' ? undefined : value.kind} onValueChange={(next) => onChange('kind', next)}>
                        <SelectTrigger id={billingFieldId('kind')} className="w-full" aria-invalid={error('kind') ? true : undefined}>
                            <SelectValue placeholder={__('Choose…')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="PERSON">{__('Private')}</SelectItem>
                            <SelectItem value="COMPANY">{__('Business')}</SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>

                {value.kind === 'COMPANY' && text('company_name', __('Legal business name'))}
            </div>

            {value.kind === 'PERSON' && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {text('first_name', __('First name'))}
                    {text('last_name', __('Last name'))}
                    {text('tax_code', __('Tax code'), { uppercase: true })}
                </div>
            )}

            {value.kind === 'COMPANY' && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {text('vat_number', __('VAT number'), { uppercase: true })}
                    {text('tax_code', __('Tax code'), { uppercase: true })}
                    {text('sdi_code', __('SDI code'), { uppercase: true, maxLength: 7 })}
                    {text('pec', __('PEC'), { type: 'email' })}
                    {text('contact_name', __('Contact person'))}
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                {text('street', __('Street address'), { className: 'sm:col-span-2' })}
                {text('postal_code', __('Postal code'))}
                {text('city', __('City'))}
                {text('province', __('Province / region'))}
                {text('country', __('Country code'), { placeholder: 'IT, DE, FR…', maxLength: 2, uppercase: true })}
                {text('email', __('Email'), { type: 'email' })}
                {text('billing_email', __('Email for invoices and notices'), { type: 'email', placeholder: __('Only if different from the email.') })}
                {text('phone', __('Phone'))}
            </div>
        </section>
    );
}
