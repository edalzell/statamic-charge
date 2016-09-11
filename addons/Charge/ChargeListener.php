<?php

namespace Statamic\Addons\Charge;

use Statamic\Extend\Listener;
use Statamic\CP\Navigation\Nav;
use Statamic\CP\Navigation\NavItem;


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

                // add the Stripe token form the request
                $data['stripeToken'] = request()->get('stripeToken');

                // get paid and add the charge details to the submission
                $submission->set('charge', $this->charge->processPayment($data));
            } catch (\Stripe\Error\Base $e)
            {
                return ['errors' => $e->getMessage()];
            }
        }

        return $submission;
    }

    /**
     * Add Stripe to the side nav
     * @param  Nav    $nav [description]
     * @return void
     */
    public function nav(Nav $nav)
    {
        $charge = (new NavItem)->name('Charge')->route('charge')->icon('credit-card');
        $nav->addTo('tools', $charge);
    }

}
