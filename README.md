README

Copy Charge folder to `site/addons` and create (or copy) the settings file to `site/settings/addons/charge.yaml`

Settings:

* in `charge.yaml`
	* `formset` - which forest you'll be using for the Form submission
	* `show_on` - an array that lists which templates the Charge JS should appear on, or leave out for all pages
* in your `.env` file, which MUST NOT be checked in:
	* `STRIPE_SECRET_KEY` - your stripe secret key, found here: https://dashboard.stripe.com/account/apikeys
	* `STRIPE_PUBLIC_KEY` - your stripe public key, found here: https://dashboard.stripe.com/account/apikeys

Usage:

There are two ways to use it, either with Statamic's `Form` tag, or with Charge's `{{ charge:form }}`

Charge Form

* pass in the `amount` and `description` as parameters on the tag
* if you want to redirect the customer after the charge, use a `redirect` parameter
* `{{ success }}` and `{{ details }}` are available to you after a successful charge.

Statamic Form

* the following fields *must* be in your form:
    * `stripeEmail` - email of customer
* somewhere in your form you need to set the `amount` and `description` via `{{ charge:data }}`	
* this is the template where the `{{ charge:js }}` needs to be
* please note the `data-*` attributes on the form items. Those are required.

Example - Charge Form - Stripe Checkout:
```
{{ charge:form redirect="/thanks" amount="{amount}" description="{description}" }}
    {{ if success }}
        {{ details }}
            ID: {{ id }}
        {{ /details }}
    {{ /if}}

    <script
            src="https://checkout.stripe.com/checkout.js" class="stripe-button"
            data-key="{{ env: STRIPE_PUBLIC_KEY }}"
            data-amount="{{ amount }}"
            data-name="{{ company }}"
            data-description="{{ description }}"
            data-image="/img/documentation/checkout/marketplace.png"
            data-locale="auto"
            data-currency="usd">
    </script>
{{ /charge:form }}
```

Example - Statamic Form:
```
{{ form:create in="charge" attr="class:form|data-charge-form" redirect="/thanks" }}
	<div class="form-item">
		<label>Email</label>
		<input type="email" name="stripeEmail" value="{{ old:stripeEmail }}" />
	</div>


	<fieldset class="payment">
		<div class="form-item">
			<label for="cc_name">Name on Card</label>
			<input type="text" data-stripe="name" id="cc_name" required>
		</div>

		<div class="form-item">
			<label for="cc_number">Zip/Postal Code</label>
			<input type="text" data-stripe="number" id="address_zip" required>
		</div>

		<div class="form-item">
			<label for="cc_number">Card Number</label>
			<input type="text" data-stripe="number" id="cc_number" required>
		</div>

		<div class="row row-inner">
			<div class="col">
				<div class="form-item">
					<label for="exp_month">Expiry Month</label>
					<input type="text" data-stripe="exp_month" id="exp_month" maxlength="2" required placeholder="00">
				</div>
			</div>

			<div class="col">
				<div class="form-item">
					<label for="exp_year">Expiry Year</label>
					<input type="text" data-stripe="exp_year" id="exp_year" maxlength="2" required placeholder="00">
				</div>
			</div>

			<div class="col">
				<div class="form-item">
					<label for="cvc">CVC</label>
					<input type="text" data-stripe="cvc" id="cvc" maxlength="3" required placeholder="000">
				</div>
			</div>
		</div>

	</fieldset>

	{{ charge:data amount="{amount}" description="{description}" }}

	<button class="button primary" id="register" data-charge-button>Pay</button>

{{ /form:create }}
```