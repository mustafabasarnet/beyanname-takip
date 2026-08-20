<?php

namespace Config;

/**
 * Ortam Kayıtçısı — baseURL'i istek hostuna göre dinamik ayarlar.
 *
 * CI4'te .env'deki app.baseURL, $_ENV üzerinden yapılandırmayı ezer ve
 * nokta içeren ortam değişkenleri $_ENV'e düşmediği için çalışma zamanı
 * host farklılaşması tek .env ile çözülemez. Bu kayıtçı:
 *
 *   • Canlı önizleme proxy'si (https://<port>-<sandbox>.e2b.app)
 *     → baseURL o host olur,
 *   • Yerel test sunucusu (127.0.0.1:8099)
 *     → baseURL o adres olur.
 *
 * Üretimde .env'deki app.baseURL bu değerleri zaten ezer (initEnvValue
 * Registrar'dan sonra çalışır); bu kayıtçı yalnızca geliştirme/önizleme
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

        // Yerel test sunucusu (tests/*.sh bu adrese bağlanır)
        if ($host === '127.0.0.1:8099' || $host === 'localhost:8099') {
            return ['baseURL' => 'http://' . $host . '/'];
        }

        return [];
    }
}
