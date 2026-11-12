<?php

use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Support\BillingData;
use App\Support\ItalianTaxId;
use Illuminate\Support\Facades\Validator;

/**
 * What the billing section of the portal form makes of its input: the same
 * cases as docs/riferimento/customer-profile.test.ts, which must give the same
 * answers. The endpoint tests in PortalBillingTest only prove that the portal
 * form applies these rules.
 */

/**
 * A private Italian person, as the form sends it.
 *
 * @param  array<string, string>  $changes
 * @return array<string, string>
 */
function billingPerson(array $changes = []): array
{
    return [
        'kind' => 'PERSON', 'first_name' => ' Mario ', 'last_name' => 'Rossi', 'tax_code' => 'rss mra 85t10 a562s',
        'street' => 'Via Roma 1', 'postal_code' => '30100', 'city' => 'Venezia', 'province' => 've', 'country' => '',
        'email' => 'MARIO@EXAMPLE.IT', 'billing_email' => '', 'phone' => '+39 333 1234567',
        ...$changes,
    ];
}

/**
 * An Italian business, as the form sends it.
 *
 * @param  array<string, string>  $changes
 * @return array<string, string>
 */
function billingCompany(array $changes = []): array
{
    return [
        'kind' => 'COMPANY', 'company_name' => 'Edil Rossi Srl', 'contact_name' => 'Luca Bianchi',
        'vat_number' => 'IT 01234567897', 'tax_code' => '01234567897', 'sdi_code' => 'abc1234', 'pec' => '',
        'street' => 'Via Garibaldi 10', 'postal_code' => '20121', 'city' => 'Milano', 'province' => 'MI', 'country' => 'IT',
        'email' => 'info@edilrossi.example', 'billing_email' => 'amministrazione@edilrossi.example', 'phone' => '',
        ...$changes,
    ];
}

/**
 * Run the form's input through normalisation and the rules, as PortalRequest does.
 *
 * @param  array<string, string>  $input
 * @return array<string, string> the first message of every field that failed, keyed by field without the section name
 */
function billingErrors(array $input): array
{
    $billing = BillingData::normalise($input);
    $validator = Validator::make(['billing' => $billing], BillingData::rules($billing), BillingData::messages(), BillingData::attributes());

    return collect($validator->errors()->messages())
        ->mapWithKeys(fn (array $messages, string $key) => [str_replace('billing.', '', $key) => $messages[0]])
        ->all();
}

/**
 * The 16th character that makes a 15-character prefix a valid tax code; exactly one letter does.
 */
function billingCheckLetter(string $prefix): string
{
    $valid = array_filter(range('A', 'Z'), fn (string $letter) => ItalianTaxId::isValidTaxCode($prefix.$letter));

    expect($valid)->toHaveCount(1, $prefix);

    return $prefix.reset($valid);
}

test('a personal tax code is checked on format and check character', function (string $code, bool $valid) {
    expect(ItalianTaxId::isValidTaxCode($code))->toBe($valid);
})->with([
    'valid' => ['RSSMRA85T10A562S', true],
    'wrong check character' => ['RSSMRA85T10A562X', false],
    'too short' => ['RSSMRA85T10A56', false],
    'letter in place of a letter that must stay a letter' => ['RSSMRA85T10A56IS', false],
    'letter outside the omocodia table' => ['RSSMRA8ET10A562S', false],
]);

test('a VAT number is checked on length and check digit', function (string $vat, bool $valid) {
    expect(ItalianTaxId::isValidVatNumber($vat))->toBe($valid);
})->with([
    'valid' => ['01234567897', true],
    'wrong check digit' => ['01234567890', false],
    'ten digits' => ['1234567897', false],
]);

test('tax codes with omocodia letters in place of digits are accepted', function (string $prefix) {
    $code = billingCheckLetter($prefix);

    expect(ItalianTaxId::isValidTaxCode($code))->toBeTrue()
        ->and(BillingData::normalise(billingPerson(['tax_code' => strtolower($code)]))['tax_code'])->toBe($code)
        ->and(billingErrors(billingPerson(['tax_code' => strtolower($code)])))->toBe([]);
})->with([
    'no substitution' => ['RSSMRA85T10A562'],
    'last digit' => ['RSSMRA85T10A56N'],
    'last two digits' => ['RSSMRA85T10A5SN'],
    'last three digits' => ['RSSMRA85T10ARSN'],
    'four digits' => ['RSSMRA85T1MARSN'],
    'every substitutable position' => ['RSSMRATUTMMARSN'],
]);

