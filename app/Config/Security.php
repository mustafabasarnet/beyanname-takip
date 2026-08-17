<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Security extends BaseConfig
{
    public string $csrfProtection = 'session';
    public bool $tokenRandomize = false;
    public string $tokenName = 'csrf_beyanname';
    public string $headerName = 'X-CSRF-TOKEN';
    public string $cookieName = 'csrf_cookie';
    public int $expires = 7200;
    public bool $regenerate = false;   // AJAX'ta token dönmesin diye kapalı
    public bool $redirect = false;
    public string $samesite = 'Lax';
}
