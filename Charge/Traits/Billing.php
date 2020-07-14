<?php

namespace Statamic\Addons\Charge\Traits;

use Stripe\Plan;
use Stripe\Token;
use Stripe\Refund;
use Stripe\Customer;
use Statamic\API\Arr;
use Statamic\API\URL;
use Statamic\API\User;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Subscription;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session;
use Stripe\Charge as StripeCharge;
use Stripe\Exception\ApiErrorException;

trait Billing
{
    private function createSession(array $params): Session
    {
        $sessionParams = [
            'payment_method_types' => ['card'],
            'success_url' => URL::makeAbsolute(Arr::get($params, 'success_url')),
            'cancel_url' => URL::makeAbsolute(Arr::get($params, 'cancel_url')),
        ];

        switch (Arr::get($params, 'type')) {
            case 'one-time':
                $sessionParams['line_items'] = [
                    [
                        'name' => Arr::get($params, 'name'),
                        'description' => Arr::get($params, 'description'),
                        'amount' => Arr::get($params, 'amount'),
                        'currency' => Arr::get($params, 'currency', $this->getConfig('currency', 'usd')),
                        'quantity' => Arr::get($params, 'quantity', 1),
                    ],
                ];
                break;
            case 'subscription':
                $sessionParams['subscription_data'] = [
                    'items' => [
                        [
                            'plan' => Arr::get($params, 'plan'),
                        ],
                    ],
                ];
                break;
        }

        return Session::create($sessionParams);
    }

    private function createPaymentIntent(array $params): PaymentIntent
    {
        $data = Arr::only($params, [
            'amount',
            'description',
            'customer',
            'currency',
        ]);

        $data['payment_method_types'] = ['card'];
        $data['setup_future_usage'] = 'off_session';

        $data['currency'] = $data['currency'] ?? $this->getConfig('currency', 'usd');

        if ($metadata = Arr::get($params, 'metadata')) {
            $data['metadata'] = json_decode(urldecode($metadata), true);
        }

        return PaymentIntent::create($data);
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
        return Refund::create(['charge' => $id]);
    }

    /**
     * @param string $subscription_id Stripe Subscription id
     */
    public function cancel($subscription_id)
    {
        // don't renew at end of period
        $this->getSubscription($subscription_id)->cancel(['cancel_at_period_end' => true]);
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
        // in the weird case there's no user, don't do nuthin'
        if (!$user) {
            return;
        }

        // add the customer_id to the user
        $user->set('customer_id', $charge['customer']['id']);

        // add the creation date
        $user->set('created_on', time());

        if (isset($charge['subscription'])) {
            $this->updateUserRoles($user, $charge['subscription']['plan']['id'], $user->get('plan'));
            $this->updateUserSubscription($user, $charge['subscription']);
        }

        if ($save) {
            $user->save();
        }
    }

    public function updateUserBilling($user)
    {
        // don't do anything if there's no user
        if (!$user) {
            return;
        }

        $request = request();

        try {
            // if there's a token we're updating the payment info
            if ($request->has('stripeToken')) {
                $token = $request->get('stripeToken');

                if (Token::retrieve($token)->used) {
                    return;
                }

                $customer = Customer::retrieve($user->get('customer_id')); // stored in your application
                $customer->source = $token; // obtained with Checkout
                $customer->save();
            }

            // if there's no existing plan, do nothing
            if (!$user->has('subscription_id')) {
                return ['success' => true];
            }

            $subscription = Subscription::retrieve($user->get('subscription_id'));

            if (!$subscription) {
                return ['success' => true];
            }

            // if the quantity changed, update
            $qty = $request->get('quantity', 1);
            $new_plan = $request->get('plan');

            if (($subscription->quantity != $qty) || ($subscription->plan->id != $new_plan)) {
                if ($subscription->quantity != $qty) {
                    $subscription->quantity = $qty;
                }

                // if the plan changed, update
                if ($subscription->plan->id != $new_plan) {
                    $old_plan = $subscription->plan->id;
                    $subscription->plan = $new_plan;
                    $this->updateUserRoles($user, $new_plan, $old_plan);
                }

                $subscription->save();
                $this->updateUserSubscription(
                    $user,
                    $subscription->toArray()
                );
            }

            return ['success' => true];
        } catch (\Stripe\Error\Card $e) {
            \Log::error($e->getMessage());

            // Use the variable $error to save any errors
            // To be displayed to the customer later in the page
            $body = $e->getJsonBody();
            $error = $body['error'];

            return ['error' => $error['message']];
        }
    }

    /**
     * @param \Statamic\Data\Users\User $user
     * @param $plan string
     */
    public function updateUserRoles($user, $new_plan, $old_plan)
    {
        // if the plan is different
        if ($user->get('plan') != $new_plan) {
            $this->removeUserRoles($user, $old_plan);
            $this->addUserRoles($user, $new_plan);
            $user->set('plan', $new_plan);
            $user->save();
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
        if ($plan && $role = $this->getRole($plan)) {
            // remove role from user
            $roles = array_filter($user->get('roles', []), function ($item) use ($role) {
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
        if ($role = $this->getRole($plan)) {
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
        $charges = StripeCharge::all(['limit' => 100])->toArray();

        // only want the ones that have NOT been refunded
        return collect($charges['data'])->filter(function ($charge) {
            return !$charge['refunded'];
        });
    }

    public function getRole(string $plan)
    {
        $plan_role = $this->getPlansConfig($plan);

        return $plan_role ? $plan_role['role'][0] : null;
    }

    /**
     * @param $customer_id
     *
     * @return array
     */
    public function getCustomerDetails($customer_id)
    {
        $details = Customer::retrieve(
            [
                'id' => $customer_id,
                'expand' => ['default_source'],
            ]
        )->toArray();

        $details['sources'] = $details['sources']['data'];
        $details['subscriptions'] = $details['subscriptions']['data'];

        return $details;
    }

    /**
     * The {{ charge:customer_portal }} tag.
     *
     * @return array
     */
    public function customerPortal()
    {
        if (! $customerId = $this->getParam('id')) {
            return false;
        }

        try {
            $session = BillingPortalSession::create([
                'customer' => $customerId,
                'return_url' => URL::makeAbsolute(URL::getCurrentWithQueryString()),
            ]);

            return $this->parse($session->toArray());
        } catch (ApiErrorException $e) {
            \Log::error($e->getError());
        }

        return false;
    }
}
