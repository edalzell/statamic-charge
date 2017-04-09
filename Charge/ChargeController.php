<?php

namespace Statamic\Addons\Charge;

use Log;
use Stripe\Customer;
use Statamic\API\Str;
use Statamic\API\User;
use Statamic\API\Email;
use Statamic\API\Config;
use Stripe\Subscription;
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
        $customers = Customer::all(['limit'=>100])->__toArray(true);

        return $this->view('lists.customers', ['customers' =>  $customers['data']]);
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

    public function postProcessPayment()
    {
        try
        {
            $params = $this->charge->getDetails();

            // process the payment
            $charge = $this->charge->charge($params);

            // if there's a user logged in, store the details
            if ($user = User::getCurrent())
            {
                $this->charge->updateUser($user, $charge, true);
            }

            // get the results ready for display
            $this->flash->put('success', true);
            $this->flash->put('details', $charge);

            $redirect = array_get($params, 'redirect', false);

            return $redirect ? redirect($redirect) : back();
        }
        catch (\Stripe\Error\Base $e)
        {
            \Log::error($e->getMessage());
            return back()->withInput()->withErrors($e->getMessage(), 'charge');
        }
    }

    public function postUpdateCustomer()
    {
        $request = request();

        if ($user = User::getCurrent())
        {
            // if theres a stripe token then they're updating the payment info
            // but also check if the plan is different so that can be updated too
            $hasToken = $request->has('stripeToken');
            $plan = $request->get('plan');
            $diffPlan = $user->get('plan') != $plan;

            if ($hasToken || $diffPlan)
            {
                try
                {
                    // if there's a token we're updating the payment info
                    if ($hasToken)
                    {
                        $customer = Customer::retrieve($user->get('customer_id')); // stored in your application
                        $customer->source = $request->get('stripeToken'); // obtained with Checkout
                        $customer->save();
                    }

                    if ($diffPlan)
                    {
                        $subscription = Subscription::retrieve($user->get('subscription_id'));
                        $subscription->plan = $plan;
                        $subscription->save();

                        $this->charge->updateUserRoles($user, $plan);
                        $this->charge->updateUserSubscription($user, $subscription->__toArray(true));

                        $user->save();
                    }

                    // send 'success' back
                    $this->flash->put('success', true);

                    $params = $this->charge->getDetails();

                    $redirect = array_get($params, 'redirect', false);

                    return $redirect ? redirect($redirect) : back();
                }
                catch(\Stripe\Error\Card $e) {
                    \Log::error($e->getMessage());

                    // Use the variable $error to save any errors
                    // To be displayed to the customer later in the page
                    $body = $e->getJsonBody();
                    $error  = $body['error'];

                    return back()->withInput()->withErrors($error['message'], 'charge');
                }
            }
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
            $this->charge->updateUserSubscription($user, $data);

            $user->save();
        }
        elseif ($event['type'] == 'customer.subscription.deleted')
        {
            $user->set('subscription_status', 'canceled');

            // remove the role from the user
            $this->charge->removeUserRoles($user);

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
        $this->charge->$action($id ?? request()->segment(4));

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

    /**
     * @param string $customer_id Stripe Customer ID
     * @return \Statamic\Contracts\Data\Users\User
     *
     */
    private function whereUser($customer_id)
    {
        return User::all()->first(function ($id, $user) use ($customer_id) {
            return $user->get('customer_id') === $customer_id;
        });
    }
}
