import { z } from "zod";

// Customer record used as a reference for invoicing done elsewhere. Pure functions: shared by the panel forms
// and, later, by the API that lets the software create or update customers with the same fields.

export type CustomerKind = "PERSON" | "COMPANY";

export const CUSTOMER_KIND_LABELS: Record<CustomerKind, string> = { PERSON: "Privato", COMPANY: "Azienda" };

/** What is stored; `name` is the display name used everywhere else in the panel. */
export type CustomerProfile = {
  kind: CustomerKind | null;
  name: string;
  firstName: string | null;
  lastName: string | null;
  contactName: string | null;
  taxCode: string | null;
  vatNumber: string | null;
  sdiCode: string | null;
  pec: string | null;
  street: string | null;
  postalCode: string | null;
  city: string | null;
  province: string | null;
  country: string | null;
  email: string | null;
  billingEmail: string | null;
  phone: string | null;
};

export type CustomerField =
  | "kind" | "name" | "firstName" | "lastName" | "companyName" | "contactName" | "taxCode" | "vatNumber" | "sdiCode" | "pec"
  | "street" | "postalCode" | "city" | "province" | "country" | "email" | "billingEmail" | "phone";

export const CUSTOMER_FORM_FIELDS: readonly CustomerField[] = [
  "kind", "name", "firstName", "lastName", "companyName", "contactName", "taxCode", "vatNumber", "sdiCode", "pec",
  "street", "postalCode", "city", "province", "country", "email", "billingEmail", "phone",
];

// ---------- Italian check digits ----------

const ODD_VALUES: Record<string, number> = {
  0: 1, 1: 0, 2: 5, 3: 7, 4: 9, 5: 13, 6: 15, 7: 17, 8: 19, 9: 21,
  A: 1, B: 0, C: 5, D: 7, E: 9, F: 13, G: 15, H: 17, I: 19, J: 21, K: 2, L: 4, M: 18,
  N: 20, O: 11, P: 3, Q: 6, R: 8, S: 12, T: 14, U: 16, V: 10, W: 22, X: 25, Y: 24, Z: 23,
};
const PERSONAL_TAX_CODE = /^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/;

/** 16-character personal tax code (codice fiscale), including the "omocodia" letter substitutions. */
export function isValidItalianTaxCode(code: string): boolean {
  if (!PERSONAL_TAX_CODE.test(code)) return false;
  let sum = 0;
  for (let i = 0; i < 15; i += 1) {
    const char = code[i];
    // Positions are counted from 1: odd positions use the odd table, even positions the plain value.
    sum += i % 2 === 0 ? ODD_VALUES[char] : /\d/.test(char) ? Number(char) : char.charCodeAt(0) - 65;
  }
  return code[15] === String.fromCharCode(65 + (sum % 26));
}

/** 11-digit Italian VAT number (partita IVA). */
export function isValidVatNumber(vat: string): boolean {
  if (!/^\d{11}$/.test(vat)) return false;
  let sum = 0;
  for (let i = 0; i < 10; i += 1) {
    const digit = Number(vat[i]);
    if (i % 2 === 0) sum += digit;
    else sum += digit * 2 > 9 ? digit * 2 - 9 : digit * 2;
  }
  return (10 - (sum % 10)) % 10 === Number(vat[10]);
}

// ---------- Form reading ----------

const emailCheck = z.string().email();

export type CustomerFormResult = { ok: true; data: CustomerProfile } | { ok: false; field: CustomerField; message: string };

class FieldError extends Error {
  readonly field: CustomerField;
  constructor(field: CustomerField, message: string) {
    super(message);
    this.field = field;
  }
}

