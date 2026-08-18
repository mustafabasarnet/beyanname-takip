<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

class Autoload extends AutoloadConfig
{
    public $psr4 = [
        APP_NAMESPACE => APPPATH,
    ];

    public $classmap = [];
    public $files    = [];

    /** Her istekte otomatik yüklenecek helper'lar */
    public $helpers = ['url', 'form', 'text', 'cookie', 'beyanname'];
}
