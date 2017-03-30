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
            'amount'   =>$details['amount'] ?: round($details['amount_dollar'] * 100),
            'currency' => array_get($details, 'currency', $this->getConfig('currency', 'usd')),
            'receipt_email' => $details['email'],
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
     * @param string $subscription_id Stripe Subscription id
     */
    public function cancel($subscription_id)
    {
        // don't renew at end of period
        $this->getSubscription($subscription_id)->cancel(['at_period_end' => true]);
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

        $email = $details['email'];
        $token = $details['stripeToken'];

        // first see if the customer exists already
        if ($yaml = $this->storage->getYAML($email))
        {
            $customer = Customer::retrieve($yaml['customer_id']);

            // update the payment details
            $customer->source = $token;
            $customer->save();
        }
        else
        {
            $customer = Customer::create([
                "email" => $email,
                "source" => $token,
            ]);

            $this->storage->putYAML($email, ['customer_id' => $customer->id]);
        }

        return $customer->__toArray(true);
     }

    /**
     * @return array|string
     */
    public function decryptParams()
    {
        return request()->has(Charge::PARAM_KEY) ? Crypt::decrypt(request(Charge::PARAM_KEY)) : [];
    }


    /**
     * Add the subscription data
     *
     * @param \Statamic\Data\Users\User $user
     * @param array $charge
     *
     */
    public function updateUser($user, $charge, $save = false)
    {
        // add the customer_id to the user
        $user->set('customer_id', $charge['customer']['id']);

        // add the creation date
        $user->set('created_on', time());

        if (isset($charge['subscription']))
        {
            $user->set('plan', $charge['subscription']['plan']['id']);
            $user->set('subscription_id', $charge['subscription']['id']);
            $user->set('subscription_start', $charge['subscription']['current_period_start']);
            $user->set('subscription_end', $charge['subscription']['current_period_end']);
            $user->set('subscription_status', 'active');

            if ($role = $this->getRole($user->get('plan')))
            {
                // get the user's roles
                $roles = $user->get('roles', []);

                // add the role id to the roles
                $roles[] = $role;

                // set the user's roles
                $user->set('roles', $roles);
            }
        }

        if ($save)
        {
            $user->save();
        }
    }

    /**
     * @param $subscription_id string Stripe subscription ID
     *
     * @return \Stripe\Subscription|null
     */
    private function getSubscription($subscription_id)
    {
        return $subscription_id ? Subscription::retrieve($subscription_id) : null;
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
        $subscriptions = Subscription::all([
            'limit' => 100,
            'expand' => ['data.customer']
        ])->__toArray(true);

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
    public function getDetails($data = [])
    {
        // gotta merge the email stuff so there's just one
        $data = array_merge(
            $data,
            request()->only(['stripeEmail', 'stripeToken', 'plan', 'amount', 'amount_dollar', 'email']),
            $this->decryptParams());

        // if `stripeEmail` is there, use that, otherwise, use `email`
        $data['email'] = $data['stripeEmail'] ?: $data['email'];

        return $data;
    }

    public function getRole($plan)
    {
        $plan_role = collect($this->getConfig('plans_and_roles', []))->first(function($ignored, $data) use ($plan) {
            return $plan == array_get($data, 'plan');
        });

        return $plan_role ? $plan_role['role'][0] : null;
    }

    public function getSourceDetails($customer_id)
    {
        return Customer::retrieve([
            'id' => $customer_id,
            'expand' => ['default_source']])->default_source->__toArray(true);
    }


    /**
     * Return the proper action link, based on if the subscription is set to auto-renew
     *
     * @param array $subscription
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
        $dt = new Carbon('@' . $timestamp, 'Etc/UTC');

        /*
         * Convert to the server timezone
         */
        return $dt->tz(Config::get('system.timezone'));
    }
}
