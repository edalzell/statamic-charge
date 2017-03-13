<?php

namespace Statamic\Addons\Charge;

use Statamic\API\Crypt;
use Statamic\API\URL;
use Statamic\Extend\Tags;

class ChargeTags extends Tags
{
    /**
     * The {{ charge:data }} tag
     *
     * @return string
     */
    public function data()
    {
        return '<input type="hidden" name="' . Charge::PARAM_KEY .'" value="'. Crypt::encrypt($this->parameters) .'" />';
    }

    /**
     * The {{ charge:form }} tag
     *
     * @return string|array
     */
    public function form()
    {
        $data = [];
        $params = $this->parameters;

        $html = $this->formOpen('process');

        if ($this->success())
        {
            $data['success'] = true;
            $data['details'] = $this->flash->get('details');
        }

        if ($redirect = $this->get('redirect')) {
            $params['redirect'] = $redirect;
        }

        // need to encrypt the amount & description so they can't be modified
        $html .= '<input type="hidden" name="' . Charge::PARAM_KEY .'" value="'. Crypt::encrypt($params) .'" />';

        $html .= $this->parse($data);

        $html .= '</form>';

        return $html;
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
        $js = '<script src="https://js.stripe.com/v2/"></script>' . PHP_EOL;
        $js .= $this->js->tag("charge") . PHP_EOL;
        $js .= $this->js->inline("Stripe.setPublishableKey('" . env('STRIPE_PUBLIC_KEY') . "')") . PHP_EOL;

        return $js;
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

    public function cancelSubscriptionLink()
    {
        return URL::assemble($this->actionUrl('cancel', false), $this->getParam('customer'));
    }

    /**
     * Does this form have errors?
     *
     * @return bool
     */
    private function hasErrors()
    {
        return (session()->has('errors'))
            ? session()->get('errors')->hasBag('charge')
            : false;
    }
}
