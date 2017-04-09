<?php

namespace Statamic\Addons\Charge;

use Stripe\Plan;
use Statamic\API\URL;
use Statamic\API\Crypt;
use Statamic\Extend\Tags;

class ChargeTags extends Tags
{
    /** @var  \Statamic\Addons\Charge\Charge */
    private $charge;

    public function init()
    {
        $this->charge = new Charge;
    }

    /**
     * The {{ charge:data }} tag
     *
     * @return string
     */
    public function data($params = null)
    {
        return '<input type="hidden" name="' . Charge::PARAM_KEY .'" value="'. Crypt::encrypt($params ?? $this->parameters) .'" />';
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
     * The {{ charge:update_payment_form }} tag
     *
     * @return string|array
     */
    public function updateCustomerForm()
    {
        return $this->createForm(
            'update_customer',
            $this->charge->getSourceDetails($this->getParam('customer_id'))
        );
    }

    private function createForm($action, $data = [])
    {
        if ($this->success())
        {
            $data['success'] = true;
            $data['details'] = $this->flash->get('details');
        }

        if ($this->hasErrors()) {
            $data['errors'] = $this->getErrorBag()->all();
        }

        return $this->formOpen($action) . $this->data() . $this->parse($data) . '</form>';
    }

    /**
     * Maps to {{ charge:success }}
     *
     * @return bool
     */
    public function success()
    {
        return $this->flash->exists('success');
    }

    /**
     * Maps to {{ charge:details }}
     *
     * @return array
     */
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
     */
    public function js()
    {
        $plan_id = $this->getParam('plan_id');
        $free_plan = $this->getParam('free_plan');
        $js = '<script src="https://js.stripe.com/v2/"></script>' . PHP_EOL;
        $js .= $this->js->inline("var Charge = ". json_encode(['plan' => $plan_id, 'freePlan' => $free_plan]) . ";") . PHP_EOL;
        $js .= $this->js->tag("charge") . PHP_EOL;
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
        return $this->parse($this->charge->getPlan($this->getParam('plan')));
    }

    /**
     * Get the Stripe plans
     *
     * @return string
     */
    public function plans()
    {
        $plans = Plan::all()->__toArray(true);

        return $this->parseLoop($plans['data']);
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
        if ($redirect = $this->getParam('redirect'))
        {
            $url .= '&redirect=' . $redirect;
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
