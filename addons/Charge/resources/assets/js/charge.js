/**
 * Created by erin on 2016-11-23.
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form[data-charge-form]');
    form.addEventListener('submit', function(event) {

        // Disable the submit button to prevent repeated clicks:
        if (form.querySelector('form[data-charge-button]')) {
            form.querySelector('form[data-charge-button]').disabled = true;
        }
        // Request a token from Stripe:
        Stripe.card.createToken(form, stripeResponseHandler);

        // Prevent the form from being submitted:
        event.preventDefault(); // Is better than return false ;-)
    });

    function stripeResponseHandler(status, response) {
        // Grab the form:
        var form = document.querySelector('form[data-charge-form]');

        if (response.error) { // Problem!

            // Show the errors on the form:
            if (form.querySelector('form[data-charge-errors]')) {
                form.querySelector('form[data-charge-errors]').textContent = response.error.message;
            }

            if (form.querySelector('form[data-charge-button]')) {
                form.querySelector('form[data-charge-button]').disabled = false; // Re-enable submission
            }

        } else { // Token was created!

            // Get the token ID:
            var token = response.id;

            // Insert the token ID into the form so it gets submitted to the server:
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'stripeToken';
            input.value = token;

            form.appendChild(input);

            // Submit the form:
            form.submit();
        }
    }
});
/*
 There a few minor differences what happens! As example `querySelector` only returns one element, if it is possible to have multiple of the forms or button you need to use `querySelectorAll` and loop over the `NodeList`.

 Example with a loop with a nice `each` helper method
 *//**
 * @param {Array|Object} array
 * @param {Function} callback
 * @param {Object} [scope]
 */
/*var each = function (array, callback, scope) {
        for (var key in array) {
            if (array.hasOwnProperty(key)) {
                callback.call(scope, array[key], key);
            }
        }
    };*/
/*Then it would look something like
 */
/*var forms = document.querySelectorAll('form[data-charge-form]');
 each(forms, function (form) {
 form.addEventListener('submit', stripeResponseHandler);
 });*/