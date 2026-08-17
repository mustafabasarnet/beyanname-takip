<?php

namespace App\Libraries;

use App\Models\BeyannameTuruModel;
use App\Models\MukellefModel;

/**
 * Excel/CSV'den toplu mükellef içe aktarma.
 *
 * Akış:
 *   1. cozumle()  → dosyayı satır satır okur, doğrular, ÖNİZLEME üretir (DB'ye yazmaz)
 *   2. aktar()    → önizlemede "eklenecek" işaretli satırları veritabanına yazar
 *
 * Kural: Aynı VKN/TCKN sistemde varsa satır ATLANIR (kullanıcı tercihi).
 * Dosya biçimi: CSV (; veya , ayraçlı), UTF-8 veya Windows-1254.
 */
class MukellefIceAktar
{
    /** Şablon sütunları — SIRA ÖNEMLİDİR, başlık satırı bu sırayla beklenir */
    public const SUTUNLAR = [
        'kod'                => 'Kod',
        'unvan'              => 'Ünvan',
        'mukellef_tipi'      => 'Tip',
        'vergi_kimlik_no'    => 'VKN',
        'tc_kimlik_no'       => 'TCKN',
        'vergi_dairesi'      => 'Vergi Dairesi',
        'defter_tipi'        => 'Defter Tipi',
        'ise_baslama_tarihi' => 'İşe Başlama',
        'takip_baslangic'    => 'Takip Başlangıcı',
        'terk_tarihi'        => 'Terk Tarihi',
        'beyannameler'       => 'Beyannameler',
        'genc_girisimci'     => 'Genç Girişimci',
        'gg_baslangic_yili'  => 'GG Başlangıç Yılı',
        'muhasebe_ucreti'    => 'Muhasebe Ücreti',
        'telefon'            => 'Telefon',
        'eposta'             => 'E-posta',
        'yetkili_kisi'       => 'Yetkili Kişi',
        'faaliyet_konusu'    => 'Faaliyet Konusu',
        'nace_kodu'          => 'NACE Kodu',
        'sgk_isyeri_sicil'   => 'SGK Sicil',
        'adres'              => 'Adres',
        'notlar'             => 'Notlar',
    ];

    /** Zorunlu sütunlar */
    public const ZORUNLU = ['unvan', 'ise_baslama_tarihi'];

    /** Tek dosyada işlenebilecek en fazla veri satırı */
    public const AZAMI_SATIR = 2000;

    /** Defter tipi eşlemesi (kullanıcı ne yazarsa yazsın) */
    public const DEFTER_ESLEME = [
        'isletme'        => 'isletme',
        'işletme'        => 'isletme',
        'isletme defteri'=> 'isletme',
        'bilanco'        => 'bilanco',
        'bilanço'        => 'bilanco',
        'serbest meslek' => 'serbest_meslek',
        'serbest_meslek' => 'serbest_meslek',
        'sm'             => 'serbest_meslek',
        'basit usul'     => 'basit_usul',
        'basit_usul'     => 'basit_usul',
        'diger'          => 'diger',
        'diğer'          => 'diger',
    ];

    /** Mükellef tipi eşlemesi */
    public const TIP_ESLEME = [
        'gercek'        => 'gercek',
        'gerçek'        => 'gercek',
        'gercek kisi'   => 'gercek',
        'gerçek kişi'   => 'gercek',
        'sahis'         => 'gercek',
        'şahıs'         => 'gercek',
        'tuzel'         => 'tuzel',
        'tüzel'         => 'tuzel',
        'tuzel kisi'    => 'tuzel',
        'tüzel kişi'    => 'tuzel',
        'sirket'        => 'tuzel',
        'şirket'        => 'tuzel',
    ];

    protected MukellefModel $mukellefModel;

    /** @var array<string,int> kod => tür id */
    protected array $turKodlari = [];

    /** @var array<string,int> kısa ad (küçük harf) => tür id */
    protected array $turKisaAdlari = [];

    public function __construct()
    {
        $this->mukellefModel = new MukellefModel();

        foreach ((new BeyannameTuruModel())->aktifler() as $t) {
            $this->turKodlari[strtoupper((string) $t['kod'])]              = (int) $t['id'];
            $this->turKisaAdlari[$this->sadelestir((string) $t['kisa_ad'])] = (int) $t['id'];
        }
    }

