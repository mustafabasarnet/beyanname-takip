<?php

namespace App\Libraries;

use App\Models\MakbuzModel;
use App\Models\MukellefModel;

/**
 * Excel/CSV'den toplu içe aktarma — iki kip:
 *
 *   'ucret'  → Yıllık sözleşme ücretleri (tarife her yıl değiştiği için)
 *   'makbuz' → Kesilen serbest meslek makbuzları (aylık liste)
 *
 * Mükellef eşleştirmesi VKN/TCKN üzerinden yapılır; bulunamazsa satır
 * hatalı işaretlenir ve nedeni yazılır (sessizce atlanmaz).
 *
 * Dosya biçimi: CSV (; , veya sekme ayraçlı), UTF-8 veya Windows-1254.
 */
class MakbuzIceAktar
{
    /** Tek seferde işlenebilecek en fazla satır (bellek koruması) */
    public const AZAMI_SATIR = 5000;

    /** Ücret dosyası sütun eşlemeleri */
    protected array $ucretAnahtar = [
        'kimlik'   => ['vkn', 'tckn', 'vkn/tckn', 'vergikimlikno', 'tckimlikno', 'kimlikno', 'vergino'],
        'unvan'    => ['unvan', 'unvani', 'mukellef', 'mukellefunvani', 'adsoyad', 'firma'],
        'tutar'    => ['tutar', 'ucret', 'yillikucret', 'sozlesmeucreti', 'yilliktutar', 'ucrettutari'],
        'aciklama' => ['aciklama', 'not', 'notu'],
    ];

    /** Makbuz dosyası sütun eşlemeleri */
    protected array $makbuzAnahtar = [
        'kimlik'    => ['vkn', 'tckn', 'vkn/tckn', 'vergikimlikno', 'tckimlikno', 'kimlikno', 'vergino'],
        'unvan'     => ['unvan', 'unvani', 'mukellef', 'mukellefunvani', 'adsoyad', 'firma'],
        'makbuz_no' => ['makbuzno', 'belgeno', 'no', 'seriNo', 'serino', 'makbuznumarasi'],
        'tarih'     => ['tarih', 'makbuztarihi', 'belgetarihi', 'duzenlemetarihi'],
        'brut'      => ['brut', 'bruttutar', 'tutar', 'matrah', 'hizmetbedeli', 'brutucret'],
        'stopaj'    => ['stopaj', 'tevkifat', 'gelirvergisistopaji', 'stopajtutari'],
        'kdv'       => ['kdv', 'kdvtutari', 'hesaplanankdv'],
        'aciklama'  => ['aciklama', 'not', 'notu'],
    ];

    protected MukellefModel $mukModel;
    protected MakbuzModel $makbuzModel;

    public function __construct()
    {
        $this->mukModel    = new MukellefModel();
        $this->makbuzModel = new MakbuzModel();
    }

    // =================================================================
    //  ŞABLONLAR
    // =================================================================

    /** Yıllık ücret şablonu (UTF-8 BOM ile — Excel Türkçe'yi doğru açsın) */
    public function ucretSablonu(int $yil): string
    {
        $s  = "\xEF\xBB\xBF";
        $s .= "VKN/TCKN;Unvan;Yillik Ucret;Aciklama\n";
        $s .= "1112223334;ORNEK INSAAT LTD. STI.;36000,00;{$yil} sozlesmesi\n";
        $s .= "12345678901;ORNEK SAHIS;18000,00;\n";

        return $s;
    }

    /** Makbuz şablonu */
    public function makbuzSablonu(int $yil, ?int $ay = null): string
    {
        $ay  = $ay ?: (int) date('n');
        $t1  = sprintf('%02d.%02d.%04d', 5, $ay, $yil);
        $t2  = sprintf('%02d.%02d.%04d', 12, $ay, $yil);

        $s  = "\xEF\xBB\xBF";
        $s .= "VKN/TCKN;Unvan;Makbuz No;Tarih;Brut;Stopaj;KDV;Aciklama\n";
        $s .= "1112223334;ORNEK INSAAT LTD. STI.;2026000145;{$t1};3000,00;600,00;600,00;\n";
        $s .= "12345678901;ORNEK SAHIS;2026000146;{$t2};1500,00;;;stopaj ve KDV bos birakilirsa hesaplanir\n";

        return $s;
    }

