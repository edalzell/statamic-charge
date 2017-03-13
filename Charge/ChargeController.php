<?php

namespace Statamic\Addons\Charge;

use Log;
use Statamic\API\Str;
use Statamic\API\User;
use Statamic\API\Email;
use Statamic\API\Config;
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
            $params = $this->charge->getDetails();

            // process the payment
            $charge = $this->charge->charge($params);

            // if there's a user logged in, store the details
            if ($user = User::getCurrent())
            {
                $this->charge->updateUser($user, $charge);
            }

            // get the results ready for display
            $this->flash->put('success', true);
            $this->flash->put('details', $charge);

            $redirect = array_get($params, 'redirect', false);

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
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function postWebhook()
    {
        $event = request()->json()->all();
        $data = $event['data']['object'];

        if ($event['type'] == 'invoice.payment_succeeded')
        {
            // find the right user (w/ the matching Stripe Customer ID)
            /** @var \Statamic\Data\Users\User $user */
            if ($user = $this->whereUser($data['customer']))
            {
                // udpate the subscription dates and status
                $user->set('subscription_start', $data['period_start']);
                $user->set('subscription_end', $data['period_end']);
                $user->set('subscription_status', 'canceled');
                $user->save();
            }
        }
        elseif ($event['type'] == 'invoice.payment_failed')
        {
            // find the right user (w/ the matching Stripe Customer ID)
            /** @var \Statamic\Data\Users\User $user */
            if ($user = $this->whereUser($data['customer']))
            {
                // on the last try, the `next_payment_attempt` is null
                if (!$data['next_payment_attempt'])
                {
                    $this->sendEmail(
                        $user,
                        'canceled_email_template',
                        [
                            'plan' => $user->get('plan'),
                            'first_name' => $user->get('first_name'),
                            'last_name' => $user->get('last_name'),
                        ]
                    );

                    // get the role associated w/ this plan
                    if ($role = $this->charge->getRole($user->get('plan')))
                    {
                        // remove role from user
                        $roles = array_filter($user->get('roles', []), function($item) use ($role) {
                            return $item != $role;
                        });

                        $user->set('roles', $roles);
                        $user->set('subscription_status', 'canceled');

                        // store it
                        $user->save();
                    }
                }
                else
                {
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

                    $user->set('subscription_status', 'past_due');
                    $user->save();
                }
            }
        }

        return response('Stripe Webhook processed');
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