    // =================================================================
    //  1) ŞABLON ÜRETİMİ
    // =================================================================

    /**
     * İndirilebilir örnek CSV şablonu üretir.
     *
     * @param bool $ornekli Örnek satırlar eklensin mi
     */
    public function sablon(bool $ornekli = true): string
    {
        // UTF-8 BOM: Excel Türkçe karakterleri doğru açsın
        $csv = "\xEF\xBB\xBF";
        $csv .= implode(';', array_values(self::SUTUNLAR)) . "\n";

        if (! $ornekli) {
            return $csv;
        }

        $ornekler = [
            [
                'M001', 'ÖRNEK İNŞAAT SANAYİ LTD. ŞTİ.', 'Tüzel', '1234567890', '',
                'Nevşehir', 'Bilanço', '01.01.2020', '', '',
                'KDV1_A,MUHSGK_A,KURUMLAR,KURUM_GECICI', 'Hayır', '',
                '5.000,00', '0384 000 00 00', 'ornek@firma.com', 'Ahmet Yılmaz',
                'İnşaat', '4120', '1234567890123', 'Merkez / Nevşehir', '',
            ],
            [
                'M002', 'AYŞE DEMİR', 'Gerçek', '', '12345678901',
                'Ürgüp', 'İşletme', '15.03.2024', '01.01.2026', '',
                'KDV1_A,MUHSGK_A,YILLIK_GV,GELIR_GECICI', 'Evet', '2024',
                '2.500,00', '', '', '',
                'Kuaför', '9602', '', '', 'Genç girişimci teşviki var',
            ],
            [
                'M003', 'MEHMET KAYA', 'Gerçek', '', '98765432109',
                'Avanos', 'Serbest Meslek', '01.06.2018', '', '31.12.2026',
                'KDV1_A,MUHSGK_A,YILLIK_GV,GELIR_GECICI', 'Hayır', '',
                '', '', '', '',
                'Mali müşavirlik', '6920', '', '', 'Yıl sonunda terk edecek',
            ],
        ];

        foreach ($ornekler as $satir) {
            $csv .= implode(';', array_map(
                static fn ($h) => str_replace(';', ',', (string) $h),
                $satir
            )) . "\n";
        }

        return $csv;
    }

    // =================================================================
    //  2) DOSYA ÇÖZÜMLEME + ÖNİZLEME
    // =================================================================