    // =================================================================
    //  ÇÖZÜMLEME (önizleme)
    // =================================================================

    /**
     * Dosyayı okur, satırları çözümler ve ÖNİZLEME döndürür.
     * Veritabanına HİÇBİR ŞEY yazmaz.
     *
     * @param string $kip 'ucret' | 'makbuz'
     */
    public function cozumle(string $dosyaYolu, string $kip, int $yil, ?int $musavirId = null): array
    {
        if (! is_readable($dosyaYolu)) {
            return $this->hata('Dosya okunamadı.');
        }

        $ham = file_get_contents($dosyaYolu);

        if ($ham === false || trim($ham) === '') {
            return $this->hata('Dosya boş.');
        }

        // XLSX bir ZIP arşividir; düz metin olarak okunamaz
        if (str_starts_with($ham, "PK\x03\x04")) {
            return $this->hata(
                'Bu dosya .xlsx biçiminde. Excel\'de "Farklı Kaydet → CSV (Noktalı Virgülle Ayrılmış)" '
                . 'seçerek kaydedip tekrar deneyin.'
            );
        }

        $ham      = $this->kodlamayiDuzelt($ham);
        $satirlar = $this->satirlaraBol($ham);

        if (count($satirlar) < 2) {
            return $this->hata('Dosyada başlık satırından başka veri yok.');
        }

        $ayrac     = $this->ayraciBul($satirlar[0]);
        $basliklar = $this->hucrelereBol(array_shift($satirlar), $ayrac);
        $anahtar   = $kip === 'ucret' ? $this->ucretAnahtar : $this->makbuzAnahtar;
        $harita    = $this->basliklariEsle($basliklar, $anahtar);

        if (! isset($harita['kimlik'])) {
            return $this->hata(
                'Zorunlu sütun bulunamadı: VKN/TCKN. '
                . 'Bulunan sütunlar: ' . implode(', ', array_filter($basliklar))
            );
        }

        $zorunlu = $kip === 'ucret' ? 'tutar' : 'brut';

        if (! isset($harita[$zorunlu])) {
            return $this->hata(
                'Zorunlu sütun bulunamadı: ' . ($kip === 'ucret' ? 'Yillik Ucret' : 'Brut') . '. '
                . 'Bulunan sütunlar: ' . implode(', ', array_filter($basliklar))
            );
        }

        $kirpildi = false;

        if (count($satirlar) > self::AZAMI_SATIR) {
            $satirlar = array_slice($satirlar, 0, self::AZAMI_SATIR);
            $kirpildi = true;
        }

        $sonuc = [
            'durum'    => true,
            'kip'      => $kip,
            'yil'      => $yil,
            'satirlar' => [],
            'ozet'     => ['gecerli' => 0, 'hatali' => 0, 'mukerrer' => 0, 'toplam' => 0, 'tutar' => 0.0],
            'kirpildi' => $kirpildi,
            'mesaj'    => null,
        ];

        $kimlikHarita = $this->kimlikHaritasi($musavirId);
        $unvanHarita  = $this->unvanHaritasi($musavirId);
        $dosyaIcinde  = [];   // dosya içi mükerrer kontrolü

        foreach ($satirlar as $i => $satir) {
            if (trim($satir) === '') {
                continue;
            }

            $h = $this->hucrelereBol($satir, $ayrac);

            $sonuc['satirlar'][] = $kip === 'ucret'
                ? $this->ucretSatiri($h, $harita, $i + 2, $yil, $kimlikHarita, $unvanHarita, $dosyaIcinde)
                : $this->makbuzSatiri($h, $harita, $i + 2, $yil, $kimlikHarita, $unvanHarita, $dosyaIcinde);
        }

        foreach ($sonuc['satirlar'] as $s) {
            $sonuc['ozet']['toplam']++;

            if ($s['durum'] === 'HATALI') {
                $sonuc['ozet']['hatali']++;
            } elseif ($s['durum'] === 'MUKERRER') {
                $sonuc['ozet']['mukerrer']++;
            } else {
                $sonuc['ozet']['gecerli']++;
                $sonuc['ozet']['tutar'] += $s['veri'][$kip === 'ucret' ? 'tutar' : 'brut'] ?? 0;
            }
        }

        if ($kirpildi) {
            $sonuc['mesaj'] = 'Dosyada ' . self::AZAMI_SATIR . ' satırdan fazlası var; '
                . 'yalnızca ilk ' . self::AZAMI_SATIR . ' satır işlendi.';
        }

        return $sonuc;
    }

