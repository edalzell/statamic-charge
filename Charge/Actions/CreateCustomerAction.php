<?php

namespace Statamic\Addons\Charge\Actions;

use Stripe\Customer;

class CreateCustomerAction
{
    public function execute(string $email, string $paymentMethod): Customer
    {
        return Customer::create(
            [
                'email' => $email,
                'payment_method' => $paymentMethod,
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethod,
                ],
            ]
        );
    }
}