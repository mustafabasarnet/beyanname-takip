<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Beyanname dışı ödeme kalemleri (Bağkur primi, MTV, harç, ceza vb.).
 *
 * Ödeme listesinde ilgili mükellefin altına eklenir; mükellef toplamına
 * ve ödeme bildirimine dahil olur.
 */
class OzelOdemeModel extends Model
{
    protected $table         = 'ozel_odemeler';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'mukellef_id', 'baslik', 'aciklama', 'tutar', 'son_tarih',
        'donem_etiketi', 'durum', 'odendi', 'odeme_tarihi', 'tekrar',
        'tekrar_bitis', 'tekrar_kaynak_id', 'kaydeden_id',
    ];

    public const DURUMLAR = [
        'BEKLIYOR'  => 'Bekliyor',
        'ONAYLANDI' => 'Onaylandı',
        'IPTAL'     => 'İptal',
    ];

    /** Sık kullanılan kalem başlıkları (hızlı seçim için) */
    public const ONERILEN = [
        'Bağkur Primi',
        'SGK 4/A Primi',
        'MTV 1. Taksit',
        'MTV 2. Taksit',
        'Emlak Vergisi',
        'Çevre Temizlik Vergisi',
        'Vergi Cezası',
        'Gecikme Zammı',
        'Harç',
        'Oda Aidatı',
    ];

    protected $validationRules = [
        'mukellef_id' => 'required|is_natural_no_zero',
        'baslik'      => 'required|min_length[2]|max_length[200]',
        'tutar'       => 'required|decimal|greater_than_equal_to[0]',
        'son_tarih'   => 'required|valid_date[Y-m-d]',
        'durum'       => 'required|in_list[BEKLIYOR,ONAYLANDI,IPTAL]',
    ];

    protected $validationMessages = [
        'mukellef_id' => ['required' => 'Mükellef seçimi zorunludur.'],
        'baslik'      => ['required' => 'Ödeme başlığı zorunludur.'],
        'tutar'       => [
            'required'              => 'Tutar zorunludur.',
            'decimal'               => 'Tutar sayısal olmalıdır.',
            'greater_than_equal_to' => 'Tutar negatif olamaz.',
        ],
        'son_tarih' => [
            'required'   => 'Son ödeme tarihi zorunludur.',
            'valid_date' => 'Geçerli bir tarih giriniz.',
        ],
    ];

    /**
     * Ödeme listesi için: son tarihi verilen yıl/ay aralığına düşen kalemler.
     *
     * @param array $f ['yil','ay','musavir_id'(int|int[]),'mukellef_id','odendi','kaydeden_id']
     *
     * @return array<int,array> mukellef_id ile gruplanabilir düz liste
     */
    public function listele(array $f = []): array
    {
        $b = $this->select('ozel_odemeler.*,
                            m.id as m_id, m.unvan as mukellef_unvan, m.kod as mukellef_kod,
                            m.vergi_kimlik_no, m.tc_kimlik_no, m.vergi_dairesi,
                            mus.ad_soyad as musavir_adi,
                            k.ad_soyad as kaydeden_adi')
            ->join('mukellefler m', 'm.id = ozel_odemeler.mukellef_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->join('kullanicilar k', 'k.id = ozel_odemeler.kaydeden_id', 'left')
            ->where('m.deleted_at', null)
            ->where('ozel_odemeler.durum !=', 'IPTAL');

        if (! empty($f['yil'])) {
            $b->where('YEAR(ozel_odemeler.son_tarih)', (int) $f['yil']);
        }

        if (! empty($f['ay'])) {
            $b->where('MONTH(ozel_odemeler.son_tarih)', (int) $f['ay']);
        }

        if (! empty($f['mukellef_id'])) {
            $b->where('ozel_odemeler.mukellef_id', (int) $f['mukellef_id']);
        }

        if (! empty($f['musavir_id'])) {
            if (is_array($f['musavir_id'])) {
                $b->whereIn('m.musavir_id', array_map('intval', $f['musavir_id']));
            } else {
                $b->where('m.musavir_id', (int) $f['musavir_id']);
            }
        }

        if (isset($f['odendi']) && $f['odendi'] !== '' && $f['odendi'] !== null) {
            $b->where('ozel_odemeler.odendi', (int) $f['odendi']);
        }

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('m.unvan', $f['q'])
                ->orLike('ozel_odemeler.baslik', $f['q'])
              ->groupEnd();
        }

        // Kalemler kullanıcıya özeldir: yalnızca sahibi (ve yönetici) görür.
        if (! empty($f['kaydeden_id'])) {
            $b->where('ozel_odemeler.kaydeden_id', (int) $f['kaydeden_id']);
        }

        return $b->orderBy('ozel_odemeler.son_tarih', 'ASC')
            ->orderBy('ozel_odemeler.baslik', 'ASC')
            ->findAll();
    }

    /** Ödeme işaretle / geri al */
    public function odemeIsaretle(int $id, bool $odendi): bool
    {
        return $this->update($id, [
            'odendi'       => $odendi ? 1 : 0,
            'odeme_tarihi' => $odendi ? date('Y-m-d') : null,
        ]);
    }

    /**
     * Aylık tekrar eden kalemleri hedef ay için üretir.
     * (Örn. her ay ödenen Bağkur primi)
     *
     * ÖNEMLİ: Eski sürümde yalnızca "bir önceki ay"a bakılıyordu; bu yüzden
     * bir ay atlandığında zincir kopuyor ve kalem bir daha hiç görünmüyordu.
     * Ayrıca metot hiçbir yerden çağrılmadığı için tekrar hiç çalışmıyordu.
     * Artık serinin BAŞLANGIÇ kalemi bulunur ve hedef aya kadar olan tüm
     * eksik aylar tamamlanır.
     *
     * @param int      $yil        Hedef yıl
     * @param int      $ay         Hedef ay
     * @param int|null $kaydedenId Yalnızca bu kullanıcının kalemleri (null = hepsi)
     *
     * @return int Oluşturulan kayıt sayısı
     */
    public function tekrarlariUret(int $yil, int $ay, ?int $kaydedenId = null): int
    {
        if ($ay < 1 || $ay > 12) {
            return 0;
        }

        $hedefBas = sprintf('%04d-%02d-01', $yil, $ay);

        // Seri başlangıçları: kendisi üretilmemiş (kaynak_id boş), tekrarlı,
        // iptal edilmemiş ve son tarihi hedef aydan ÖNCE olan kalemler.
        $b = $this->where('tekrar', 'AYLIK')
            ->where('tekrar_kaynak_id', null)
            ->where('durum !=', 'IPTAL')
            ->where('son_tarih <', $hedefBas);

        if ($kaydedenId !== null) {
            $b->where('kaydeden_id', $kaydedenId);
        }

        $kaynaklar = $b->findAll();
        $sayac     = 0;

        foreach ($kaynaklar as $k) {
            // Tekrar bitiş tarihi geçtiyse üretme
            if (! empty($k['tekrar_bitis']) && $k['tekrar_bitis'] < $hedefBas) {
                continue;
            }

            $gun       = (int) date('j', strtotime($k['son_tarih']));
            $ayGunu    = (int) date('t', mktime(0, 0, 0, $ay, 1, $yil));
            $yeniTarih = sprintf('%04d-%02d-%02d', $yil, $ay, min($gun, $ayGunu));

            // Bitiş tarihi bu ayın içindeyse ve yeni tarih onu aşıyorsa üretme
            if (! empty($k['tekrar_bitis']) && $yeniTarih > $k['tekrar_bitis']) {
                continue;
            }

            // Bu seri için hedef ayda kayıt var mı? (kaynak ya da üretilmiş)
            if ($this->seriKaydiVarMi($k, $yil, $ay)) {
                continue;
            }

            $this->insert([
                'mukellef_id'      => $k['mukellef_id'],
                'baslik'           => $k['baslik'],
                'aciklama'         => $k['aciklama'],
                'tutar'            => $k['tutar'],
                'son_tarih'        => $yeniTarih,
                'donem_etiketi'    => $this->ayAdi($ay) . ' ' . $yil,
                'durum'            => 'ONAYLANDI',
                'odendi'           => 0,
                // Üretilen kopya kendisi yeni seri başlatmasın
                'tekrar'           => 'AYLIK',
                'tekrar_bitis'     => $k['tekrar_bitis'] ?? null,
                'tekrar_kaynak_id' => (int) $k['id'],
                'kaydeden_id'      => $k['kaydeden_id'],
            ]);
            $sayac++;
        }

        return $sayac;
    }

    /**
     * Bir tekrar serisinin belirli ayda kaydı var mı?
     * Hem kaynağın kendisi hem de ondan üretilmiş kopyalar kontrol edilir.
     * Kullanıcı kopyayı silmişse tekrar üretilmemesi için silinen ay da
     * "aynı mükellef + aynı başlık" ölçütüyle yakalanır.
     */
    protected function seriKaydiVarMi(array $kaynak, int $yil, int $ay): bool
    {
        $bas = sprintf('%04d-%02d-01', $yil, $ay);
        $bit = date('Y-m-t', strtotime($bas));

        $var = $this->groupStart()
                ->where('tekrar_kaynak_id', (int) $kaynak['id'])
                ->orWhere('id', (int) $kaynak['id'])
            ->groupEnd()
            ->where('son_tarih >=', $bas)
            ->where('son_tarih <=', $bit)
            ->first();

        if ($var !== null) {
            return true;
        }

        // Elle eklenmiş aynı isimli kalem varsa da mükerrer üretme
        $elle = $this->where('mukellef_id', (int) $kaynak['mukellef_id'])
            ->where('baslik', $kaynak['baslik'])
            ->where('son_tarih >=', $bas)
            ->where('son_tarih <=', $bit)
            ->where('durum !=', 'IPTAL')
            ->first();

        return $elle !== null;
    }

    /**
     * Hedef ay için üretilebilecek (henüz oluşmamış) tekrarlı kalem sayısı.
     * Düğmeyi göstermek/gizlemek ve kullanıcıyı bilgilendirmek için kullanılır.
     */
    public function bekleyenTekrarSayisi(int $yil, int $ay, ?int $kaydedenId = null): int
    {
        if ($ay < 1 || $ay > 12) {
            return 0;
        }

        $hedefBas = sprintf('%04d-%02d-01', $yil, $ay);

        $b = $this->where('tekrar', 'AYLIK')
            ->where('tekrar_kaynak_id', null)
            ->where('durum !=', 'IPTAL')
            ->where('son_tarih <', $hedefBas);

        if ($kaydedenId !== null) {
            $b->where('kaydeden_id', $kaydedenId);
        }

        $sayac = 0;

        foreach ($b->findAll() as $k) {
            if (! empty($k['tekrar_bitis']) && $k['tekrar_bitis'] < $hedefBas) {
                continue;
            }

            if ($this->seriKaydiVarMi($k, $yil, $ay)) {
                continue;
            }

            $sayac++;
        }

        return $sayac;
    }

    /**
     * Bir tekrar serisini tamamen durdurur:
     * kaynağın tekrarı kapatılır, gelecekteki ÖDENMEMİŞ kopyalar silinir.
     *
     * @return int Silinen gelecek kayıt sayısı
     */
    public function tekrariDurdur(int $id): int
    {
        $kayit = $this->find($id);

        if ($kayit === null) {
            return 0;
        }

        // Serinin kaynağını bul
        $kaynakId = (int) ($kayit['tekrar_kaynak_id'] ?: $kayit['id']);

        $this->update($kaynakId, ['tekrar' => 'YOK']);

        // Bugünden sonraki, ödenmemiş kopyaları temizle
        $this->where('tekrar_kaynak_id', $kaynakId)
            ->where('son_tarih >', date('Y-m-d'))
            ->where('odendi', 0)
            ->delete();

        $silinen = $this->db->affectedRows();

        // Kalanların da tekrarını kapat
        $this->where('tekrar_kaynak_id', $kaynakId)->set('tekrar', 'YOK')->update();

        return $silinen;
    }

    protected function ayAdi(int $ay): string
    {
        $aylar = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
            'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

        return $aylar[$ay] ?? '';
    }
}
