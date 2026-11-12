import { test } from "node:test";
import { strict as assert } from "node:assert";
import {
  billingGaps, billingText, changedFields, customerFormValues, isValidItalianTaxCode, isValidVatNumber, kindProblem, noticeEmail,
  readCustomerForm, searchCodeOf, type CustomerProfile,
} from "../src/lib/customer-profile.ts";

const form = (values: Record<string, string>) => (name: string) => values[name] ?? "";

const person = {
  kind: "PERSON", firstName: " Mario ", lastName: "Rossi", taxCode: "rss mra 85t10 a562s", street: "Via Roma 1", postalCode: "30100",
  city: "Venezia", province: "ve", country: "", email: "MARIO@EXAMPLE.IT", billingEmail: "", phone: "+39 333 1234567",
};
const company = {
  kind: "COMPANY", companyName: "Edil Rossi Srl", contactName: "Luca Bianchi", vatNumber: "IT 01234567897", taxCode: "01234567897",
  sdiCode: "abc1234", pec: "", street: "Via Garibaldi 10", postalCode: "20121", city: "Milano", province: "MI", country: "IT",
  email: "info@edilrossi.example", billingEmail: "amministrazione@edilrossi.example", phone: "",
};

test("italian tax code and VAT number check digits", () => {
  assert.equal(isValidItalianTaxCode("RSSMRA85T10A562S"), true);
  assert.equal(isValidItalianTaxCode("RSSMRA85T10A562X"), false);
  assert.equal(isValidItalianTaxCode("RSSMRA85T10A56"), false);
  assert.equal(isValidVatNumber("01234567897"), true);
  assert.equal(isValidVatNumber("01234567890"), false);
  assert.equal(isValidVatNumber("1234567897"), false);
});

test("a private customer gets its display name from first and last name, normalized fields and IT by default", () => {
  const result = readCustomerForm(form(person));
  assert.ok(result.ok, result.ok ? "" : result.message);
  const data = result.data;
  assert.equal(data.kind, "PERSON");
  assert.equal(data.name, "Mario Rossi");
  assert.equal(data.firstName, "Mario");
  assert.equal(data.taxCode, "RSSMRA85T10A562S");
  assert.equal(data.province, "VE");
  assert.equal(data.country, "IT");
  assert.equal(data.email, "mario@example.it");
  assert.equal(data.billingEmail, null);
  assert.equal(data.vatNumber, null);
  assert.equal(data.sdiCode, null);
});

test("company-only fields are dropped for a private customer", () => {
  const result = readCustomerForm(form({ ...person, vatNumber: "01234567897", sdiCode: "ABC1234", contactName: "X", companyName: "Y" }));
  assert.ok(result.ok);
  assert.equal(result.data.vatNumber, null);
  assert.equal(result.data.sdiCode, null);
  assert.equal(result.data.contactName, null);
});

test("a company is shown by its business name; the VAT prefix IT is accepted", () => {
  const result = readCustomerForm(form(company));
  assert.ok(result.ok, result.ok ? "" : result.message);
  assert.equal(result.data.name, "Edil Rossi Srl");
  assert.equal(result.data.vatNumber, "01234567897");
  assert.equal(result.data.taxCode, "01234567897");
  assert.equal(result.data.sdiCode, "ABC1234");
  assert.equal(result.data.firstName, null);
  // A sole proprietorship has a personal tax code.
  assert.ok(readCustomerForm(form({ ...company, taxCode: "RSSMRA85T10A562S" })).ok);
});

test("typing mistakes are refused with the field and an Italian message", () => {
  const cases: [Record<string, string>, string][] = [
    [{ ...person, taxCode: "RSSMRA85T10A562X" }, "taxCode"],
    [{ ...company, vatNumber: "01234567890" }, "vatNumber"],
    [{ ...company, taxCode: "12345" }, "taxCode"],
    [{ ...person, postalCode: "3010" }, "postalCode"],
    [{ ...person, province: "VEN" }, "province"],
    [{ ...company, sdiCode: "AB12" }, "sdiCode"],
    [{ ...company, pec: "non-una-pec" }, "pec"],
    [{ ...person, email: "sbagliata" }, "email"],
    [{ ...person, billingEmail: "sbagliata" }, "billingEmail"],
    [{ ...person, country: "ITA" }, "country"],
    [{ ...person, lastName: "" }, "lastName"],
    [{ ...company, companyName: " " }, "companyName"],
    [{ ...person, street: "x".repeat(201) }, "street"],
  ];
  for (const [values, field] of cases) {
    const result = readCustomerForm(form(values));
    assert.equal(result.ok, false, field);
    assert.ok(!result.ok && result.field === field, `${field}: got ${!result.ok ? result.field : "ok"}`);
    assert.ok(!result.ok && result.message.length > 0);
  }
});

test("foreign customers skip the Italian checks", () => {
  const result = readCustomerForm(form({ ...company, country: "de", vatNumber: "DE123456789", taxCode: "", sdiCode: "", postalCode: "10115", province: "", city: "Berlin" }));
  assert.ok(result.ok, result.ok ? "" : result.message);
  assert.equal(result.data.country, "DE");
  assert.equal(result.data.vatNumber, "DE123456789");
});