    /** Ücret satırını çözümler */
    protected function ucretSatiri(array $h, array $harita, int $satirNo, int $yil,
        array $kimlikHarita, array $unvanHarita, array &$dosyaIcinde): array
    {
        $al = static fn (string $k) => isset($harita[$k]) ? ($h[$harita[$k]] ?? '') : '';

        $satir = [
            'satir_no' => $satirNo,
            'durum'    => 'GECERLI',
            'uyari'    => [],
            'hata'     => null,
            'ham'      => ['kimlik' => $al('kimlik'), 'unvan' => $al('unvan'), 'tutar' => $al('tutar')],
            'veri'     => [],
        ];

        $mukellef = $this->mukellefBul($al('kimlik'), $al('unvan'), $kimlikHarita, $unvanHarita, $satir);

        if ($mukellef === null) {
            $satir['durum'] = 'HATALI';

            return $satir;
        }

        $tutar = $this->paraCoz($al('tutar'));

        if ($tutar === null) {
            $satir['durum'] = 'HATALI';
            $satir['hata']  = 'Ücret tutarı okunamadı ("' . $al('tutar') . '").';

            return $satir;
        }

        // Dosya içinde aynı mükellef iki kez varsa
        if (isset($dosyaIcinde[$mukellef['id']])) {
            $satir['durum'] = 'MUKERRER';
            $satir['hata']  = 'Bu mükellef dosyada birden çok kez var (satır '
                . $dosyaIcinde[$mukellef['id']] . ').';

            return $satir;
        }

        $dosyaIcinde[$mukellef['id']] = $satirNo;

        // Veritabanında zaten kayıt varsa üzerine yazılacağı bildirilir
        $mevcut = $this->makbuzModel->ucretAl((int) $mukellef['id'], $yil);

        if ($mevcut > 0) {
            $satir['uyari'][] = 'Mevcut ücret ' . number_format($mevcut, 2, ',', '.')
                . ' TL — üzerine yazılacak.';
        }

        $satir['veri'] = [
            'mukellef_id' => (int) $mukellef['id'],
            'unvan'       => $mukellef['unvan'],
            'yil'         => $yil,
            'tutar'       => $tutar,
            'aciklama'    => $this->kirp($al('aciklama'), 200) ?: null,
        ];

        return $satir;
    }

