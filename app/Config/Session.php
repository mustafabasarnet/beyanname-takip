<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Session\Handlers\BaseHandler;
use CodeIgniter\Session\Handlers\FileHandler;

class Session extends BaseConfig
{
    public string $driver = FileHandler::class;
    public string $cookieName = 'bt_session';
    public int $expiration = 14400;             // 4 saat
    public string $savePath = WRITEPATH . 'session';
    public bool $matchIP = false;
    public int $timeToUpdate = 300;
    public bool $regenerateDestroy = false;
    public ?string $DBGroup = null;

    /** Kilit yeniden deneme aralığı (mikrosaniye) — CI 4.7+ */
    public int $lockRetryInterval = 100_000;

    /** Maksimum kilit deneme sayısı — CI 4.7+ */
    public int $lockMaxRetries = 300;
}
