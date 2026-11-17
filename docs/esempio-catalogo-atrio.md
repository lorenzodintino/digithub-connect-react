# Esempio: catalogo dei piani di Atrio

Risposte **reali** del License Manager (generate facendo girare il codice dell'API su un database di prova), per il software `ATRIO` con i tre piani **Base**, **Pro** e **Studio** e gli attributi iniziali elencati nel prompt 3. I valori sono quelli indicativi del prompt: li decide l'amministratore dal pannello. Gli `id` cambiano da un'installazione all'altra, i `code` no.

Come sono stati impostati nel pannello:

- Attributi (sezione **Attributi dei piani**): booleani `resources`, `waiting_list`, `packages`, `group_services`, `calendar_buttons`, `embed`, `custom_smtp` (predefinito **No**); numerici `max_services`, `max_operators`, `max_bookings_per_month`, `max_waiting_list`, `max_packages` (predefinito **0**, cioè «non incluso»).
- I «—» della tabella del prompt non hanno un valore proprio: prendono il predefinito (`0` / `false`). «Illimitato» è un valore numerico a `null`.
- Listino indicativo: Base 19 €, Pro 39 €, Studio 79 € al mese; 12 mesi = 10 mensilità.

## `GET /api/v1/plans`

Header della risposta: `Content-Type: application/json`, `Cache-Control: no-store`, `ETag: "0c39ebf828a21aa88b4ebcf56ebceafd"`.

```json
{
  "data": {
    "software": {
      "code": "ATRIO",
      "name": "Atrio"
    },
    "updatedAt": "2026-11-17T13:40:54.775Z",
    "attributes": [
      {
        "key": "max_services",
        "type": "integer",
        "label": "Servizi",
        "default": 0
      },
      {
        "key": "max_operators",
        "type": "integer",
        "label": "Operatori",
        "default": 0
      },
      {
        "key": "max_bookings_per_month",
        "type": "integer",
        "label": "Prenotazioni al mese",
        "default": 0
      },
      {
        "key": "max_waiting_list",
        "type": "integer",
        "label": "Persone in lista d'attesa per servizio",
        "default": 0
      },
      {
        "key": "max_packages",
        "type": "integer",
        "label": "Pacchetti",
        "default": 0
      },
      {
        "key": "resources",
        "type": "boolean",
        "label": "Risorse",
        "default": false
      },
      {
        "key": "waiting_list",
        "type": "boolean",
        "label": "Lista d'attesa",
        "default": false
      },
      {
        "key": "packages",
        "type": "boolean",
        "label": "Pacchetti",
        "default": false
      },
      {
        "key": "group_services",
        "type": "boolean",
        "label": "Servizi di gruppo",
        "default": false
      },
      {
        "key": "calendar_buttons",
        "type": "boolean",
        "label": "Pulsanti calendario",
        "default": false
      },
      {
        "key": "embed",
        "type": "boolean",
        "label": "Prenotazione incorporata",
        "default": false
      },
      {
        "key": "custom_smtp",
        "type": "boolean",
        "label": "Server di posta personalizzato",
        "default": false
      }
    ],
    "plans": [
      {
        "id": "cmwkqq2jw000qtt94rx7qgcpq",
        "code": "base",
        "name": "Base",
        "description": "Per iniziare: un professionista con poche prenotazioni.",
        "position": 1,
        "active": true,
        "prices": [
          {
            "months": 1,
            "priceCents": 1900,
            "currency": "EUR",
            "active": true
          },
          {
            "months": 12,
            "priceCents": 19000,
            "currency": "EUR",
            "active": true
          }
        ],
        "attributes": {
          "max_services": 3,
          "max_operators": 1,
          "max_bookings_per_month": 100,
          "max_waiting_list": 0,
          "max_packages": 0,
          "resources": false,
          "waiting_list": false,
          "packages": false,
          "group_services": false,
          "calendar_buttons": false,
          "embed": false,
          "custom_smtp": false
        }
      },
      {
        "id": "cmwkqq2k1000ytt94mfkw28xm",
        "code": "pro",
        "name": "Pro",
        "description": "Per uno studio con più persone e un giro di prenotazioni costante.",
        "position": 2,
        "active": true,
        "prices": [
          {
            "months": 1,
            "priceCents": 3900,
            "currency": "EUR",
            "active": true
          },
          {
            "months": 12,
            "priceCents": 39000,
            "currency": "EUR",
            "active": true
          }
        ],
        "attributes": {
          "max_services": 15,
          "max_operators": 5,
          "max_bookings_per_month": 1000,
          "max_waiting_list": 5,
          "max_packages": 3,
          "resources": true,
          "waiting_list": true,
          "packages": true,
          "group_services": false,
          "calendar_buttons": true,
          "embed": true,
          "custom_smtp": true
        }
      },
      {
        "id": "cmwkqq2k6001ett94n5i9o6r4",
        "code": "studio",
        "name": "Studio",
        "description": "Per studi strutturati: nessun limite e tutte le funzioni.",
        "position": 3,
        "active": true,
        "prices": [
          {
            "months": 1,
            "priceCents": 7900,
            "currency": "EUR",
            "active": true
          },
          {
            "months": 12,
            "priceCents": 79000,
            "currency": "EUR",
            "active": true
          }
        ],
        "attributes": {
          "max_services": null,
          "max_operators": null,
          "max_bookings_per_month": null,
          "max_waiting_list": null,
          "max_packages": null,
          "resources": true,
          "waiting_list": true,
          "packages": true,
          "group_services": true,
          "calendar_buttons": true,
          "embed": true,
          "custom_smtp": true
        }
      }
    ]
  }
}
```

Rimandando l'`ETag` in `If-None-Match` (`curl -i -H "Authorization: Bearer $LICENSE_API_KEY" -H 'If-None-Match: "0c39ebf828a21aa88b4ebcf56ebceafd"' "$LICENSE_API_URL/api/v1/plans"`) il catalogo non cambiato risponde `304 Not Modified`, senza corpo.

## `GET /api/v1/license?tenant=org_7f3c9a1e2b` (tenant sul piano Pro)

Risposta completa; i campi nuovi sono `license.planType.code`, `license.planType.attributes` e `periods[].planType.code`.

```json
{
  "data": {
    "checkedAt": "2026-11-18T09:30:00.000Z",
    "access": {
      "allowed": true,
      "reason": "active",
      "state": "ACTIVE",
      "expiresAt": "2027-11-18T00:00:00.000Z",
      "graceEndsAt": "2027-11-25T00:00:00.000Z",
      "graceDays": 7
    },
    "license": {
      "id": "cmwkqq2ki001ytt94lkv69p00",
      "status": "ACTIVE",
      "startsAt": "2026-11-18T00:00:00.000Z",
      "expiresAt": "2027-11-18T00:00:00.000Z",
      "lifetime": false,
      "createdAt": "2026-11-17T13:40:54.787Z",
      "updatedAt": "2026-11-17T13:40:54.787Z",
      "planType": {
        "id": "cmwkqq2k1000ytt94mfkw28xm",
        "code": "pro",
        "name": "Pro",
        "prices": [
          {
            "months": 1,
            "priceCents": 3900,
            "currency": "EUR",
            "active": true
          },
          {
            "months": 12,
            "priceCents": 39000,
            "currency": "EUR",
            "active": true
          }
        ],
        "attributes": {
          "max_services": 15,
          "max_operators": 5,
          "max_bookings_per_month": 1000,
          "max_waiting_list": 5,
          "max_packages": 3,
          "resources": true,
          "waiting_list": true,
          "packages": true,
          "group_services": false,
          "calendar_buttons": true,
          "embed": true,
          "custom_smtp": true
        }
      }
    },
    "periods": [
      {
        "id": "cmwkqq2ki0020tt9481z7aufd",
        "kind": "ISSUE",
        "source": "MANUAL",
        "planType": {
          "id": "cmwkqq2k1000ytt94mfkw28xm",
          "code": "pro",
          "name": "Pro"
        },
        "months": 12,
        "amountCents": 39000,
        "previousExpiresAt": null,
        "newExpiresAt": "2027-11-18T00:00:00.000Z",
        "voidedAt": null,
        "voidReason": null,
        "createdAt": "2026-11-17T13:40:54.787Z",
        "corrections": []
      }
    ],
    "balance": {
      "dueCents": 39000,
      "paidCents": 39000,
      "outstandingCents": 0,
      "currency": "EUR"
    },
    "payments": [
      {
        "id": "cmwkqq2km0022tt94oo5tyuos",
        "amountCents": 39000,
        "currency": "EUR",
        "paidAt": "2026-11-18T00:00:00.000Z",
        "note": "Bonifico",
        "voidedAt": null,
        "voidReason": null,
        "createdAt": "2026-11-17T13:40:54.791Z"
      }
    ],
    "customer": {
      "id": "cmwkqq2kb001utt9420kt1oh2",
      "kind": "COMPANY",
      "name": "Studio Esempio Srl",
      "firstName": null,
      "lastName": null,
      "contactName": "Giulia Verdi",
      "email": "info@studioesempio.example",
      "billingEmail": "amministrazione@studioesempio.example",
      "phone": "+39 0412345678",
      "taxCode": "01234567897",
      "vatNumber": "01234567897",
      "sdiCode": "ABC1234",
      "pec": null,
      "address": {
        "street": "Via Garibaldi 10",
        "postalCode": "30100",
        "city": "Venezia",
        "province": "VE",
        "country": "IT"
      },
      "birthDate": null,
      "archived": false,
      "archivedAt": null,
      "createdAt": "2026-11-17T13:40:54.779Z"
    },
    "tenant": {
      "id": "cmwkqq2kf001wtt94h79jb3tg",
      "externalId": "org_7f3c9a1e2b",
      "archived": false,
      "archivedAt": null,
      "createdAt": "2026-11-17T13:40:54.783Z"
    },
    "software": {
      "id": "cmwkqq2id0000tt94769jas0u",
      "code": "ATRIO",
      "name": "Atrio",
      "description": "Gestionale e portale di prenotazione",
      "status": "ACTIVE",
      "graceDays": 7
    }
  }
}
```

## Il piano attuale nella risposta `/license`, per ciascuno dei tre piani

Per un tenant sul piano **Base** (`license.planType` e il piano di ogni periodo):

```json
{
  "license": {
    "planType": {
      "id": "cmwkqq2jw000qtt94rx7qgcpq",
      "code": "base",
      "name": "Base",
      "prices": [
        {
          "months": 1,
          "priceCents": 1900,
          "currency": "EUR",
          "active": true
        },
        {
          "months": 12,
          "priceCents": 19000,
          "currency": "EUR",
          "active": true
        }
      ],
      "attributes": {
        "max_services": 3,
        "max_operators": 1,
        "max_bookings_per_month": 100,
        "max_waiting_list": 0,
        "max_packages": 0,
        "resources": false,
        "waiting_list": false,
        "packages": false,
        "group_services": false,
        "calendar_buttons": false,
        "embed": false,
        "custom_smtp": false
      }
    }
  },
  "periods": [
    {
      "planType": {
        "id": "cmwkqq2jw000qtt94rx7qgcpq",
        "code": "base",
        "name": "Base"
      }
    }
  ]
}
```

Per un tenant sul piano **Studio** (i limiti a `null` sono **illimitati**):

```json
{
  "license": {
    "planType": {
      "id": "cmwkqq2k6001ett94n5i9o6r4",
      "code": "studio",
      "name": "Studio",
      "prices": [
        {
          "months": 1,
          "priceCents": 7900,
          "currency": "EUR",
          "active": true
        },
        {
          "months": 12,
          "priceCents": 79000,
          "currency": "EUR",
          "active": true
        }
      ],
      "attributes": {
        "max_services": null,
        "max_operators": null,
        "max_bookings_per_month": null,
        "max_waiting_list": null,
        "max_packages": null,
        "resources": true,
        "waiting_list": true,
        "packages": true,
        "group_services": true,
        "calendar_buttons": true,
        "embed": true,
        "custom_smtp": true
      }
    }
  },
  "periods": [
    {
      "planType": {
        "id": "cmwkqq2k6001ett94n5i9o6r4",
        "code": "studio",
        "name": "Studio"
      }
    }
  ]
}
```

(Per il piano **Pro** vedi la risposta completa qui sopra.)

## Come leggerli nel software

- Il piano si riconosce da `code` (`base`, `pro`, `studio`), mai da `name`.
- `attributes.max_services === null` → nessun limite; `0` → limite di zero; chiave assente → non incluso.
- `attributes.resources === true` → funzione attiva; `false` o chiave assente → non attiva.
