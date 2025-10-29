# Subscription Links

Subscription Links allow your buyers to easily sign up for subscriptions. These links can be shared via email, SMS, or other channels, making it easy to sign up for a recurring service.

Early Access
This product is in early access. This feature is enabled in Sandbox by default. If you would like to have this enabled on your Live environment, email your Finix point of contact.

## Creating a Subscription Link on the Finix Dashboard

You can create a `Subscription Link` in the Finix Dashboard without any development work. To create a Subscription Link, you must first have a pre-existing Subscription Plan.

Using the Finix Dashboard, you can:

- Create `Subscription Links`
- View all existing `Subscription Links`
- De-Activate a `Subscription Link` if you no longer want to use a Subscription Link


### Step 1: Navigate to Subscription Links

To create a Subscription Link in the Finix Dashboard:

1. Log into the [Finix Dashboard](https://finix.payments-dashboard.com/Login).
2. Click **Payment Tools** > **Subscriptions** > **Subscription Links**.
![Subscriptions List Page](/assets/subscription-links-list-page.05600648654e5ca48c956a18866933d8d4fd9582bff05af47cbeb0427acfe936.08ff7529.png)


### Step 2. Create a Link

When you click on **Create Subscription Link**, a form will appear allowing you to create a Subscription.

![Subscriptions List Page](/assets/subscription-links-create.afb9a0268ec3626732c0d045d715bc1d246a35306b53cc2ca78a2591ccefe40f.08ff7529.png)

#### Subscription Plan Selection

- **Plan Template:** To create a subscription link, you must have an existing Subscription Plan.
- **Subscription Name:** You will need to enter a Subscription Name that buyers will see. We recommend using a name that the buyers will recognize, which defaults to the same name as the plan template.
- **Plan Image URL:** You can include an image URL to be used for a Subscription.


#### Adding Additional Subscription Plans

Finix's Subscription Links allow you to attach multiple Subscription Plans to a Subscription. This allows buyers to select which Subscription Plan to subscribe to.

For example, you could offer a Gold, Silver, and Bronze plan with different pricing.

![Subscriptions List Page](/assets/subscription-links-add-another-plan.b24b0167a68821dee29a62942360ef06d0b8f23731d2bb2f38aa8aec13bf0eeb.08ff7529.png)

#### Subscription Settings

On the following page, you can configure:

- **Single Use or Multi-Use:** You can choose whether the Subscription Link is one-time or multi-use.
- **Link Validity:** You can configure the default validity of the subscription link.
- **Terms of Service URL:** A Terms of Service URL is required for using subscription links. We recommend linking to your terms that dictate Subscription or recurring payment guidelines and policies.
- **Buyer Details:** We collect email by default. You have additional options you can collect, such as name, phone number, and more.
- **Accepted Payment Details:** We support Cards and Bank Payments. You can configure to support both or just one of each.
- **Receipt Settings:** You can choose to send a receipt to your buyer and include additional recipients.


![Subscriptions List Page](/assets/subscription-links-subscription-settings.9ebe914293006fd3448611b4307b5036f65e175a0292db275b6f1c06dbd515d0.08ff7529.png)

#### Customize your Subscription Link

On the Subscription Link itself you can customize the following:

- **Button Text:** You can customize the text that appears on the Subscription Link. The default value is Subscribe.
- **Custom success return URL:** A URL to redirect buyers to if their subscription signup is successful. This can be used to show a custom success page or link to a good or service.
- **Custom failure return URL:** A URL to redirect buyers to if their subscription signup is unsuccessful. This can be used to provide additional instructions or allow them to restart the process.
- **Custom expired session URL:** A URL to redirect buyers to if their session expires before completing the subscription process. This can be used to provide additional instructions or allow them to restart the process.


#### Customize your branding

Each Subscription Link can have custom branding. This is helpful if you manage multiple brands in the same Application.

## Example Subscription Link

To see an example Subscription Link, you can paste this sandbox subscription link in your browser.


```
https://link.sandbox-payments-checkout.com/jNGmn6
```