<?php

namespace Statamic\Addons\Charge;

use Log;
use Stripe\Stripe;
use Stripe\Customer;
use Statamic\API\Str;
use Statamic\API\User;
use Statamic\API\Email;
use Statamic\API\Config;
use Statamic\API\Request;
use Statamic\Extend\Controller;
use Symfony\Component\Intl\Intl;
use Statamic\CP\Publish\ValidationBuilder;

class ChargeController extends Controller
{
    use Charge;

    public function init()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
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
        $customers = Customer::all(['limit'=>100])->__toArray(true);

        return $this->view('lists.customers', ['customers' =>  $customers['data']]);
    }

    public function charges()
    {
        // get currency symbol
        $currency = Str::upper($this->getConfig('currency', 'usd'));
        $currency_symbol  = Intl::getCurrencyBundle()->getCurrencySymbol($currency);
        $charges = $this->getCharges();

        return $this->view('lists.charges', compact('currency_symbol', 'charges'));
    }

    public function subscriptions()
    {
        return $this->view('lists.subscriptions', ['subscriptions' => $this->getSubscriptions()]);
    }

    public function postProcessPayment()
    {
        try
        {
            $params = $this->getDetails();

            // process the payment & send details back
            $this->flash->put('details', $this->charge($params));
            $this->flash->put('success', true);

            return $this->redirectOrBack($params);
        }
        catch (\Stripe\Error\Base $e)
        {
            \Log::error($e->getMessage());
            return back()->withInput()->withErrors($e->getMessage(), 'charge');
        }
    }

    /**
     * Update a user with new data.
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function postUpdateUser()
    {
        if ($user = User::getCurrent()) {
            $fields = Request::except(['_token', '_charge_params']);

            $validator = $this->runValidation($fields, $user->fieldset());

            if ($validator->fails()) {
                return back()->withInput()->withErrors($validator);
            }

            $user->data(array_merge($user->data(), $fields));
            $user->save();

            return $this->postUpdateBilling($user);
        }
        else
        {
            return back()->withInput()->withErrors('Not logged in', 'charge');
        }
    }

    public function postUpdateBilling($user = null)
    {
        if (!$user)
        {
            $user = User::getCurrent();
        }

        if ($user)
        {
            $result = $this->updateUserBilling($user);

            if (isset($result['success']))
            {
                // send 'success' back
                $this->flash->put('success', true);

                return $this->redirectOrBack($this->getDetails());
            }
            else
            {
                return back()->withInput()->withErrors($result['error'], 'charge');
            }
        }
        else
        {
            return back()->withInput()->withErrors('Not logged in', 'charge');
        }
    }

    /**
     * Deal with the Stripe events
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function postWebhook()
    {
        $event = request()->json()->all();
        $data = $event['data']['object'];

        // find the right user (w/ the matching Stripe Customer ID)
        /** @var \Statamic\Data\Users\User $user */
        if (!($user = $this->whereUser(array_get($data, 'customer'))))
        {
            return response('No User Found');
        }

        if ($event['type'] == 'invoice.payment_succeeded')
        {
            // update the subscription dates and status
            $user->set('subscription_start', $data['period_start'])
                ->set('subscription_end', $data['period_end'])
                ->set('subscription_status', 'active')
                ->save();
        }
        elseif (($event['type'] == 'invoice.payment_failed') && ($data['next_payment_attempt']))
        {
            $user->set('subscription_status', 'past_due');
            $user->save();

            $this->sendEmail(
                $user,
                'payment_failed_email_template',
                [
                    'plan' => $user->get('plan'),
                    'first_name' => $user->get('first_name'),
                    'last_name' => $user->get('last_name'),
                    'attempt_count' => $data['attempt_count'],
                    'next_payment_attempt' => $data['next_payment_attempt'],
                ]);
        }
        elseif ($event['type'] == 'customer.subscription.updated')
        {
            $this->updateUserSubscription($user, $data);

            $user->save();
        }
        elseif ($event['type'] == 'customer.subscription.deleted')
        {
            $user->set('subscription_status', 'canceled');

            // remove the role from the user
            $this->removeUserRoles($user);

            // store it
            $user->save();

            $this->sendEmail(
                $user,
                'canceled_email_template',
                [
                    'plan' => $user->get('plan'),
                    'first_name' => $user->get('first_name'),
                    'last_name' => $user->get('last_name'),
                ]
            );
        }

        return response('Stripe Webhook processed');
    }

    /**
     * Cancel a subscription
     */
    public function getCancel($subscription_id = null)
    {
        return $this->doAction('cancel', $subscription_id);
    }

    /**
     * Resubscribe to a subscription
     */
    public function getResubscribe($subscription_id = null)
    {
        return $this->doAction('resubscribe', $subscription_id);
    }

    /**
     * Refund a charge
     */
    public function getRefund($charge_id = null)
    {
        return $this->doAction('refund', $charge_id);
    }

    /**
     * Perform an action then redirect if required
     */
    private function doAction($action, $id = null)
    {
        if (!$id)
        {
            $id = request()->segment(4);
        }

        $this->$action($id);

        $redirect = request()->query('redirect', false);

        // send 'success' back
        $this->flash->put('success', true);

        return $redirect ? redirect($redirect) : back();
    }

    /**
     * @param $user     \Statamic\Data\Users\User
     * @param $template string
     * @param $data     array
     */
    private function sendEmail($user, $template, $data)
    {
        Email::to($user->email())
            ->from($this->getConfig('from_email'))
            ->in('site/themes/' . Config::getThemeName() . '/templates')
            ->template($this->getConfig($template))
            ->with($data)
            ->send();
    }

    private function redirectOrBack($data)
    {
        $redirect = array_get($data, 'redirect', false);

        return $redirect ? redirect($redirect) : back();
    }

    /**
     * Get the Validator instance
     *
     * @return mixed
     */
    private function runValidation($fields, $fieldset)
    {
        $fields = array_merge($fields, ['username' => 'required']);

        $builder = new ValidationBuilder(['fields' => $fields], $fieldset);

        $builder->build();

        return app('validator')->make(['fields' => $fields], $builder->rules());
    }
}
