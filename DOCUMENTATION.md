## Requirements ##

1. Charge requires PHP 7.1+
2. Stripe API >= 2019-10-17

## Installation ##

1. Copy Charge folder to `site/addons`
2. Run `php please update:addons`
3. If you're going to use subscriptions, register the webhook with Stripe [here](https://dashboard.stripe.com/account/webhooks). Set it to `https://yoursite.com/!/Charge/webhook`.

## Settings ##

* Go to `cp/addons/charge/settings` and fill in the required fields. `Collections` is for if you are using Workshop.
* in `charge.yaml`
    * `charge_collections` - which collection(s) you'll be using for the Workshop submission
    * `currency` - default currency for payments
    * `plan` & `role` - when a customer subscribles to a plan, which role(s) should they have. You could use this to have different membership tiers for example.
    * `from_email` - when the payment failed emails go out, what email account do they come from
    * `one_time_payment_email_template` - successful one time payment
    * `canceled_email_template` & `payment_failed_email_template` - email templates to use for the failed payment emails
    * `upcoming_payment_email_template` - email template to use for the customer's upcoming payment/invoice

* in your [`.env` file](https://docs.statamic.com/environments#the-env-file), which MUST NOT be checked in with Git:
    * please note the proper format for the [key/value pair](https://docs.statamic.com/environments#the-env-file)
    * `STRIPE_SECRET_KEY` - Stripe secret key, found here: https://dashboard.stripe.com/account/apikeys
    * `STRIPE_PUBLIC_KEY` - Stripe public key, found here: https://dashboard.stripe.com/account/apikeys
    * `STRIPE_ENDPOINT_SECRET` - Webhook signing secret, found here: https://dashboard.stripe.com/webhooks (click on the Charge webhook then "Click to reveal")

#### Example settings ####
```
charge_collections:
  - things
currency: usd
plans_and_roles:
  - 
    plan: associate
    role:
      - dd062758-f56c-4ca5-a381-fe88f9c54517
from_email: support@silentz.co
one_time_payment_email_template: emails/one_time_payment
canceled_email_template: emails/cancel_subscription
payment_failed_email_template: emails/payment_failed
upcoming_payment_email_template: emails/payment_upcoming
payment_succeeded_email_template: emails/payment_successful
```

## Usage ##

Please note that much of the payment processing now occurs on the front end, so please see the [Stripe docs](https://stripe.com/docs/stripe-js) to understand the payment flow.

You have two options, [One Time](docs/one-time/general.md) payments or [subscriptions](docs/subscription/general.md)