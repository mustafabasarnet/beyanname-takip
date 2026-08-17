<?php

namespace Config;

/**
 * Ortam Kayıtçısı — baseURL'i istek hostuna göre dinamik ayarlar.
 *
 * CI4'te .env'deki app.baseURL, $_ENV üzerinden yapılandırmayı ezer ve
 * nokta içeren ortam değişkenleri $_ENV'e düşmediği için çalışma zamanı
 * host farklılaşması (test sunucusu 127.0.0.1:8099 vs canlı önizleme
 * https://....e2b.app) tek .env ile çözülemez. Bu kayıtçı, istek e2b.app
 * önizleme alan adından geldiğinde baseURL'i o hosta göre kurar; diğer
 * durumlarda .env değeri (127.0.0.1:8099) aynen kullanılır.
 *
 * İstenirse tamamen kaldırılabilir — yalnızca geliştirme/önizleme
 * ortamında bağlantı üretimini kolaylaştırır.
 */
class Registrar
{
    public static function App(): array
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';

        // Canlı önizleme proxy'sinden gelen isteklerde baseURL'i o host yap
        if ($host !== '' && str_ends_with($host, '.e2b.app')) {
            return ['baseURL' => 'https://' . $host . '/'];
        }

        return [];
    }
}