/** Reads and checks the customer form; the first problem is returned with its field. */
export function readCustomerForm(get: (name: string) => FormDataEntryValue | null): CustomerFormResult {
  const raw = (name: CustomerField) => {
    const value = get(name);
    return typeof value === "string" ? value : "";
  };
  const text = (name: CustomerField, max: number, label: string): string | null => {
    const value = raw(name).replace(/\s+/g, " ").trim();
    if (value.length > max) throw new FieldError(name, `${label}: massimo ${max} caratteri.`);
    if (/[\u0000-\u001f\u007f]/.test(value)) throw new FieldError(name, `${label}: caratteri non validi.`);
    return value || null;
  };
  const code = (name: CustomerField, max: number, label: string): string | null => {
    const value = raw(name).replace(/\s+/g, "").toUpperCase();
    if (value.length > max) throw new FieldError(name, `${label}: massimo ${max} caratteri.`);
    if (/[\u0000-\u001f\u007f]/.test(value)) throw new FieldError(name, `${label}: caratteri non validi.`);
    return value || null;
  };
  const email = (name: CustomerField, label: string): string | null => {
    const value = raw(name).trim().toLowerCase();
    if (!value) return null;
    if (value.length > 254 || !emailCheck.safeParse(value).success) throw new FieldError(name, `${label} non valida.`);
    return value;
  };

  try {
    const kindRaw = raw("kind").trim();
    if (kindRaw !== "" && kindRaw !== "PERSON" && kindRaw !== "COMPANY") throw new FieldError("kind", "Scegli se il cliente è un privato o un'azienda.");
    const kind = (kindRaw || null) as CustomerKind | null;

    const country = code("country", 2, "Nazione") ?? "IT";
    if (!/^[A-Z]{2}$/.test(country)) throw new FieldError("country", "Nazione: usa il codice di due lettere, per esempio IT, DE, FR.");
    const italian = country === "IT";

    let name: string | null;
    let firstName: string | null = null;
    let lastName: string | null = null;
    let contactName: string | null = null;
    let vatNumber: string | null = null;
    let sdiCode: string | null = null;
    let pec: string | null = null;

    if (kind === "PERSON") {
      firstName = text("firstName", 60, "Nome");
      lastName = text("lastName", 60, "Cognome");
      if (!firstName) throw new FieldError("firstName", "Indica il nome.");
      if (!lastName) throw new FieldError("lastName", "Indica il cognome.");
      name = `${firstName} ${lastName}`;
      // The display name is stored in one column of 120 characters: refuse rather than cut it.
      if (name.length > 120) throw new FieldError("lastName", "Nome e cognome insieme: massimo 120 caratteri.");
    } else if (kind === "COMPANY") {
      name = text("companyName", 120, "Ragione sociale");
      if (!name || name.length < 2) throw new FieldError("companyName", "Indica la ragione sociale.");
      contactName = text("contactName", 120, "Referente");
      vatNumber = code("vatNumber", 30, "Partita IVA");
      if (vatNumber && italian) {
        if (vatNumber.startsWith("IT")) vatNumber = vatNumber.slice(2);
        if (!isValidVatNumber(vatNumber)) throw new FieldError("vatNumber", "Partita IVA non valida: servono 11 cifre con la cifra di controllo corretta.");
      }
      sdiCode = code("sdiCode", 7, "Codice SDI");
      if (sdiCode && !/^[A-Z0-9]{7}$/.test(sdiCode)) throw new FieldError("sdiCode", "Codice SDI non valido: sono 7 caratteri tra lettere e cifre.");
      pec = email("pec", "PEC");
    } else {
      name = text("name", 120, "Nome cliente");
      if (!name || name.length < 2) throw new FieldError("name", "Indica il nome del cliente.");
    }

    let taxCode = code("taxCode", 30, "Codice fiscale");
    // A company often writes its VAT number, with the IT prefix, as tax code; a personal code never looks like that.
    if (taxCode && italian && kind === "COMPANY" && /^IT\d{11}$/.test(taxCode)) taxCode = taxCode.slice(2);
    if (taxCode && italian) {
      const valid = kind === "COMPANY" ? isValidVatNumber(taxCode) || isValidItalianTaxCode(taxCode) : isValidItalianTaxCode(taxCode);
      if (!valid) {
        throw new FieldError("taxCode", kind === "COMPANY"
          ? "Codice fiscale non valido: 11 cifre come la partita IVA, oppure 16 caratteri per una ditta individuale."
          : "Codice fiscale non valido: controlla i 16 caratteri.");
      }
    }

    const street = text("street", 200, "Indirizzo");
    // Italian postcodes and province codes are compact; foreign ones keep their spaces and case (SW1A 1AA, Greater London).
    const postalCode = italian ? code("postalCode", 12, "CAP") : text("postalCode", 12, "CAP")?.toUpperCase() ?? null;
    if (postalCode && italian && !/^\d{5}$/.test(postalCode)) throw new FieldError("postalCode", "CAP non valido: sono 5 cifre.");
    const city = text("city", 100, "Comune");
    const province = italian ? code("province", 40, "Provincia") : text("province", 40, "Provincia");
    if (province && italian && !/^[A-Z]{2}$/.test(province)) throw new FieldError("province", "Provincia: usa la sigla di due lettere, per esempio MI.");

    const mainEmail = email("email", "Email");
    const billingEmail = email("billingEmail", "Email per avvisi e fatture");
    const phone = text("phone", 40, "Telefono");

    return {
      ok: true,
      data: {
        kind, name, firstName, lastName, contactName, taxCode, vatNumber, sdiCode, pec,
        street, postalCode, city, province, country, email: mainEmail, billingEmail, phone,
      },
    };
  } catch (error) {
    if (error instanceof FieldError) return { ok: false, field: error.field, message: error.message };
    throw error;
  }
}

// ---------- Reading the record ----------

/** Tax codes and VAT numbers are stored uppercase, without spaces and without the IT prefix of a VAT number. */
export function searchCodeOf(query: string): string {
  const compact = query.replace(/\s+/g, "").toUpperCase();
  return /^IT\d/.test(compact) ? compact.slice(2) : compact;
}

