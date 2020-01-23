## Tags

```
{{ charge:create_subscription_form }}
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
        <select name="plan">
            <option value="">--Please choose a plan--</option>
            {{ charge:plans }}
                <option value="{{ id }}" {{ if plan == id }} selected{{ /if }}>{{ product.name }} {{ if nickname }}({{ nickname }}){{ /if }}</option>
            {{ /charge:plans }}
        </select>

        <button id="payment-button">Pay</button>
    {{ /if }}
{{ /charge:create_subscription_form }}
```

Example JS:
```
<script>
    var stripe = Stripe('{{ env:STRIPE_PUBLIC_KEY }}');
    var elements = stripe.elements();

    // Create an instance of the card Element.
    var card = elements.create('card');

    // Add an instance of the card Element into the `card-element` <div>.
    card.mount('#card-element');

    let form = document.getElementById("data-charge-form");
    let button = document.getElementById('confirm');

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            // Stripe gives a warning if you don't disable the submit button
            document.getElementById('payment-button').disabled = true;

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
                        form.submit();
                    }
                });
            }
        );
    }

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

    function addToForm(name, value, form) {
        let input = document.createElement('input');

        input.type = 'hidden';
        input.name = name;
        input.value = value;

        form.appendChild(input);
    }

</script>
```