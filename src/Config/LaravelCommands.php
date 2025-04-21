<?php

namespace Reymart221111\Cia4LaravelMod\Config;

use CodeIgniter\Config\BaseConfig;

class LaravelCommands extends BaseConfig
{
    /**
     * Register package commands
     *
     * @var array<string, string>
     */
    public $commands = [
        'laravel:setup' => \Reymart221111\Cia4LaravelMod\Commands\Setup::class,
    ];
}