/** Why the type is missing, or null when the save may go on: only a customer saved before the type existed may stay without. */
export function kindProblem(existing: { kind: string | null } | null, kind: CustomerKind | null): string | null {
  if (kind) return null;
  return existing === null || existing.kind ? "Scegli se il cliente è un privato o un'azienda." : null;
}

const PROFILE_KEYS = [
  "kind", "name", "firstName", "lastName", "contactName", "taxCode", "vatNumber", "sdiCode", "pec",
  "street", "postalCode", "city", "province", "country", "email", "billingEmail", "phone",
] as const satisfies readonly (keyof CustomerProfile)[];

/** Names of the fields that differ, for the audit log (never the values). */
export function changedFields(current: StoredCustomer, next: CustomerProfile): string[] {
  return PROFILE_KEYS.filter((key) => current[key] !== next[key]);
}

export type CustomerFormValues = Partial<Record<CustomerField, string>>;

/**
 * A saved customer as form values. A customer saved before the type existed has only a name: it is offered ready in the
 * business-name field and split into first and last name, so choosing a type does not mean typing it again.
 */
export function customerFormValues(customer: StoredCustomer): CustomerFormValues {
  const untyped = customer.kind !== "PERSON" && customer.kind !== "COMPANY";
  const [first = "", ...rest] = untyped ? customer.name.trim().split(/\s+/) : [customer.firstName ?? ""];
  return {
    kind: customer.kind ?? "",
    name: customer.name,
    firstName: untyped ? first : customer.firstName ?? "",
    lastName: untyped ? rest.join(" ") : customer.lastName ?? "",
    companyName: customer.kind === "PERSON" ? "" : customer.name,
    contactName: customer.contactName ?? "",
    taxCode: customer.taxCode ?? "",
    vatNumber: customer.vatNumber ?? "",
    sdiCode: customer.sdiCode ?? "",
    pec: customer.pec ?? "",
    street: customer.street ?? "",
    postalCode: customer.postalCode ?? "",
    city: customer.city ?? "",
    province: customer.province ?? "",
    country: customer.country ?? "IT",
    email: customer.email ?? "",
    billingEmail: customer.billingEmail ?? "",
    phone: customer.phone ?? "",
  };
}

/** A customer as read from the database, where the type is a plain string. */
export type StoredCustomer = Omit<CustomerProfile, "kind"> & { kind: string | null };

type BillingFacts = Pick<StoredCustomer, "kind" | "taxCode" | "vatNumber" | "sdiCode" | "pec" | "street" | "postalCode" | "city" | "province" | "country">;

/** What is missing to invoice this customer; empty when complete. Saving never depends on it. */
export function billingGaps(customer: BillingFacts): string[] {
  if (customer.kind !== "PERSON" && customer.kind !== "COMPANY") return ["tipo cliente"];
  const italian = (customer.country ?? "IT") === "IT";
  const gaps: string[] = [];
  if (customer.kind === "COMPANY") {
    if (!customer.vatNumber) gaps.push("partita IVA");
    if (italian && !customer.sdiCode && !customer.pec) gaps.push("codice SDI o PEC");
  } else if (italian && !customer.taxCode) {
    gaps.push("codice fiscale");
  }
  if (!customer.street) gaps.push("indirizzo");
  if (italian && !customer.postalCode) gaps.push("CAP");
  if (!customer.city) gaps.push("comune");
  if (italian && !customer.province) gaps.push("provincia");
  return gaps;
}

export function formatAddress(customer: Pick<CustomerProfile, "street" | "postalCode" | "city" | "province" | "country">): string | null {
  const town = [customer.postalCode, customer.city].filter(Boolean).join(" ") + (customer.province ? ` (${customer.province})` : "");
  const parts = [customer.street, town.trim(), customer.country].filter((part): part is string => Boolean(part));
  return customer.street || customer.city || customer.postalCode ? parts.join(", ") : null;
}

/** A plain text block to paste into the invoicing software. */
export function billingText(customer: StoredCustomer): string {
  const address = formatAddress(customer);
  const lines: (string | null)[] = [
    customer.name,
    customer.vatNumber ? `Partita IVA: ${customer.vatNumber}` : null,
    customer.taxCode ? `Codice fiscale: ${customer.taxCode}` : null,
    address ? `Indirizzo: ${address}` : null,
    customer.sdiCode ? `Codice SDI: ${customer.sdiCode}` : null,
    customer.pec ? `PEC: ${customer.pec}` : null,
    customer.contactName ? `Referente: ${customer.contactName}` : null,
    customer.billingEmail ? `Email fatture e avvisi: ${customer.billingEmail}` : null,
    customer.email ? `Email: ${customer.email}` : null,
    customer.phone ? `Telefono: ${customer.phone}` : null,
  ];
  return lines.filter((line): line is string => line !== null).join("\n");
}

/** Where automatic notices go: the billing email when there is one, else the main email. */
export function noticeEmail(customer: { email: string | null; billingEmail: string | null }): string | null {
  return customer.billingEmail ?? customer.email;
}
