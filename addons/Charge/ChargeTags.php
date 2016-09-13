<?php

namespace Statamic\Addons\Charge;

use Statamic\API\Crypt;
use Statamic\API\Helper;
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
        $params = [];

        $params['amount'] = $this->get('amount');
        $params['description'] = $this->get('description');
        $params['currency'] = $this->get('currency');

        $html = '<input type="hidden" name="_charge_params" value="'. Crypt::encrypt($params) .'" />';

        return $html;
    }

    /**
     * The {{ charge:example }} tag
     *
     * @return string|array
     */
    public function form()
    {
        $data = [];
        $params = [];

        $html = $this->formOpen('process');

        // grab the amount & description
        $params['amount'] = $this->get('amount');
        $params['description'] = $this->get('description');
        $params['currency'] = $this->get('currency');

        if ($this->success())
        {
            $data['success'] = true;
            $data['details'] = $this->flash->get('details');
        }

        if ($redirect = $this->get('redirect')) {
            $params['redirect'] = $redirect;
        }

        // need to encrypt the amount & description so they can't be modified
        $html .= '<input type="hidden" name="_charge_params" value="'. Crypt::encrypt($params) .'" />';

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
        if ($this->success()) {
            return $this->flash->get('details');
        }
    }

    public function errors()
    {
        if (! $this->hasErrors()) {
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
        $js = '';
        $show_on = $this->getConfig('show_on', array());

        // only add it if we're on the right template or it's not set at all
        if (!$show_on || in_array($this->context['template'], Helper::ensureArray($show_on), true))
        {
            $js = '<script src="https://js.stripe.com/v2/"></script>' . PHP_EOL;
            $js .= $this->js->tag("charge") . PHP_EOL;
            $js .= $this->js->inline("Stripe.setPublishableKey('" . env('STRIPE_PUBLIC_KEY') . "')") . PHP_EOL;
        }

        return $js;
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
