<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Cookie\Cookie as CookieClass;
use DateTimeInterface;

class Cookie extends BaseConfig
{
    public string $prefix = '';
    public $expires = 0;
    public string $path = '/';
    public string $domain = '';
    public bool $secure = false;
    public bool $httponly = true;
    public string $samesite = CookieClass::SAMESITE_LAX;
    public bool $raw = false;
}
