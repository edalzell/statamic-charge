<?php

namespace Statamic\Addons\Charge;

use Statamic\API\Str;
use Statamic\API\User;
use Statamic\Extend\Controller;
use Symfony\Component\Intl\Intl;

class ChargeController extends Controller
{
    /** @var  \Statamic\Addons\Charge\Charge */
    private $charge;

    public function init()
    {
        $this->charge = new Charge;
    }

    /**
     * Maps to your route definition in routes.yaml
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->redirectToRoute('lists.customers');
    }

    public function customers()
    {
        return $this->view('lists.customers', ['customers' => $this->charge->getCustomers()]);
    }

    public function charges()
    {
        // get currency symbol
        $currency = Str::upper($this->getConfig('currency', 'usd'));
        $currency_symbol  = Intl::getCurrencyBundle()->getCurrencySymbol($currency);
        $charges = $this->charge->getCharges();

        return $this->view('lists.charges', compact('currency_symbol', 'charges'));
    }

    public function subscriptions()
    {
        return $this->view('lists.subscriptions', ['subscriptions' => $this->charge->getSubscriptions()]);
    }

    public function postProcess()
    {
        try
        {
            //$data = request()->except(['_token', '_params']);

            // process the payment
            $charge = $this->charge->charge($this->charge->getDetails());

            // if there's a user logged in, store the details
            if ($user = User::getCurrent())
            {
                $this->charge->updateUser($user, $charge);
            }

            // get the results ready for display
            $this->flash->put('success', true);
            $this->flash->put('details', $charge);

            $redirect = request()->get('redirect');

            return ($redirect) ? redirect($redirect) : back();
        }
        catch (\Stripe\Error\Base $e)
        {
            return back()->withInput()->withErrors($e->getMessage(), 'charge');
        }
    }

    /**
     * Deal with the Stripe events
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function postWebhook()
    {
        $event = request()->json()->all();
        if ($event['type'] == 'invoice.payment_succeeded')
        {
            // find the right user (w/ the matching Stripe Customer ID)
            /** @var \Statamic\Data\Users\User $user */
            if ($user = $this->whereUser($event['data']['object']['customer']))
            {
                // store it
                $user->set('subscription_end', $event['data']['object']['period_end']);
                $user->save();
            }
        }

        return response();
    }

    private function whereUser($customer_id)
    {
        return User::all()->first(function ($id, $user) use ($customer_id) {
            return $user->get('customer_id') === $customer_id;
        });
    }

    public function refund($id = null)
    {
        $this->charge->refund($id);

        // redirect back to main page
        return response()->redirectToRoute('lists.charges');
    }

    public function cancel($id = null)
    {
        $this->charge->cancel($id);

        // redirect back to main page
        return response()->redirectToRoute('lists.subscriptions');
    }

    public function resubscribe($id = null)
    {
        $this->charge->resubscribe($id);

        // redirect back to main page
        return response()->redirectToRoute('lists.subscriptions');
    }

}
