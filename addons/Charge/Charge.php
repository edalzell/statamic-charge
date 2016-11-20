<?php

namespace Statamic\Addons\Charge;

use Stripe\Stripe;
use Stripe\Refund;
use Carbon\Carbon;
use Stripe\Customer;
use Statamic\API\URL;
use Statamic\API\Crypt;
use Statamic\API\Config;
use Stripe\Subscription;
use Statamic\Extend\Extensible;
use Stripe\Charge as StripeCharge;

class Charge
{
    use Extensible;

    const PARAM_KEY = "_charge_params";

    public function __construct()
	{
		Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
	}

    /**
     * Make the appropriate charge, either a one time or a subscription
     *
     * @param array $details   charging details
     *
     * @return array           details of the charge
     */
    public function charge($details)
    {
        $result = [];

        // always make a customer
        /** @var \Stripe\Customer $customer */
        $result['customer'] = $customer = $this->getOrCreateCustomer($details);

        $details['customer'] = $customer['id'];

        // is this a subscription?
        if (isset($details['plan']))
        {
            $result['subscription'] = $this->subscribe($details);
        }
        else
        {
            $result['charge'] = $this->oneTimeCharge($details);
        }

        return $result;
    }

    public function oneTimeCharge($details)
    {
        /** @var \Stripe\Charge $charge */
        return StripeCharge::create(array(
            'customer' => $details['customer'],
            'amount'   =>$details['amount'],
            'currency' => array_get($details, 'currency', $this->getConfig('currency', 'usd')),
            'receipt_email' => $details['stripeEmail'],
            'description' => $details['description']
        ))->__toArray(true);
    }

    /**
     * @param array $details
     *
     * @return array
     */
    public function subscribe($details)
    {
        // charge them
        return Subscription::create([
            'customer' => $details['customer'],
            'plan' => $details['plan']
        ])->__toArray(true);

    }

    public function resubscribe($id)
    {
        $subscription = $this->getSubscription($id);

        // this looks silly but it's how you get Stripe to re-activate a subscription
        $subscription->plan = $subscription->plan;

        $subscription->save();
    }

    /**
     * @param string $id Stripe charge id
     *
     * @return \Stripe\Refund
     */
    public function refund($id)
    {
        return Refund::create(["charge" => $id]);
    }


    /**
     * @param string $id
     */
    public function cancel($id)
    {
        $subscription = $this->getSubscription($id);

        // don't renew at end of period
        $subscription->cancel(['at_period_end' => true]);
    }

    /**
     * Get the customer if it exists, otherwise create a new one
     *
     * @param $details
     * @return null|Customer
     */
    private function getOrCreateCustomer($details)
    {
        /** @var \Stripe\Customer $customer */
        $customer = null;

        // first see if the customer exists already
        if ($yaml = $this->storage->getYAML($details['stripeEmail']))
        {
            $customer = Customer::retrieve($yaml['customer_id']);

            // update the payment details
            $customer->source = $details['stripeToken'];
            $customer->save();
        }
        else
        {
            $customer = Customer::create([
                "email" => $details['stripeEmail'],
                "source" => $details['stripeToken']
            ]);

            // store it for later
            $this->storage->putYAML($details['stripeEmail'], ['customer_id' => $customer->id]);
        }

        return $customer->__toArray(true);
     }

    public function decryptParams()
    {
        return request()->has(Charge::PARAM_KEY) ? Crypt::decrypt(request(Charge::PARAM_KEY)) : [];
    }


    /**
     * Add the customer id
     *
     * @param \Statamic\Data\Users\User $user
     * @param string $customer_id
     *
     */
    public function updateUser($user, $customer_id)
    {
        // add the customer_id to the user
        $user->set('customer_id', $customer_id);

        // add the creation date
        $user->set('created_on', time());
    }

    /**
     * @param $id string customer_id
     *
     * @return \Stripe\Subscription
     */
    private function getSubscription($id)
    {
        return $id ? Subscription::retrieve($id)->__toArray(true) : null;
    }

    /**
     * Get the list of customers
     *
     * @return array
     */
    public function getCustomers()
    {
        $customers = Customer::all(['limit'=>100])->__toArray(true);

        return $customers['data'];
    }

    public function getCharges()
    {
        $charges = StripeCharge::all(['limit'=>100])->__toArray(true);

        // only want the ones that have NOT been refunded
        return collect($charges['data'])->filter(function ($charge) {
            return !$charge['refunded'];
        });
    }

    /**
     * Return all the subscription data but merge in the customer details and plan details
     *
     * @return array
     */
    public function getSubscriptions()
    {
        $subscriptions = Subscription::all(['limit' => 100, 'expand' => ['data.customer']])->__toArray(true);

        return collect($subscriptions['data'])->map(function ($subscription) {
            return [
                'id' => $subscription['id'],
                'email' => $subscription['customer']['email'],
                'expiry_date' => $subscription['current_period_end'],
                'plan' => $subscription['plan']['name'],
                'amount' => $subscription['plan']['amount'],
                'auto_renew' => !$subscription['cancel_at_period_end'],
                'has_subscription' => true
            ];
        })->toArray();
    }

    /**
     * Merge the encrypted params (amount, description) with the data & request
     *
     * @param $data array
     * @return array
     */
    public function getDetails($data)
    {
        return array_merge(
            $this->decryptParams(),
            $data,
            request()->only('stripeEmail', 'stripeToken', 'plan'));
    }


    /**
     * Return the proper action link, based on if the subscription is set to auto-renew
     *
     * @param array $customer
     *
     * @return string
     */
    public static function getActionLink($subscription)
    {
        $action = $subscription['auto_renew'] ? 'cancel' : 'resubscribe';

        return '<a href="' . URL::assemble($action, $subscription['id']) . '">' . ucfirst($action) . '</a>';
    }

    /**
     * This converts from a UTC timestamp to a DateTime in the local PHP timezone
     *
     * @param $timestamp int timestamp
     * @return \DateTime
     */
    public static function getLocalDateTimeFromUTC($timestamp)
    {
        /** @var \Carbon\Carbon $dt */
        $dt = new Carbon( '@' . $timestamp, 'Etc/UTC');

        /*
         * Convert to the server timezone
         */
        $dt->tz(Config::get('system.timezone'));

        return $dt;
    }

}
