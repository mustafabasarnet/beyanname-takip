<?php

namespace App\Controllers;

use App\Models\KullaniciModel;
use App\Models\MusavirModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected $request;

    /** @var list<string> */
    protected $helpers = ['url', 'form', 'text', 'beyanname'];

    protected $session;

    /** Giriş yapan kullanıcı bilgileri */
    protected array $aktifKullanici = [];

    /** Erişilebilir müşavir ID önbelleği (istek başına) */
    protected ?array $erisimOnbellek = null;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->session = service('session');

        $this->aktifKullanici = [
            'id'         => $this->session->get('kullanici_id'),
            'ad_soyad'   => $this->session->get('ad_soyad'),
            'rol'        => $this->session->get('rol'),
            'musavir_id' => $this->session->get('musavir_id'),
        ];
    }

    /**
     * Ödeme listesi ve muhasebe ücreti gibi BÜRO düzeyi mali bilgileri
     * görebilir mi? Personel göremez.
     */
    protected function maliYetkiVarMi(): bool
    {
        return in_array($this->aktifKullanici['rol'] ?? '', ['admin', 'musavir'], true);
    }

    /**
     * Beyanname tahakkuk tutarı girebilir mi?
     *
     * Personel de girebilir — tahakkuk, beyannamenin bir parçasıdır ve
     * beyannameyi hazırlayan kişi tutarı bilir. Ödeme listesi ve muhasebe
     * ücreti ise yine personele kapalıdır (bkz. maliYetkiVarMi).
     */
    protected function tahakkukYetkisiVarMi(): bool
    {
        return in_array($this->aktifKullanici['rol'] ?? '', ['admin', 'musavir', 'personel'], true);
    }

    /** Giriş yapan kullanıcı yönetici mi? */
    protected function adminMi(): bool
    {
        return ($this->aktifKullanici['rol'] ?? '') === 'admin';
    }

    /**
     * Kullanıcının erişebildiği mali müşavir ID listesi.
     *
     * @return int[] Admin için boş dizi = "kısıtlama yok"
     */
    protected function erisilenMusavirler(): array
    {
        if ($this->adminMi()) {
            return [];
        }

        if ($this->erisimOnbellek !== null) {
            return $this->erisimOnbellek;
        }

        $kid = (int) ($this->aktifKullanici['id'] ?? 0);

        return $this->erisimOnbellek = $kid > 0
            ? (new KullaniciModel())->erisilebilirMusavirler($kid)
            : [];
    }

    /**
     * Sorgu filtresi olarak kullanılacak müşavir ID listesi.
     * Admin -> null (tümü). Diğerleri -> erişilen ID'ler.
     *
     * Not: Yetkisi hiç tanımlanmamış kullanıcı hiçbir şey görmesin diye
     * [0] döndürülür (eşleşmeyen ID).
     *
     * @return int[]|null
     */
    protected function musavirFiltresi(): ?array
    {
        if ($this->adminMi()) {
            return null;
        }

        $idler = $this->erisilenMusavirler();

        return $idler === [] ? [0] : $idler;
    }

    /** Kullanıcının bu mükellefe erişim yetkisi var mı? */
    protected function mukellefeErisebilirMi(array $mukellef): bool
    {
        if ($this->adminMi()) {
            return true;
        }

        return in_array((int) $mukellef['musavir_id'], $this->erisilenMusavirler(), true);
    }

    /** Kullanıcı bu müşavir adına işlem yapabilir mi? */
    protected function musavireErisebilirMi(int $musavirId): bool
    {
        if ($this->adminMi()) {
            return true;
        }

        return in_array($musavirId, $this->erisilenMusavirler(), true);
    }

    /**
     * Form dropdown'ları için: kullanıcının seçebileceği müşavirler.
     *
     * @return array<int,string> [id => ad]
     */
    protected function secilebilirMusavirler(): array
    {
        $tumu = (new MusavirModel())->seceneklar();

        if ($this->adminMi()) {
            return $tumu;
        }

        $izin = $this->erisilenMusavirler();

        return array_intersect_key($tumu, array_flip($izin));
    }

    /**
     * Filtre ekranlarından gelen müşavir seçimini yetkiyle harmanlar.
     *
     * @param mixed $secilen GET/POST'tan gelen musavir_id
     *
     * @return int[]|null null = kısıtlama yok (admin, seçim yapmamış)
     */
    protected function kapsamBelirle($secilen): ?array
    {
        $izin = $this->musavirFiltresi();

        if ($secilen === null || $secilen === '') {
            return $izin;
        }

        $secilen = (int) $secilen;

        if ($this->musavireErisebilirMi($secilen)) {
            return [$secilen];
        }

        return $izin;
    }

    /**
     * Kullanıcının varsayılan müşaviri (form ön seçimi için).
     * Tek müşavire erişiyorsa onu, çoklu erişimde birincilini döner.
     */
    protected function varsayilanMusavir(): ?int
    {
        $izin = $this->erisilenMusavirler();

        if (count($izin) === 1) {
            return $izin[0];
        }

        $birincil = $this->aktifKullanici['musavir_id'] ?? null;

        if ($birincil && in_array((int) $birincil, $izin, true)) {
            return (int) $birincil;
        }

        return null;
    }

    protected function jsonBasarili(string $mesaj, array $ek = []): ResponseInterface
    {
        return $this->response->setJSON(array_merge(['durum' => true, 'mesaj' => $mesaj], $ek));
    }

    protected function jsonHata(string $mesaj, int $kod = 400, array $ek = []): ResponseInterface
    {
        return $this->response->setStatusCode($kod)
            ->setJSON(array_merge(['durum' => false, 'mesaj' => $mesaj], $ek));
    }

    /** Sayfa görünümü + ortak veriler */
    protected function goster(string $view, array $veri = [], string $baslik = ''): string
    {
        $veri['aktifKullanici'] = $this->aktifKullanici;
        $veri['sayfaBasligi']   = $baslik ?: ($veri['sayfaBasligi'] ?? 'Beyanname Takip');
        $veri['ajandaRozet']    = $veri['ajandaRozet'] ?? $this->ajandaRozet();

        return view($view, $veri);
    }

    /**
     * Menüdeki ajanda rozeti: gecikmiş + bugünkü iş sayısı.
     *
     * Her sayfada çalıştığı için istek başına önbelleklenir. Ajanda tablosu
     * yoksa (migration çalıştırılmamışsa) 0 döner — eski kurulum çökmesin.
     */
    protected function ajandaRozet(): int
    {
        static $onbellek = null;

        if ($onbellek !== null) {
            return $onbellek;
        }

        if (empty($this->aktifKullanici['id'])) {
            return $onbellek = 0;
        }

        try {
            $db = \Config\Database::connect();

            if (! $db->tableExists('ajanda')) {
                return $onbellek = 0;
            }

            $m = new \App\Models\AjandaModel();
            $s = $m->sayaclar($this->aktifKullanici, $this->erisilenMusavirler(), 0);

            return $onbellek = (int) $s['gecikmis'] + (int) $s['bugun'];
        } catch (\Throwable $e) {
            return $onbellek = 0;
        }
    }

    // =================================================================
    //  ÇOKLU SEÇİM FİLTRESİ (durum, tür, sorumlu…)
    //
    //  _coklu_secim.php bileşeni onay kutuları sunar ve formu ad[] dizisi
    //  olarak gönderir. Burada kabul edilen biçimler:
    //    ?durum=HAZIR            → 'HAZIR'            (tek, eski bağlantılar)
    //    ?durum[]=A&durum[]=B    → [A, B]             (form gönderimi)
    //    ?durum=A,B              → [A, B]             (kısa bağlantı / dışa aktar)
    //  Tek değer kaldıysa DİZİYE ÇEVRİLMEZ; eski davranış korunur.
    // =================================================================

    /**
     * Çoklu seçilebilen filtre değerini okur.
     *
     * @param array<string>|null $izinli Verilirse yalnız bu değerler kabul edilir
     *
     * @return string|array<string>|null
     */
    protected function cokluAl(string $ad, ?array $izinli = null)
    {
        $ham = $this->request->getGet($ad);

        if ($ham === null || $ham === '') {
            return null;
        }

        // "A,B" biçimini diziye çevir
        if (! is_array($ham)) {
            $ham = str_contains($ham, ',') ? explode(',', $ham) : $ham;
        }

        if (! is_array($ham)) {
            return $izinli !== null && ! in_array($ham, $izinli, true) ? null : $ham;
        }

        $temiz = [];

        foreach ($ham as $d) {
            $d = trim((string) $d);

            if ($d === '' || in_array($d, $temiz, true)) {
                continue;
            }

            if ($izinli !== null && ! in_array($d, $izinli, true)) {
                continue;
            }

            $temiz[] = $d;
        }

        if ($temiz === []) {
            return null;
        }

        return count($temiz) === 1 ? $temiz[0] : $temiz;
    }

    /**
     * Çoklu filtreyi okur ve kullanıcının son seçimini çerezden hatırlar.
     *
     * Öncelik:
     *   1) Adres çubuğunda parametre VARSA o kullanılır ve çereze yazılır
     *   2) Form gönderildiği hâlde alan boşsa ("Tümü") çerez TEMİZLENİR
     *   3) Sayfa temiz açıldıysa (hiç filtre parametresi yok) çerez okunur
     *
     * 2. madde önemli: kullanıcı "Temizle" dediğinde eski seçim geri
     * gelmemeli. Formun gönderildiğini, filtre çubuğundaki başka bir
     * parametrenin (yil) varlığından anlıyoruz.
     *
     * @param array<string>|null $izinli Verilirse yalnız bu değerler kabul edilir
     * @param string             $onEk   Çerez adı ön eki (ekranlar birbirine karışmasın)
     *
     * @return string|array<string>|null
     */
    protected function cokluHatirla(string $ad, ?array $izinli = null, string $onEk = 'bt_f_')
    {
        $deger  = $this->cokluAl($ad, $izinli);
        $cerez  = $onEk . $ad;
        $formGeldi = $this->request->getGet('yil') !== null;

        if ($deger !== null) {
            $this->filtreCerezYaz($cerez, is_array($deger) ? implode(',', $deger) : (string) $deger);

            return $deger;
        }

        if ($formGeldi) {
            $this->filtreCerezYaz($cerez, '');   // "Tümü" seçildi -> hatırlamayı bırak

            return null;
        }

        $saklanan = (string) ($this->request->getCookie($cerez) ?? '');

        if ($saklanan === '') {
            return null;
        }

        $parca = array_values(array_filter(explode(',', $saklanan), static fn ($v) => $v !== ''));

        if ($izinli !== null) {
            $parca = array_values(array_intersect($parca, $izinli));
        }

        if ($parca === []) {
            return null;
        }

        return count($parca) === 1 ? $parca[0] : $parca;
    }

    /** Çoklu filtre çerezi yazar (bir yıl geçerli, yalnız site içi) */
    protected function filtreCerezYaz(string $ad, string $deger): void
    {
        setcookie($ad, $deger, [
            'expires'  => $deger === '' ? time() - 3600 : time() + 31536000,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
    }
}
