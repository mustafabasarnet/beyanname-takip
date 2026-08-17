<?php

namespace Config;

class Paths
{
    public string $systemDirectory = __DIR__ . '/../../vendor/codeigniter4/framework/system';
    public string $appDirectory    = __DIR__ . '/..';
    public string $writableDirectory = __DIR__ . '/../../writable';
    public string $testsDirectory  = __DIR__ . '/../../tests';
    public string $viewDirectory   = __DIR__ . '/../Views';

    /** .env dosyasının bulunduğu dizin — CI 4.7+ */
    public string $envDirectory = __DIR__ . '/../../';
}
