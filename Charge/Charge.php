<?php

namespace Statamic\Addons\Charge;

use Carbon\Carbon;
use Stripe\Refund;
use Stripe\Customer;
use Statamic\API\URL;
use Statamic\API\User;
use Statamic\API\Crypt;
use Statamic\API\Config;
use Stripe\Subscription;
use Stripe\Charge as StripeCharge;

trait Charge
{
    static $param_key = "_charge_params";

    /**
     * Make the appropriate charge, either a one time or a subscription
     *
     * @param array $details   charging details
     * @param \Statamic\Data\Users\User $user
     *
     * @return array           details of the charge
     */
    public function charge($details, $user = null)
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
            $this->updateUser($user ?? User::getCurrent(), $result, true);
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
        return StripeCharge::create([
            'customer' => $details['customer'],
            'amount'   =>$details['amount'] ?: round($details['amount_dollar'] * 100),
            'currency' => array_get($details, 'currency', $this->getConfig('currency', 'usd')),
            'receipt_email' => $details['email'],
            'description' => array_get($details, 'description')
        ])->__toArray(true);
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
            'items' => [
                    [
                        'plan' => $details['plan'],
                        'quantity' => array_get($details, 'quantity', 1),
                    ],
                ],
            'coupon' => array_get($details, 'coupon')
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
     * @return array
     */
    public function getOrCreateCustomer($details)
    {
        /** @var \Stripe\Customer $customer */
        $customer = null;
        $token = null;

        $email = $details['email'];

        if (isset($details['stripeToken']))
        {
            $token = $details['stripeToken'];
        }

        if ($customer_id = $this->getCustomerId($email))
        {
            $customer = Customer::retrieve($customer_id);

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

    private function getCustomerId($email)
    {
        $yaml = $this->storage->getYAML($email);
        if ($user = User::email($email))
        {
            if (!($id = $user->get('customer_id')))
            {
                return array_get($yaml, 'customer_id');
            }

            return $id;
        }

        return array_get($yaml, 'customer_id');
    }

    /**
     * @return array|string
     */
    public function decryptParams()
    {
        return request()->has(self::$param_key) ? Crypt::decrypt(request(self::$param_key)) : [];
    }


    /**
     * Add the subscription data
     *
     * @param \Statamic\Data\Users\User $user
     * @param array                     $charge
     * @param boolean                   $save   save the user?
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
            $this->updateUserRoles($user, $charge['subscription']['plan']['id'], $user->get('plan'));
            $this->updateUserSubscription($user, $charge['subscription']);
        }

        if ($save)
        {
            $user->save();
        }
    }

    public function updateUserBilling($user)
    {
        $request = request();

        // if there's a stripe token then they're updating the payment info
        // but also check if the plan is different so that can be updated too
        $new_plan = $request->get('plan');

        try
        {
            // if there's a token we're updating the payment info
            if ($request->has('stripeToken'))
            {
                $customer = Customer::retrieve($user->get('customer_id')); // stored in your application
                $customer->source = $request->get('stripeToken'); // obtained with Checkout
                $customer->save();
            }

            $subscription = Subscription::retrieve($user->get('subscription_id'));
            if ($subscription->plan != $new_plan) {
                $old_plan = $subscription->plan->id;
                $subscription->plan = $new_plan;
                $subscription->save();

                $this->updateUserRoles($user, $new_plan, $old_plan);
                $this->updateUserSubscription($user, $subscription->__toArray(true));

                $user->save();
            }

            return ['success' => true];
        }
        catch(\Stripe\Error\Card $e) {
            \Log::error($e->getMessage());

            // Use the variable $error to save any errors
            // To be displayed to the customer later in the page
            $body = $e->getJsonBody();
            $error  = $body['error'];

            return [ 'error' => $error['message']];
        }
    }

    /**
     * @param \Statamic\Data\Users\User $user
     * @param $subscription array
     */
    public function updateUserSubscription($user, $subscription)
    {
        $user->set('plan', $subscription['plan']['id']);
        $user->set('subscription_id', $subscription['id']);
        $user->set('subscription_start', $subscription['current_period_start']);
        $user->set('subscription_end', $subscription['current_period_end']);
        $user->set('subscription_status', 'active');
    }

    /**
     * @param \Statamic\Data\Users\User $user
     * @param $plan string
     */
    public function updateUserRoles($user, $new_plan, $old_plan)
    {
        // if the plan is different
        if ($user->get('plan') != $new_plan)
        {
            $this->removeUserRoles($user, $old_plan);
            $this->addUserRoles($user, $new_plan);
        }
    }
    /**
     * @param $user \Statamic\Data\Users\User
     * @param $plan string
     */
    public function removeUserRoles($user, $plan)
    {
        // remove the role from the user
        // get the role associated w/ this plan
        if ($plan && $role = $this->getRole($plan))
        {
            // remove role from user
            $roles = array_filter($user->get('roles', []), function($item) use ($role) {
                return $item != $role;
            });

            $user->set('roles', $roles);
        }
    }

    /**
     * @param $user \Statamic\Data\Users\User
     * @param $plan string
     */
    public function addUserRoles($user, $plan)
    {
        if ($role = $this->getRole($plan))
        {
            // get the user's roles
            $roles = $user->get('roles', []);

            // add the role id to the roles
            $roles[] = $role;

            // set the user's roles
            $user->set('roles', array_unique($roles));
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

    /**
     * @param $customer_id
     *
     * @return array
     */
    public function getSourceDetails($customer_id)
    {
        $customer = Customer::retrieve([
            'id' => $customer_id,
            'expand' => ['default_source']]);

        return ($customer && $customer->default_source) ? $customer->default_source->__toArray(true) : [];
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