    /**
     * Yüklenen dosyayı çözümler ve satır satır önizleme üretir.
     * Veritabanına HİÇBİR ŞEY YAZMAZ.
     *
     * @return array{
     *   basarili:bool, mesaj:string, satirlar:array, ozet:array, basliklar:array
     * }
     */
    public function cozumle(string $dosyaYolu, int $musavirId): array
    {
        $ham = @file_get_contents($dosyaYolu);

        if ($ham === false || $ham === '') {
            return $this->hata('Dosya okunamadı veya boş.');
        }

        $ham = $this->kodlamayiDuzelt($ham);
        $satirlarHam = $this->satirlaraBol($ham);

        if (count($satirlarHam) < 2) {
            return $this->hata('Dosyada başlık satırından sonra veri bulunamadı.');
        }

        if (count($satirlarHam) - 1 > self::AZAMI_SATIR) {
            return $this->hata(
                'Dosyada ' . (count($satirlarHam) - 1) . ' satır var. Tek seferde en fazla '
                . self::AZAMI_SATIR . ' mükellef aktarılabilir. '
                . 'Lütfen dosyayı parçalara bölüp sırayla yükleyin.'
            );
        }

        $ayrac    = $this->ayraciBul($satirlarHam[0]);
        $basliklar = $this->hucrelereBol($satirlarHam[0], $ayrac);

        // Sabit şablon: başlıkları sırayla eşle, sütun sayısı yetersizse uyar
        $anahtarlar = array_keys(self::SUTUNLAR);
        $eslesme    = $this->basliklariEsle($basliklar, $anahtarlar);

        if ($eslesme['eksik'] !== []) {
            return $this->hata(
                'Şablon sütunları eşleşmedi. Eksik/hatalı zorunlu sütun: '
                . implode(', ', $eslesme['eksik'])
                . '. Lütfen "Örnek Şablonu İndir" ile indirdiğiniz dosyayı kullanın.'
            );
        }

        $harita = $eslesme['harita'];   // alan => sütun indeksi

        // Mevcut kayıtlar (VKN/TCKN) — çakışma tespiti için
        $mevcutKimlikler = $this->mevcutKimlikler();

        $satirlar = [];
        $dosyadaGorulen = [];   // dosya içi mükerrer kontrolü
        $sayac = ['eklenecek' => 0, 'atlanacak' => 0, 'hatali' => 0];

        for ($i = 1; $i < count($satirlarHam); $i++) {
            $hamSatir = trim($satirlarHam[$i]);

            if ($hamSatir === '' || rtrim($hamSatir, $ayrac) === '') {
                continue;   // tamamen boş satır
            }

            $hucreler = $this->hucrelereBol($satirlarHam[$i], $ayrac);
            $satir    = $this->satiriIsle($hucreler, $harita, $musavirId, $i + 1);

            // Dosya içi mükerrer
            $kimlik = ($satir['veri']['vergi_kimlik_no'] ?? '') ?: ($satir['veri']['tc_kimlik_no'] ?? '');

            if ($satir['durum'] !== 'hatali' && $kimlik !== null && $kimlik !== '') {
                if (isset($dosyadaGorulen[$kimlik])) {
                    $satir['durum']   = 'atlanacak';
                    $satir['neden'][] = 'Bu VKN/TCKN dosyanın ' . $dosyadaGorulen[$kimlik]
                        . '. satırında da var (mükerrer).';
                } elseif (isset($mevcutKimlikler[$kimlik])) {
                    $satir['durum']   = 'atlanacak';
                    $satir['neden'][] = 'Sistemde zaten kayıtlı: '
                        . $mevcutKimlikler[$kimlik];
                } else {
                    $dosyadaGorulen[$kimlik] = $i + 1;
                }
            }

            $sayac[$satir['durum']]++;
            $satirlar[] = $satir;
        }

        if ($satirlar === []) {
            return $this->hata('Dosyada işlenebilir veri satırı bulunamadı.');
        }

        return [
            'basarili'  => true,
            'mesaj'     => count($satirlar) . ' satır okundu.',
            'satirlar'  => $satirlar,
            'ozet'      => $sayac,
            'basliklar' => $basliklar,
        ];
    }

    // =================================================================
    //  3) VERİTABANINA AKTARMA
    // =================================================================

    /**
     * Önizlemede "eklenecek" durumundaki satırları veritabanına yazar.
     *
     * @param array $satirlar cozumle() çıktısındaki satırlar
     *
     * @return array{eklenen:int,atlanan:int,hatali:int,hatalar:array,idler:array}
     */
    public function aktar(array $satirlar): array
    {
        $eklenen = 0;
        $atlanan = 0;
        $hatali  = 0;
        $hatalar = [];
        $idler   = [];

        foreach ($satirlar as $s) {
            if (($s['durum'] ?? '') !== 'eklenecek') {
                if (($s['durum'] ?? '') === 'hatali') {
                    $hatali++;
                } else {
                    $atlanan++;
                }

                continue;
            }

            $veri = $s['veri'];
            $turIdler = $veri['__turler'] ?? [];
            unset($veri['__turler']);

            // Boş alanları temizle (NULL kalsın, model varsayılanı çalışsın)
            $veri = array_filter(
                $veri,
                static fn ($v) => $v !== null && $v !== ''
            );

            if (! $this->mukellefModel->insert($veri)) {
                $hatali++;
                $hatalar[] = [
                    'satir' => $s['satir_no'],
                    'unvan' => $s['veri']['unvan'] ?? '',
                    'mesaj' => implode(' ', $this->mukellefModel->errors()),
                ];

                continue;
            }

            $id = (int) $this->mukellefModel->getInsertID();

            if ($turIdler !== []) {
                $this->mukellefModel->turleriKaydet($id, $turIdler);
            }

            $idler[] = $id;
            $eklenen++;
        }

        return [
            'eklenen' => $eklenen,
            'atlanan' => $atlanan,
            'hatali'  => $hatali,
            'hatalar' => $hatalar,
            'idler'   => $idler,
        ];
    }

