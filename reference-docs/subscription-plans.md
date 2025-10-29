# Subscription Plans

Learn how to create Subscription Plans to manage Subscriptions at scale.

Subscription plans are templates with set recurring costs and frequencies that can be used to create subscriptions for individual buyers.

Example use cases for Subscription Plans:

- You have pricing tiers (e.g., Basic, Premium, Deluxe, Enterprise). You can use the pricing tiers to group subscriptions under them.
- You have different products or services you offer that have different recurring costs and frequencies (e.g. 30 min guitar lessons, 60 min guitar lessons,)
- You charge different recurring rates based on the location


Early Access
This product requires an additional fee. Your Finix Point of Contact will update you on pricing for this feature.

## Creating a Subscription Plan on the Finix Dashboard

You can create a `Subscription Plan` in the Finix Dashboard without any development work. Using the Finix Dashboard, you can:

- Create `Subscription Plans`
- View all existing `Subscription Plans`
- De-Activate a `Subscription Plan` if you no longer want to add new subscriptions under that Subscription Plan.


### Step 1: Navigate to Subscription Plans

To create a Subscription Plan in the Finix Dashboard:

1. Log into the [Finix Dashboard](https://finix.payments-dashboard.com/Login).
2. Click **Billing** > **Subscription Plans**.
3. Click **Create Subscription Plan**
4. Fill out the form with the requested details.


![Subscriptions List Page](/assets/subscriptions-list-page.dfcf153ce782c2bede25e5fcd034405620e24c263c305789f4a191d628bdc9f4.08ff7529.svg)

### Step 2. Create a Subscription Plan

When you click on **Create Subscription Plan**, a form will appear with the following options:

#### Billing Settings

This section specifies how you want to bill your buyers.

- Subscription Name
- Billing Frequency
- Recurring Price


#### Trial Phase

You can enter a trial phase if your `Subscription` requires a set number of days before charging the buyer. For example, a 30-day trial, 3-month trial, or other combinations are possible.

#### Discount Phase

You can enter a discount phase if you would like to have a discounted rate for a set of amount of intervals. These take place after the Trial Phase is completed.

#### Review and Create Subscription Plans

After filling out the previous section, you can create the `Subscription Plan`.

## Creating a Subscription from a Subscription Plan

You have two options to create a Subscription from a Subscription Plan.

### From the Subscription Plan Details Page

1. Navigate to a Subscription Plan
2. Click on the Actions dropdown on the top right
3. Select Create Subscription
4. Enter the buyer information and complete the form.


### From the Subscriptions List Page

1. Click **Billing** > **Subscriptions**.
2. Click **Create Subscription**
3. Unselect **Create subscription without a plan**
4. Select a Subscription Plan
5. Enter the buyer information and complete the form.


## Creating a Subscription Plan via API

To create a Subscription Plan, include:

- The ID of the `Merchant` that the Subscription Plan will be `linked_to`
- The `amount` and `currency` of the Subscription Plan
- Additional details of the Subscription Plan. For detailed API information, please see our [Subscriptions Plans API Reference](/api/subscription-plans/createsubscriptionplan).


At this time, only APPROVED Merchants with one of the following processors can create Subscription Plans:

- `DUMMY_V1`
- `FINIX_V1`



```shell Create Subscription Plan Request
curl https://finix.sandbox-payments-api.com/subscription_plans \
    -H 'Content-Type: application/json' \
    -H 'Finix-Version: 2022-02-01' \
    -u USksBJMwkNUz5GyxPevL2yFY:71b641c1-861d-435b-9a9c-532760731c5e \
    -X POST \
    -d '{
        "amount": 1099,
        "currency": "USD",
        "plan_name": "Bronze Plan",
        "description": "Test",
        "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
        "linked_type": "MERCHANT",
        "billing_interval": "MONTHLY",
        "billing_defaults": {
            "collection_method": "BILL_AUTOMATICALLY"
        },
        "trial_defaults": {
            "interval_type": "MONTH",
            "interval_count": "1"
        }
    }'
```


```json Create Subscription Plan Response
{
    "id": "subscription_plan_cd92Tzs8qNPbNbd2JMhN5",
    "created_at": "2024-07-13T00:28:58.42Z",
    "updated_at": "2024-07-13T00:28:58.42Z",
    "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
    "linked_type": "MERCHANT",
    "billing_interval": "MONTHLY",
    "nickname": null,
    "plan_name": "Bronze Plan",
    "description": "Test",
    "amount": 1099,
    "currency": "USD",
    "billing_defaults": {
        "collection_method": "BILL_AUTOMATICALLY",
        "send_invoice": false,
        "send_receipt": false
    },
    "trial_defaults": {
        "interval_type": "MONTH",
        "interval_count": 1
    },
    "state": "ACTIVE",
    "_links": {
        "self": {
            "href": "https://finix.sandbox-payments-api.com/subscription_plans/subscription_plan_cd92Tzs8qNPbNbd2JMhN5"
        }
    }
}
```

## Creating a Subscription from a Subscription Plan via the API

To create a Subscription from a Subscription Plan, specify the `subscription_plan_id` in the request body of a `Subscription`.


```shell Create Subscription Request
curl https://finix.sandbox-payments-api.com/subscriptions \
    -H 'Content-Type: application/json' \
    -H 'Finix-Version: 2022-02-01' \
    -u USksBJMwkNUz5GyxPevL2yFY:71b641c1-861d-435b-9a9c-532760731c5e \
    -X POST \
    -d '{
        "amount": 1099,
        "currency": "USD",
        "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
        "linked_type": "MERCHANT",
        "subscription_plan_id": "subscription_plan_cd92Tzs8qNPbNbd2JMhN5",
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
    "id": "subscription_cd93R9WNaowZCDMGbyzc6",
    "created_at": "2024-07-13T00:41:32.38Z",
    "updated_at": "2024-07-13T00:41:32.38Z",
    "first_charge_at": "2024-07-13T00:41:32.00Z",
    "amount": 1099,
    "buyer_details": {
        "identity_id": "ID2maTpthyAYJWnZ5kDD42Cd",
        "instrument_id": "PInE8utFwr4eoXdftZBQuxGw"
    },
    "currency": "USD",
    "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
    "linked_type": "MERCHANT",
    "nickname": null,
    "billing_interval": "MONTHLY",
    "subscription_details": {
        "collection_method": "BILL_AUTOMATICALLY",
        "send_invoice": false,
        "send_receipt": false,
        "trial_details": {
            "interval_type": "MONTH",
            "interval_count": 1,
            "trial_started_at": "2024-07-13T00:41:31.00Z",
            "trial_expected_end_at": "2024-08-13T00:41:31.00Z"
        }
    },
    "subscription_phase": "TRIAL",
    "state": "ACTIVE",
    "subscription_plan_id": "subscription_plan_cd92Tzs8qNPbNbd2JMhN5",
    "_links": {
        "self": {
            "href": "https://finix.sandbox-payments-api.com/subscriptions/subscription_cd93R9WNaowZCDMGbyzc6"
        }
    }
}
```

## Creating a Subscription from a Subscription Plan via the API with a Start Date

You can specify a start date for a `Subscription` that uses a [Subscription Plan](/guides/subscriptions/subscription-plans).

To do so, include a `start_subscription_at` timestamp in the request body:


```shell Create Subscription Request
curl https://finix.sandbox-payments-api.com/subscriptions \
    -H 'Content-Type: application/json' \
    -H 'Finix-Version: 2022-02-01' \
    -u USksBJMwkNUz5GyxPevL2yFY:71b641c1-861d-435b-9a9c-532760731c5e \
    -X POST \
    -d '{
        "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
        "linked_type": "MERCHANT",
        "subscription_plan_id": "subscription_plan_ccDDx1CcN1Mtqto55TWiZ",
        "start_subscription_at": "2025-05-05T22:42:05.490Z",
        "buyer_details": {
            "identity_id": "IDe8MHoq9cevVGocJwpAN8tR",
            "instrument_id": "PIh5syYGDw2SnvFjPVLcV3oD"
        }
    }'
```


```json Create Subscription Response
{
    "id": "subscription_cjDY6TyAEJ8do2LyapNUW",
    "created_at": "2025-01-28T15:23:53.48Z",
    "updated_at": "2025-01-28T15:23:53.48Z",
    "first_charge_at": "2025-05-05T22:42:05.49Z",
    "amount": 19900,
    "buyer_details": {
        "identity_id": "IDeDVrf2ahuKc9Eg5TeZugvz",
        "instrument_id": "PIeDVrf2ahuKc9Eg5TeZugvb",
        "requested_delivery_methods": []
    },
    "currency": "USD",
    "linked_to": "MUaC9hbNvRwBoCJzqrjWk69N",
    "linked_type": "MERCHANT",
    "nickname": null,
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
    "subscription_plan_id": "subscription_plan_cjDXvcWFaKb672s4zknCC",
    "start_subscription_at": "2025-05-05T22:42:05.490Z",
    "total_billing_intervals": null,
    "expires_at": null,
    "tags": {},
    "_links": {
        "self": {
            "href": "https://finix.sandbox-payments-api.com/subscriptions/subscription_cjDY6TyAEJ8do2LyapNUW"
        }
    }
}
```