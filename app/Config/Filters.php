<?php

namespace Config;

use App\Filters\AuthFilter;
use App\Filters\GuestFilter;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseConfig
{
    /**
     * Filtre kısayolları.
     * Not: cors / forcehttps / pagecache / performance framework tarafından
     * zorunlu tutulur ($required), bu yüzden burada tanımlı kalmalıdır.
     */
    public array $aliases = [
        // --- Framework filtreleri ---
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,

        // --- Uygulama filtreleri ---
        'auth'  => AuthFilter::class,   // Giriş + rol kontrolü  (auth:admin,musavir)
        'guest' => GuestFilter::class,  // Sadece giriş yapmamışlar
    ];

    /**
     * Her istekte çalışan filtreler.
     * CSRF koruması açık; sadece kurulum ekranı hariç tutuldu.
     */
    public array $globals = [
        'before' => [
            'csrf' => ['except' => ['kurulum*']],
        ],
        'after' => [],
    ];

    /**
     * HTTP metoduna göre filtreler.
     */
    public array $methods = [];

    /**
     * URI desenine göre filtreler.
     */
    public array $filters = [];

    /**
     * Framework tarafından her istekte çalıştırılması ZORUNLU filtreler.
     * (CodeIgniter 4.7+ — bu diziyi kaldırmayın.)
     */
    public array $required = [
        'before' => [
            'forcehttps', // Global güvenli istek zorlaması
            'pagecache',  // Sayfa önbelleği
        ],
        'after' => [
            'pagecache',   // Sayfa önbelleği
            'performance', // Performans metrikleri
            'toolbar',     // Hata ayıklama araç çubuğu
        ],
    ];
}
