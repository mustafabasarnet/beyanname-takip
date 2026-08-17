<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * AJANDA / HATIRLATICI
 *
 * Beyanname, e-defter, evrak ve karşıt inceleme uyarıları sistemde zaten
 * otomatik üretiliyor. Ajanda bunları TEKRARLAMAZ; elle girilen işler
 * içindir: "vergi dairesine uğra", "sözleşme yenile", "müşteriyi ara".
 *
 * GÖRÜNÜRLÜK
 *   kisisel → yalnız oluşturan
 *   genel   → tüm büro
 *   gorev   → atanan kişi + atayan
 *   musavir → seçilen müşavirin ekibi (o müşavire erişimi olan kullanıcılar)
 *
 * Admin her kaydı görür.
 */
class AjandaModel extends Model
{
    protected $table         = 'ajanda';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $allowedFields = [
        'baslik', 'aciklama', 'tarih', 'saat', 'bitis_tarihi',
        'gorunurluk', 'atanan_id', 'musavir_id',
        'oncelik', 'etiket', 'renk', 'mukellef_id',
        'tekrar', 'tekrar_bitis', 'hatirlat_gun',
        'durum', 'yapildi_at', 'yapan_id', 'olusturan_id',
    ];

    protected $validationRules = [
        'baslik' => 'required|min_length[2]|max_length[200]',
        'tarih'  => 'required|valid_date[Y-m-d]',
    ];

    protected $validationMessages = [
        'baslik' => [
            'required'   => 'Başlık zorunludur.',
            'min_length' => 'Başlık en az 2 karakter olmalıdır.',
        ],
        'tarih' => ['required' => 'Tarih zorunludur.'],
    ];

    public const GORUNURLUK = [
        'kisisel' => 'Kişisel (yalnız ben)',
        'genel'   => 'Büro geneli (herkes)',
        'gorev'   => 'Görev (bir kişiye atanmış)',
        'musavir' => 'Mali müşavir ekibi',
    ];

    public const ONCELIK = [
        'dusuk'  => 'Düşük',
        'normal' => 'Normal',
        'yuksek' => 'Yüksek',
        'acil'   => 'Acil',
    ];

    public const TEKRAR = [
        'yok'      => 'Tekrar yok',
        'gunluk'   => 'Her gün',
        'haftalik' => 'Her hafta',
        'aylik'    => 'Her ay',
        'yillik'   => 'Her yıl',
    ];

    public const DURUMLAR = [
        'BEKLIYOR' => 'Bekliyor',
        'YAPILDI'  => 'Yapıldı',
        'IPTAL'    => 'İptal',
    ];

    /** Öncelik renkleri (rozet ve takvim için) */
    public const ONCELIK_RENK = [
        'dusuk'  => '#94a3b8',
        'normal' => '#2563eb',
        'yuksek' => '#ea580c',
        'acil'   => '#dc2626',
    ];

    // =================================================================
    //  GÖRÜNÜRLÜK
    // =================================================================

    /**
     * Kullanıcının görebileceği kayıtları süzer.
     *
     * DİKKAT: Bu koşul groupStart/groupEnd ile SARILIR; başka WHERE'lerle
     * OR çakışması olmasın diye. (Daha önce ödeme listesinde bu tuzağa
     * düşülmüştü: parantezsiz OR tüm filtreyi geçersiz kılıyor.)
     *
     * @param object $b          Query builder
     * @param array  $kullanici  ['id','rol']
     * @param int[]  $musavirlar Erişilen müşavir id'leri
     */
    public function gorunurlukKosulu($b, array $kullanici, array $musavirlar)
    {
        $kid = (int) ($kullanici['id'] ?? 0);

        // Admin her şeyi görür
        if (($kullanici['rol'] ?? '') === 'admin') {
            return $b;
        }

        $b->groupStart()
            ->where('ajanda.gorunurluk', 'genel')
            ->orGroupStart()
                ->where('ajanda.gorunurluk', 'kisisel')
                ->where('ajanda.olusturan_id', $kid)
            ->groupEnd()
            ->orGroupStart()
                ->where('ajanda.gorunurluk', 'gorev')
                ->groupStart()
                    ->where('ajanda.atanan_id', $kid)
                    ->orWhere('ajanda.olusturan_id', $kid)
                ->groupEnd()
            ->groupEnd();

        // Müşavir ekibi kayıtları
        if ($musavirlar !== []) {
            $b->orGroupStart()
                ->where('ajanda.gorunurluk', 'musavir')
                ->whereIn('ajanda.musavir_id', array_map('intval', $musavirlar))
            ->groupEnd();
        }

        return $b->groupEnd();
    }