    // =================================================================
    //  YARDIMCILAR
    // =================================================================

    /** Tek satırı doğrulayıp normalize eder */
    protected function satiriIsle(array $h, array $harita, int $musavirId, int $satirNo): array
    {
        $al = static fn (string $alan) => isset($harita[$alan], $h[$harita[$alan]])
            ? trim((string) $h[$harita[$alan]])
            : '';

        $neden = [];
        $uyari = [];

        $unvan = $al('unvan');

        // --- Zorunlu: ünvan ---
        // Ünvan yoksa satır işlenemez; yine de tam veri iskeleti döndürülür ki
        // önizleme ekranı ve mükerrer kontrolü eksik anahtar hatası vermesin.
        if ($unvan === '') {
            return [
                'satir_no' => $satirNo,
                'durum'    => 'hatali',
                'neden'    => ['Ünvan boş olamaz.'],
                'uyari'    => [],
                'veri'     => [
                    'musavir_id'       => $musavirId,
                    'unvan'            => '',
                    'kod'              => $al('kod'),
                    'vergi_kimlik_no'  => null,
                    'tc_kimlik_no'     => null,
                    'mukellef_tipi'    => null,
                    'defter_tipi'      => null,
                    'ise_baslama_tarihi' => null,
                    'terk_tarihi'      => null,
                    'takip_baslangic'  => null,
                    'genc_girisimci'   => 0,
                    '__turler'         => [],
                ],
                'turler'   => [],
            ];
        }

        // --- Tarihler ---
        $iseBaslama = $this->tarihCoz($al('ise_baslama_tarihi'));

        if ($iseBaslama === null) {
            $neden[] = 'İşe başlama tarihi geçersiz veya boş ("' . $al('ise_baslama_tarihi') . '").';
        }

        $takipBas = $al('takip_baslangic') !== '' ? $this->tarihCoz($al('takip_baslangic')) : null;

        if ($al('takip_baslangic') !== '' && $takipBas === null) {
            $uyari[] = 'Takip başlangıcı okunamadı, boş bırakıldı.';
        }

        $terk = $al('terk_tarihi') !== '' ? $this->tarihCoz($al('terk_tarihi')) : null;

        if ($al('terk_tarihi') !== '' && $terk === null) {
            $uyari[] = 'Terk tarihi okunamadı, boş bırakıldı.';
        }

        if ($iseBaslama !== null && $terk !== null && $terk < $iseBaslama) {
            $neden[] = 'Terk tarihi işe başlama tarihinden önce olamaz.';
        }

        // --- Kimlik numaraları ---
        $vkn  = preg_replace('/\D/', '', $al('vergi_kimlik_no'));
        $tckn = preg_replace('/\D/', '', $al('tc_kimlik_no'));

        // Yanlış sütuna yazılmışsa düzelt (11 hane VKN sütununda vb.)
        if (strlen($vkn) === 11 && $tckn === '') {
            $tckn = $vkn;
            $vkn  = '';
            $uyari[] = '11 haneli numara VKN sütunundaydı, TCKN olarak alındı.';
        } elseif (strlen($tckn) === 10 && $vkn === '') {
            $vkn  = $tckn;
            $tckn = '';
            $uyari[] = '10 haneli numara TCKN sütunundaydı, VKN olarak alındı.';
        }

        if ($vkn !== '' && strlen($vkn) !== 10) {
            $neden[] = 'VKN 10 haneli olmalı (girilen: ' . strlen($vkn) . ' hane).';
        }

        if ($tckn !== '' && strlen($tckn) !== 11) {
            $neden[] = 'TCKN 11 haneli olmalı (girilen: ' . strlen($tckn) . ' hane).';
        }

        if ($vkn === '' && $tckn === '') {
            $uyari[] = 'VKN/TCKN boş — mükerrer kontrolü yapılamaz.';
        }

        // --- Mükellef tipi ---
        $tipHam = $this->sadelestir($al('mukellef_tipi'));
        $tip    = self::TIP_ESLEME[$tipHam] ?? null;

        if ($tip === null) {
            // Tahmin: TCKN varsa gerçek, VKN varsa tüzel
            $tip = $tckn !== '' ? 'gercek' : ($vkn !== '' ? 'tuzel' : 'gercek');

            if ($al('mukellef_tipi') !== '') {
                $uyari[] = 'Tip "' . $al('mukellef_tipi') . '" tanınmadı, "'
                    . ($tip === 'gercek' ? 'Gerçek' : 'Tüzel') . '" varsayıldı.';
            } else {
                $uyari[] = 'Tip boş, kimlik numarasına göre "'
                    . ($tip === 'gercek' ? 'Gerçek' : 'Tüzel') . '" varsayıldı.';
            }
        }

        // --- Defter tipi ---
        $defterHam = $this->sadelestir($al('defter_tipi'));
        $defter    = self::DEFTER_ESLEME[$defterHam] ?? null;

        if ($defter === null) {
            $defter = $tip === 'tuzel' ? 'bilanco' : 'isletme';

            if ($al('defter_tipi') !== '') {
                $uyari[] = 'Defter tipi "' . $al('defter_tipi') . '" tanınmadı, "'
                    . defterTipiKisa($defter) . '" varsayıldı.';
            }
        }

        // --- Beyanname türleri ---
        [$turIdler, $turUyari] = $this->turleriCoz($al('beyannameler'));
        $uyari = array_merge($uyari, $turUyari);

        if ($turIdler === []) {
            $uyari[] = 'Beyanname türü belirtilmedi — mükellef eklenir ama dönem üretilmez.';
        }

        // --- Genç girişimci ---
        // GVK mükerrer 20 istisnası yalnızca GERÇEK KİŞİ mükelleflerde
        // uygulanır. Dosyada tüzel kişi için "Evet" yazsa bile yok sayılır;
        // kullanıcıya neden yok sayıldığı uyarı olarak bildirilir.
        $ggHam = $this->sadelestir($al('genc_girisimci'));
        $gg    = in_array($ggHam, ['evet', 'e', 'var', 'x', '1', 'true', 'yes'], true) ? 1 : 0;
        $ggYil = null;

        if ($gg === 1 && $tip === 'tuzel') {
            $gg      = 0;
            $uyari[] = 'Genç girişimci istisnası tüzel kişilere uygulanmaz, yok sayıldı.';
        }

        if ($gg === 1) {
            $ggYil = (int) preg_replace('/\D/', '', $al('gg_baslangic_yili'));

            if ($ggYil < 2000 || $ggYil > (int) date('Y') + 1) {
                $ggYil = $iseBaslama !== null ? (int) substr($iseBaslama, 0, 4) : (int) date('Y');
                $uyari[] = 'Genç girişimci başlangıç yılı okunamadı, ' . $ggYil . ' varsayıldı.';
            }
        }

        // --- Muhasebe ücreti ---
        $ucret = $this->paraCoz($al('muhasebe_ucreti'));

        if ($al('muhasebe_ucreti') !== '' && $ucret === null) {
            $uyari[] = 'Muhasebe ücreti okunamadı ("' . $al('muhasebe_ucreti') . '"), boş bırakıldı.';
        }

        // --- E-posta ---
        $eposta = $al('eposta');

        if ($eposta !== '' && ! filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
            $uyari[] = 'E-posta geçersiz ("' . $eposta . '"), boş bırakıldı.';
            $eposta  = '';
        }

        $veri = [
            'musavir_id'         => $musavirId,
            'kod'                => $this->kirp($al('kod'), 30),
            'unvan'              => $this->kirp($unvan, 250),
            'mukellef_tipi'      => $tip,
            'vergi_kimlik_no'    => $vkn ?: null,
            'tc_kimlik_no'       => $tckn ?: null,
            'vergi_dairesi'      => $this->kirp($al('vergi_dairesi'), 150),
            'defter_tipi'        => $defter,
            'ise_baslama_tarihi' => $iseBaslama,
            'takip_baslangic'    => $takipBas,
            'terk_tarihi'        => $terk,
            'genc_girisimci'     => $gg,
            'gg_baslangic_yili'  => $ggYil,
            'muhasebe_ucreti'    => $ucret,
            'telefon'            => $this->kirp($al('telefon'), 30),
            'eposta'             => $eposta ?: null,
            'yetkili_kisi'       => $this->kirp($al('yetkili_kisi'), 150),
            'faaliyet_konusu'    => $this->kirp($al('faaliyet_konusu'), 300),
            'nace_kodu'          => $this->kirp($al('nace_kodu'), 20),
            'sgk_isyeri_sicil'   => $this->kirp($al('sgk_isyeri_sicil'), 50),
            'adres'              => $this->kirp($al('adres'), 500),
            'notlar'             => $al('notlar'),
            'aktif'              => 1,
            '__turler'           => $turIdler,
        ];

        return [
            'satir_no' => $satirNo,
            'durum'    => $neden === [] ? 'eklenecek' : 'hatali',
            'neden'    => $neden,
            'uyari'    => $uyari,
            'veri'     => $veri,
            'turler'   => $turIdler,
        ];
    }

