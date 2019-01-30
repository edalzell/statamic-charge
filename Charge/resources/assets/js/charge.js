document.addEventListener('DOMContentLoaded', function () {

    let form = document.querySelector('form[data-charge-form]');

    if (form) {
        let plan = form.querySelector('#' + Charge.plan);
        let button = form.querySelector('[data-charge-button]');

        button.addEventListener('click', function (event) {
            let empty = stripeFieldsEmpty();
            if (isFreePlan(plan) && stripeFieldsEmpty()) {
                // grab all the ones that are required
                let fields = [].slice.call(form.querySelectorAll('[required]'));
                fields.forEach(function (field) {
                    field.removeAttribute('required');
                });
            }
        });

        form.addEventListener('submit', function (event) {
            if (!isFreePlan(plan) && !stripeFieldsEmpty()) {
                // Disable the submit button to prevent repeated clicks:
                button.disabled = true;

                // Request a token from Stripe:
                Stripe.card.createToken(form, stripeResponseHandler);

                // Prevent the form from being submitted:
                event.preventDefault(); // Is better than return false ;-)
            }
        });
    }

    function stripeResponseHandler(status, response) {
        if (response.error) { // Problem!

            // Show the errors on the form:
            let errors = form.querySelector('[data-charge-errors]');

            if (errors) {
                errors.textContent = response.error.message;
            }
            form.querySelector('[data-charge-button]').disabled = false; // Re-enable submission

        } else { // Token was created!

            // add token to form
            addTokenToForm(response, form);

            // Submit the form:
            form.submit();
        }
    }

    function stripeFieldsEmpty() {
        let fields = [].slice.call(form.querySelectorAll('[data-stripe="number"], [data-stripe="cvc"], [data-stripe="exp_month"], [data-stripe="exp_year"], [data-stripe="exp"]'));

        return fields.every(function (field) {
            return field.value === '';
        });
    }

    function isFreePlan(plan) {
        return plan && Charge.freePlan && (plan.value == Charge.freePlan);
    }
});

function addTokenToForm(token, form) {
    // Insert the token ID into the form so it gets submitted to the server:
    let input = document.createElement('input');

    input.type = 'hidden';
    input.name = 'stripeToken';
    input.value = token.id;

    form.appendChild(input);
}