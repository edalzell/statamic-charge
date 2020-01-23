## Requirements ##

1. Charge requires PHP 7.1+
2. Stripe API >= 2019-10-17

## Installation ##

1. Copy Charge folder to `site/addons`
2. Run `php please update:addons`
3. If you're going to use subscriptions, register the webhook w/ Stripe [here](https://dashboard.stripe.com/account/webhooks). Set it to `https://yoursite.com/!/Charge/webhook`.

## Settings ##

* Go to `cp/addons/charge/settings` and fill in the required fields. `Collections` is for if you are using Workshop.
* in `charge.yaml`
    * `charge_collections` - which collection(s) you'll be using for the Workshop submission
    * `currency` - default currency for payments
    * `plan` & `role` - when a customer signs for a plan, which role(s) should they have
    * `from_email` - when the payment failed emails go out, what email account do they come from
    * `canceled_email_template` & `payment_failed_email_template` - email templates to use for the failed payment emails
    * `upcoming_payment_email_template` - email template to use for the customer's upcoming payment/invoice

* in your [`.env` file](https://docs.statamic.com/environments#the-env-file), which MUST NOT be checked in:
    * please note the proper format for the [key/value pair](https://docs.statamic.com/environments#the-env-file)
    * `STRIPE_SECRET_KEY` - Stripe secret key, found here: https://dashboard.stripe.com/account/apikeys
    * `STRIPE_PUBLIC_KEY` - Stripe public key, found here: https://dashboard.stripe.com/account/apikeys
    * `STRIPE_ENDPOINT_SECRET` - Webhook signing secret, found here: https://dashboard.stripe.com/webhooks (click on the Charge webhook then "Click to reveal")

#### Example settings ####
```
charge_formset: charge
charge_collections:
  - things
currency: usd
plans_and_roles:
  - 
    plan: associate
    role:
      - dd062758-f56c-4ca5-a381-fe88f9c54517
from_email: renewals@thedalzells.org
canceled_email_template: email/cancel_subscription
payment_failed_email_template: email/payment_failed
upcoming_payment_email_template: email/payment_upcoming
```

## Usage ##

Two options, [One Time](docs/payments/one-time.md) payments or [subscriptions](docs/payments/subscriptions.md)

Please note that much of the payment processing now occurs on the front end, so please see the Stripe docs for what's needed.

### Emails ###

#### Upcoming Payment Email ####

This is sent according to your Stripe settings, and assumes you have the webhook set up properly

In the email template, you have access to:

* `{{ plan }}` - The short name of the subscription plan
* `{{ first_name }}` - The first name of the customer
* `{{ last_name }}` - The last name of the customer
* `{{ due_date }}` - When the payment will occur, in Unix timestamp format
* `{{ amount }}` - Amount that will be charged, in cents
* `{{ currency }}` - currency of payment

#### Failed Payment Email ####

This is sent if a payment fails.

In the email template, you have access to:

* `{{ plan }}` - The short name of the subscription plan
* `{{ first_name }}` - The first name of the customer
* `{{ last_name }}` - The last name of the customer
* `{{ due_date }}` - When the payment will occur, in Unix timestamp format
* `{{ amount }}` - Amount that will be charged, in cents
* `{{ currency }}` - currency of payment
* `{{ attempt_count }}` - how many times Charge has tried to process the payment
* `{{ next_payment_attempt }}` - when Charge will try again, in Unix timestamp format
*

### Forms ###

For Workshop entry creation, add `{{ charge:process_payment }}` to the template used to **CREATE** entries. Do **NOT** put it on the template used to edit entries.

### Tags ###

* Cancel - `{{ charge:cancel_subscription_form }}` - creates a form to cancel a subscription. It uses the current logged in users' subscription.
    * example
```
{{ charge:cancel_subscription_form }}
    <button id="payment-button">Cancel</button>
{{ /charge:cancel_subscription_form }}
```
* Resubscribe - `{{ charge:renew_subscription_url }}` - creates a URL to resubscribe to a subscription. Pass in the `subscription_id`
* Success - `{{ charge:success }}` - was the last action/transaction successful?
* Errors - `{{ charge:errors }}` - if there were errors, they'll be in here
* Details - `{{ charge:details }}` - transaction details (all the data from Stripe)

### User Data ###

The following subscription data is stored in the user:

* `customer_id` - Stripe customer id
* `created_on` - timestamp indicating when customer was created
* `plan`: Stripe plan user is subscribed to
* `subscription_start`: timestamp marking the beginning of the subscription
* `subscription_end`: timestamp marking the end of the subscription
* `subscription_id`: Strip subscription id
* `subscription_status`: status of the subscription. One of:
    * `active` - subscription is current
    * `canceled` - subscription is inactive
    * `canceling` - subscription will not auto-renew at `subscription_end` 
    * `past_due` - payment has failed but subscription not canceled, yet

### Payments ###

#### Payment Failures ####

When a customer's payment fails the first time, you can send an email to the them based on the `payment_failed_email_template`.

The template has the following variables:

* `plan` - the plan the user is on
* `first_name` - customer's first name
* `last_name`- customer's last name
* `attempt_count` - how many times you've tried to process their payment
* `next_payment_attempt` - timestamp of next payment processing attempt

On the last payment failure, their subscription is cancelled and the `canceled_email_template` is used.

These variables are available:

* `plan` - the plan the user is on
* `first_name` - customer's first name
* `last_name`- customer's last name

