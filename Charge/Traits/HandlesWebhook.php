<?php

namespace Statamic\Addons\Charge\Traits;

use Stripe\Customer;
use Statamic\API\Arr;
use Statamic\API\Str;
use Statamic\API\Email;
use Stripe\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Statamic\Data\Users\User;
use Statamic\API\User as UserAPI;
use Statamic\Addons\Charge\Actions\SendEmailAction;
use Statamic\Addons\Charge\Actions\CreateCustomerAction;
use Statamic\Addons\Charge\Actions\UpdateUserRolesAction;

trait HandlesWebhook
{
    /** @var User */
    private $user;

    /**
     * Deal with the Stripe events
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function postWebhook(Request $request): Response
    {
        // don't need to verify because it's done in middleware
        $payload = json_decode($request->getContent(), true);

        // set the user
        $data = Arr::get($payload, 'data.object');
        if ($customerId = Arr::get($data, 'customer')) {
            $this->user = UserAPI::all()->first(function ($key, $user) use ($customerId) {
                return $user->get('customer_id') == $customerId;
            });
        }

        if (!$this->user) {
            return $this->successMethod();
        }

        $method = 'handle' . Str::studly(str_replace('.', '_', $payload['type']));

        if (method_exists($this, $method)) {
            return $this->{$method}($data);
        }

        return $this->successMethod();
    }

    private function handlePaymentIntentSucceeded($data): Response
    {
        // if they're logged in and there's no customer, create one
        /** @var User */
        $user = UserAPI::getCurrent();
        if ($user && !$user->get('customer_id')) {
            $action = new CreateCustomerAction();

            $customer = $action->execute($user->email(), $data['payment_method']);
            $user->set('customer_id', $customer->id);
            $user->save();
        }

        return $this->successMethod();
    }

    private function handleCustomerSubscriptionCreated($data): Response
    {
        $oldPlan = $this->user->get('plan');

        // update the subscription dates and status
        $this->user
            ->set('plan', $data['plan']['id'])
            ->set('subscription_start', $data['current_period_start'])
            ->set('subscription_end', $data['current_period_end'])
            ->set('subscription_status', 'active')
            ->save();

        $action = new UpdateUserRolesAction($this->user, json_decode($data['metadata']['plan_config'], true));

        $action->execute($data['plan']['id'], $oldPlan);

        return $this->successMethod();
    }

    private function handleInvoiceUpcoming($data): Response
    {
        (new SendEmailAction)->execute(
            $this->user,
            'upcoming_payment_email_template',
            [
                'plan' => $this->user->get('plan'),
                'first_name' => $this->user->get('first_name'),
                'last_name' => $this->user->get('last_name'),
                'due_date' => $this->user->get('subscription_end'),
                'amount' => $data['amount_due'],
                'currency' => $data['currency'],
            ]
        );

        return $this->successMethod();
    }

    private function handleInvoicePaymentFailed($data)
    {  // @todo should we send an email here?
        if ($data['next_payment_attempt']) {
            $this->user->set('subscription_status', 'past_due');
            $this->user->save();

            (new SendEmailAction)->execute(
                $this->user,
                'payment_failed_email_template',
                [
                    'plan' => $this->user->get('plan'),
                    'first_name' => $this->user->get('first_name'),
                    'last_name' => $this->user->get('last_name'),
                    'amount' => $data['amount_due'],
                    'currency' => $data['currency'],
                    'attempt_count' => $data['attempt_count'],
                    'next_payment_attempt' => $data['next_payment_attempt'],
                ]
            );
        }

        return $this->successMethod();
    }

    private function handleCustomerCreated($data): Response
    {
        // get the email from the data, find the user add the customer id
        return $this->successMethod();
    }

    private function handleCustomerSubscriptionUpdated($data): Response
    {
        $this->updateUserSubscription($this->user, $data->toArray());

        $this->user->save();

        return $this->successMethod();
    }

    private function handleCustomerSubscriptionDeleted($data): Response
    {
        $this->user->set('subscription_status', 'canceled');

        // remove the role from the user
        $this->removeUserRoles($this->user, $this->user->get('plan'));

        // store it
        $this->user->save();

        (new SendEmailAction)->execute(
            $this->user,
            'canceled_email_template',
            array_only(
                $this->user->data(),
                ['plan', 'first_name', 'last_name', 'subscription_end']
            )
        );

        return $this->successMethod();
    }

    /**
     * Handle successful calls on the controller.
     *
     * @param  array  $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function successMethod()
    {
        return new Response('Webhook Handled', 200);
    }
}
