For requirements and installation please see the [top-level page](../../DOCUMENTATION.md)

This page describes how to accept one time payments on your site via Stripe [Elements](https://stripe.com/docs/web/setup).

Please see the [documentation](https://stripe.com/docs/payments/payment-intents/web) on the client JS you need.

You can get the Payment Intents ID two ways, with a tag, or via AJAX.

## Tag

`{{ charge:payment_intent ...params }}` - returns the client secret

Supported parameters:

* name - required
* description - optional
* amount - required
* currency - optional, defaults to `usd`

### Example 

```
<article class="content">
    {{ form:create in="charge" attr="class:form|id:payment-form" redirect="/thanks" }}
        {{ if errors }}
            {{ errors }}
                {{ value }}
            {{ /errors }}
        {{ /if }}
        <div class="form-item">
            <label>Cardholder Name</label>
            <input type="text" id="name" name="name" value="{{ old:name }}" />
        </div>

        <div class="form-item">
            <label>Email</label>
            <input type="email" id="email" name="email" value="{{ old:email }}" />
        </div>

        <div class="form-row">
            <label for="card-element">Credit card</label>
            <!-- A Stripe Element will be inserted here. -->
            <div id="card-element"></div>

            <!-- Used to display Element errors. -->
            <div id="card-errors" role="alert"></div>
        </div>

        <button id="payment-button" data-secret="{{ charge:payment_intent name="Donation" amount="1000" }}">Donate</button>
    {{ /form:create }}
</article>

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
        button = document.getElementById('payment-button');
        button.disabled = true;

        stripe.confirmCardPayment(
            button.dataset.secret,
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
</script>
```

## AJAX

Send a GET request to the `/!/Charge/payment_intent` endpoint. Returns an array with `client_secret => $payment_intent->client_secret`

Supported parameters:

* name - required
* description - optional
* amount - required
* currency - optional, defaults to `usd`

### Example

Please note, the charging of the card and any SCA-related actions happen on the front end now. Please see the Stripe documentation for all the details.

```
<article class="content">
    {{ form:create in="charge" attr="class:form|id:payment-form" redirect="/thanks" }}
        {{ if errors }}
            {{ errors }}
                {{ value }}
            {{ /errors }}
        {{ /if }}
        <div class="form-item">
            <label>Cardholder Name</label>
            <input type="text" id="name" name="name" value="{{ old:name }}" />
        </div>

        <div class="form-item">
            <label>Email</label>
            <input type="email" id="email" name="email" value="{{ old:email }}" />
        </div>

        <div class="form-row">
            <label for="card-element">Credit card</label>
            <!-- A Stripe Element will be inserted here. -->
            <div id="card-element"></div>

            <!-- Used to display Element errors. -->
            <div id="card-errors" role="alert"></div>
        </div>

        <button id="payment-button">Submit Payment</button>
    {{ /form:create }}
</article>

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