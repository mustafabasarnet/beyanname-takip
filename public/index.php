<?php

use CodeIgniter\Boot;
use Config\Paths;

/*
 *---------------------------------------------------------------
 * PHP SÜRÜM KONTROLÜ
 *---------------------------------------------------------------
 */
$minPhpVersion = '8.1';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo sprintf(
        'Bu uygulama en az PHP v%s gerektirir. Sunucunuzdaki sürüm: v%s',
        $minPhpVersion,
        PHP_VERSION
    );

    exit(1);
}

/*
 *---------------------------------------------------------------
 * COMPOSER KONTROLÜ
 *---------------------------------------------------------------
 */
if (! is_file(__DIR__ . '/../vendor/autoload.php')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8">'
       . '<title>Kurulum Tamamlanmamış</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#f1f5f9;display:grid;'
       . 'place-items:center;min-height:100vh;margin:0}.k{background:#fff;padding:32px 36px;'
       . 'border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.1);max-width:520px}'
       . 'h2{margin:0 0 12px;color:#dc2626}code{background:#f1f5f9;padding:2px 7px;'
       . 'border-radius:5px;font-size:14px}</style></head><body><div class="k">'
       . '<h2>⚠ Kurulum Tamamlanmamış</h2>'
       . '<p>Bağımlılıklar yüklenmemiş. Proje kök dizininde şu komutu çalıştırın:</p>'
       . '<p><code>composer install</code></p>'
       . '<p style="color:#64748b;font-size:14px">Ardından <code>.env</code> dosyasını '
       . 'oluşturup veritabanı bilgilerinizi girin.</p>'
       . '</div></body></html>';

    exit(1);
}

/*
 *---------------------------------------------------------------
 * ÇALIŞMA DİZİNİ (FCPATH)
 *---------------------------------------------------------------
 * Bu sabit tanımlanmazsa framework "The application environment
 * is not set correctly." benzeri hatalar üretir.
 */

// Ön denetleyicinin (bu dosyanın) bulunduğu dizin
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Geçerli dizini ön denetleyiciye sabitle
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * UYGULAMAYI BAŞLAT
 *---------------------------------------------------------------
 */

// Yol yapılandırmasını yükle
require FCPATH . '../app/Config/Paths.php';

$paths = new Paths();

// Framework önyükleyicisini yükle
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'Boot.php';

exit(Boot::bootWeb($paths));