test('a private person gets the display name from first and last name, with clean fields and Italy by default', function () {
    $billing = BillingData::normalise(billingPerson());
    $columns = BillingData::columns($billing);

    expect(billingErrors(billingPerson()))->toBe([])
        ->and($columns)->toMatchArray([
            'kind' => 'PERSON', 'name' => 'Mario Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA85T10A562S',
            'province' => 'VE', 'country' => 'IT', 'email' => 'mario@example.it', 'billing_email' => null,
            'vat_number' => null, 'sdi_code' => null,
        ]);
});

test('the fields of a business are dropped for a private person', function () {
    $billing = BillingData::normalise(billingPerson(['vat_number' => '01234567897', 'sdi_code' => 'ABC1234', 'pec' => 'a@b.it', 'contact_name' => 'X', 'company_name' => 'Y']));

    expect($billing)->toMatchArray(['vat_number' => null, 'sdi_code' => null, 'pec' => null, 'contact_name' => null, 'company_name' => null]);
});

test('the personal fields are dropped for a business', function () {
    $billing = BillingData::normalise(billingCompany(['first_name' => 'Mario', 'last_name' => 'Rossi']));

    expect($billing)->toMatchArray(['first_name' => null, 'last_name' => null]);
});

test('a business is shown by its business name and the IT prefix is taken off the VAT number', function () {
    $billing = BillingData::normalise(billingCompany());
    $columns = BillingData::columns($billing);

    expect(billingErrors(billingCompany()))->toBe([])
        ->and($columns)->toMatchArray(['name' => 'Edil Rossi Srl', 'vat_number' => '01234567897', 'tax_code' => '01234567897', 'sdi_code' => 'ABC1234', 'first_name' => null])
        // A sole proprietorship has a personal tax code.
        ->and(billingErrors(billingCompany(['tax_code' => 'RSSMRA85T10A562S'])))->toBe([]);
});

test('the IT prefix is accepted on the tax code of a business, but not on a personal one', function () {
    expect(BillingData::normalise(billingCompany(['tax_code' => 'it 01234567897']))['tax_code'])->toBe('01234567897')
        ->and(billingErrors(billingCompany(['tax_code' => 'it 01234567897'])))->toBe([])
        ->and(billingErrors(billingCompany(['tax_code' => 'IT01234567890'])))->toHaveKey('tax_code')
        // A private person's tax code is never a VAT number: the prefix stays and the code is invalid.
        ->and(billingErrors(billingPerson(['tax_code' => 'IT01234567897'])))->toHaveKey('tax_code');
});

test('typing mistakes are refused on the field that holds them', function (array $input, string $field) {
    expect(billingErrors($input))->toHaveKey($field);
})->with([
    'tax code with a wrong check character' => [billingPerson(['tax_code' => 'RSSMRA85T10A562X']), 'tax_code'],
    'VAT number with a wrong check digit' => [billingCompany(['vat_number' => '01234567890']), 'vat_number'],
    'business tax code of five digits' => [billingCompany(['tax_code' => '12345']), 'tax_code'],
    'postal code of four digits' => [billingPerson(['postal_code' => '3010']), 'postal_code'],
    'province of three letters' => [billingPerson(['province' => 'VEN']), 'province'],
    'SDI code of four characters' => [billingCompany(['sdi_code' => 'AB12']), 'sdi_code'],
    'PEC that is not an email' => [billingCompany(['pec' => 'non-una-pec']), 'pec'],
    'main email that is not an email' => [billingPerson(['email' => 'sbagliata']), 'email'],
    'billing email that is not an email' => [billingPerson(['billing_email' => 'sbagliata']), 'billing_email'],
    'country of three letters' => [billingPerson(['country' => 'ITA']), 'country'],
    'missing last name' => [billingPerson(['last_name' => '']), 'last_name'],
    'blank business name' => [billingCompany(['company_name' => ' ']), 'company_name'],
    'street over 200 characters' => [billingPerson(['street' => str_repeat('x', 201)]), 'street'],
    'unknown kind' => [billingPerson(['kind' => 'ALTRO']), 'kind'],
    'no kind' => [billingPerson(['kind' => '']), 'kind'],
]);

test('the messages are the ones of the License Manager, in the language of the screen', function () {
    expect(billingErrors(billingPerson(['tax_code' => 'RSSMRA85T10A562X'])))->toMatchArray(['tax_code' => 'Tax code not valid: check the 16 characters.'])
        ->and(billingErrors(billingCompany(['vat_number' => '01234567890'])))->toMatchArray(['vat_number' => 'VAT number not valid: it has 11 digits with the correct check digit.'])
        ->and(billingErrors(billingPerson(['postal_code' => '3010'])))->toMatchArray(['postal_code' => 'Postal code not valid: it has 5 digits.'])
        ->and(billingErrors(billingPerson(['province' => 'VEN'])))->toMatchArray(['province' => 'Province: use the two-letter code, for example MI.'])
        ->and(billingErrors(billingPerson(['kind' => ''])))->toMatchArray(['kind' => 'Choose whether the payer is a private person or a business.']);
});

