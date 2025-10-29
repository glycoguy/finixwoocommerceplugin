# Recurring Payments Guidelines

Learn how to stay in compliance with Recurring Payments and Subscriptions

**Note:** This product requires an additional fee. Please contact your Finix Point of Contact for pricing on this feature.

Subscriptions allow you to charge a fixed amount to `payment_instrument` on a recurring time period. You can use Subscriptions to charge buyers, customers, or even `merchants`.

## 1. General Disclaimer

Important: There are federal, state, card network, and NACHA requirements that govern a merchant’s use of recurring, subscription, negative option, and automatic renewal payment plans (collectively, “automatic renewal requirements”). These automatic renewal requirements impose obligations on merchants to disclose the key terms of any automatic renewal payment plan, obtain the customer’s informed consent and authorization, provide simple mechanisms to cancel, and provide confirmation of the authorization to the customer, among other notice requirements.

Before implementing any automatic renewal payment plan you should consult with legal counsel on compliance with applicable requirements. By using the Finix Platform, you represent and warrant that your use of any automatic renewal payment plan complies with applicable automatic renewal requirements. You also acknowledge and agree that Finix shall have no responsibility or liability for your compliance or failure to comply with same.

The information provided on this website does not, and is not intended to, constitute legal advice. All information, content, and materials available on this website are provided for general informational purposes only. Information on this website may not constitute the most up-to-date legal or other information. Finix makes no representations about the content of the information provided on this website and disclaims all liability for the content.

## 2. Card Brand Requirements

