For requirements and installation please see the [top-level page](../../DOCUMENTATION.md)

This page describes how to create subscriptions on your site. The methods below work with the with or without the SCA requirements.

Please see the [documentation](https://stripe.com/docs/billing/subscriptions/) to understand Stripe subscriptions.

**Notes**:
* this only works if you already have a logged in user. i.e. setting up subscriptions during user registration is **not** supported at this time.
* use [Elements](https://stripe.com/docs/stripe-js#elements) to gather the payment information

```
<script>
    var stripe = Stripe('{{ env:STRIPE_PUBLIC_KEY }}');
    var elements = stripe.elements();

    // Create an instance of the card Element.
    var card = elements.create('card');

    // Add an instance of the card Element into the `card-element` <div>.
    card.mount('#card-element');
</script>
```

## Webhooks

You must set up the [webhooks](https://stripe.com/docs/billing/webhooks), use `https://yoursite.com/!/Charge/webhook` as the endpoint and listen for:

* customer.subscription.created
* customer.subscription.deleted
* customer.subscription.updated
* invoice.upcoming
* invoice.payment_failed
* payment_intent.succeeded

To test you can use `valet share` or a service like [Ultrahook](http://www.ultrahook.com).

## Flow

The general flow to subscribe a user to a plan/product is:

1. Gather the payment information and have the user choose a plan
2. Create the PaymentMethod
3. Subscribe to plan (Charge creates the customer)

You can use JS for the whole flow, or a combination of JS and the `{{ charge:create_subscription_form }}`:

* [JS](create-subscription-tag.md)
* [JS + tag](create-subscription-ajax.md)