test("foreign postcodes and regions keep their spaces and case", () => {
  const result = readCustomerForm(form({ ...company, country: "GB", vatNumber: "GB123456789", taxCode: "", sdiCode: "", postalCode: "sw1a 1aa", province: "Greater London", city: "London" }));
  assert.ok(result.ok, result.ok ? "" : result.message);
  assert.equal(result.data.postalCode, "SW1A 1AA");
  assert.equal(result.data.province, "Greater London");
});

test("a customer of unknown type keeps working with just a name", () => {
  const result = readCustomerForm(form({ kind: "", name: "Cliente storico", email: "", phone: "" }));
  assert.ok(result.ok);
  assert.equal(result.data.kind, null);
  assert.equal(result.data.name, "Cliente storico");
  assert.equal(readCustomerForm(form({ kind: "", name: "" })).ok, false);
  assert.equal(readCustomerForm(form({ kind: "ALTRO", name: "X" })).ok, false);
});

function profile(overrides: Partial<CustomerProfile> = {}): CustomerProfile {
  const result = readCustomerForm(form(company));
  assert.ok(result.ok);
  return { ...result.data, ...overrides };
}

test("billing gaps list what is missing for invoicing", () => {
  assert.deepEqual(billingGaps(profile()), []);
  assert.deepEqual(billingGaps(profile({ sdiCode: null, pec: null })), ["codice SDI o PEC"]);
  assert.deepEqual(billingGaps(profile({ vatNumber: null, postalCode: null })), ["partita IVA", "CAP"]);
  const privateResult = readCustomerForm(form({ ...person, taxCode: "", street: "", city: "" }));
  assert.ok(privateResult.ok);
  assert.deepEqual(billingGaps(privateResult.data), ["codice fiscale", "indirizzo", "comune"]);
  assert.deepEqual(billingGaps(profile({ kind: null })), ["tipo cliente"]);
  assert.deepEqual(billingGaps(profile({ country: "DE", province: null, sdiCode: null, pec: null })), []);
});

test("billing text is ready to paste", () => {
  assert.equal(billingText(profile()), [
    "Edil Rossi Srl",
    "Partita IVA: 01234567897",
    "Codice fiscale: 01234567897",
    "Indirizzo: Via Garibaldi 10, 20121 Milano (MI), IT",
    "Codice SDI: ABC1234",
    "Referente: Luca Bianchi",
    "Email fatture e avvisi: amministrazione@edilrossi.example",
    "Email: info@edilrossi.example",
  ].join("\n"));
  const privateResult = readCustomerForm(form(person));
  assert.ok(privateResult.ok);
  assert.equal(billingText(privateResult.data), [
    "Mario Rossi",
    "Codice fiscale: RSSMRA85T10A562S",
    "Indirizzo: Via Roma 1, 30100 Venezia (VE), IT",
    "Email: mario@example.it",
    "Telefono: +39 333 1234567",
  ].join("\n"));
});

test("notices go to the billing email when there is one", () => {
  assert.equal(noticeEmail({ email: "a@x.it", billingEmail: "b@x.it" }), "b@x.it");
  assert.equal(noticeEmail({ email: "a@x.it", billingEmail: null }), "a@x.it");
  assert.equal(noticeEmail({ email: null, billingEmail: null }), null);
});

// ---------- Second pass: edge cases found in the final review ----------

/** The 16th character that makes a 15-character prefix a valid tax code; exactly one letter does. */
function withCheckLetter(prefix: string): string {
  const valid = [..."ABCDEFGHIJKLMNOPQRSTUVWXYZ"].filter((letter) => isValidItalianTaxCode(prefix + letter));
  assert.equal(valid.length, 1, prefix);
  return prefix + valid[0];
}

test("tax codes with omocodia letters in place of digits are accepted", () => {
  assert.equal(withCheckLetter("RSSMRA85T10A562"), "RSSMRA85T10A562S");
  // Last digit, then the last two, then every substitutable position: each one is still a valid code.
  for (const prefix of ["RSSMRA85T10A56N", "RSSMRA85T10A5SN", "RSSMRA85T10ARSN", "RSSMRA85T1MARSN", "RSSMRATUTMMARSN"]) {
    const code = withCheckLetter(prefix);
    assert.equal(isValidItalianTaxCode(code), true, code);
    const result = readCustomerForm(form({ ...person, taxCode: code.toLowerCase() }));
    assert.ok(result.ok, code);
    assert.equal(result.data.taxCode, code);
  }
  // A substituted letter in a position that holds a letter, or a letter outside the table, is not an omocodia.
  assert.equal(isValidItalianTaxCode("RSSMRA85T10A56IS"), false);
  assert.equal(isValidItalianTaxCode("RSSMRA8ET10A562S"), false);
});

