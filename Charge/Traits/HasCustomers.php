<?php

namespace Statamic\Addons\Charge\Traits;

use Stripe\Customer;
use Statamic\API\Arr;
use Statamic\API\User;
use Stripe\PaymentMethod;
use Stripe\Exception\ApiErrorException;
use Statamic\Addons\Charge\Actions\CreateCustomerAction;

trait HasCustomers
{
    /**
     * The {{ charge:update_billing_form }} tag.
     *
     * @return string|array
     */
    public function updateBillingForm()
    {
        return $this->createForm('customer/'.$this->getParam('id'), [], 'PATCH');
    }

    public function patchCustomer($id)
    {
        try {
            $customer = Customer::retrieve($id);

            if ($oldPaymentMethod = $customer->invoice_settings->default_payment_method) {
                PaymentMethod::retrieve($oldPaymentMethod)->detach();
            }

            $customer->invoice_settings->default_payment_method = PaymentMethod::retrieve(request('payment_method'))->attach(['customer' => $id]);
            $customer->save();

            return $this->updateSuccess($customer);
        } catch (ApiErrorException $e) {
            $error = $e->getError();

            $errors = [];
            $errors['type'] = $error->type;
            $errors['code'] = $error->code;
            $errors['message'] = $error->message;

            return $this->error($errors);
        }
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

        /* @var \Statamic\Data\Users\User */
        if ($user = User::email($email)) {
            if (! ($id = $user->get('customer_id'))) {
                return Arr::get($yaml, 'customer_id');
            }

            return $id;
        }

        return Arr::get($yaml, 'customer_id');
    }

    /**
     * @return Response|RedirectResponse
     */
    private function updateSuccess(Customer $customer)
    {
        if (request()->ajax()) {
            return response([
                'status' => 'success',
                'details' => $customer->toArray(),
            ]);
        }

        $this->flash->put('success', true);
        $this->flash->put('details', $customer->toArray());

        $redirect = request('redirect', false);

        return $redirect ? redirect($redirect) : back();
    }
}
