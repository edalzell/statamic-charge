<?php

namespace Statamic\Addons\Charge;

use Stripe\Stripe;
use Stripe\Customer;
use Statamic\API\User;
use Statamic\API\Crypt;
use Stripe\SetupIntent;
use Statamic\Extend\Tags;
use Stripe\PaymentIntent;
use Statamic\Addons\Charge\Traits\Billing;
use Statamic\Addons\Charge\Traits\HasPrices;
use Statamic\Addons\Charge\Traits\HasProducts;
use Statamic\Addons\Charge\Traits\HasSubscriptions;

class ChargeTags extends Tags
{
    use Billing;
    use HasPrices;
    use HasProducts;
    use HasSubscriptions;

    public function init()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
    }

    public function session()
    {
        $session = $this->createSession($this->parameters);

        return $session->id;
    }

    public function setupIntent()
    {
        $si = SetupIntent::create([
            'usage' => 'off_session', // The default usage is off_session
        ]);

        return $si->client_secret;
    }

    public function paymentIntent()
    {
        $pi = PaymentIntent::create([
            'amount' => $this->getParam('amount'),
            'description' => $this->getParam('description'),
            'currency' => $this->getParam('currency', $this->getConfig('currency', 'usd')),
            'payment_method_types' => ['card'],
            'setup_future_usage' => 'off_session',
        ]);

        return $pi->client_secret;
    }

    /**
     * The {{ charge:data }} tag
     *
     * @return string
     */
    public function data($params = null)
    {
        if (!$params) {
            $params = $this->parameters;
        }

        return '<input type="hidden" name="' . self::$param_key . '" value="' . Crypt::encrypt($params) . '" />';
    }

    /**
     * The {{ charge:payment_form }} tag
     *
     * @deprecated Use '{{ charge:payment_form }}' instead
     *
     * @return string|array
     */
    public function form()
    {
        return $this->paymentForm();
    }

    /**
     * The {{ charge:payment_form }} tag
     *
     * @return string|array
     */
    public function paymentForm()
    {
        return $this->createForm('process_payment');
    }

    /**
     * The {{ charge:update_customer_form }} tag
     *
     * @return string|array
     */
    public function updateCustomerForm()
    {
        return $this->updateBillingForm();
    }

    /**
     * The {{ charge:update_user_form }} tag
     *
     * @return string|array
     */
    public function updateUserForm()
    {
        $user = User::getCurrent();
        $data = $user->data();
        $data['username'] = $user->username();

        if ($customer_id = $user->get('customer_id')) {
            $customer = Customer::retrieve(['id' => $customer_id, 'expand' => ['default_source']]);

            /** @var \Stripe\Card $card */
            $card = $customer->default_source;

            $data['exp_month'] = $card->exp_month;
            $data['exp_year'] = $card->exp_year;
            $data['last4'] = $card->last4;
            $data['address_zip'] = $card->address_zip;
        }

        return $this->createForm('update_user', $data);
    }

    private function createForm(string $action, array $data = [], string $method = 'POST'): string
    {
        $html = $this->formOpen($action) . method_field($method);

        if ($this->success()) {
            $data['success'] = true;
            $data['details'] = $this->flash->get('details');
        }

        if ($this->requiresAction()) {
            $data['requires_action'] = true;
            $data['client_secret'] = $this->flash->get('client_secret');
        }

        if ($this->hasErrors()) {
            $data['errors'] = $this->getErrorBag()->all();
        }

        if ($redirect = $this->getRedirectUrl()) {
            $html .= '<input type="hidden" name="redirect" value="' . $redirect . '" />';
        }

        $params = [];
        if ($redirect = $this->get('redirect')) {
            $params['redirect'] = $redirect;
        }

        if ($error_redirect = $this->get('error_redirect')) {
            $params['error_redirect'] = $error_redirect;
        }

        if ($action_needed_redirect = $this->get('action_needed_redirect')) {
            $params['action_needed_redirect'] = $action_needed_redirect;
        }

        $html .= '<input type="hidden" name="_params" value="' . Crypt::encrypt($params) . '" />';

        return $html . $this->data() . $this->parse($data) . '</form>';
    }

    /**
     * Get the redirect URL
     *
     * @return string
     **/
    private function getRedirectUrl()
    {
        $return = $this->get('redirect');

        if ($this->getBool('allow_request_redirect')) {
            $return = request('redirect', $return);
        }

        return $return;
    }

    /**
     * Maps to {{ charge:success }}
     *
     * @return bool
     **/
    public function success()
    {
        return $this->flash->exists('success');
    }

    public function requiresAction()
    {
        return $this->flash->exists('requires_action');
    }

    /**
     * Maps to {{ charge:details }}
     *
     * @return array
     **/
    public function details()
    {
        return $this->success() ? $this->flash->get('details') : [];
    }

    public function errors()
    {
        if (!$this->hasErrors()) {
            return false;
        }

        $errors = [];

        foreach (session('errors')->getBag('charge')->all() as $error) {
            $errors[]['value'] = $error;
        }

        return ($this->content === '')    // If this is a single tag...
            ? !empty($errors)             // just output a boolean.
            : $this->parseLoop($errors);  // Otherwise, parse the content loop.
    }

    /**
     * The {{ charge:js }} tag
     *
     * @return string|array
     **/
    public function js()
    {
        $plan_id = $this->getParam('plan_id');
        $free_plan = $this->getParam('free_plan');
        //$js = '<script src="https://js.stripe.com/v3/"></script>' . PHP_EOL;
        // $js .= $this->js->inline("var stripe = Stripe('" . env('STRIPE_PUBLIC_KEY') . "');") . PHP_EOL;
        // $js .= $this->js->inline("var elements = stripe.elements();") . PHP_EOL;
        $js = $this->js->inline('var Charge = ' . json_encode(['plan' => $plan_id, 'freePlan' => $free_plan]) . ';') . PHP_EOL;
        $js .= $this->js->tag('charge-new') . PHP_EOL;
        $js .= $this->js->inline("Stripe.setPublishableKey('" . env('STRIPE_PUBLIC_KEY') . "')") . PHP_EOL;

        return $js;
    }

    public function status()
    {
        $status = $this->get('status', array_get($this->context, 'status', ''));

        return $this->getConfig($status, $status);
    }

    /**
     * The {{ charge:process_payment }} tag
     *
     * @return string
     */
    public function processPayment()
    {
        return '<input type="hidden" name="process_payment" value="true" />';
    }

    /**
     * Does this form have errors?
     *
     * @return bool
     */
    private function hasErrors()
    {
        return (session()->has('errors'))
            ? session('errors')->hasBag('charge')
            : false;
    }

    /**
     * Get the errorBag from session
     *
     * @return object
     */
    private function getErrorBag()
    {
        if ($this->hasErrors()) {
            return session('errors')->getBag('charge');
        }
    }
}
