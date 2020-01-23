## Tags

[Create Subscription](create-subscription.md)

The tags below share the Statamic [forms](https://docs.statamic.com/tags/form-create#parameters) parameters.

### Cancel Subscription
```
{{ charge:cancel_subscription_form :id="subscription_id" }}
    <button id="cancel-button">Cancel</button>
{{ /charge:cancel_subscription_form }}
```

### Update Subscription

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

### Update Billing

```
{{ charge:update_billing_form :id="customer_id" }}
    <div class="form-row">
        <label for="card-element">Credit card</label>
        <!-- A Stripe Element will be inserted here. -->
        <div id="card-element"></div>

        <!-- Used to display Element errors. -->
        <div id="card-errors" role="alert"></div>
    </div>

    <button id="billing-button">Update</button>
{{ /charge:update_billing_form }}
```

Need to create a new payment method via JS and add the `payment_method` to the form:
```
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

        // first create the payment method
        stripe.createPaymentMethod(
            'card',
            card,
            {
                billing_details: {
                    email: '{{ email }}',
                },
            }).then(function(result) {
                addToForm('payment_method', result.paymentMethod.id, form);
                form.submit();
            });
    }
);

function addToForm(name, value, form) {
    let input = document.createElement('input');

    input.type = 'hidden';
    input.name = name;
    input.value = value;

    form.appendChild(input);
}

</script>
```