    /**
     * "KDV1_A, MUHSGK_A" veya "KDV1 (Ay), Kurumlar" gibi metni tür ID'lerine çevirir.
     *
     * @return array{0:array<int>,1:array<string>} [tür id'leri, uyarılar]
     */
    protected function turleriCoz(string $metin): array
    {
        if (trim($metin) === '') {
            return [[], []];
        }

        $idler  = [];
        $uyari  = [];
        $parcalar = preg_split('/[,;|\/]+/', $metin) ?: [];

        foreach ($parcalar as $p) {
            $p = trim($p);

            if ($p === '') {
                continue;
            }

            $kod = strtoupper(str_replace([' ', '-'], ['_', '_'], $p));

            if (isset($this->turKodlari[$kod])) {
                $idler[] = $this->turKodlari[$kod];

                continue;
            }

            // Kısa ada göre dene ("KDV1 (Ay)")
            $sade = $this->sadelestir($p);

            if (isset($this->turKisaAdlari[$sade])) {
                $idler[] = $this->turKisaAdlari[$sade];

                continue;
            }

            $uyari[] = 'Beyanname türü tanınmadı: "' . $p . '" (atlandı).';
        }

        return [array_values(array_unique($idler)), $uyari];
    }

    /** Başlık satırını şablon alanlarıyla eşler */
    protected function basliklariEsle(array $basliklar, array $anahtarlar): array
    {
        $harita = [];
        $eksik  = [];

        // Sabit şablon: konum bazlı eşleme; ama başlık metni de doğrulanır
        foreach ($anahtarlar as $sira => $alan) {
            $beklenen = $this->sadelestir(self::SUTUNLAR[$alan]);
            $bulunan  = isset($basliklar[$sira]) ? $this->sadelestir($basliklar[$sira]) : '';

            if ($bulunan === $beklenen) {
                $harita[$alan] = $sira;

                continue;
            }

            // Konum kaymışsa başlığı tüm sütunlarda ara (kullanıcı sütun eklemiş olabilir)
            $indeks = null;

            foreach ($basliklar as $i => $b) {
                if ($this->sadelestir($b) === $beklenen) {
                    $indeks = $i;
                    break;
                }
            }

            if ($indeks !== null) {
                $harita[$alan] = $indeks;
            } elseif (in_array($alan, self::ZORUNLU, true)) {
                $eksik[] = self::SUTUNLAR[$alan];
            }
        }

        return ['harita' => $harita, 'eksik' => $eksik];
    }