    /** Kullanıcı bu kaydı görebilir mi? */
    public function gorebilirMi(array $kayit, array $kullanici, array $musavirlar): bool
    {
        if (($kullanici['rol'] ?? '') === 'admin') {
            return true;
        }

        $kid = (int) ($kullanici['id'] ?? 0);

        return match ($kayit['gorunurluk']) {
            'genel'   => true,
            'kisisel' => (int) $kayit['olusturan_id'] === $kid,
            'gorev'   => (int) $kayit['atanan_id'] === $kid || (int) $kayit['olusturan_id'] === $kid,
            'musavir' => in_array((int) $kayit['musavir_id'], $musavirlar, true),
            default   => false,
        };
    }

    /**
     * Kullanıcı bu kaydı DÜZENLEYEBİLİR mi?
     * Oluşturan, atanan ve admin düzenleyebilir; diğerleri yalnız görür.
     */
    public function duzenleyebilirMi(array $kayit, array $kullanici): bool
    {
        if (($kullanici['rol'] ?? '') === 'admin') {
            return true;
        }

        $kid = (int) ($kullanici['id'] ?? 0);

        return (int) $kayit['olusturan_id'] === $kid
            || (int) ($kayit['atanan_id'] ?? 0) === $kid;
    }

    // =================================================================
    //  LİSTELEME
    // =================================================================

    /** Ortak SELECT + JOIN */
    protected function temelSorgu()
    {
        return $this->db->table('ajanda')
            ->select("ajanda.*,
                      k.ad_soyad AS olusturan_adi,
                      a.ad_soyad AS atanan_adi,
                      mus.ad_soyad AS musavir_adi, mus.renk AS musavir_renk,
                      m.unvan AS mukellef_unvan, m.kod AS mukellef_kod,
                      (SELECT COUNT(*) FROM ajanda_ek e WHERE e.ajanda_id = ajanda.id) AS ek_sayisi")
            ->join('kullanicilar k', 'k.id = ajanda.olusturan_id', 'left')
            ->join('kullanicilar a', 'a.id = ajanda.atanan_id', 'left')
            ->join('musavirler mus', 'mus.id = ajanda.musavir_id', 'left')
            ->join('mukellefler m', 'm.id = ajanda.mukellef_id', 'left')
            ->where('ajanda.deleted_at', null);
    }

    /**
     * Filtreli liste.
     *
     * @param array $f ['bas','bit','durum','gorunurluk','oncelik','etiket',
     *                  'mukellef_id','atanan_id','q','limit','ofset']
     */
    public function liste(array $f, array $kullanici, array $musavirlar): array
    {
        $b = $this->listeSorgusu($f, $kullanici, $musavirlar);

        // Bekleyenler önce, sonra tarihe göre
        $b->orderBy("FIELD(ajanda.durum,'BEKLIYOR','YAPILDI','IPTAL')", '', false)
            ->orderBy('ajanda.tarih', 'ASC')
            ->orderBy('ajanda.saat', 'ASC')
            ->orderBy('ajanda.id', 'ASC');

        $rows = ! empty($f['limit'])
            ? $b->get((int) $f['limit'], (int) ($f['ofset'] ?? 0))->getResultArray()
            : $b->get()->getResultArray();

        return array_map([$this, 'zenginlestir'], $rows);
    }

    public function listeSayisi(array $f, array $kullanici, array $musavirlar): int
    {
        return $this->listeSorgusu($f, $kullanici, $musavirlar)->countAllResults();
    }

    protected function listeSorgusu(array $f, array $kullanici, array $musavirlar)
    {
        $b = $this->temelSorgu();
        $this->gorunurlukKosulu($b, $kullanici, $musavirlar);

        if (! empty($f['bas'])) {
            $b->where('ajanda.tarih >=', $f['bas']);
        }

        if (! empty($f['bit'])) {
            $b->where('ajanda.tarih <=', $f['bit']);
        }

        foreach (['durum', 'gorunurluk', 'oncelik'] as $alan) {
            if (! empty($f[$alan])) {
                $b->where('ajanda.' . $alan, $f[$alan]);
            }
        }

        if (! empty($f['etiket'])) {
            $b->where('ajanda.etiket', $f['etiket']);
        }

        if (! empty($f['mukellef_id'])) {
            $b->where('ajanda.mukellef_id', (int) $f['mukellef_id']);
        }

        if (! empty($f['atanan_id'])) {
            $b->where('ajanda.atanan_id', (int) $f['atanan_id']);
        }

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('ajanda.baslik', $f['q'])
                ->orLike('ajanda.aciklama', $f['q'])
                ->orLike('ajanda.etiket', $f['q'])
                ->orLike('m.unvan', $f['q'])
              ->groupEnd();
        }

        return $b;
    }

