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

    public function execute(string $to, string $template, string $subject, array $data)
    {
        Email::to($to)
            ->from($this->setting('from_email'))
            ->subject($this->setting($subject))
            ->in($this->themeFolder())
            ->template($this->setting($template))
            ->with($data)
            ->send();
    }

    private function setting(string $setting)
    {
        return Arr::get($this->config, $setting);
    }

    private function themeFolder()
    {
        return 'site/themes/' . Config::getThemeName() . '/templates';
    }
}
