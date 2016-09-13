<?php

namespace Statamic\Addons\Charge;

use Statamic\Extend\Listener;
use Statamic\CP\Navigation\Nav;
use Statamic\CP\Navigation\NavItem;
use Monolog;


class ChargeListener extends Listener
{
    /**
     * The events to be listened for, and the methods to call.
     *
     * @var array
     */
    public $events = [
        'Form.submission.creating' => 'charge',
        'cp.nav.created'  => 'nav',
        'cp.add_to_head' => 'addToHead'
    ];

    /** @var  \Statamic\Addons\Charge\Charge */
    private $charge;

    public function init()
    {
        $this->charge = new Charge;
    }

    /**
     * @param \Statamic\Forms\Submission $submission
     *
     * @return \Statamic\Forms\Submission|array
     */
    public function charge($submission)
    {
        if ($submission->formset()->name() === $this->getConfig('formset'))
        {
            try
            {
                // merge the encrypted params (amount, description) with the form data
                $data = array_merge($this->charge->decryptParams(), $submission->data());

                // add the Stripe token from the request
                $data['stripeToken'] = request()->get('stripeToken');

                // get paid
                $charge = $this->charge->processPayment($data);

                // add the charge id to the submission
                $submission->set('charge_id', $charge['id']);
            } catch (\Stripe\Error\Base $e)
            {
                \Log::error($e->getMessage());
                return array('errors' => array($e->getMessage()));
            }
        }

        return $submission;
    }

    /**
     * Add Charge to the side nav
     * @param  Nav    $nav [description]
     * @return void
     */
    public function nav(Nav $nav)
    {
        $charge = (new NavItem)->name('Charge')->route('charge')->icon('credit-card');
        $nav->addTo('tools', $charge);
    }

    /**
     * Need some JS in the CP to deal with the refund confirmations
     *
     * @return string
     */
    public function addToHead()
    {
        return $this->js->tag("charge-cp") . PHP_EOL;
    }

}
