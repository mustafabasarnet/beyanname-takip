<?php

/**
 * Beyanname Takip - Ortak yardımcı fonksiyonlar
 */

if (! function_exists('trTarih')) {
    /** 2026-03-31 -> 31.03.2026 */
    function trTarih(?string $tarih, string $bicim = 'd.m.Y'): string
    {
        if (empty($tarih) || $tarih === '0000-00-00') {
            return '-';
        }

        $ts = strtotime($tarih);

        return $ts === false ? '-' : date($bicim, $ts);
    }
}

if (! function_exists('trTarihUzun')) {
    /** 31.03.2026 Salı */
    function trTarihUzun(?string $tarih): string
    {
        if (empty($tarih)) {
            return '-';
        }

        $gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
        $ts     = strtotime($tarih);

        return date('d.m.Y', $ts) . ' ' . $gunler[(int) date('w', $ts)];
    }
}

if (! function_exists('ayAdi')) {
    function ayAdi(int $ay): string
    {
        $aylar = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
        ];

        return $aylar[$ay] ?? '';
    }
}

if (! function_exists('ayKisa')) {
    function ayKisa(int $ay): string
    {
        return mb_substr(ayAdi($ay), 0, 3);
    }
}

if (! function_exists('durumRozeti')) {
    /** Beyanname durumu için renkli rozet HTML'i */
    function durumRozeti(string $durum, ?string $sonTarih = null): string
    {
        $etiket = [
            'BEKLIYOR'     => ['Bekliyor', 'bekliyor'],
            'HAZIR'        => ['Hazır', 'hazir'],
            'ONAYLANDI'    => ['Onaylandı', 'onaylandi'],
            'VERILMEYECEK' => ['Verilmeyecek', 'verilmeyecek'],
        ];

        [$metin, $sinif] = $etiket[$durum] ?? [$durum, 'bekliyor'];

        // Süresi geçmiş ve hâlâ tamamlanmamışsa kırmızı uyarı
        if ($sonTarih !== null && in_array($durum, ['BEKLIYOR', 'HAZIR'], true)
            && substr($sonTarih, 0, 10) < date('Y-m-d')) {
            $sinif .= ' gecikmis';
            $metin .= ' • Gecikmiş';
        }

        return '<span class="rozet ' . $sinif . '">' . esc($metin) . '</span>';
    }
}

if (! function_exists('kalanGunMetni')) {
    function kalanGunMetni(string $sonTarih): array
    {
        $bugun = new DateTime(date('Y-m-d'));
        $son   = new DateTime(substr($sonTarih, 0, 10));
        $fark  = (int) $bugun->diff($son)->format('%r%a');

        if ($fark < 0) {
            return ['metin' => abs($fark) . ' gün gecikti', 'sinif' => 'kirmizi', 'gun' => $fark];
        }

        if ($fark === 0) {
            return ['metin' => 'BUGÜN SON GÜN', 'sinif' => 'kirmizi', 'gun' => 0];
        }

        if ($fark <= 3) {
            return ['metin' => $fark . ' gün kaldı', 'sinif' => 'turuncu', 'gun' => $fark];
        }

        if ($fark <= 7) {
            return ['metin' => $fark . ' gün kaldı', 'sinif' => 'sari', 'gun' => $fark];
        }

        return ['metin' => $fark . ' gün kaldı', 'sinif' => 'yesil', 'gun' => $fark];
    }
}

if (! function_exists('paraFormat')) {
    function paraFormat($tutar, string $simge = '₺'): string
    {
        if ($tutar === null || $tutar === '') {
            return '-';
        }

        return number_format((float) $tutar, 2, ',', '.') . ' ' . $simge;
    }
}

if (! function_exists('vknTckn')) {
    function vknTckn(array $mukellef): string
    {
        return $mukellef['vergi_kimlik_no'] ?: ($mukellef['tc_kimlik_no'] ?: '-');
    }
}

if (! function_exists('mukellefTipiAdi')) {
    function mukellefTipiAdi(string $tip): string
    {
        return $tip === 'tuzel' ? 'Tüzel Kişi (Kurum)' : 'Gerçek Kişi (Şahıs)';
    }
}

if (! function_exists('defterTipleri')) {
    /**
     * Defter tipi seçenekleri (tek kaynak).
     *
     * @return array<string,string>
     */
    function defterTipleri(): array
    {
        return [
            'isletme'        => 'İşletme Defteri',
            'bilanco'        => 'Bilanço (Yevmiye)',
            'serbest_meslek' => 'Serbest Meslek Kazanç Defteri',
            'basit_usul'     => 'Basit Usul',
            'diger'          => 'Diğer',
        ];
    }
}

