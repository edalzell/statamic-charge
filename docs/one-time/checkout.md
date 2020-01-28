For requirements and installation please see the [top-level page](../../DOCUMENTATION.md)

This page describes how to accept one time payments via Stripe [Checkout](https://stripe.com/docs/payments/checkout)

Please see the [documentation](https://stripe.com/docs/payments/checkout/server) on the client JS you need.

**NOTE:** the `redirectToCheckout` does NOT work well with Statamic's `form` and `user` form tags as the redirect happens and form submission does not. In general, use Elements (see below) instead.

You can get the Session ID two ways, with a tag, or via AJAX.

## Tag

`{{ charge:session type="one-time" ...params }}`

Supported parameters:

* type - required, use `one-time`
* success_url - required
* cancel_url - required
* name - required
* description - optional
* amount - required
* currency - optional, defaults to `usd`
* quantity - optional, defaults to `1`

**NOTE**: Statamic tags are parsed BEFORE the page is rendered so the tag method only works if the amount is fixed (or passed in to the tag somehow).

### Example

```
<article class="content">
    <form>
        <button class="button primary" id="payment-button">Pay</button>
    </form>}
</article>

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

## AJAX

Send a POST request (don't forget the CSRF token) to the `/!/Charge/session` endpoint. Returns an array with `id` => $session->id.

Supported parameters:

* type - required, use `one-time`
* success_url - required
* cancel_url - required
* name - required
* description - optional
* amount - required
* currency - optional, defaults to `usd`
* quantity - optional, defaults to `1`

### Example

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

