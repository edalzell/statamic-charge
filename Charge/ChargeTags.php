<?php

namespace Statamic\Addons\Charge;

use Stripe\Plan;
use Stripe\Stripe;
use Stripe\Customer;
use Statamic\API\URL;
use Statamic\API\User;
use Statamic\API\Crypt;
use Statamic\API\Request;
use Statamic\Extend\Tags;

class ChargeTags extends Tags
{
    use Billing;

    public function init()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
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
     * The {{ charge:update_billing_form }} tag
     *
     * @return string|array
     */
    public function updateBillingForm()
    {
        return $this->createForm(
            'update_billing',
            $this->getCustomerDetails($this->getParam('customer_id'))
        );
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

    private function createForm($action, $data = [])
    {
        $html = $this->formOpen($action);

        if ($this->success()) {
            $data['success'] = true;
            $data['details'] = $this->flash->get('details');
        }

        if ($this->hasErrors()) {
            $data['errors'] = $this->getErrorBag()->all();
        }

        if ($redirect = $this->getRedirectUrl()) {
            $html .= '<input type="hidden" name="redirect" value="' . $redirect . '" />';
        }

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
            $return = Request::input('redirect', $return);
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
        $js = '<script src="https://js.stripe.com/v2/"></script>' . PHP_EOL;
        $js .= $this->js->inline('var Charge = ' . json_encode(['plan' => $plan_id, 'freePlan' => $free_plan]) . ';') . PHP_EOL;
        $js .= $this->js->tag('charge') . PHP_EOL;
        $js .= $this->js->inline("Stripe.setPublishableKey('" . env('STRIPE_PUBLIC_KEY') . "')") . PHP_EOL;

        return $js;
    }

    /**
     * Get the plan details
     *
     * @return string
     */
    public function plan()
    {
        return $this->parse(Plan::retrieve($this->getParam('plan'))->__toArray());
    }

    /**
     * Get the Stripe plans
     *
     * @return string
     */
    public function plans()
    {
        $plans = Plan::all(
            [
                'expand' => ['data.product'],
            ]
        )->__toArray(true);

        return $this->parseLoop($plans['data']);
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
     * The {{ charge:cancel_subscription_url }} tag
     *
     * @return string
     */
    public function cancelSubscriptionUrl()
    {
        return $this->makeUrl('cancel');
    }

    /**
     * The {{ charge:renew_subscription_url }} tag
     *
     * @return string
     */
    public function renewSubscriptionUrl()
    {
        return $this->makeUrl('resubscribe');
    }

    private function makeUrl($action)
    {
        $url = URL::assemble($this->actionUrl($action, false), $this->getParam('subscription_id'));

        // if they want to redirect, add it as a queary param
        if ($redirect = $this->getParam('redirect')) {
            $url .= '?redirect=' . $redirect;
        }

        return $url;
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
