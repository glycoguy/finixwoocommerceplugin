# Canada

This guide outlines how Finix supports payments in Canada and what you need to know when building integrations for Canadian merchants.

![Finix Canada](/assets/canada.936b7469400135b74e4841f8b7cfc5edd0d7efa2a7a6acbec5f5ae414e334ac9.fec90461.svg)

## Payments Overview

Finix supports Interac, Visa, Mastercard, American Express, and Discover payments for Canadian merchants. All transactions must be processed in Canadian dollars (CAD).

## Online Payments

You can use our existing API calls and low-code/no-code features in Canada. Finix's Payment Links, Checkout Forms, Subscriptions are supported for USA and CAN merchants.

## In-Person Payments

**In Person Payments in Canada is currently in pilot and not generally available.** To participate in the pilot, you must reach out to [Finix Support](mailto:support@finix.com).

### Supported Devices

Finix currently supports the following two certified devices in Canada:

- [**PAX A800**](/guides/in-person-payments/select-your-device/pax-a800)
- [**PAX A920 Pro**](/guides/in-person-payments/select-your-device/pax-a920-pro)


### Cardholder Language Selection

Cardholders can toggle between English and French on the terminal when processing a transaction.

div
div
img
div
img
div
img
## Interac Payments

Interac is the domestic debit network in Canada. Debit cards issued by Canadian banks typically display the Interac logo, even when co-branded with Visa or Mastercard.

Regardless of co-branding, all Canadian debit transactions are routed through the Interac network when processed in Canada.

Payment Instruments associated with Interac cards will have a `brand` value of `INTERAC`. You can find all potential values for the `brand` field in the [Payment Instrument API Reference](/api/payment-instruments/createpaymentinstrument#payment-instruments/createpaymentinstrument/t=response&c=201&path=&oneof=0/brand).

Interact Payment Instruments can only be used for card-present transactions and do not support card-not-present transactions.

### Interac Sales

All Interac payments must be submitted as sales and are authorized and captured in one request. Interac does not support multi-step authorization and capture flows.

That means:

- The `POST /authorizations` flow is not supported for Interac transactions.
- Attempting to use `POST /authorizations` with an Interac card will return an error. Example response below


Sample Response:


```json
{
    "total": 1,
    "_embedded": {
        "errors": [
            {
                "logref": "c5a343f2egf4f37b",
                "message": "Authorization AUhKLUbEwkVFfDECA9Aom71A was declined.",
                "code": "DECLINED",
                "failure_code": "TRANSACTION_NOT_PERMITTED",
                "failure_message": "The transaction was declined because the card or transaction type is not permitted. The cardholder needs to use a different type of card or attempt a different transaction method.",
                "_links": {
                    "authorization": {
                        "href": "https://finix.sandbox-payments-api.com/authorizations/AUhKLUbEwkVFfDECA9Aom71A"
                    },
                    "self": {
                        "href": "https://finix.sandbox-payments-api.com/authorizations"
                    }
                },
                "authorization": "AUhKLUbEwkVFfDECA9Aom71A"
            }
        ]
    }
}
```

### Refunds for Interac transactions

Refunds for Interac transactions must be performed **in person** using a supported terminal and the **same card** that was used in the original transaction. If the card is unavailable, merchants may offer store credit or cash as an alternative refund method.

The following demonstrates how to initiate a refund on a Standalone Terminal, via API, or via the Finix Dashboard.

Standalone
On a Standalone Terminal, navigate to the transaction you wish to refund and select **Create Refund**. The terminal will prompt for the amount you wish to refund and for the card to be presented.

div
div
img
div
img
API
The following example demonstrates how to initiate an Interac refund via API.

**Request**


```bash
curl -i -X POST \
  -H 'Authorization: Basic xxxx' \
  https://finix.sandbox-payments-api.com/transfers/TRnErBfrHLgdAi3BqAkWLN27/reversals \
  -H 'Content-Type: application/json' \
  -H 'Finix-Version: 2022-02-01' \
  -d '{
    "refund_amount": 500,
    "device": "DVsEanpBtsAVvCHbNXkFaH6f",
    "operation_key": "CARD_PRESENT_REFERENCED_REFUND",
    "tags": {
      "order_number": "test-interac-refund"
    }
  }'
```

**Response**


```json
{
    "id": "TRnErBfrHLgdAi3BqAkWLN27",
    "created_at": "2025-06-13T08:22:56.38Z",
    "updated_at": "2025-06-13T08:22:57.59Z",
    "amount": 150,
    "amount_requested": 150,
    "application": "APeUbTUjvYb1CdPXvNcwW1wP",
    "card_present_details": {
        "payment_type": "NONE"
    },
    "currency": "CAD",
    "destination": "PIdk3BzKSmtMXAN42W6mvD3Y",
    "device": "DVf2H8sh4LZZC52GTUrwCPPf",
    "failure_code": null,
    "failure_message": null,
    "fee": 0,
    "merchant": "MUeDVrf2ahuKc9Eg5TeZugvs",
    "merchant_identity": "IDsbTBawhnLBAVeinRb84vFR",
    "operation_key": "CARD_PRESENT_REFERENCED_REFUND",
    "state": "SUCCEEDED",
    "statement_descriptor": "FIN*FINIX FLOWERS",
    "subtype": "API",
    "tags": {
        "order_number": "testing123"
    },
    "trace_id": "FNXoXopxGC9Bk8cwmiHRxBUaV",
    "type": "DEBIT",
    "_links": {
        "application": {
            "href": "https://finix.sandbox-payments-api.com/applications/APeUbTUjvYb1CdPXvNcwW1wP"
        },
        "self": {
            "href": "https://finix.sandbox-payments-api.com/transfers/TRnErBfrHLgdAi3BqAkWLN27"
        },
        "merchant_identity": {
            "href": "https://finix.sandbox-payments-api.com/identities/IDsbTBawhnLBAVeinRb84vFR"
        },
        "device": {
            "href": "https://finix.sandbox-payments-api.com/devices/DVf2H8sh4LZZC52GTUrwCPPf"
        },
        "reversals": {
            "href": "https://finix.sandbox-payments-api.com/transfers/TRnErBfrHLgdAi3BqAkWLN27/reversals"
        },
        "destination": {
            "href": "https://finix.sandbox-payments-api.com/payment_instruments/PIdk3BzKSmtMXAN42W6mvD3Y"
        }
    }
}
```

Dashboard
To initiate a refund via the Finix Dashboard, navigate to the transaction you wish to refund and select **Issue Refund**. The dashboard will prompt for the amount you wish to refund and to select the device that will be used to process the refund.

div
div
img
## Settlements

Interac transactions are settled independently from other card networks. When a merchant processes both Interac and non-Interac transactions, Finix will generate **two separate settlements**:

- One settlement for all Interac transactions
- One settlement for all other card brands (such as Visa, Mastercard, and Discover)


This separation ensures compliance with Interac’s clearing and settlement rules and helps merchants reconcile their payouts accurately by card network.

### Funding Schedule

The table below outlines funding timelines for sales created by Canadian merchants, based on when the sale is created (relative to the 7 PM PST cutoff) and the currency or card type used.

| Card Type | Created Before 7 PM PST | Created After 7 PM PST |
|  --- | --- | --- |
| Domestic Cards | 1 business day | 2 business days |
| International Visa | 2 business days | 3 business days |
| International Mastercard | 1 business day | 2 business days |