    /** Satıra hesaplanmış alanlar ekler */
    protected function zenginlestir(array $r): array
    {
        $bugun = date('Y-m-d');

        $r['gecikmis']  = $r['durum'] === 'BEKLIYOR' && $r['tarih'] < $bugun;
        $r['bugun']     = $r['tarih'] === $bugun;
        $r['yarin']     = $r['tarih'] === date('Y-m-d', strtotime('+1 day'));
        $r['ek_sayisi'] = (int) ($r['ek_sayisi'] ?? 0);

        // Kaç gün kaldı (negatif = geçti)
        $r['kalan_gun'] = (int) floor(
            (strtotime($r['tarih']) - strtotime($bugun)) / 86400
        );

        $r['renk_efektif'] = $r['renk'] ?: (self::ONCELIK_RENK[$r['oncelik']] ?? '#2563eb');

        return $r;
    }

    // =================================================================
    //  TAKVİM
    // =================================================================

    /**
     * Bir ayın kayıtlarını gün gün gruplar.
     *
     * @return array<string, array> ['2026-08-13' => [kayıt, kayıt], ...]
     */
    public function takvim(int $yil, int $ay, array $kullanici, array $musavirlar, array $f = []): array
    {
        $bas = sprintf('%04d-%02d-01', $yil, $ay);
        $bit = date('Y-m-t', strtotime($bas));

        $kayitlar = $this->liste(
            ['bas' => $bas, 'bit' => $bit] + $f,
            $kullanici,
            $musavirlar
        );

        $gunler = [];

        foreach ($kayitlar as $k) {
            $gunler[$k['tarih']][] = $k;
        }

        return $gunler;
    }

    // =================================================================
    //  PANEL / HATIRLATMA
    // =================================================================

    /**
     * Panelde ve giriş uyarısında gösterilecek işler.
     *
     * Kapsam: gecikmiş olanlar + bugünden itibaren $gun günlük pencere.
     * hatirlat_gun dolu olan kayıtlar erken görünür.
     */
    public function yaklasan(array $kullanici, array $musavirlar, int $gun = 7): array
    {
        $bugun = date('Y-m-d');
        $son   = date('Y-m-d', strtotime("+{$gun} days"));

        $b = $this->temelSorgu();
        $this->gorunurlukKosulu($b, $kullanici, $musavirlar);

        $b->where('ajanda.durum', 'BEKLIYOR')
            ->groupStart()
                // Geçmiş + pencere içi
                ->where('ajanda.tarih <=', $son)
                // Erken hatırlatma isteyenler
                ->orWhere("DATE_SUB(ajanda.tarih, INTERVAL ajanda.hatirlat_gun DAY) <= '{$bugun}'", null, false)
            ->groupEnd()
            ->orderBy('ajanda.tarih', 'ASC')
            ->orderBy('ajanda.saat', 'ASC');

        return array_map([$this, 'zenginlestir'], $b->get()->getResultArray());
    }

    /**
     * Panel/menü rozeti için sayaçlar.
     *
     * @return array{gecikmis:int,bugun:int,yaklasan:int,toplam:int}
     */
    public function sayaclar(array $kullanici, array $musavirlar, int $gun = 7): array
    {
        $o = ['gecikmis' => 0, 'bugun' => 0, 'yaklasan' => 0, 'toplam' => 0];

        foreach ($this->yaklasan($kullanici, $musavirlar, $gun) as $k) {
            $o['toplam']++;

            if ($k['gecikmis']) {
                $o['gecikmis']++;
            } elseif ($k['bugun']) {
                $o['bugun']++;
            } else {
                $o['yaklasan']++;
            }
        }

        return $o;
    }

    /** Girişte gösterilecek işler: yalnız gecikmiş + bugün */
    public function bugunkuIsler(array $kullanici, array $musavirlar): array
    {
        return array_values(array_filter(
            $this->yaklasan($kullanici, $musavirlar, 0),
            static fn ($k) => $k['gecikmis'] || $k['bugun']
        ));
    }

    // =================================================================
    //  DURUM / TEKRAR
    // =================================================================

