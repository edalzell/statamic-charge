$(function() {
	var $form = $('form[data-charge-form]');
	$form.submit(function(event) {

		// Disable the submit button to prevent repeated clicks:
		$form.find('data-charge-button').prop('disabled', true);

		// Request a token from Stripe:
		Stripe.card.createToken($form, stripeResponseHandler);

		// Prevent the form from being submitted:
		return false;
	});
});

function stripeResponseHandler(status, response) {
  // Grab the form:
  var $form = $('form[data-charge-form]');

  if (response.error) { // Problem!

    // Show the errors on the form:
    $form.find('data-charge-errors').text(response.error.message);
    $form.find('data-charge-button').prop('disabled', false); // Re-enable submission

  } else { // Token was created!

    // Get the token ID:
    var token = response.id;

    // Insert the token ID into the form so it gets submitted to the server:
    $form.append($('<input type="hidden" name="stripeToken">').val(token));

    // Submit the form:
    $form.get(0).submit();
  }
};