test('an Italian business needs the SDI code or the PEC, one of the two is enough', function () {
    expect(billingErrors(billingCompany(['sdi_code' => '', 'pec' => ''])))->toMatchArray(['sdi_code' => 'Enter the SDI code or the PEC.'])
        ->and(billingErrors(billingCompany(['sdi_code' => '', 'pec' => 'AMM@Edilrossi.example'])))->toBe([])
        ->and(BillingData::normalise(billingCompany(['sdi_code' => '', 'pec' => 'AMM@Edilrossi.example']))['pec'])->toBe('amm@edilrossi.example');
});

test('what an Italian profile needs to be complete', function (array $input, array $missing) {
    expect(array_keys(billingErrors($input)))->toEqualCanonicalizing($missing);
})->with([
    'private, nothing but the kind' => [['kind' => 'PERSON'], ['first_name', 'last_name', 'tax_code', 'street', 'postal_code', 'city', 'province', 'email']],
    'business, nothing but the kind' => [['kind' => 'COMPANY'], ['company_name', 'vat_number', 'sdi_code', 'street', 'postal_code', 'city', 'province', 'email']],
    'nothing at all' => [[], ['kind', 'street', 'postal_code', 'city', 'province', 'email']],
]);

test('abroad no Italian check applies and fewer fields are required', function () {
    $foreignCompany = billingCompany(['country' => 'de', 'vat_number' => 'DE123456789', 'tax_code' => '', 'sdi_code' => '', 'postal_code' => '10115', 'province' => '', 'city' => 'Berlin']);
    $foreignPerson = billingPerson(['country' => 'DE', 'tax_code' => '', 'postal_code' => '', 'province' => '']);

    expect(BillingData::normalise($foreignCompany))->toMatchArray(['country' => 'DE', 'vat_number' => 'DE123456789'])
        ->and(billingErrors($foreignCompany))->toBe([])
        ->and(billingErrors($foreignPerson))->toBe([])
        // A business abroad still needs its VAT number or equivalent, and everyone needs street, city and email.
        ->and(billingErrors([...$foreignCompany, 'vat_number' => '']))->toHaveKey('vat_number')
        ->and(billingErrors([...$foreignPerson, 'street' => '', 'city' => '', 'email' => '']))->toHaveKeys(['street', 'city', 'email']);
});

test('foreign postcodes and regions keep their spaces, the postcode its uppercase', function () {
    $input = billingCompany(['country' => 'GB', 'vat_number' => 'GB123456789', 'tax_code' => '', 'sdi_code' => '', 'postal_code' => 'sw1a 1aa', 'province' => 'Greater London', 'city' => 'London']);

    expect(BillingData::normalise($input))->toMatchArray(['postal_code' => 'SW1A 1AA', 'province' => 'Greater London'])
        ->and(billingErrors($input))->toBe([]);
});

test('a private name that would not fit the display name is refused instead of being cut', function () {
    expect(billingErrors(billingPerson(['first_name' => str_repeat('a', 60), 'last_name' => str_repeat('b', 60)])))->toHaveKey('last_name')
        ->and(billingErrors(billingPerson(['first_name' => str_repeat('a', 60), 'last_name' => str_repeat('b', 59)])))->toBe([])
        ->and(BillingData::columns(BillingData::normalise(billingPerson(['first_name' => str_repeat('a', 60), 'last_name' => str_repeat('b', 59)])))['name'])->toHaveLength(120);
});

test('control characters are refused in codes as well as in text, while ordinary white space is removed', function (string $field, string $value) {
    $foreign = billingCompany(['country' => 'DE', 'tax_code' => '', 'sdi_code' => '', 'postal_code' => '10115', 'province' => '', 'city' => 'Berlin']);

    expect(billingErrors([...$foreign, $field => $value]))->toHaveKey($field);
})->with([
    'VAT number with a start-of-heading' => ['vat_number', "DE12\x0134567"],
    'tax code with a delete character' => ['tax_code', "AB\x7fCD"],
    'VAT number with a null byte' => ['vat_number', "DE\x001234"],
    'street with a bell' => ['street', "Via\x07 Roma"],
]);

