<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * MÜKELLEF EVRAK MUAFİYETİ
 *
 * "Bu mükellefte bu evrak türü hiç yok" bilgisini tutar.
 * Örn. banka hesabı olmayan bir mükellefin "Banka Ekstreleri" hücresi
 * her ay kırmızı görünmesin diye burada işaretlenir.
 *
 * Tablo satırı VARSA muafiyet vardır. Kalıcıdır (tüm aylar).
 * Tek bir ay için istisna gerekirse evrak_takip.durum = 'YOK' kullanılır;
 * o kayıt kalıcı ayarı EZER.
 *
 * GERİYE DÖNÜK UYUM: migration_evrak_muafiyet.sql çalıştırılmamış
 * kurulumlarda tablo yoktur. Tüm okuma metotları bu durumda boş sonuç
 * döndürür, yazma metotları sessizce hiçbir şey yapmaz — program çöker
 * yerine eski davranışıyla (muafiyet yok) çalışmaya devam eder.
 */
class EvrakMuafiyetModel extends Model
{
    protected $table         = 'mukellef_evrak_muafiyet';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = ['mukellef_id', 'evrak_turu_id', 'aciklama'];

    /** Tablo var mı? (her istekte bir kez sorulur) */
    protected ?bool $tabloVar = null;

    public function kullanilabilir(): bool
    {
        if ($this->tabloVar === null) {
            try {
                $this->tabloVar = $this->db->tableExists($this->table);
            } catch (\Throwable $e) {
                $this->tabloVar = false;
            }
        }

        return $this->tabloVar;
    }

    /**
     * Verilen mükellefler için muafiyet haritası.
     *
     * @param array<int> $mukellefIdler
     *
     * @return array<int,array<int,string>> [mukellef_id][evrak_turu_id] = açıklama
     */
    public function harita(array $mukellefIdler): array
    {
        if (! $this->kullanilabilir() || $mukellefIdler === []) {
            return [];
        }

        $rows = $this->whereIn('mukellef_id', array_map('intval', $mukellefIdler))->findAll();
        $out  = [];

        foreach ($rows as $r) {
            $out[(int) $r['mukellef_id']][(int) $r['evrak_turu_id']] = (string) ($r['aciklama'] ?? '');
        }

        return $out;
    }

    /**
     * Tek mükellefin muaf tür kimlikleri.
     *
     * @return array<int>
     */
    public function turIdListesi(int $mukellefId): array
    {
        if (! $this->kullanilabilir()) {
            return [];
        }

        return array_map(
            'intval',
            array_column($this->where('mukellef_id', $mukellefId)->findAll(), 'evrak_turu_id')
        );
    }

    /** Tek mükellefin muafiyetleri: [evrak_turu_id => açıklama] */
    public function aciklamalar(int $mukellefId): array
    {
        if (! $this->kullanilabilir()) {
            return [];
        }

        $out = [];

        foreach ($this->where('mukellef_id', $mukellefId)->findAll() as $r) {
            $out[(int) $r['evrak_turu_id']] = (string) ($r['aciklama'] ?? '');
        }

        return $out;
    }

    public function muafMi(int $mukellefId, int $evrakTuruId): bool
    {
        if (! $this->kullanilabilir()) {
            return false;
        }

        return $this->where('mukellef_id', $mukellefId)
            ->where('evrak_turu_id', $evrakTuruId)->countAllResults() > 0;
    }

    /** Muafiyet ekler (varsa açıklamayı günceller). */
    public function ekle(int $mukellefId, int $evrakTuruId, ?string $aciklama = null): bool
    {
        if (! $this->kullanilabilir()) {
            return false;
        }

        $mevcut = $this->where('mukellef_id', $mukellefId)
            ->where('evrak_turu_id', $evrakTuruId)->first();

        if ($mevcut !== null) {
            $this->update($mevcut['id'], ['aciklama' => $aciklama ?: null]);

            return true;
        }

        $this->insert([
            'mukellef_id'   => $mukellefId,
            'evrak_turu_id' => $evrakTuruId,
            'aciklama'      => $aciklama ?: null,
        ]);

        return true;
    }

    public function kaldir(int $mukellefId, int $evrakTuruId): bool
    {
        if (! $this->kullanilabilir()) {
            return false;
        }

        $this->where('mukellef_id', $mukellefId)
            ->where('evrak_turu_id', $evrakTuruId)->delete();

        return true;
    }

    /**
     * Mükellef kartından gelen tam liste ile eşitler.
     * Listede olmayan eski muafiyetler silinir.
     *
     * @param array<int>         $turIdler   Muaf tutulacak tür kimlikleri
     * @param array<int,string>  $notlar     [tur_id => açıklama]
     */
    public function eslestir(int $mukellefId, array $turIdler, array $notlar = []): void
    {
        if (! $this->kullanilabilir()) {
            return;
        }

        $turIdler = array_values(array_unique(array_map('intval', $turIdler)));

        // Fazlalıkları sil
        $b = $this->where('mukellef_id', $mukellefId);

        if ($turIdler !== []) {
            $b->whereNotIn('evrak_turu_id', $turIdler);
        }

        $b->delete();

        foreach ($turIdler as $tid) {
            $this->ekle($mukellefId, $tid, $notlar[$tid] ?? null);
        }
    }

    /**
     * Bir mükellefin muaf türlerinde birikmiş GELMEDI kayıtlarını temizler.
     *
     * Muafiyet açıldığında geçmiş aylarda kalan boş kayıtlar sayaçları
     * şişirmeye devam ederdi. "Geldi" işaretlenmiş kayıtlara DOKUNULMAZ;
     * kullanıcı gerçekten evrak almışsa bu bilgi kaybolmamalı.
     */
    public function bosKayitlariTemizle(int $mukellefId): int
    {
        if (! $this->kullanilabilir()) {
            return 0;
        }

        $turler = $this->turIdListesi($mukellefId);

        if ($turler === []) {
            return 0;
        }

        $this->db->table('evrak_takip')
            ->where('mukellef_id', $mukellefId)
            ->whereIn('evrak_turu_id', $turler)
            ->where('durum', 'GELMEDI')
            ->delete();

        return $this->db->affectedRows();
    }
}