    /** Makbuz satırını çözümler */
    protected function makbuzSatiri(array $h, array $harita, int $satirNo, int $yil,
        array $kimlikHarita, array $unvanHarita, array &$dosyaIcinde): array
    {
        $al = static fn (string $k) => isset($harita[$k]) ? ($h[$harita[$k]] ?? '') : '';

        $satir = [
            'satir_no' => $satirNo,
            'durum'    => 'GECERLI',
            'uyari'    => [],
            'hata'     => null,
            'ham'      => [
                'kimlik' => $al('kimlik'), 'unvan' => $al('unvan'),
                'no' => $al('makbuz_no'), 'tarih' => $al('tarih'), 'brut' => $al('brut'),
            ],
            'veri'     => [],
        ];

        $mukellef = $this->mukellefBul($al('kimlik'), $al('unvan'), $kimlikHarita, $unvanHarita, $satir);

        if ($mukellef === null) {
            $satir['durum'] = 'HATALI';

            return $satir;
        }

        $brut = $this->paraCoz($al('brut'));

        if ($brut === null || $brut <= 0) {
            $satir['durum'] = 'HATALI';
            $satir['hata']  = 'Brüt tutar okunamadı ("' . $al('brut') . '").';

            return $satir;
        }

        $tarih = $this->tarihCoz($al('tarih'));

        if ($tarih === null) {
            $satir['durum'] = 'HATALI';
            $satir['hata']  = 'Makbuz tarihi okunamadı ("' . $al('tarih') . '").';

            return $satir;
        }

        // Tarih seçilen yıla ait değilse uyar — ama engelleme
        $tarihYil = (int) date('Y', strtotime($tarih));

        if ($tarihYil !== $yil) {
            $satir['uyari'][] = 'Makbuz tarihi ' . $tarihYil . ' yılına ait; '
                . $yil . ' yılı ücretine sayılacak.';
        }

        $stopaj = $this->paraCoz($al('stopaj'));
        $kdv    = $this->paraCoz($al('kdv'));
        $hesap  = $this->makbuzModel->tutarHesapla($brut, $stopaj, $kdv);

        if ($stopaj === null) {
            $satir['uyari'][] = 'Stopaj boş; %' . rtrim(rtrim(number_format(
                $this->makbuzModel->stopajOrani(), 2, ',', '.'), '0'), ',')
                . ' ile hesaplandı.';
        }

        if ($kdv === null) {
            $satir['uyari'][] = 'KDV boş; %' . rtrim(rtrim(number_format(
                $this->makbuzModel->kdvOrani(), 2, ',', '.'), '0'), ',')
                . ' ile hesaplandı.';
        }

        $makbuzNo = $this->kirp($al('makbuz_no'), 40);

        // Dosya içi mükerrer (aynı makbuz no)
        if ($makbuzNo !== '') {
            $anahtar = $mukellef['id'] . '|' . $makbuzNo;

            if (isset($dosyaIcinde[$anahtar])) {
                $satir['durum'] = 'MUKERRER';
                $satir['hata']  = 'Bu makbuz no dosyada birden çok kez var (satır '
                    . $dosyaIcinde[$anahtar] . ').';

                return $satir;
            }

            $dosyaIcinde[$anahtar] = $satirNo;
        }

        // Veritabanında zaten var mı?
        if ($this->makbuzModel->mukerrerMi((int) $mukellef['id'], $yil, $makbuzNo ?: null, $tarih, $brut)) {
            $satir['durum'] = 'MUKERRER';
            $satir['hata']  = $makbuzNo !== ''
                ? 'Bu makbuz (' . $makbuzNo . ') zaten kayıtlı.'
                : 'Aynı tarih ve tutarda makbuz zaten kayıtlı.';

            return $satir;
        }

        $satir['veri'] = [
            'mukellef_id' => (int) $mukellef['id'],
            'unvan'       => $mukellef['unvan'],
            'musavir_id'  => $mukellef['musavir_id'] ? (int) $mukellef['musavir_id'] : null,
            'yil'         => $yil,
            'ay'          => (int) date('n', strtotime($tarih)),
            'makbuz_no'   => $makbuzNo ?: null,
            'tarih'       => $tarih,
            'brut'        => $hesap['brut'],
            'stopaj'      => $hesap['stopaj'],
            'kdv'         => $hesap['kdv'],
            'net'         => $hesap['net'],
            'aciklama'    => $this->kirp($al('aciklama'), 250) ?: null,
        ];

        return $satir;
    }

    /**
     * Mükellefi VKN/TCKN ile bulur; bulunamazsa ünvandan dener.
     * Hata mesajını $satir'a yazar.
     */
    protected function mukellefBul(string $kimlik, string $unvan,
        array $kimlikHarita, array $unvanHarita, array &$satir): ?array
    {
        $kimlik = preg_replace('/\D/', '', $kimlik);

        if ($kimlik !== '' && isset($kimlikHarita[$kimlik])) {
            return $kimlikHarita[$kimlik];
        }

        // Ünvandan eşleştirme (yalnızca tek eşleşme varsa güvenli)
        $anahtar = $this->sadelestir($unvan);

        if ($anahtar !== '' && isset($unvanHarita[$anahtar])) {
            $bulunan = $unvanHarita[$anahtar];

            if ($bulunan === 'COKLU') {
                $satir['hata'] = 'Ünvan birden çok mükellefle eşleşiyor ("' . $unvan . '"); VKN/TCKN yazın.';

                return null;
            }

            $satir['uyari'][] = 'VKN eşleşmedi, ünvandan bulundu: ' . $bulunan['unvan'];

            return $bulunan;
        }

        $satir['hata'] = $kimlik === ''
            ? 'VKN/TCKN boş ve ünvan eşleşmedi.'
            : 'Mükellef bulunamadı (VKN/TCKN: ' . $kimlik . ').';

        return null;
    }

