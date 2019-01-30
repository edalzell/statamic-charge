<?php

namespace Statamic\Addons\Charge\Commands;

use Statamic\API\User;
use Statamic\Extend\Command;

class ConvertUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'charge:convert-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Converts existing users to email';

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
        $users = User::all();

        $this->info('Converting Users');

        $bar = $this->output->createProgressBar(count($users));

        $users->each(function ($user, $key) use ($bar) {
            $user->username($user->email());

            if ($user->has('_uid')) {
                $user->remove('_uid');
            }

            $user->save();

            $bar->advance();
        });

        $bar->finish();

        $this->output->newLine();

        $this->checkInfo('Converted users');
    }
}
