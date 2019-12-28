For requirements and installation please see the [top-level page](../../DOCUMENTATION.md)

This page describes how to accept one time payments on your site. The methods below work with with or without the SCA requirements.

There are [two ways](https://stripe.com/docs/web) to take payment with Stripe, [Checkout](https://stripe.com/docs/payments/checkout) or [Elements](https://stripe.com/docs/web/setup), and Charge works either way.

## Checkout ##

Please see the [documentation](https://stripe.com/docs/payments/checkout/server) on the client JS you need.

**NOTE:** the `redirectToCheckout` does NOT work well with Statamic's `form` and `user` form tags as the redirect happens and form submission does not. In generat, use Elements (see below) instead.

You can get the Session ID two ways, with a tag, or via AJAX.

### Tag ###

`{{ charge:session ...params... }}`

For Workshop entry creation, add `{{ charge:process_payment }}` to the template used to **CREATE** entries. Do **NOT** put it on the template used to edit entries.


### AJAX ###

Send a POST request (don't forget the CSRF token) to the `/!/Charge/session` endpoint. Returns an array with `id` => $session->id.

### Parameters ###

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