    /** Sistemdeki VKN/TCKN → ünvan haritası */
    protected function mevcutKimlikler(): array
    {
        $rows = $this->mukellefModel
            ->select('unvan, vergi_kimlik_no, tc_kimlik_no')
            ->withDeleted(false)
            ->findAll();

        $harita = [];

        foreach ($rows as $r) {
            if (! empty($r['vergi_kimlik_no'])) {
                $harita[$r['vergi_kimlik_no']] = $r['unvan'];
            }

            if (! empty($r['tc_kimlik_no'])) {
                $harita[$r['tc_kimlik_no']] = $r['unvan'];
            }
        }

        return $harita;
    }

    /**
     * "01.03.2026", "2026-03-01", "1/3/2026", Excel seri numarası → Y-m-d
     */
    protected function tarihCoz(string $ham): ?string
    {
        $ham = trim($ham);

        if ($ham === '') {
            return null;
        }

        // Excel'in sayısal tarih seri numarası (1900 tabanlı)
        if (preg_match('/^\d{5}$/', $ham)) {
            $gun = (int) $ham;

            // 1900 artık yıl hatası düzeltmesi
            $zaman = ($gun - 25569) * 86400;

            return gmdate('Y-m-d', $zaman);
        }

        $ham = str_replace(['\\', ' '], ['/', ''], $ham);

        $kaliplar = [
            '/^(\d{4})-(\d{1,2})-(\d{1,2})/'   => [1, 2, 3],   // 2026-03-01
            '/^(\d{1,2})\.(\d{1,2})\.(\d{4})/' => [3, 2, 1],   // 01.03.2026
            '/^(\d{1,2})\/(\d{1,2})\/(\d{4})/' => [3, 2, 1],   // 01/03/2026
            '/^(\d{1,2})-(\d{1,2})-(\d{4})/'   => [3, 2, 1],   // 01-03-2026
        ];

        foreach ($kaliplar as $kalip => $sira) {
            if (preg_match($kalip, $ham, $m)) {
                $y = (int) $m[$sira[0]];
                $a = (int) $m[$sira[1]];
                $g = (int) $m[$sira[2]];

                if (checkdate($a, $g, $y)) {
                    return sprintf('%04d-%02d-%02d', $y, $a, $g);
                }

                return null;
            }
        }

        return null;
    }

