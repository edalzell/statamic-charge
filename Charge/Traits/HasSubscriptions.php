<?php

namespace Statamic\Addons\Charge\Traits;

use Stripe\Plan;
use Carbon\Carbon;
use Stripe\Charge;
use Statamic\API\Arr;
use Statamic\API\URL;
use Statamic\API\User;
use Stripe\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Validation\ValidatesRequests;

trait HasSubscriptions
{
    use ValidatesRequests, HasCustomers, HasParameters;

    public function postSubscription(Request $request)
    {
        $this->validate($request, [
            'plan' => 'required',
            'payment_method' => 'required',
        ]);

//        $planConfig = $this->getPlansConfig($request->input('plan'));

        $details = $this->getDetails($request);

        $customer = $this->getOrCreateCustomer($details['payment_method']);

        /*
                if there's a fixed billing date set the billing-cycle-anchor
                if prorate, set prorate to true
        */

        $planId = $details['plan'];
        $plan = Plan::retrieve(['id' => $planId, 'expand' => ['product']]);
        $trialDays = Arr::get($details, 'trial_period_days', 0);

        if ($billingDay = Arr::get($details, 'billing_day')) {
            $trialDays = Carbon::now()->diffInDays(Carbon::now()->addMonths(2)->day($billingDay));
            // we need to bill them the same amount as the plan, immediately
            Charge::create([
                    'customer' => $customer->id,
                    'amount' => $plan->amount * Arr::get($details, 'quantity', 1),
                    'currency' => $plan->currency,
                    'description' => $plan->product->statement_descriptor,
                ]);
        }
        $subscription = [
                'customer' => $customer->id,
                'items' => [
                    [
                        'plan' => $details['plan'],
                        'quantity' => Arr::get($details, 'quantity', 1),
                    ],
                ],
                'prorate' => Arr::get($details, 'prorate', true),
                'coupon' => Arr::get($details, 'coupon'),
                'expand' => ['latest_invoice.payment_intent'],
            ];
        if ($plan->trial_period_days) {
            $subscription['trial_from_plan'] = true;
        } else {
            $subscription['trial_period_days'] = $trialDays;
        }

        // add metadata
//        $subscription['metadata']['plan_config'] = json_encode($planConfig);

        $subscription = Subscription::create($subscription);

        // check for further actions and redirect
        $status = $subscription->latest_invoice->payment_intent->status;
        if ($subscription->latest_invoice->payment_intent->status == 'requires_action') {
            return $this->subscriptionRequiresAction($subscription, $details);
        }

        if (false /* errors */) {
            return $this->errors($subscription);
        }

        return $this->subscriptionSuccess($subscription, $details);
    }

    public function patchSubscription(Request $request)
    {
        $user = User::getCurrent();
        $plan = $request->get('plan');

        $subscription = Subscription::retrieve($user->get('subscription_id'));

        $subscription->quantity = $request->get('quantity', 1);
        $subscription->plan = $plan;

        $subscription->save();

        return $this->subscriptionSuccess($subscription, []);
    }

    public function deleteSubscription(Request $request)
    {
        $user = User::getCurrent();

        $subscription = Subscription::retrieve($user->get('subscription_id'));

        $subscription->cancel_at_period_end = true;

        $subscription->save();

        return $this->subscriptionSuccess($subscription, []);
    }

    /**
     * The {{ charge:create_subscription_form }} tag
     *
     * @return string|array
     */
    public function createSubscriptionForm()
    {
        return $this->createForm('subscription');
    }

    /**
     * The {{ charge:update_subscription_form }} tag
     *
     * @return string|array
     */
    public function updateSubscriptionForm()
    {
        return $this->createForm('subscription', [], 'PATCH');
    }

    /**
     * The {{ charge:cancel_subscription_form }} tag
     *
     * @return string
     */
    public function cancelSubscriptionForm()
    {
        return $this->createForm('subscription', [], 'DELETE');
    }

    /**
     * Get the plan details
     *
     * @return string
     */
    public function plan()
    {
        return $this->parse(Plan::retrieve($this->getParam('plan'))->toArray());
    }

    /**
     * Get the Stripe plans
     *
     * @return string
     */
    public function plans()
    {
        $plans = Plan::all(
            [
                'expand' => ['data.product'],
            ]
        )->toArray();

        return $this->parseLoop($plans['data']);
    }

    /**
     * The {{ charge:cancel_subscription_url }} tag
     *
     * @return string
     */
    public function cancelSubscriptionUrl()
    {
        return $this->makeUrl('cancel');
    }

    /**
     * The {{ charge:renew_subscription_url }} tag
     *
     * @return string
     */
    public function renewSubscriptionUrl()
    {
        return $this->makeUrl('resubscribe');
    }

    private function makeUrl($action)
    {
        $url = URL::assemble($this->actionUrl($action, false), $this->getParam('subscription_id'));

        // if they want to redirect, add it as a queary param
        if ($redirect = $this->getParam('redirect')) {
            $url .= '?redirect=' . $redirect;
        }

        return $url;
    }

    public function getSubscriptions(): array
    {
        $subscriptions = Subscription::all([
            'limit' => 100,
            'expand' => ['data.customer', 'data.plan', 'data.plan.product'],
        ])->toArray();

        return collect($subscriptions['data'])
            ->reject(function ($subscription) {
                return array_get($subscription['plan']['product'], 'deleted', false);
            })->map(function ($subscription) {
                return [
                    'id' => $subscription['id'],
                    'email' => $subscription['customer']['email'],
                    'expiry_date' => $subscription['current_period_end'],
                    'plan' => $subscription['plan']['product']['name'],
                    'amount' => $subscription['plan']['amount'],
                    'auto_renew' => !$subscription['cancel_at_period_end'],
                    'has_subscription' => true,
                ];
            })->toArray();
    }

    /**
     * @return Response|RedirectResponse
     */
    private function subscriptionSuccess(Subscription $subscription, array $params)
    {
        if (request()->ajax()) {
            return response([
                'status' => 'success',
                'subscription' => $subscription->toArray(),
            ]);
        }

        $redirect = Arr::get($params, 'redirect', false);

        $response = ($redirect) ? redirect($redirect) : back();

        $this->flash->put('success', true);
        $this->flash->put('details', $subscription->toArray());

        return $response;
    }

    /**
     * The steps for a failed form submission.
     *
     * @return Response|RedirectResponse
     */
    private function subscriptionRequiresAction(Subscription $subscription, array $params)
    {
        $secret = $subscription->latest_invoice->payment_intent->client_secret;
        if (request()->ajax()) {
            return response([
                'status' => 'requires_action',
                'client_secret' => $secret,
            ]);
        }

        $this->flash->put('requires_action', true);
        $this->flash->put('client_secret', $secret);

        if ($requires_action_redirect = Arr::get($params, 'requires_action_redirect')) {
            return redirect($requires_action_redirect);
        }

        return back();
    }
}
