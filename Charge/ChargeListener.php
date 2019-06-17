<?php

namespace Statamic\Addons\Charge;

use Stripe\Stripe;
use Statamic\API\User;
use Statamic\Extend\Listener;
use Statamic\CP\Navigation\Nav;
use Illuminate\Support\MessageBag;
use Statamic\CP\Navigation\NavItem;
use Statamic\Events\Data\UserSaved;
use Illuminate\Support\ViewErrorBag;

class ChargeListener extends Listener
{
    use Billing;
    /**
     * The events to be listened for, and the methods to call.
     *
     * @var array
     */
    public $events = [
        'Form.submission.creating' => 'chargeForm',
        'content.saving' => 'chargeEntry',
        'user.registering' => 'register',
        UserSaved::class => 'updateBilling',
        'Charge.cancel' => 'cancel',
        'Charge.resubscribe' => 'resubscribe',
        'cp.nav.created' => 'nav',
        'cp.add_to_head' => 'addToHead',
    ];

    public function init()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
    }

    /**
     * @param \Statamic\Data\Entries\Entry $entry
     *
     */
    public function chargeEntry($entry)
    {
        // only do something if we're in the right collection
        // @todo hack in here due to Workshop not differentiating between editing and creating
        if (
            request()->has('process_payment') &&
            in_array($entry->collectionName(), $this->getConfig('charge_collections'))
        ) {
            try {
                // get paid
                $charge = $this->charge($this->getDetails($entry->data()));

                // get the results ready for display
                $this->flash->put('success', true);
                $this->flash->put('details', $charge);

                return $charge;
            } catch (\Exception $e) {
                \Log::error($e->getMessage());

                // @todo how return an error?
                // @todo this is what `back()->withError(..)` does. Remove when they update Workshop
                $value = new MessageBag((array)$e->getMessage());

                $this->session->flash(
                    'errors',
                    $this->session->get('errors', new ViewErrorBag)->put('charge', $value)
                );

                return null;
            }
        }

        return true;
    }

    /**
     * @param \Statamic\Forms\Submission $submission
     *
     * @return \Statamic\Forms\Submission|array
     */
    public function chargeForm($submission)
    {
        // only do something if we're on the right formset
        if (in_array($submission->formset()->name(), $this->getConfig('charge_formsets', []))) {
            try {
                // get paid
                $charge = $this->charge($this->getDetails($submission->data()));

                // add the charge id to the submission
                $submission->set('customer_id', $charge['customer']['id']);

                $this->flash->put('details', $charge);
            } catch (\Exception $e) {
                \Log::error($e->getMessage());

                return ['errors' => [$e->getMessage()]];
            }
        }

        return $submission;
    }

    /**
     * @param Statamic\Data\Users\User $user
     *
     * @return Statamic\Data\Users\User|array
     */
    public function register($user)
    {
        // only do something if there's an amount or a plan
        if (request()->has('amount') || request()->has('plan')) {
            try {
                $charge = $this->charge($this->getDetails(['email' => $user->email()]), $user);

                $this->flash->put('details', $charge);
            } catch (\Exception $e) {
                \Log::error($e->getMessage());

                return ['errors' => [$e->getMessage()]];
            }
        }

        return $user;
    }

    /**
     * @param \Statamic\Events\Data\UserSaved $event
     */
    public function updateBilling($event)
    {
        $this->updateUserBilling($event->data);
    }

    /**
     * Add Charge to the side nav
     * @param  Nav    $nav [description]
     * @return void
     */
    public function nav(Nav $nav)
    {
        if (User::getCurrent()->isSuper()) {
            $charge = (new NavItem)->name('Charge')->route('charge')->icon('credit-card');
            $nav->addTo('tools', $charge);
        }
    }

    /**
     * Need some JS in the CP to deal with the refund confirmations
     *
     * @return string
     */
    public function addToHead()
    {
        return $this->js->tag('charge-cp') . PHP_EOL;
    }

    public function cancel()
    {
        $this->cancel($this->getId());
    }

    public function resubscribe()
    {
        $this->resubscribe($this->getId());
    }

    /**
     * Grab the id from the URL
     *
     * @return string
     */
    private function getId()
    {
        return request()->segment(4);
    }
}