    /** "5.000,00" / "5000.00" / "5000" → float */
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

    /** Karşılaştırma için sadeleştirme: küçük harf, Türkçe karakter, boşluk/noktalama yok */
    protected function sadelestir(string $m): string
    {
        $m = trim($m);
        $m = str_replace(
            ['İ', 'I', 'ı', 'Ş', 'ş', 'Ğ', 'ğ', 'Ü', 'ü', 'Ö', 'ö', 'Ç', 'ç'],
            ['i', 'i', 'i', 's', 's', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c'],
            $m
        );
        $m = mb_strtolower($m, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $m) ?? '');
    }

    protected function kirp(string $m, int $uzunluk): string
    {
        return mb_substr(trim($m), 0, $uzunluk, 'UTF-8');
    }

    /** BOM temizler, gerekirse Windows-1254'ten UTF-8'e çevirir */
    protected function kodlamayiDuzelt(string $ham): string
    {
        // UTF-8 BOM
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

    /** Satırlara böler (tırnak içindeki yeni satırları korur) */
    protected function satirlaraBol(string $ham): array
    {
        $ham = str_replace(["\r\n", "\r"], "\n", $ham);

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

    /** Ayraç tespiti: ; veya , veya sekme */
    protected function ayraciBul(string $baslikSatiri): string
    {
        $adaylar = [';' => substr_count($baslikSatiri, ';'),
            ',' => substr_count($baslikSatiri, ','),
            "\t" => substr_count($baslikSatiri, "\t")];

        arsort($adaylar);

        $ilk = array_key_first($adaylar);

        return $adaylar[$ilk] > 0 ? $ilk : ';';
    }

    /** Bir CSV satırını hücrelere böler (tırnak destekli) */
    protected function hucrelereBol(string $satir, string $ayrac): array
    {
        $hucreler = str_getcsv($satir, $ayrac, '"', '\\');

        return array_map(
            static fn ($h) => $h === null ? '' : trim((string) $h),
            $hucreler
        );
    }

    protected function hata(string $mesaj): array
    {
        return [
            'basarili'  => false,
            'mesaj'     => $mesaj,
            'satirlar'  => [],
            'ozet'      => ['eklenecek' => 0, 'atlanacak' => 0, 'hatali' => 0],
            'basliklar' => [],
        ];
    }
}
