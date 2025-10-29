# Managing Subscriptions

Learn how to manage your recurring subscriptions on the dashboard or via API.

## Editing a Subscription

You can edit Subscriptions in the Finix Dashboard or via API.

To edit a Subscription via the Dashboard:

1. Navigate to the Subscription you would like to Edit.
2. On the top right, select Edit Subscription.
3. Update the editable fields and save the form.


Note:

- Please see the [Recurring Payment Guidelines](/guides/subscriptions/recurring-payment-guidelines#recurring-payments-guidelines) if you want to change an amount for a subscription. We also suggest you discuss this with your legal counsel.
- For ACH payments, you must obtain a new Authorization for modified amounts.


### Editable Fields

You can edit:

- Subscription Name
- Recurring Price
- Payment Instrument associated with a subscription


### Editing Amounts

Subscriptions that are edited after a successful payment, will have the following charge_date correspond with the change in pricing.

### Editing a Subscription via API

To edit a Subscription via API, see our API reference.

## Canceling a Subscription

Canceling a Subscription is available via the Finix Dashboard and via API. Canceling a subscription is non-reversible. If you would like to re-activate this subscription, you will need to create a new Subscription.

To cancel a Subscription on the Dashboard:

1. Navigate to the Subscription you would like to cancel
2. On the top right, select Cancel Subscription
3. Confirm you want to cancel the Subscription


### Canceling a Subscription via API

To cancel our Subscription via API, please see our [API Reference](/api/subscriptions/removesubscription).