if (! function_exists('defterTipiAdi')) {
    function defterTipiAdi(?string $tip): string
    {
        return defterTipleri()[$tip] ?? '-';
    }
}

if (! function_exists('defterTipiKisa')) {
    /** Dar sütunlar için kısa etiket */
    function defterTipiKisa(?string $tip): string
    {
        $kisa = [
            'isletme'        => 'İşletme',
            'bilanco'        => 'Bilanço',
            'serbest_meslek' => 'Serbest Meslek',
            'basit_usul'     => 'Basit Usul',
            'diger'          => 'Diğer',
        ];

        return $kisa[$tip] ?? '-';
    }
}

if (! function_exists('periyotAdi')) {
    function periyotAdi(string $p): string
    {
        $liste = [
            'AYLIK'      => 'Aylık',
            'UC_AYLIK'   => 'Üç Aylık',
            'ALTI_AYLIK' => 'Altı Aylık',
            'YILLIK'     => 'Yıllık',
        ];

        return $liste[$p] ?? $p;
    }
}

if (! function_exists('aktifMenu')) {
    /** Menü linki aktif mi? */
    function aktifMenu(string $parca): string
    {
        $url = uri_string();

        return str_starts_with(ltrim($url, '/'), ltrim($parca, '/')) ? 'aktif' : '';
    }
}

if (! function_exists('yilSecenekleri')) {
    function yilSecenekleri(int $geri = 3, int $ileri = 1): array
    {
        $bu     = (int) date('Y');
        $liste  = [];

        for ($y = $bu - $geri; $y <= $bu + $ileri; $y++) {
            $liste[] = $y;
        }

        return array_reverse($liste);
    }
}

if (! function_exists('kisalt')) {
    function kisalt(?string $metin, int $uzunluk = 40): string
    {
        $metin = (string) $metin;

        if (mb_strlen($metin) <= $uzunluk) {
            return $metin;
        }

        return mb_substr($metin, 0, $uzunluk - 1) . '…';
    }
}

if (! function_exists('gencGirisimciDurum')) {
    /**
     * Genç girişimci kazanç istisnasının (GVK mükerrer 20) durumunu hesaplar.
     *
     * İstisna, faaliyete başlanan takvim yılından itibaren 3 vergilendirme
     * dönemi boyunca geçerlidir. Süre, ayarlardan (gg_istisna_donem) yönetilir.
     *
     * @param array    $mukellef mukellefler tablosu satırı
     * @param int|null $yil      Hangi vergilendirme yılı için sorulduğu
     *
     * @return array{
     *   var:bool, gecerli:bool, donem:int|null, toplam:int,
     *   baslangic:int|null, bitis:int|null, metin:string, sinif:string
     * }
     */
    function gencGirisimciDurum(array $mukellef, ?int $yil = null): array
    {
        $bos = [
            'var' => false, 'gecerli' => false, 'donem' => null, 'toplam' => 3,
            'baslangic' => null, 'bitis' => null, 'metin' => '', 'sinif' => 'gri',
        ];

        if (empty($mukellef['genc_girisimci'])) {
            return $bos;
        }

        // Genç girişimci istisnası GVK mükerrer 20'ye göre yalnızca GERÇEK
        // KİŞİ (gelir vergisi) mükelleflerine uygulanır; şirketlerde söz
        // konusu değildir. Veritabanında eski/hatalı bir işaret kalmış olsa
        // bile tüzel kişide istisna YOK sayılır.
        //
        // Kontrol tek noktada (bu fonksiyonda) yapılır: rozetler, tahakkuk
        // uyarısı, mükellef kartı ve listeler hepsi buradan beslendiği için
        // tüzel kişilerde hiçbir yerde görünmez.
        if (($mukellef['mukellef_tipi'] ?? 'gercek') === 'tuzel') {
            return $bos;
        }

        // Süre ayardan okunur (mevzuat değişirse tek yerden güncellenir)
        static $toplamDonem = null;

        if ($toplamDonem === null) {
            try {
                $toplamDonem = (int) (new \App\Models\AyarModel())->oku('gg_istisna_donem', 3);
            } catch (\Throwable $e) {
                $toplamDonem = 3;
            }

            $toplamDonem = $toplamDonem > 0 ? $toplamDonem : 3;
        }

        // Başlangıç yılı girilmemişse işe başlama yılı esas alınır
        $baslangic = ! empty($mukellef['gg_baslangic_yili'])
            ? (int) $mukellef['gg_baslangic_yili']
            : (! empty($mukellef['ise_baslama_tarihi'])
                ? (int) date('Y', strtotime((string) $mukellef['ise_baslama_tarihi']))
                : null);

        if ($baslangic === null) {
            return array_merge($bos, [
                'var' => true, 'gecerli' => true,
                'metin' => 'Genç Girişimci', 'sinif' => 'yesil',
            ]);
        }

        $yil   = $yil ?? (int) date('Y');
        $bitis = $baslangic + $toplamDonem - 1;
        $donem = $yil - $baslangic + 1;

        // Henüz başlamamış
        if ($donem < 1) {
            return array_merge($bos, [
                'var' => true, 'gecerli' => false, 'toplam' => $toplamDonem,
                'baslangic' => $baslangic, 'bitis' => $bitis,
                'metin' => 'Genç Girişimci (' . $baslangic . ' yılında başlayacak)',
                'sinif' => 'gri',
            ]);
        }

        // Süre dolmuş
        if ($donem > $toplamDonem) {
            return array_merge($bos, [
                'var' => true, 'gecerli' => false, 'donem' => $donem, 'toplam' => $toplamDonem,
                'baslangic' => $baslangic, 'bitis' => $bitis,
                'metin' => 'Genç Girişimci süresi doldu (' . $bitis . ' sonu)',
                'sinif' => 'kirmizi',
            ]);
        }

        // Geçerli — son dönemse turuncu uyarı
        return [
            'var' => true, 'gecerli' => true, 'donem' => $donem, 'toplam' => $toplamDonem,
            'baslangic' => $baslangic, 'bitis' => $bitis,
            'metin' => 'Genç Girişimci ' . $donem . '/' . $toplamDonem . '. dönem',
            'sinif' => $donem === $toplamDonem ? 'turuncu' : 'yesil',
        ];
    }
}