    /**
     * "Yapıldı" işaretler.
     *
     * Tekrarlı kayıtta kayıt kapatılmaz; tarihi bir sonraki döneme ötelenir
     * ve BEKLIYOR olarak kalır. Böylece "her ayın 20'si" gibi işler kendini
     * yeniler. Tekrar bitiş tarihi geçmişse kayıt kapanır.
     */
    public function yapildiIsaretle(int $id, int $kullaniciId): array
    {
        $k = $this->find($id);

        if ($k === null) {
            return ['durum' => false, 'mesaj' => 'Kayıt bulunamadı.'];
        }

        if ($k['tekrar'] === 'yok') {
            $this->update($id, [
                'durum'      => 'YAPILDI',
                'yapildi_at' => date('Y-m-d H:i:s'),
                'yapan_id'   => $kullaniciId,
            ]);

            return ['durum' => true, 'kapandi' => true, 'mesaj' => 'Yapıldı olarak işaretlendi.'];
        }

        $yeni = $this->sonrakiTarih($k['tarih'], $k['tekrar']);

        // Tekrar bitişi geçildiyse kaydı kapat
        if (! empty($k['tekrar_bitis']) && $yeni > $k['tekrar_bitis']) {
            $this->update($id, [
                'durum'      => 'YAPILDI',
                'yapildi_at' => date('Y-m-d H:i:s'),
                'yapan_id'   => $kullaniciId,
            ]);

            return ['durum' => true, 'kapandi' => true, 'mesaj' => 'Son tekrar tamamlandı, kayıt kapatıldı.'];
        }

        $this->update($id, [
            'tarih'      => $yeni,
            'durum'      => 'BEKLIYOR',
            'yapildi_at' => date('Y-m-d H:i:s'),
            'yapan_id'   => $kullaniciId,
        ]);

        return [
            'durum'   => true,
            'kapandi' => false,
            'yeni'    => $yeni,
            'mesaj'   => 'Yapıldı. Sonraki tekrar: ' . date('d.m.Y', strtotime($yeni)),
        ];
    }

    /**
     * Tekrar aralığına göre sonraki tarih.
     *
     * Aylık/yıllıkta ay sonu taşması engellenir: 31 Ocak + 1 ay = 28 Şubat
     * (PHP'nin varsayılanı 3 Mart olurdu; kullanıcı bunu beklemiyor).
     */
    public function sonrakiTarih(string $tarih, string $tekrar): string
    {
        $z = strtotime($tarih);

        return match ($tekrar) {
            'gunluk'   => date('Y-m-d', strtotime('+1 day', $z)),
            'haftalik' => date('Y-m-d', strtotime('+7 days', $z)),
            'aylik'    => $this->ayEkle($tarih, 1),
            'yillik'   => $this->ayEkle($tarih, 12),
            default    => $tarih,
        };
    }

    /** Ay ekler, ay sonu taşmasını ay sonuna sabitler */
    protected function ayEkle(string $tarih, int $ay): string
    {
        $g = (int) date('d', strtotime($tarih));
        $ilk = date('Y-m-01', strtotime($tarih));
        $hedefAy = date('Y-m-01', strtotime("+{$ay} months", strtotime($ilk)));
        $sonGun  = (int) date('t', strtotime($hedefAy));

        return date('Y-m-', strtotime($hedefAy)) . sprintf('%02d', min($g, $sonGun));
    }

    /** Kaydı yeniden aç */
    public function geriAl(int $id): bool
    {
        return (bool) $this->update($id, [
            'durum'      => 'BEKLIYOR',
            'yapildi_at' => null,
            'yapan_id'   => null,
        ]);
    }

    // =================================================================
    //  YARDIMCI
    // =================================================================

    /** Kullanılan etiketler (filtre açılır listesi için) */
    public function etiketler(array $kullanici, array $musavirlar): array
    {
        $b = $this->db->table('ajanda')
            ->select('DISTINCT etiket', false)
            ->where('deleted_at', null)
            ->where('etiket IS NOT NULL', null, false)
            ->where("etiket <> ''", null, false);

        $this->gorunurlukKosulu($b, $kullanici, $musavirlar);

        $out = array_column($b->orderBy('etiket', 'ASC')->get()->getResultArray(), 'etiket');

        return array_values(array_filter($out));
    }

    /** Tek kayıt + ilişkili alanlar */
    public function detay(int $id): ?array
    {
        $r = $this->temelSorgu()->where('ajanda.id', $id)->get()->getRowArray();

        return $r === null ? null : $this->zenginlestir($r);
    }

    /** Bir mükellefin ajanda kayıtları (mükellef kartında gösterilir) */
    public function mukellefKayitlari(int $mukellefId, array $kullanici, array $musavirlar, int $limit = 20): array
    {
        return $this->liste(
            ['mukellef_id' => $mukellefId, 'limit' => $limit],
            $kullanici,
            $musavirlar
        );
    }
}