test('white space inside a code is just removed, and in text it collapses', function () {
    $foreign = billingCompany(['country' => 'DE', 'tax_code' => '', 'sdi_code' => '', 'postal_code' => '10115', 'province' => '', 'city' => 'Berlin']);

    expect(BillingData::normalise([...$foreign, 'vat_number' => "DE 123 456\t789"])['vat_number'])->toBe('DE123456789')
        ->and(BillingData::normalise([...$foreign, 'street' => "  Via   Roma \t 1 "])['street'])->toBe('Via Roma 1');
});

test('the section counts as empty when only the country is there', function () {
    expect(BillingData::isEmpty(BillingData::normalise(['country' => 'IT', 'kind' => '', 'street' => '  ', 'email' => ''])))->toBeTrue()
        ->and(BillingData::isEmpty(BillingData::normalise(['kind' => 'PERSON'])))->toBeFalse()
        ->and(BillingData::isEmpty(BillingData::normalise(['phone' => '333'])))->toBeFalse();
});

test('the missing fields are listed in words and nothing is listed for a complete profile', function () {
    $company = TenantBillingProfile::factory()->make();

    expect(BillingData::gaps($company))->toBe([])
        ->and(BillingData::gaps(TenantBillingProfile::factory()->person()->make()))->toBe([])
        ->and(BillingData::gaps(TenantBillingProfile::factory()->foreign()->make()))->toBe([])
        ->and(BillingData::gaps(null))->toBe(['billing type'])
        ->and(BillingData::gaps(TenantBillingProfile::factory()->make(['kind' => null])))->toBe(['billing type'])
        ->and(BillingData::gaps(TenantBillingProfile::factory()->make(['sdi_code' => null, 'pec' => null])))->toBe(['SDI code or PEC'])
        ->and(BillingData::gaps(TenantBillingProfile::factory()->make(['vat_number' => null, 'postal_code' => null])))->toBe(['vat number', 'postal code'])
        ->and(BillingData::gaps(TenantBillingProfile::factory()->person()->make(['tax_code' => null, 'street' => null, 'city' => null])))->toBe(['tax code', 'street address', 'city'])
        // Abroad the Italian fields are not asked for.
        ->and(BillingData::gaps(TenantBillingProfile::factory()->foreign()->make(['sdi_code' => null, 'province' => null, 'postal_code' => null])))->toBe([]);
});

test('the block for the License Manager leaves out empty lines and ends with the portal id', function () {
    $tenant = Tenant::factory()->create();
    $company = TenantBillingProfile::factory()->make();
    $person = TenantBillingProfile::factory()->person()->make(['phone' => '+39 333 1234567']);

    expect(BillingData::clipboardText($tenant, $company))->toBe(implode("\n", [
        'Edil Rossi Srl',
        'Partita IVA: 01234567897',
        'Codice fiscale: 01234567897',
        'Indirizzo: Via Garibaldi 10, 20121 Milano (MI), IT',
        'Codice SDI: ABC1234',
        'Referente: Luca Bianchi',
        'Email fatture e avvisi: amministrazione@edilrossi.example',
        'Email: info@edilrossi.example',
        'Tenant id (License Manager): '.$tenant->license_id,
    ]))->and(BillingData::clipboardText($tenant, $person))->toBe(implode("\n", [
        'Mario Rossi',
        'Codice fiscale: RSSMRA85T10A562S',
        'Indirizzo: Via Roma 1, 30100 Venezia (VE), IT',
        'Email: mario@example.it',
        'Telefono: +39 333 1234567',
        'Tenant id (License Manager): '.$tenant->license_id,
    ]))->and(BillingData::clipboardText($tenant, null))->toBe('Tenant id (License Manager): '.$tenant->license_id);
});

test('notices go to the billing email when there is one', function () {
    expect(BillingData::noticeEmail(TenantBillingProfile::factory()->make(['email' => 'a@x.it', 'billing_email' => 'b@x.it'])))->toBe('b@x.it')
        ->and(BillingData::noticeEmail(TenantBillingProfile::factory()->make(['email' => 'a@x.it', 'billing_email' => null])))->toBe('a@x.it')
        ->and(BillingData::noticeEmail(TenantBillingProfile::factory()->make(['email' => null, 'billing_email' => null])))->toBeNull();
});

test('the register is told which fields changed and never their values', function () {
    $before = TenantBillingProfile::factory()->make();
    $same = BillingData::columns(BillingData::normalise(billingCompany()));
    $after = BillingData::columns(BillingData::normalise(billingCompany(['city' => 'Torino', 'pec' => 'amm@edilrossi.example', 'phone' => ''])));

    expect(BillingData::changedFields($before, $same))->toBe([])
        ->and(BillingData::changedFields($before, $after))->toBe(['pec', 'city'])
        ->and(BillingData::changedFields(null, $same))->toContain('kind', 'name', 'vat_number');
});
