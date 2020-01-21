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

    public function execute(string $email, string $template, array $data)
    {
        Email::to($email)
            ->from($this->from())
            ->in($this->themeFolder())
            ->template($this->template($template))
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

    private function template($template)
    {
        return Arr::get($this->config, $template);
    }
}
