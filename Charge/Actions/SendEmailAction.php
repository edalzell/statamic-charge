<?php

namespace Statamic\Addons\Charge\Actions;

use Statamic\API\Arr;
use Statamic\API\Email;
use Statamic\API\Config;
use Statamic\Config\Addons;

class SendEmailAction
{
    private $config;

    public function __construct()
    {
        $this->config = app(Addons::class)->get('charge');
    }

    /**
     * @param $user     \Statamic\Data\Users\User
     * @param $template string
     * @param $data     array
     */
    public function execute($user, $template, $data)
    {
//        Config::
        Email::to($user->email())
            ->from($this->from())
            ->in($this->themeFolder())
            ->template($this->getConfig($template))
            ->with($data)
            ->send();
    }

    private function from()
    {
        return Arr::get($this->config, 'from_email');
    }

    private function themeFolder()
    {
        return 'site/themes/' . Config::getThemeName() . '/templates';
    }

    private function template()
    {
        return Arr::get($this->config, 'template');
    }
}