Visa and Mastercard impose disclosure and related requirements on merchants that sell goods or services through automatic renewal payment plans (referred to as recurring or subscription payments). You can find more information on the Visa and Mastercard requirements by consulting the [Mastercard Rules](https://www.mastercard.us/content/dam/public/mastercardcom/na/global-site/documents/transaction-processing-rules.pdf) and [Visa Rules](https://usa.visa.com/content/dam/VCOM/download/about-visa/visa-rules-public.pdf). A summary of the Mastercard and Visa rules for automatic renewal payment plans is provided below. **Visa and Mastercard requirements do not take the place of federal and state automatic renewal laws; compliance with Visa and Mastercard rules may not ensure your compliance with federal or state automatic renewal laws.**

**Mastercard**

- Disclose the subscription terms when collecting the payment credential, including the price that will be billed and the frequency of the billing, as well as any trial period.
- Display the subscription terms on any payment or order summary webpage and capture cardholder’s affirmative acceptance before completing the order.
- After cardholder completes the subscription order, send an email confirmation or other electronic message that includes:
  - The subscription terms
  - A transaction receipt
  - Clear instructions about how to cancel the subscription.
- For any subscription where billing frequency is every 6 months or less, send an electronic reminder to the cardholder at least 7 days but no more than 30 days prior to the next billing date that includes the subscription terms and instructions for how to cancel the subscription.
- Provide an online or electronic cancellation mechanism or clear instructions on how to cancel that are easily accessible online (such as “Manage Subscription” or “Cancel Subscription” links on the merchant’s home page).
- Provide the cardholder with written confirmation in either hard copy or electronic format at least 7 days in advance when any of the following events occur:
  - Trial period expires
  - Changes to subscription billing terms
  - Recurring payment schedule has been terminated by either the merchant or cardholder, in which case the notice must be sent no more than 7 days after the cardholder’s decision to cancel.
- For trial periods longer than 7 days, send a reminder notice to the cardholder at least 3 days before the renewal and include basic terms of the subscription and instructions on how to cancel. After trial period expires, provide cardholder with information on the recurring payments and obtain explicit consent for recurring payments.
- For negative option plans, each time the merchant receives an approved authorization request, provide the cardholder with a receipt through an email or SMS text message with instructions for terminating the recurring payment plan.


**Visa**

- At the time of enrollment, obtain the cardholder’s express consent to the recurring transactions. Disclose the fixed dates or intervals on which recurring transactions will be processed.
- At the time of enrollment, send an electronic copy of the terms and conditions of the recurring payments to the cardholder.
- If the recurring payments involve a trial period, disclose the length of the trial period, and send an electronic reminder notice with a link to online cancellation at least 7 days before initiating a recurring transaction after the trial period ends. Visa has specific billing statement descriptors for indicating that a transaction is related to a trial period.
- Provide a simple cancellation procedure. If the purchase was online, provide at least an online cancellation procedure.


## 3. Common Practices for Compliance

Before implementing an automatic renewal payment plan you should consult with legal counsel. You can find more information on the Mastercard and Visa requirements by consulting the [Mastercard Rules](https://www.mastercard.us/content/dam/public/mastercardcom/na/global-site/documents/transaction-processing-rules.pdf) and [Visa Rules](https://usa.visa.com/content/dam/VCOM/download/about-visa/visa-rules-public.pdf).

Common practices for recurring payments compliance may include:

- Disclose clearly and conspicuously the material terms and conditions of the transaction and payment schedule before collecting the payment credential. Material terms and conditions may include, without limitation:
  - the identity of the merchant;
  - a description of the goods or services;
  - the cost or price of the goods or services, including any introductory or trial price and the regular price that will be charged after any introductory period;
  - the existence of the recurring payment plan, including the amount and payment schedule;
  - the length of any introductory or trial period, if applicable;
  - the amount of any subsequent charges, and the dates or frequency of any subsequent charges; and
  - how to cancel the recurring payment plan, including the date by when the consumer must cancel to avoid the next charge.
  - Display the subscription terms on any payment or order summary webpage and capture cardholder’s affirmative acceptance before completing the order.
  - Obtain the customer’s informed consent and authorization for the transaction and recurring payment schedule through a written agreement, which may include having the purchaser check a box or provide a similar electronic signature confirming review and consent to the terms and conditions of the transaction and recurring payment schedule.
  - Provide an online or electronic cancellation mechanism or clear instructions on how to cancel that are easily accessible online (such as “Manage Subscription” or “Cancel Subscription” links on the merchant’s home page).
  - Send the purchaser a confirmation of the transaction and recurring payment schedule that includes: (1) the subscription terms, (2) the amount of each recurring payment and payment schedule, (3) the cancellation policy, and (4) how to cancel (including an online cancellation mechanism for online purchases)
  - Send a reminder notice with the payment amount and information on how to cancel prior to the next scheduled payment. For annual payment plans, a written reminder notification that includes the material terms and conditions, including cancellation policy, sent no less than 30 days but no more than 60 days before the cancellation deadline.
    - Mastercard: For any subscription where billing frequency is every six months or less, send an electronic reminder to the cardholder at least 7 days but no more than 30 days prior to the next billing date that includes the subscription terms and instructions for how to cancel the subscription.
  - If there is a material change in the terms of the recurring payment plan, before implementing the change, provide the customer with clear and conspicuous notice of the material change and information regarding how to cancel prior to the change being implemented.
    - Mastercard: Provide the cardholder with written confirmation in either hard copy or electronic format at least 7 days in advance when any of the following events occur:
      - Trial period expires
      - Changes to the subscription billing terms
      - The recurring payment plan has been terminated by either the merchant or the cardholder, in which case the notice must be sent no more than 7 days after the cardholder’s decision to cancel.
  - If offering a trial period in connection with a recurring payment plan, disclose the length of the trial period.
    - Mastercard: For trial periods longer than 7 days, send a reminder notice (via email) to the cardholder at least 3 days before the renewal and include basic terms of the subscription and instructions on how to cancel. After trial period expires, provide cardholder with information on the recurring payments and obtain explicit consent for recurring payments.
    - Visa: Send an electronic reminder notice with a link to online cancellation at least 7 days before initiating a recurring transaction after the trial period ends.