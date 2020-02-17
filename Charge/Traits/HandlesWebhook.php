<?php

namespace Statamic\Addons\Charge\Traits;

use Statamic\API\Arr;
use Statamic\API\Str;
use Statamic\Config\Addons;
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

        (new SendEmailAction)->execute(
            Arr::get($data, 'charges.data.0.receipt_email'),
            'one_time_payment_email_template',
            'one_time_payment_email_subject',
            [
                'amount' => Arr::get($data, 'charges.data.0.amount'),
                'currency' => Arr::get($data, 'charges.data.0.currency'),
                'description' => Arr::get($data, 'charges.data.0.description'),
                'receipt_url' => Arr::get($data, 'charges.data.0.receipt_url'),
            ]
        );

        return $this->successMethod();
    }

    private function handleCustomerSubscriptionCreated($data): Response
    {
        // update the subscription dates and status
        $this->user
            ->set('plan', $data['plan']['id'])
            ->set('subscription_id', $data['id'])
            ->set('subscription_start', $data['current_period_start'])
            ->set('subscription_end', $data['current_period_end'])
            ->set('subscription_status', 'active')
            ->save();

        $action = new UpdateUserRolesAction($this->user, $this->getPlansAndRoles());

        $action->execute($data['plan']['id']);

        return $this->successMethod();
    }

    private function handleInvoiceUpcoming($data): Response
    {
        (new SendEmailAction)->execute(
            $this->user->email(),
            'upcoming_payment_email_template',
            'upcoming_payment_email_subject',
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

    private function handleInvoicePaymentSucceeded($data)
    {
        (new SendEmailAction)->execute(
            $this->user->email(),
            'payment_succeeded_email_template',
            'payment_succeeded_email_subject',
            [
                'plan' => $this->user->get('plan'),
                'first_name' => $this->user->get('first_name'),
                'last_name' => $this->user->get('last_name'),
                'amount' => $data['amount_due'],
                'currency' => $data['currency'],
            ]
        );

        return $this->successMethod();
    }

    private function handleInvoicePaymentFailed($data)
    {
        if ($data['next_payment_attempt']) {
            $this->user->set('subscription_status', 'past_due');
            $this->user->save();

            (new SendEmailAction)->execute(
                $this->user->email(),
                'payment_failed_email_template',
                'payment_failed_email_subject',
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
        $oldPlan = $this->user->get('plan');

        // update the subscription dates and status
        $this->user
            ->set('plan', $data['plan']['id'])
            ->set('subscription_id', $data['id'])
            ->set('subscription_start', $data['current_period_start'])
            ->set('subscription_end', $data['current_period_end'])
            ->set('subscription_status', $data['cancel_at_period_end'] ? 'canceling' : 'active')
            ->save();

        $action = new UpdateUserRolesAction($this->user, $this->getPlansAndRoles());

        $action->execute($data['plan']['id'], $oldPlan);

        (new SendEmailAction)->execute(
            $this->user->email(),
            'subscription_updated_email_template',
            'subscription_updated_email_subject',
            array_only(
                $this->user->data(),
                ['plan', 'first_name', 'last_name', 'subscription_status', 'subscription_end']
            )
        );

        return $this->successMethod();
    }

    private function handleCustomerSubscriptionDeleted($data): Response
    {
        $oldPlan = $this->user->get('plan');

        // update the subscription dates and status
        $this->user
            ->set('plan', $data['plan']['id'])
            ->set('subscription_id', $data['id'])
            ->set('subscription_start', $data['current_period_start'])
            ->set('subscription_end', $data['ended_at'])
            ->set('subscription_status', 'canceled')
            ->save();

        $action = new UpdateUserRolesAction($this->user, $this->getPlansAndRoles());

        $action->execute(null, $oldPlan);

        (new SendEmailAction)->execute(
            $this->user->email(),
            'canceled_email_template',
            'canceled_email_subject',
            array_only(
                $this->user->data(),
                ['plan', 'first_name', 'last_name', 'subscription_status', 'subscription_end']
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

    private function getPlansAndRoles(): array
    {
        $config = app(Addons::class)->get('charge') ?: [];

        return Arr::get($config, 'plans_and_roles', []);
    }
}
