# Subscriptions Quickstart

Learn how to create subscriptions quickly using the Finix Dashboard our using the Finix API.

Subscriptions allow you to charge a fixed amount to card or bank account on a recurring time period. You can use Subscriptions to charge buyers, customers, and more.

Example use cases for Subscriptions:

- Charging buyers a recurring monthly fee to access a gym or health club
- Charging buyers an annual membership fee for services or goods rendered
- Charging merchants a monthly or annual fee for using your software


Pricing
This product requires an additional fee. Please get in touch with your Finix Point of Contact for pricing on this feature.

## Creating a Subscription on the Finix Dashboard

You can create a `Subscription` in the Finix Dashboard without any development work. Using the Finix Dashboard, you can:

- Create subscriptions from existing buyers and payment instruments
- View the existing `Subscriptions`
- Cancel a `Subscription` if a buyer or customer chooses to end their goods or services


### Step 1: Navigate to Subscriptions

To create a Subscription in the Finix Dashboard:

1. Log into the [Finix Dashboard](https://finix.payments-dashboard.com/Login).
2. Click **Billing** > **Subscriptions**.
3. Click **Create Subscription**
4. Fill out the form with the requested details.


![Subscriptions List Page](/assets/subscriptions-list-page.dfcf153ce782c2bede25e5fcd034405620e24c263c305789f4a191d628bdc9f4.08ff7529.svg)

### Step 2. Create a Subscription

When you click on **Create Subscription**, a form will appear with the following options:

#### Billing Settings

This section specifies how you want to bill your buyers.

- Subscription Name
- Billing Frequency
- Recurring Price


![Create Subscriptions](/assets/subscriptions-create.55662c84bcd80d6480104d387feb02291b4476d346cffa3af9de0d44cc43773b.08ff7529.png)

#### Trial Phase

You can enter a trial phase if your `Subscription` requires a set number of days before charging the buyer. For example, a 30-day trial, 3-month trial, or other combinations are possible.

#### Discount Phase

You can enter a discount phase if you would like to have a discounted rate for a set of amount of intervals. These take place after the Trial Phase is completed.

#### Start Date

You can specify a start date for the subscription in the future. This can be useful if you would like all your subscriptions to start on the same day.

#### Interval Length

If you want to create a Subscription for a set amount of time, you can specify an interval length. For example, if you have a monthly subscription, you can specify an interval length of 6 and the subscription will bill for 6 months.

#### Select Buyer

When you create a Subscription, you'll need to:

1. Select a buyer you want to charge. This can be either a buyer, seller, or recipient identity.
2. Select the payment instrument you want to bill.


#### Creating a Buyer and Payment Instrument

On the **Create Subscription** page, we allow you to create a buyer and a card payment instrument.

If you would like to add a bank account on this page, you will need to use our API. If you require an exception, email our support team.

#### Review and Create Subscriptions

After filling out the previous section, you can create the subscription.

Depending on whether there is a trial phase or not, the subscription will create a transfer shortly after creation. The initial transfer will be made within 60 minutes of the subscription creation.

##### Card Validation with $0 Authorization

When a subscription is created using a card-type payment instrument, Finix automatically performs a **$0 `Authorization`** to validate the card. This soft check confirms that the
card is active and that the billing address and security code match the card issuer’s records (AVS and CVV checks).

By performing this check up front, Finix helps reduce failed payments and prevent fraudulent transactions — ensuring a smoother experience for both you and your customers.

## Additional Details

### Supported Countries

Subscriptions are available in the following countries:

- United States
- Canada


### Supported Payment Methods

Subscriptions currently support:

- Recurring Card payments
- Recurring Bank account payments (ACH in USA and EFTs in Canada)


For more details about ACH payments, see [ACH Direct Debit](/guides/online-payments/bank-payments/ach-direct-debits).

### Supported Time Periods

Finix supports the following time periods for Subscriptions:

- Daily
- Weekly
- Monthly
- Quarterly
- Yearly


When creating a Subscription, you can specify the `billing_frequency`.

## Creating a Subscription via API

To create a Subscription, include:

- The ID of the `Merchant` that the `Subscription` will be `linked_to`
- The `amount` and `currency` of the `Subscription`
- The `subscription_details`
- The `buyer_details` including both the `identity_id` and `instrument_id`


At this time, only APPROVED Merchants with one of the following processors can create subscriptions:

- `DUMMY_V1`
- `FINIX_V1`


For detailed API information, please see our [Subscriptions API Reference](/api/subscriptions/createsubscription).

The following example shows how to create a basic subscription:


```shell Create Subscription Request
curl https://finix.sandbox-payments-api.com/subscriptions \
    -H 'Content-Type: application/json' \
    -H 'Finix-Version: 2022-02-01' \
    -u USksBJMwkNUz5GyxPevL2yFY:71b641c1-861d-435b-9a9c-532760731c5e \
    -X POST \
    -d '{
        "amount": 2900,
        "currency": "USD",
        "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
        "nickname": "Test Subscription",
        "billing_interval": "MONTHLY",
        "subscription_details": {
            "collection_method": "BILL_AUTOMATICALLY",
            "trial_details": {
                "interval_type": "MONTH",
                "interval_count": "1"
            }
        },
        "buyer_details": {
            "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
            "instrument_id": "PInE8utFwr4eoXdftZBQuxGw"
        }
    }'
```


```json Create Subscription Response
{
    "id": "subscription_cd5brMYg6u1WuicGTbq1P",
    "created_at": "2024-07-10T23:38:47.43Z",
    "updated_at": "2024-07-10T23:38:47.43Z",
    "first_charge_at": "2024-07-10T23:38:47.00Z",
    "amount": 2900,
    "buyer_details": {
        "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
        "instrument_id": "PInE8utFwr4eoXdftZBQuxGw",
        "requested_delivery_methods": []
    },
    "currency": "USD",
    "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
    "linked_type": "MERCHANT",
    "nickname": "Test Subscription",
    "billing_interval": "MONTHLY",
    "subscription_details": {
        "collection_method": "BILL_AUTOMATICALLY",
        "send_invoice": false,
        "send_receipt": false,
        "trial_details": null,
        "discount_phase_details": null
    },
    "subscription_phase": "TRIAL",
    "state": "ACTIVE",
    "subscription_plan_id": null,
    "start_subscription_at": "2025-01-01T00:00:00.000Z",
    "tags": {},
    "_links": {
        "self": {
            "href": "https://finix.sandbox-payments-api.com/subscriptions/subscription_cd5brMYg6u1WuicGTbq1P"
        }
    }
}
```

### Trial Period

You can create a subscription with a trial period by providing `trial_details` in the request body.

For example, to include a trial period that lasts one month set `trial_details.interval_type` to `MONTH` and `trial_details.interval_count` to `1`.

After creating a `Subscription`, a `Transfer` will occur after the trial period has elapsed. The `first_charge_at` property in the response determines when the `Transfer` will occur.


```shell Create Subscription Request
curl https://finix.sandbox-payments-api.com/subscriptions \
    -H 'Content-Type: application/json' \
    -H 'Finix-Version: 2022-02-01' \
    -u USksBJMwkNUz5GyxPevL2yFY:71b641c1-861d-435b-9a9c-532760731c5e \
    -X POST \
    -d '{
        "amount": 2900,
        "currency": "USD",
        "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
        "nickname": "Test Subscription",
        "billing_interval": "MONTHLY",
        "subscription_details": {
            "collection_method": "BILL_AUTOMATICALLY",
            "trial_details": {
                "interval_type": "MONTH",
                "interval_count": "1"
            }
        },
        "buyer_details": {
            "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
            "instrument_id": "PInE8utFwr4eoXdftZBQuxGw"
        }
    }'
```


```json Create Subscription Response
{
    "id": "subscription_cd5brMYg6u1WuicGTbq1P",
    "created_at": "2024-07-10T23:38:47.43Z",
    "updated_at": "2024-07-10T23:38:47.43Z",
    "first_charge_at": "2024-07-10T23:38:47.00Z",
    "amount": 2900,
    "buyer_details": {
        "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
        "instrument_id": "PInE8utFwr4eoXdftZBQuxGw",
        "requested_delivery_methods": []
    },
    "currency": "USD",
    "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
    "linked_type": "MERCHANT",
    "nickname": "Test Subscription",
    "billing_interval": "MONTHLY",
    "subscription_details": {
        "collection_method": "BILL_AUTOMATICALLY",
        "send_invoice": false,
        "send_receipt": false,
        "trial_details": {
            "interval_type": "MONTH",
            "interval_count": 1,
            "trial_started_at": "2024-07-10T19:13:14.00Z",
            "trial_expected_end_at": "2024-08-10T19:13:14.00Z"
        },
        "discount_phase_details": null
    },
    "subscription_phase": "TRIAL",
    "state": "ACTIVE",
    "subscription_plan_id": null,
    "start_subscription_at": "2024-07-10T23:38:47.00Z",
    "tags": {},
    "_links": {
        "self": {
            "href": "https://finix.sandbox-payments-api.com/subscriptions/subscription_cd5brMYg6u1WuicGTbq1P"
        }
    }
}
```

### Discount Period

To apply a discount period, include `discount_phase_details` in the request body.

For example, if the full subscription price is $20 per month, and you want to offer a discounted rate of $10 per month for three months, set `discount_phase_details.amount` to `2000` and `discount_phase_details.billing_interval_count` to `3`.

After this discount period, the subscriber will be charged the full `amount` of the `Subscription`.


```shell Create Subscription Request
curl https://finix.sandbox-payments-api.com/subscriptions \
    -H 'Content-Type: application/json' \
    -H 'Finix-Version: 2022-02-01' \
    -u USksBJMwkNUz5GyxPevL2yFY:71b641c1-861d-435b-9a9c-532760731c5e \
    -X POST \
    -d '{
        "amount": 2000,
        "currency": "USD",
        "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
        "linked_type": "MERCHANT",
        "nickname": "Finflix Gold Package",
        "billing_interval": "MONTHLY",
        "subscription_details": {
            "collection_method": "BILL_AUTOMATICALLY",
            "discount_phase_details": {
                "amount": 1000,
                "billing_interval_count": 3
            }
        },
        "buyer_details": {
            "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
            "instrument_id": "PInE8utFwr4eoXdftZBQuxGw"
        }
    }'
```


```json Create Subscription Response
{
    "id": "subscription_ckpv3GLDNjDTWmxzgmptd",
    "created_at": "2025-02-20T15:15:14.63Z",
    "updated_at": "2025-02-20T15:15:14.63Z",
    "first_charge_at": "2025-02-20T15:15:14.00Z",
    "next_billing_date": {
        "year": 2025,
        "month": 2,
        "day": 20
    },
    "amount": 2000,
    "buyer_details": {
        "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
        "instrument_id": "PInE8utFwr4eoXdftZBQuxGw",
        "requested_delivery_methods": []
    },
    "currency": "USD",
    "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
    "linked_type": "MERCHANT",
    "nickname": "Finflix Gold Package",
    "billing_interval": "MONTHLY",
    "subscription_details": {
        "collection_method": "BILL_AUTOMATICALLY",
        "send_invoice": false,
        "send_receipt": false,
        "trial_details": null,
        "discount_phase_details": {
            "amount": 1000,
            "billing_interval_count": 3
        }
    },
    "subscription_phase": "DISCOUNT",
    "state": "ACTIVE",
    "subscription_plan_id": null,
    "start_subscription_at": "2025-02-20T00:00:00.000Z",
    "total_billing_intervals": null,
    "expires_at": null,
    "tags": {},
    "_links": {
        "self": {
            "href": "https://finix.sandbox-payments-api.com/subscriptions/subscription_ckpv3GLDNjDTWmxzgmptd"
        }
    }
}
```

### Start Date

Finix allows you to specify a start date for subscriptions. The `starts_subscription_at` field takes an ISO8601 formatted timestamp to start the subscription. The timestamp must be in the future.


```shell Create Subscription Request
curl https://finix.sandbox-payments-api.com/subscriptions \
    -H 'Content-Type: application/json' \
    -H 'Finix-Version: 2022-02-01' \
    -u USksBJMwkNUz5GyxPevL2yFY:71b641c1-861d-435b-9a9c-532760731c5e \
    -X POST \
    -d '{
        "amount": 2900,
        "currency": "USD",
        "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
        "nickname": "Test Subscription",
        "billing_interval": "MONTHLY",
        "start_subscription_at": "2025-05-05T22:42:05.490Z",
        "subscription_details": {
            "collection_method": "BILL_AUTOMATICALLY"
        },
        "buyer_details": {
            "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
            "instrument_id": "PInE8utFwr4eoXdftZBQuxGw"
        }
    }'
```


```json Create Subscription Response
{
    "id": "subscription_cj5AmwBg2iu8xUvcs8DUG",
    "created_at": "2025-01-11T00:19:05.57Z",
    "updated_at": "2025-01-11T00:19:05.57Z",
    "first_charge_at": "2025-05-05T22:42:05.49Z",
    "amount": 2900,
    "buyer_details": {
        "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
        "instrument_id": "PInE8utFwr4eoXdftZBQuxGw",
        "requested_delivery_methods": []
    },
    "currency": "USD",
    "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
    "linked_type": "MERCHANT",
    "nickname": "Test Subscription",
    "billing_interval": "MONTHLY",
    "subscription_details": {
        "collection_method": "BILL_AUTOMATICALLY",
        "send_invoice": false,
        "send_receipt": false,
        "trial_details": null,
        "discount_phase_details": null
    },
    "subscription_phase": "NONE",
    "state": "NOT_STARTED",
    "subscription_plan_id": null,
    "start_subscription_at": "2025-05-05T22:42:05.490Z",
    "total_billing_intervals": null,
    "expires_at": null,
    "tags": {},
    "_links": {
        "self": {
            "href": "https://finix.sandbox-payments-api.com/subscriptions/subscription_cj5AmwBg2iu8xUvcs8DUG"
        }
    }
}
```

### Fixed Length Subscriptions

To create fixed-length subscriptions, you can pass `total_billing_intervals` in the request body. The API will return an `expires_at` field to let you know when the `Subscription` will expire. The `subscription_phase` will also return a value of `FIXED`.


```shell Create Subscription Request
curl https://finix.sandbox-payments-api.com/subscriptions \
    -H 'Content-Type: application/json' \
    -H 'Finix-Version: 2022-02-01' \
    -u USksBJMwkNUz5GyxPevL2yFY:71b641c1-861d-435b-9a9c-532760731c5e \
    -X POST \
    -d '{
        "amount": 2900,
        "currency": "USD",
        "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
        "nickname": "Test Subscription",
        "billing_interval": "MONTHLY",
        "total_billing_intervals": 3,
        "subscription_details": {
            "collection_method": "BILL_AUTOMATICALLY"
        },
        "buyer_details": {
            "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
            "instrument_id": "PInE8utFwr4eoXdftZBQuxGw"
        }
    }'
```


```json Create Subscription Response
{
    "id": "subscription_cj5BAPDczRbbEZFEWQBkN",
    "created_at": "2025-01-11T00:35:26.30Z",
    "updated_at": "2025-01-11T00:35:26.30Z",
    "first_charge_at": "2025-01-11T00:35:25.00Z",
    "amount": 2900,
    "buyer_details": {
        "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
        "instrument_id": "PInE8utFwr4eoXdftZBQuxGw",
        "requested_delivery_methods": []
    },
    "currency": "USD",
    "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
    "linked_type": "MERCHANT",
    "nickname": "Test Subscription",
    "billing_interval": "MONTHLY",
    "subscription_details": {
        "collection_method": "BILL_AUTOMATICALLY",
        "send_invoice": false,
        "send_receipt": false,
        "trial_details": null,
        "discount_phase_details": null
    },
    "subscription_phase": "FIXED",
    "state": "ACTIVE",
    "subscription_plan_id": null,
    "start_subscription_at": "2025-01-11T00:00:00.000Z",
    "total_billing_intervals": 3,
    "expires_at": "2025-04-11T00:35:25.681Z",
    "tags": {},
    "_links": {
        "self": {
            "href": "https://finix.sandbox-payments-api.com/subscriptions/subscription_cj5BAPDczRbbEZFEWQBkN"
        }
    }
}
```