test("the IT prefix is accepted on the company tax code too, but not mistaken for part of a personal one", () => {
  const withPrefix = readCustomerForm(form({ ...company, taxCode: "it 01234567897" }));
  assert.ok(withPrefix.ok, withPrefix.ok ? "" : withPrefix.message);
  assert.equal(withPrefix.data.taxCode, "01234567897");
  assert.equal(readCustomerForm(form({ ...company, taxCode: "IT01234567890" })).ok, false);
  // A private customer's tax code is never a VAT number: the prefix is not stripped and the code stays invalid.
  assert.equal(readCustomerForm(form({ ...person, taxCode: "IT01234567897" })).ok, false);
});

test("search codes drop spaces and the IT prefix of a VAT number", () => {
  assert.equal(searchCodeOf("it 012 345 678 97"), "01234567897");
  assert.equal(searchCodeOf("rss mra85"), "RSSMRA85");
  assert.equal(searchCodeOf("Italia"), "ITALIA");
  assert.equal(searchCodeOf("IT"), "IT");
  assert.equal(searchCodeOf("IT0123"), "0123");
});

test("a private name that would not fit the display name is refused instead of being cut", () => {
  const long = readCustomerForm(form({ ...person, firstName: "a".repeat(60), lastName: "b".repeat(60) }));
  assert.ok(!long.ok && long.field === "lastName", "121 characters");
  const fits = readCustomerForm(form({ ...person, firstName: "a".repeat(60), lastName: "b".repeat(59) }));
  assert.ok(fits.ok && fits.data.name.length === 120);
});

test("control characters are refused in codes as well as in text", () => {
  const foreign = { ...company, country: "DE", taxCode: "", sdiCode: "", postalCode: "10115", province: "", city: "Berlin" };
  for (const [field, value] of [["vatNumber", "DE12\u000134567"], ["taxCode", "AB\u007fCD"], ["vatNumber", "DE\u00001234"]] as const) {
    const result = readCustomerForm(form({ ...foreign, [field]: value }));
    assert.ok(!result.ok && result.field === field, `${field} ${JSON.stringify(value)}`);
  }
  // Ordinary whitespace inside a code is still just removed.
  const spaced = readCustomerForm(form({ ...foreign, vatNumber: "DE 123 456\t789" }));
  assert.ok(spaced.ok && spaced.data.vatNumber === "DE123456789");
});

test("saving needs a type for a new customer and for one that already has a type", () => {
  const typed = readCustomerForm(form(person));
  const legacy = readCustomerForm(form({ kind: "", name: "Cliente storico" }));
  assert.ok(typed.ok && legacy.ok);
  assert.equal(kindProblem(null, typed.data.kind), null);
  assert.ok(kindProblem(null, legacy.data.kind));
  assert.equal(kindProblem({ kind: "PERSON" }, typed.data.kind), null);
  assert.ok(kindProblem({ kind: "COMPANY" }, legacy.data.kind));
  // Only a customer saved before the type existed may stay without one.
  assert.equal(kindProblem({ kind: null }, legacy.data.kind), null);
  assert.equal(kindProblem({ kind: null }, typed.data.kind), null);
});

test("the audit lists which fields changed and never their values", () => {
  const before = readCustomerForm(form(company));
  assert.ok(before.ok);
  assert.deepEqual(changedFields(before.data, before.data), []);
  const after = readCustomerForm(form({ ...company, city: "Torino", pec: "amm@edilrossi.example", phone: "" }));
  assert.ok(after.ok);
  assert.deepEqual(changedFields(before.data, after.data), ["pec", "city"]);
});

test("a customer saved without a type opens the form with its name ready to reuse", () => {
  const stored = { ...profile({ kind: null }), kind: null, name: "Mario Rossi", firstName: null, lastName: null, vatNumber: null, taxCode: null, country: null };
  const values = customerFormValues(stored);
  assert.equal(values.kind, "");
  assert.equal(values.name, "Mario Rossi");
  assert.equal(values.companyName, "Mario Rossi");
  assert.equal(values.firstName, "Mario");
  assert.equal(values.lastName, "Rossi");
  assert.equal(values.country, "IT");
  // A one-word name only fills the first name; nothing is invented.
  const single = customerFormValues({ ...stored, name: "Acme" });
  assert.equal(single.firstName, "Acme");
  assert.equal(single.lastName, "");
  // Stray spaces in an old name do not leak into the fields.
  const spaced = customerFormValues({ ...stored, name: " Mario  Rossi " });
  assert.equal(spaced.firstName, "Mario");
  assert.equal(spaced.lastName, "Rossi");
});

test("typed customers fill the form from their own fields", () => {
  const businessResult = readCustomerForm(form(company));
  assert.ok(businessResult.ok);
  const business = customerFormValues(businessResult.data);
  assert.equal(business.kind, "COMPANY");
  assert.equal(business.companyName, "Edil Rossi Srl");
  assert.equal(business.firstName, "");
  assert.equal(business.vatNumber, "01234567897");
  const privateResult = readCustomerForm(form(person));
  assert.ok(privateResult.ok);
  const individual = customerFormValues(privateResult.data);
  assert.equal(individual.kind, "PERSON");
  assert.equal(individual.firstName, "Mario");
  assert.equal(individual.companyName, "");
});