    // =================================================================
    //  AKTARMA
    // =================================================================

    /**
     * Önizlemede SEÇİLEN satırları veritabanına yazar.
     *
     * @param array $satirlar cozumle()'den gelen satırların seçilmiş alt kümesi
     */
    public function aktar(array $satirlar, string $kip, ?int $kaydedenId = null): array
    {
        $sonuc = ['eklenen' => 0, 'guncellenen' => 0, 'atlanan' => 0, 'hata' => []];

        foreach ($satirlar as $s) {
            if (($s['durum'] ?? '') !== 'GECERLI' || empty($s['veri'])) {
                $sonuc['atlanan']++;

                continue;
            }

            $v = $s['veri'];

            try {
                if ($kip === 'ucret') {
                    $vardi = $this->makbuzModel->ucretAl((int) $v['mukellef_id'], (int) $v['yil']) > 0;

                    $this->makbuzModel->ucretYaz(
                        (int) $v['mukellef_id'], (int) $v['yil'],
                        (float) $v['tutar'], $v['aciklama'] ?? null
                    );

                    $vardi ? $sonuc['guncellenen']++ : $sonuc['eklenen']++;
                } else {
                    unset($v['unvan']);
                    $v['kaydeden_id'] = $kaydedenId;

                    if ($this->makbuzModel->insert($v) === false) {
                        $sonuc['atlanan']++;
                        $sonuc['hata'][] = 'Satır ' . $s['satir_no'] . ': kaydedilemedi.';

                        continue;
                    }

                    $sonuc['eklenen']++;
                }
            } catch (\Throwable $e) {
                $sonuc['atlanan']++;
                $sonuc['hata'][] = 'Satır ' . $s['satir_no'] . ': ' . $e->getMessage();
            }
        }

        return $sonuc;
    }

    // =================================================================
    //  YARDIMCILAR
    // =================================================================

    /** VKN/TCKN → mükellef eşlemesi */
    protected function kimlikHaritasi(?int $musavirId): array
    {
        $b = $this->mukModel->builder()
            ->select('id, unvan, musavir_id, vergi_kimlik_no, tc_kimlik_no')
            ->where('deleted_at', null);

        if ($musavirId) {
            $b->where('musavir_id', $musavirId);
        }

        $out = [];

        foreach ($b->get()->getResultArray() as $m) {
            foreach ([$m['vergi_kimlik_no'], $m['tc_kimlik_no']] as $k) {
                $k = preg_replace('/\D/', '', (string) $k);

                if ($k !== '') {
                    $out[$k] = $m;
                }
            }
        }

        return $out;
    }

    /** Sadeleştirilmiş ünvan → mükellef (çakışma varsa 'COKLU') */
    protected function unvanHaritasi(?int $musavirId): array
    {
        $b = $this->mukModel->builder()
            ->select('id, unvan, musavir_id, vergi_kimlik_no, tc_kimlik_no')
            ->where('deleted_at', null);

        if ($musavirId) {
            $b->where('musavir_id', $musavirId);
        }

        $out = [];

        foreach ($b->get()->getResultArray() as $m) {
            $a = $this->sadelestir($m['unvan']);

            if ($a === '') {
                continue;
            }

            $out[$a] = isset($out[$a]) ? 'COKLU' : $m;
        }

        return $out;
    }

    /** Başlıkları anahtarlarla eşler (Türkçe karakter ve boşluk duyarsız) */
    protected function basliklariEsle(array $basliklar, array $anahtarlar): array
    {
        $harita = [];

        foreach ($basliklar as $i => $b) {
            $sade = $this->sadelestir($b);

            if ($sade === '') {
                continue;
            }

            foreach ($anahtarlar as $alan => $adaylar) {
                if (isset($harita[$alan])) {
                    continue;
                }

                foreach ($adaylar as $aday) {
                    if ($sade === $this->sadelestir($aday)) {
                        $harita[$alan] = $i;

                        break 2;
                    }
                }
            }
        }

        return $harita;
    }