if (! function_exists('gencGirisimciRozet')) {
    /** Genç girişimci rozeti HTML'i (yoksa boş döner) */
    function gencGirisimciRozet(array $mukellef, ?int $yil = null, bool $kisa = false): string
    {
        $d = gencGirisimciDurum($mukellef, $yil);

        if (! $d['var']) {
            return '';
        }

        $metin = $kisa
            ? 'GG' . ($d['donem'] !== null ? ' ' . $d['donem'] . '/' . $d['toplam'] : '')
            : $d['metin'];

        $baslik = $d['baslangic'] !== null
            ? 'Genç girişimci istisnası: ' . $d['baslangic'] . '-' . $d['bitis']
            : 'Genç girişimci istisnası';

        return '<span class="rozet ' . $d['sinif'] . '" title="' . esc($baslik) . '">🌱 '
            . esc($metin) . '</span>';
    }

// =====================================================================
//  MÜKELLEF İNDİRİM / KISITLAMA ROZETLERİ
//
//  Yıllık gelir/kurumlar ve geçici vergi beyannamelerinde mükellefe göre
//  uygulanan kalemler çizelgede küçük rozetlerle gösterilir. Amaç:
//  beyannameyi hazırlarken "bu mükellefte Bağkur var mıydı?" diye
//  mükellef kartına gitmeye gerek kalmaması.
// =====================================================================

if (! function_exists('indirimTanimlari')) {
    /**
     * Takip edilen indirim/kısıtlama kalemleri.
     *
     * 'turler' → rozetin HANGİ beyanname türlerinde görüneceği.
     *   Bağkur ve eğitim/sağlık yalnızca GERÇEK KİŞİ beyannamelerinde
     *   (yıllık GV + gelir geçici) anlamlıdır; kurumlar vergisinde böyle
     *   bir indirim yoktur, oraya rozet basmak yanıltıcı olurdu.
     *   Finansman gider kısıtlaması ise hem gelir hem kurumlar tarafında
     *   uygulanır (GVK 41/9 ve KVK 11/1-i).
     *
     * @return array<string,array{alan:string,not_alan:string,ikon:string,kisa:string,ad:string,sinif:string,turler:string[]}>
     */
    function indirimTanimlari(): array
    {
        return [
            'bagkur' => [
                'alan'     => 'ind_bagkur',
                'not_alan' => 'ind_bagkur_not',
                'ikon'     => '🏥',
                'kisa'     => 'BK',
                'ad'       => 'Bağkur primi indirimi',
                'sinif'    => 'mavi',
                'turler'   => ['YILLIK_GV', 'GELIR_GECICI'],
            ],
            'egitim_saglik' => [
                'alan'     => 'ind_egitim_saglik',
                'not_alan' => 'ind_egitim_saglik_not',
                'ikon'     => '🎓',
                'kisa'     => 'EĞS',
                'ad'       => 'Eğitim ve sağlık harcamaları indirimi (GVK 89/2)',
                'sinif'    => 'mor',
                'turler'   => ['YILLIK_GV', 'GELIR_GECICI'],
            ],
            'finansman' => [
                'alan'     => 'ind_finansman',
                'not_alan' => 'ind_finansman_not',
                'ikon'     => '💰',
                // Bu bir indirim değil KISITLAMA; rengi de uyarı tonunda.
                'kisa'     => 'FGK',
                'ad'       => 'Finansman gider kısıtlaması (GVK 41/9, KVK 11/1-i)',
                'sinif'    => 'turuncu',
                'turler'   => ['YILLIK_GV', 'GELIR_GECICI', 'KURUMLAR', 'KURUM_GECICI'],
            ],
        ];
    }
}

if (! function_exists('indirimRozetliTurler')) {
    /**
     * Rozet çıkabilecek TÜM beyanname türü kodları.
     * Kontrolcü/görünüm tarafında hızlı ön eleme için kullanılır.
     *
     * @return string[]
     */
    function indirimRozetliTurler(): array
    {
        $kodlar = [];

        foreach (indirimTanimlari() as $t) {
            foreach ($t['turler'] as $kod) {
                $kodlar[$kod] = true;
            }
        }

        return array_keys($kodlar);
    }
}

if (! function_exists('mukellefIndirimleri')) {
    /**
     * Mükellefte açık olan indirimleri döndürür.
     *
     * @param array       $mukellef mukellefler satırı (ind_* alanlarını içermeli)
     * @param string|null $turKodu  verilirse yalnızca o beyannamede geçerli olanlar
     *
     * @return array<int,array{anahtar:string,ikon:string,kisa:string,ad:string,sinif:string,not:string}>
     */
    function mukellefIndirimleri(array $mukellef, ?string $turKodu = null): array
    {
        $sonuc = [];

        foreach (indirimTanimlari() as $anahtar => $t) {
            // Alan hiç yoksa (migration çalıştırılmamışsa) sessizce atla —
            // eski veriyle çalışan kurulumda çizelge çökmemeli.
            if (empty($mukellef[$t['alan']])) {
                continue;
            }

            if ($turKodu !== null && ! in_array($turKodu, $t['turler'], true)) {
                continue;
            }

            $sonuc[] = [
                'anahtar' => $anahtar,
                'ikon'    => $t['ikon'],
                'kisa'    => $t['kisa'],
                'ad'      => $t['ad'],
                'sinif'   => $t['sinif'],
                'not'     => trim((string) ($mukellef[$t['not_alan']] ?? '')),
            ];
        }

        return $sonuc;
    }
}

if (! function_exists('indirimRozetleri')) {
    /**
     * İndirim rozetlerini HTML olarak üretir.
     *
     * Genç girişimci rozetiyle aynı görsel dili kullanır: "🏥 BK" gibi
     * ikon + kısa kod. Tam ad ve varsa kullanıcı notu title'da görünür.
     *
     * @param array       $mukellef mukellefler satırı
     * @param string|null $turKodu  beyanname türü kodu (null = tür süzgeci yok)
     * @param bool        $kisa     false ise açık ad yazılır (mükellef kartı)
     */
    function indirimRozetleri(array $mukellef, ?string $turKodu = null, bool $kisa = true): string
    {
        $html = '';

        foreach (mukellefIndirimleri($mukellef, $turKodu) as $i) {
            $baslik = $i['ad'];

            if ($i['not'] !== '') {
                $baslik .= ' — ' . $i['not'];
            }

            $metin = $kisa ? $i['kisa'] : $i['ad'];

            $html .= '<span class="rozet ' . $i['sinif'] . ' rozet-indirim"'
                . ' title="' . esc($baslik) . '">'
                . $i['ikon'] . ' ' . esc($metin) . '</span>';
        }

        return $html;
    }
}

if (! function_exists('secilenMusavirId')) {
    /**
     * Filtredeki müşavir seçimini TEK bir id'ye indirger.
     *
     * Neden gerekli?
     *   Kontrolcüdeki kapsamBelirle() yetki kapsamını DİZİ olarak döndürür
     *   (örn. [2], ya da erişilen tüm müşavirler [1,2,3]). Görünümlerde bu
     *   değer doğrudan `(int) $filtre['musavir_id']` biçiminde karşılaştırılıyordu.
     *   PHP'de boş olmayan bir diziyi (int)'e çevirmek HER ZAMAN 1 verir; bu
     *   yüzden hangi müşavir seçilirse seçilsin açılır listede hep ilk müşavir
     *   "selected" görünüyordu.
     *
     * Kural: tam olarak bir müşavir seçiliyse onun id'si, aksi halde null
     * ("Tümü" seçili sayılır).
     *
     * @param mixed $deger null | '' | int | string | int[]
     */
    function secilenMusavirId($deger): ?int
    {
        if (is_array($deger)) {
            return count($deger) === 1 ? (int) reset($deger) : null;
        }

        if ($deger === null || $deger === '') {
            return null;
        }

        return (int) $deger ?: null;
    }
}
}

