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

You must set up the [webhooks](https://stripe.com/docs/billing/webhooks), use `https://yoursite.com/!/Charge/webhook` as the endpoint and make to at least "listen" for:

* payment_intent.succeeded
* invoice.payment.succeeded
* invoice.upcoming
* invoice.payment_failed
* customer.subscription_updated
* customer.subscription_deleted

To test you can use `valet share` or a service like Ultrahook.

The general flow to subscribe a user to a plan/product is:

1. gather the payment information and have the user choose a plan
2. create the PaymentMethod
3. Create customer and subscribe to plan

You can use JS for the whole flow, or a combination of JS and the `{{ charge:create_subscription_form }}`.

### Create Payment Method

On button press (or form submit):
```
<script>
    var button = document.getElementById('payment-button');
    button.addEventListener('click', function (event) {
        event.preventDefault();

        // Stripe gives a warning if you don't disable the submit button
        button.disabled = true;

        // first create the payment method
        stripe.createPaymentMethod(
            'card',
            card,
            {
                billing_details: {
                    email: '{{ email }}',
                },
            }).then(function(result) {
                if (result.error) {
                    // show error
                } else {
                    // success, either submit the form or 
</script>
```

### Subscribe User to Plan

#### AJAX

```
//try to create the subscription
fetch('/!/Charge/subscription', {
    method: 'post',
    headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': '{{ csrf_token }}'
    },
    body: JSON.stringify({
        plan: 'plan_EUfLkn7S3RIy8O',
        payment_method: result.paymentMethod.id
    })
}).then(response => {
    return response.json();
})
.then(json => {
    // check the status
    const { status } = json;

    if (status == 'success') {
        alert('subscribed')
        // show success message or whatever
    } else if (status == 'requires_action') {
        const { client_secret } = json;

        stripe.confirmCardPayment(client_secret).then(function(result) {
            if (result.error) {
                // more errors
            } else {
                // success
                alert('subscribed after card confirmation');
            }
        });
    }
});
</script>
```

### Tag ###

```
{{ charge:create_subscription_form attr="id:data-charge-form" }}
    {{ if success }}
        Thank you for your subscription! :)
        {{ details }}
        <!-- subscription details, see https://stripe.com/docs/api/subscriptions/object#subscription_object for details -->
        {{ /details }}
    {{ elseif requires_action }}
        <button id="confirm" data-secret="{{ client_secret }}">Confirm Payment</button>
    {{ else }}
        <div class="form-row">
            <label for="card-element">Credit card</label>
            <!-- A Stripe Element will be inserted here. -->
            <div id="card-element"></div>

            <!-- Used to display Element errors. -->
            <div id="card-errors" role="alert"></div>
        </div>

        <input type="hidden" name="plan" value="plan_EUfLkn7S3RIy8O">

        <button id="payment-button">Pay</button>
    {{ /if }}
{{ /charge:create_subscription_form }}
```

You have to create the payment method first, see above for details. After you subscribe the user, you need to check the status for `requires_action`, then call `confirmCardPayment`:
```
<script>
if (button) {
    button.addEventListener('click', function(event) {
        event.preventDefault();

        stripe.confirmCardPayment(this.dataset.secret).then(function(result) {
            if (result.error) {
                // more errors
            } else {
                // success
                alert('subscribed after card confirmation');
            }
        });
    });
}
</script>
```

### Valid  ###

Send a POST request (don't forget the CSRF token) to the `/!/Charge/session` endpoint. Returns an array with `id` => $session->id.

### Parameters ###

The following parameters are valid for either the AJAX call or the tag. 
Supported parameters:

* type - required, use `one-time`
* success_url - required
* cancel_url - required
* name - required
* description - optional
* amount - required
* currency - optional, defaults to `usd`
* quantity - optional, defaults to `1`

### Example ###

```
<script>
    var stripe = Stripe('{{ env:STRIPE_PUBLIC_KEY }}');
    var form = document.getElementById('payment-form');
    var btn = document.getElementById('payment-button')

    btn.addEventListener('click', function (event) {
        event.preventDefault();

        // Stripe gives a warning if you don't disable the submit button
        btn.disabled = true;

        stripe.redirectToCheckout({
            sessionId: `{{ charge:session
                name="Donation"
                type="one-time"
                amount="1000"
                description="Donation $10"
                currency="usd"
                success_url="http://charge.test/success"
                cancel_url="http://charge.test/charge-checkout"
            }}`
          });
    });
</script>
```

## Elements ##

Please see the [documentation](https://stripe.com/docs/payments/payment-intents/web) on the client JS you need.

You can get the Payment Intents ID two ways, with a tag, or via AJAX.

### Tag ###

`{{ charge:payment_intent ...params... }}` - returns the client secret

### AJAX ###

Send a GET request to the `/!/Charge/payment_intent` endpoint. Returns an array with `client_secret => $payment_intent->client_secret`

### Parameters ###

At the moment, Charge supports the following Payment Intent parameters:

* name - required
* description - optional
* amount - required
* currency - optional, defaults to `usd`

Please open an [issue](https://github.com/edalzell/statamic-charge/issues) if you have a use case that requires more parameters.

### Example ###

Please note, the charging of the card and any SCA-related actions happen on the front end now. Please see the Stripe documentation for all the details.

```
<script>
    var stripe = Stripe('{{ env:STRIPE_PUBLIC_KEY }}');
    var elements = stripe.elements();

    // Create an instance of the card Element.
    var card = elements.create('card');

    // Add an instance of the card Element into the `card-element` <div>.
    card.mount('#card-element');

    var form = document.getElementById('payment-form');
    form.addEventListener('submit', function (event) {
        event.preventDefault();

        // Stripe gives a warning if you don't disable the submit button
        document.getElementById('payment-button').disabled = true;

        var url = new URL('{{ site_url }}!/Charge/payment_intent');

        var params = {
            amount: 1100,
            description: "The description"
        }

        url.search = new URLSearchParams(params).toString();

        fetch(url)
            .then(response => response.json())
            .then(pi => {
                stripe.confirmCardPayment(
                    pi.client_secret,
                    {
                        payment_method: {
                            card: card,
                            billing_details: {
                                name: document.getElementById('name').value,
                                email: document.getElementById('email').value,
                            }
                        },
                        receipt_email: document.getElementById('email').value
                    }
                ).then(function(result) {
                    if (result.error) {
                        console.log(result.error);
                        // Display error.message in your UI.
                    } else {
                        // The payment has succeeded
                        // Display a success message

                        // if you want to record the charge id in the order form
                        // add it to the form
                        var chargeId = result.paymentIntent.charges.data[0].id;

                        addToForm('charge_id', chargeId, form);

                        // let Statamic & Charge do their things
                        form.submit();
                    }
                });
            });
    });
</script>
```