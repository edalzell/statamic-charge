<?php

namespace Statamic\Addons\Charge\Traits;

use Stripe\Customer;
use Statamic\API\Arr;
use Statamic\API\User;
use Stripe\PaymentMethod;
use Statamic\Addons\Charge\Actions\CreateCustomerAction;

trait HasCustomers
{
    /**
     * The {{ charge:update_billing_form }} tag
     *
     * @return string|array
     */
    public function updateBillingForm()
    {
        return $this->createForm('customer/' . $this->getParam('id'), [], 'PATCH');
    }

    public function patchCustomer($id)
    {
        $customer = Customer::retrieve($id);

        PaymentMethod::retrieve($customer->invoice_settings->default_payment_method)->detach();

        PaymentMethod::retrieve(request('payment_method'))->attach([
            'customer' => $id,
        ]);

        $customer->invoice_settings->default_payment_method = request('payment_method');
        $customer->save();

        return back();
    }

    public function getOrCreateCustomer(string $paymentMethod = null): Customer
    {
        /** @var \Statamic\Data\Users\User */
        $user = User::getCurrent();

        if ($customerId = $user->get('customer_id')) {
            return Customer::retrieve($customerId);
        }

        $customer = (new CreateCustomerAction)->execute($user->email(), $paymentMethod);

        $user->set('customer_id', $customer->id);
        $user->save();

        return $customer;
    }

    public function customer()
    {
        if ($id = $this->getParam('id')) {
            return $this->parse(Customer::retrieve($id)->toArray());
        }
    }

    private function getCustomerId($email)
    {
        $yaml = $this->storage->getYAML($email);

        /** @var \Statamic\Data\Users\User */
        if ($user = User::email($email)) {
            if (!($id = $user->get('customer_id'))) {
                return Arr::get($yaml, 'customer_id');
            }

            return $id;
        }

        return Arr::get($yaml, 'customer_id');
    }
}
