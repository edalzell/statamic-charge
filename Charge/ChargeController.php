<?php

namespace Statamic\Addons\Charge;

use Stripe\Stripe;
use Stripe\Customer;
use Statamic\API\Str;
use Statamic\API\User;
use Illuminate\Http\Request;
use Statamic\Extend\Controller;
use Symfony\Component\Intl\Currencies;
use Statamic\Addons\Charge\Traits\Billing;
use Statamic\CP\Publish\ValidationBuilder;
use Statamic\Addons\Charge\Traits\HandlesWebhook;
use Statamic\Addons\Charge\Traits\HasSubscriptions;
use Statamic\Addons\Charge\Middleware\VerifyWebhookSignature;

class ChargeController extends Controller
{
    use Billing, HandlesWebhook, HasSubscriptions;

    /**
     * Create a new WebhookController instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (env('STRIPE_WEBHOOK_SECRET')) {
            $this->middleware(VerifyWebhookSignature::class);
        }

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

    public function postSession(Request $request): array
    {
        // @todo add validation

        $session = $this->createSession($request->all());

        return ['id' => $session->id];
    }

    public function getPaymentIntent(Request $request): array
    {
        // @todo add validation
        $pi = $this->createPaymentIntent($request->all());

        return ['client_secret' => $pi->client_secret];
    }

    /**
     * Show all the customers
     *
     * @return \Illuminate\View\View
     */
    public function customers()
    {
        $data = null;

        try {
            $customers = Customer::all(['limit' => 100])->toArray();

            $data = [
                'customers' => $customers['data'],
                'title' => 'Customers',
            ];
        } catch (Authentication $e) {
            $data = ['derp' => 'Please set your <a href="https://dashboard.stripe.com/account/apikeys">STRIPE_SECRET_KEY</a> in your .env file'];
        }

        return $this->view('lists.customers', $data);
    }

    /**
     * Show all the charges
     *
     * @return \Illuminate\View\View
     */
    public function charges()
    {
        return $this->view(
            'lists.charges',
            [
                'currency_symbol' => Currencies::getSymbol(Str::upper($this->getConfig('currency', 'usd'))),
                'charges' => $this->getCharges(),
                'title' => 'Charges',
            ]
        );
    }

    /**
     * Show all the subscriptions
     *
     * @return \Illuminate\View\View
     */
    public function subscriptions()
    {
        return $this->view(
            'lists.subscriptions',
            [
                'subscriptions' => $this->getSubscriptions(),
                'title' => 'Subscriptions',
            ]
        );
    }

    public function postProcessPayment()
    {
        try {
            $params = $this->getDetails();

            // process the payment & send details back
            $this->flash->put('details', $this->charge($params));
            $this->flash->put('success', true);

            return $this->redirectOrBack($params);
        } catch (\Stripe\Error\Base $e) {
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
            $fields = request()->except(['_token', '_charge_params', 'stripeToken']);

            $validator = $this->runValidation($fields, $user->fieldset());

            if ($validator->fails()) {
                return back()->withInput()->withErrors($validator);
            }

            $response = $this->postUpdateBilling($user);

            $user->data(array_merge($user->data(), $fields));
            $user->save();

            return $response;
        } else {
            return back()->withInput()->withErrors('Not logged in', 'charge');
        }
    }

    public function postUpdateBilling($user = null)
    {
        if (!$user) {
            $user = User::getCurrent();
        }

        if ($user) {
            $result = $this->updateUserBilling($user);

            if (isset($result['success'])) {
                // send 'success' back
                $this->flash->put('success', true);

                return $this->redirectOrBack($this->getDetails());
            } else {
                return back()->withInput()->withErrors($result['error'], 'charge');
            }
        } else {
            return back()->withInput()->withErrors('Not logged in', 'charge');
        }
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
        if (!$id) {
            $id = request()->segment(4);
        }

        $this->$action($id);

        $redirect = request()->query('redirect', false);

        // send 'success' back
        $this->flash->put('success', true);

        return $redirect ? redirect($redirect) : back();
    }

    /**
     * @param string $customer_id Stripe Customer ID
     *
     * @return \Statamic\Contracts\Data\Users\User
     */
    private function whereUser($customer_id)
    {
        return User::all()->first(function ($id, $user) use ($customer_id) {
            return $user->get('customer_id') === $customer_id;
        });
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
