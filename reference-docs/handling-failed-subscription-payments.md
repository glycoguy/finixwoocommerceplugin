# Handling Failed Subscription Payments

Learn how to create subscriptions quickly using the Finix Dashboard our using the Finix API.

## First Payment for a Subscription

When a Subscription is created, Finix creates a `first_charge_at` field that tells you when Finix will attempt a payment. This is based on whether the `Subscription` has a trial phase and when the start date of the `Subscription` is.

You can view the First Payment Description in the Dashboard on the Subscriptions List Page for easy reference.

When Finix attempts to debit the `payment_instrument` associated with the Subscription, a `Transfer` resource will be created. The `Transfer` resource contains information about the `state`, `payment_instrument`, and more.

To learn more about Viewing Payments, click here (Need to add a link to an existing article).

## Subscription Payment Re-attempts (Dunning)

In the event, the initial `Transfer` fails and does not succeed, Finix will attempt the following dunning schedule.

- 1 Day After
- 3 Days After
- 8 Days After
- 19 Days After


In the event the `Transfer` after 15 days does not succeeded, Finix will Cancel the Subscription.