if (! function_exists('trParaCoz')) {
    /**
     * TÜRKÇE BİÇİMLİ PARA METNİNİ SAYIYA ÇEVİRİR
     *
     * Neden ortak bir yardımcı?
     *   Kontrolcülerdeki eski paraCoz() yalnızca virgül GÖRÜRSE noktaları
     *   binlik ayırıcı sayıyordu. Bu yüzden kullanıcı "400.000" yazdığında
     *   (virgül yok) değer 400,0 TL olarak okunuyordu — gider 400.000 TL
     *   girildiğinde hesap 400 TL ile yapılıyordu. Gerçek ve ağır bir kusur.
     *
     * Kural:
     *   - Hem nokta hem virgül varsa: SONDA olan ondalık ayırıcıdır
     *       "1.234.567,89" → 1234567.89     "1,234,567.89" → 1234567.89
     *   - Yalnız virgül varsa: son virgülden sonra 1-2 hane varsa ondalık,
     *     değilse binlik
     *       "1234,5" → 1234.5              "1,234" → 1234
     *   - Yalnız nokta varsa: aynı kural
     *       "1234.56" → 1234.56            "400.000" → 400000
     *   - Birden çok ayırıcı varsa kesin binliktir: "1.234.567" → 1234567
     *
     * @param mixed $ham
     */
    function trParaCoz($ham): ?float
    {
        $s = trim(str_replace(['₺', 'TL', 'tl', ' ', "\xc2\xa0"], '', (string) $ham));

        if ($s === '') {
            return null;
        }

        $eksi = str_starts_with($s, '-');
        $s    = ltrim($s, '+-');

        // Rakam, nokta ve virgül dışındaki her şey atılır
        $s = preg_replace('/[^0-9.,]/', '', $s);

        if ($s === '' || $s === null) {
            return null;
        }

        $sonNokta  = strrpos($s, '.');
        $sonVirgul = strrpos($s, ',');

        if ($sonNokta !== false && $sonVirgul !== false) {
            // İkisi de var: sonda duran ondalık ayırıcıdır
            $ondalikKonum = max($sonNokta, $sonVirgul);
        } elseif ($sonNokta !== false || $sonVirgul !== false) {
            $konum  = $sonNokta !== false ? $sonNokta : $sonVirgul;
            $isaret = $sonNokta !== false ? '.' : ',';
            $adet   = substr_count($s, $isaret);
            $haneler = strlen($s) - $konum - 1;

            // Tek ayırıcı + ardından 1-2 hane → ondalık; aksi halde binlik
            $ondalikKonum = ($adet === 1 && $haneler >= 1 && $haneler <= 2) ? $konum : -1;
        } else {
            $ondalikKonum = -1;
        }

        if ($ondalikKonum >= 0) {
            $tam    = preg_replace('/[^0-9]/', '', substr($s, 0, $ondalikKonum));
            $kesir  = preg_replace('/[^0-9]/', '', substr($s, $ondalikKonum + 1));
            $sayi   = (float) (($tam === '' ? '0' : $tam) . '.' . ($kesir === '' ? '0' : $kesir));
        } else {
            $tam  = preg_replace('/[^0-9]/', '', $s);
            $sayi = $tam === '' ? null : (float) $tam;
        }

        if ($sayi === null) {
            return null;
        }

        return round($eksi ? -$sayi : $sayi, 2);
    }
}