    protected function tarihCoz(string $ham): ?string
    {
        $ham = trim($ham);

        if ($ham === '') {
            return null;
        }

        // Excel'in sayısal tarih seri numarası
        if (preg_match('/^\d{5}$/', $ham)) {
            return gmdate('Y-m-d', ((int) $ham - 25569) * 86400);
        }

        $ham = str_replace(['\\', ' '], ['/', ''], $ham);

        $kaliplar = [
            '/^(\d{4})-(\d{1,2})-(\d{1,2})/'   => [1, 2, 3],
            '/^(\d{1,2})\.(\d{1,2})\.(\d{4})/' => [3, 2, 1],
            '/^(\d{1,2})\/(\d{1,2})\/(\d{4})/' => [3, 2, 1],
            '/^(\d{1,2})-(\d{1,2})-(\d{4})/'   => [3, 2, 1],
        ];

        foreach ($kaliplar as $kalip => $sira) {
            if (preg_match($kalip, $ham, $m)) {
                $y = (int) $m[$sira[0]];
                $a = (int) $m[$sira[1]];
                $g = (int) $m[$sira[2]];

                return checkdate($a, $g, $y) ? sprintf('%04d-%02d-%02d', $y, $a, $g) : null;
            }
        }

        return null;
    }

    /**
     * Para metnini sayıya çevirir (negatifler reddedilir).
     *
     * Ortak trParaCoz() yardımcısına devreder — eski sürüm "400.000" gibi
     * virgülsüz binlikli girdiyi 400,00 olarak okuyordu.
     */
    protected function paraCoz(string $ham): ?float
    {
        helper('beyanname');

        $s = trParaCoz($ham);

        return $s === null || $s < 0 ? null : $s;
    }

    /** Karşılaştırma için sadeleştirir (Türkçe harf, boşluk, noktalama) */
    protected function sadelestir(string $m): string
    {
        $m = mb_strtolower(trim($m), 'UTF-8');
        $m = strtr($m, [
            'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
            'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $m) ?? '';
    }

    protected function kirp(string $m, int $uzunluk): string
    {
        return mb_substr(trim($m), 0, $uzunluk);
    }

    protected function kodlamayiDuzelt(string $ham): string
    {
        if (str_starts_with($ham, "\xEF\xBB\xBF")) {
            return substr($ham, 3);
        }

        if (! mb_check_encoding($ham, 'UTF-8')) {
            $cevrilen = @mb_convert_encoding($ham, 'UTF-8', 'Windows-1254');

            if ($cevrilen !== false) {
                return $cevrilen;
            }
        }

        return $ham;
    }

    /** Satırlara böler (tırnak içindeki satır sonlarını korur) */
    protected function satirlaraBol(string $ham): array
    {
        $ham      = str_replace(["\r\n", "\r"], "\n", $ham);
        $satirlar = [];
        $tampon   = '';
        $tirnakta = false;

        foreach (str_split($ham) as $k) {
            if ($k === '"') {
                $tirnakta = ! $tirnakta;
            }

            if ($k === "\n" && ! $tirnakta) {
                $satirlar[] = $tampon;
                $tampon     = '';

                continue;
            }

            $tampon .= $k;
        }

        if (trim($tampon) !== '') {
            $satirlar[] = $tampon;
        }

        return $satirlar;
    }

    protected function ayraciBul(string $baslikSatiri): string
    {
        $adaylar = [
            ';'  => substr_count($baslikSatiri, ';'),
            ','  => substr_count($baslikSatiri, ','),
            "\t" => substr_count($baslikSatiri, "\t"),
        ];

        arsort($adaylar);
        $ilk = array_key_first($adaylar);

        return $adaylar[$ilk] > 0 ? $ilk : ';';
    }

    protected function hucrelereBol(string $satir, string $ayrac): array
    {
        $hucreler = str_getcsv($satir, $ayrac, '"', '\\');

        return array_map(static fn ($h) => $h === null ? '' : trim((string) $h), $hucreler);
    }

    protected function hata(string $mesaj): array
    {
        return [
            'durum'    => false,
            'mesaj'    => $mesaj,
            'satirlar' => [],
            'ozet'     => ['gecerli' => 0, 'hatali' => 0, 'mukerrer' => 0, 'toplam' => 0, 'tutar' => 0.0],
        ];
    }
}
