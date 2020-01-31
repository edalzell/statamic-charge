## Flow

The general flow to subscribe a user to a plan/product is:

1. Gather the payment information and have the user choose a plan
2. Create the PaymentMethod
3. Subscribe to plan (Charge creates the customer)

Also, please review Stripe's [docs](https://stripe.com/docs/billing/subscriptions/set-up-subscription) on how subscriptions work.

## Example

```
<form id="my-form">
    <div class="form-row">
        <label for="card-element">Credit card</label>
        <!-- A Stripe Element will be inserted here. -->
        <div id="card-element"></div>

        <!-- Used to display Element errors. -->
        <div id="card-errors" role="alert"></div>
    </div>
    <select name="plan" id="plan">
        <option value="">--Please choose a plan--</option>
        {{ charge:plans }}
            <option value="{{ id }}" {{ if plan == id }} selected{{ /if }}>{{ product.name }} {{ if nickname }}({{ nickname }}){{ /if }}</option>
        {{ /charge:plans }}
    </select>

    <button id="payment-button">Subscribe</button>
</form>
```

Example AJAX JS:
```
<script>
    var stripe = Stripe('{{ env:STRIPE_PUBLIC_KEY }}');
    var elements = stripe.elements();

    // Create an instance of the card Element.
    var card = elements.create('card');

    // Add an instance of the card Element into the `card-element` <div>.
    card.mount('#card-element');

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
                    //try to create the subscription
                    fetch('/!/Charge/subscription', {
                        method: 'post',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': '{{ csrf_token }}'
                        },
                        body: JSON.stringify({
                            plan: document.getElementById('#plan').options[e.selectedIndex].value,
                            payment_method: result.paymentMethod.id
                        })
                    }).then(response => {
                        return response.json();
                    })
                    .then(json => {
                        // check the status
                        const { status } = json;

                        if (status == 'success') {
                            // show success message or whatever
                        } else if (status == 'requires_action') {
                            const { client_secret } = json;

                            stripe.confirmCardPayment(client_secret).then(function(result) {
                                if (result.error) {
                                    // more errors
                                } else {
                                    // success
                                }
                            });
                        } else {
                            const { errors } = json;

                            let element = document.getElementById('card-errors');

                            element.innerHTML = errors.code + ': ' + errors.message;
                        }
                    });
                }
            }
        );
    }
);
</script>
```