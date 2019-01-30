<?php

namespace Statamic\Addons\Charge\Commands;

use Stripe\Customer;
use Statamic\API\User;
use Statamic\Extend\Command;
use Statamic\Addons\Charge\Billing;

class AddSubscriptionsCommand extends Command
{
    use Billing;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'charge:add-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds Stripe information to users, based on their email';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $customers = [];
        $starting_after = null;
        $bar = $this->output->createProgressBar(count($customers));

        $this->info('Adding Subscription Details to Users');

        do {
            $results = Customer::all(
                [
                    'limit' => 100,
                    'starting_after' => $starting_after,
                ]
            )->__toArray(true);

            $starting_after = $results['data'][count($results['data']) - 1]['id'];
            $customers = array_merge($customers, $results['data']);
        } while ($results['has_more']);

        $bar->advance();

        collect($customers)->each(function ($customer, $key) {
            if (isset($customer['subscriptions']['data'][0]) && ($user = User::whereEmail($customer['email']))) {
                $this->updateUserSubscription($user, $customer['subscriptions']['data'][0]);

                $user->set('customer_id', $customer['id']);

                $user->save();
            }
        });

        $bar->finish();

        $this->output->newLine();

        $this->checkInfo('Finished adding subscriptions to users');
    }
}
