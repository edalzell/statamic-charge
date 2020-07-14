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

## Control Panel

In the Control Panel, under Tools, you'll see a `Charge` item and from there you can see/update:
* Customers
* Charges - you can refund a transaction and get a link to the Stripe transaction
* Subscriptions - you can cancel see the details and cancel subscription

Further information, please see your Stripe [dashboard](https://dashboard.stripe.com).

## Flow

The general flow to subscribe a user to a plan/product is:

1. Gather the payment information and have the user choose a plan
2. Create the PaymentMethod
3. Subscribe to plan (Charge creates the customer)


## Tags

### Plans

Lists your Stripe Plans

Supported Parameters:
* `limit` - optional, how many plans to return, defaults to 10 (Stripe default)
* `active` - optional, return plans that are active or inactive
* `product` - optional, only return plans for the given product

Fields:
* All data listed [here](https://stripe.com/docs/api/plans/object)

### Example

```
<select name="plan">
    <option value="">--Please choose a plan--</option>
    {{ charge:plans limit="20" }}
        <option value="{{ id }}" {{ if plan == id }} selected{{ /if }}>{{ product.name }}{{ if nickname }}({{ nickname }}){{ /if }}</option>
    {{ /charge:plans }}
</select>
```

### Create Subscription

Assumes a logged in user.

There are two ways to do it, with a mix of tags & JS or completely in JS:

* [JS + tag](create-subscription-tag.md)
* [JS](create-subscription-ajax.md)

### Update Plan/Quantity

Assumes a logged in user.

Use this tag to allow users to change their own subscription plan and/or quantity

Supported Parameters:
* `id` - required

Fields:
* `plan` - new plan id
* `quantity` - how many of them (usually 1)

### Example

**NOTE**: `subscription_id` will be a value available to you in the user data

```
{{ charge:update_subscription_form :id="subscription_id" }}
    <select name="plan">
        <option value="">--Please choose a plan--</option>
        {{ charge:plans }}
            <option value="{{ id }}" {{ if plan == id }} selected{{ /if }}>{{ product.name }}{{ if nickname }}({{ nickname }}){{ /if }}</option>
        {{ /charge:plans }}
    </select>

    <button id="payment-button">Update</button>
{{ /charge:update_subscription_form }}
```

### Update Billing Information

Assumes a logged in user.

Use this tag to allow users to change their billing information (credit card, etc)

Supported Parameters:
* `id` - required
* `attr` - optional, like the Form [tag](https://docs.statamic.com/tags/form-create#parameters)

Fields:
* `plan` - new plan id
* `quantity` - how many of them (usually 1)

**NOTE**: `customer_id` will be a value available to you in the user data

```
{{ charge:update_billing_form :id="customer_id" attr="id:foo"}}
    <div class="form-row">
        <label for="card-element">Credit card</label>
        <!-- A Stripe Element will be inserted here. -->
        <div id="card-element"></div>

        <!-- Used to display Element errors. -->
        <div id="card-errors" role="alert"></div>
    </div>

    <button id="billing-button">Update</button>
{{ /charge:update_billing_form }}

<script>
    var stripe = Stripe('{{ env:STRIPE_PUBLIC_KEY }}');
    var elements = stripe.elements();

    // Create an instance of the card Element.
    var card = elements.create('card');

    // Add an instance of the card Element into the `card-element` <div>.
    card.mount('#card-element');

    var button = document.getElementById('billing-button');
    var form = document.getElementById('billing-form-id');
    button.addEventListener('click', function (event) {
        event.preventDefault();

        // Stripe gives a warning if you don't disable the submit button
        button.disabled = true;

        // create the new payment method
        stripe.createPaymentMethod(
            'card',
            card,
            {
                billing_details: {
                    email: '{{ email }}',
                },
            }).then(function(result) {
                addToForm('payment_method', result.paymentMethod.id, form);

                // send to Charge
                form.submit();
            });
    }
);

```

### Cancel Subscription

Assumes a logged in user.

Use this tag to allow users to cancel their subscription

Supported Parameters:
* `id` - required

**NOTE**: `subscription_id` will be a value available to you in the user data

```
{{ charge:cancel_subscription_form :id="subscription_id" }}
    <button id="cancel-button">Cancel</button>
{{ /charge:cancel_subscription_form }}

```
