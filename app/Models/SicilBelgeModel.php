<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SİCİL — TODO EVRAKLARI (sicil_belgeleri)
 *
 * Bir işlem todo'suna ("görev tamamlandı" kanıtı olarak) yüklenen dosyalar.
 * Mevcut `ajanda_ek` deseniyle birebir uyumlu: dosya diskte rastgele adla
 * saklanır, satırda orijinal ad + boyut + tür tutulur.
 *
 * Bağlam: her belge bir GÖREVE (todo) bağlanır; üst işlem kimliği de
 * (sicil_degisikligi_id) okuma kolaylığı için denormalize saklanır.
 */
class SicilBelgeModel extends Model
{
    protected $table         = 'sicil_belgeleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowedFields = [
        'sicil_degisikligi_id', 'gorev_id', 'dosya_adi', 'saklanan',
        'boyut', 'tur', 'yukleyen_id',
    ];

    /** Belge kaydını ekler. @return array{durum:bool, id:?int, hata:?string} */
    public function ekle(array $veri, int $yukleyenId): array
    {
        $ok = $this->insert([
            'sicil_degisikligi_id' => ! empty($veri['sicil_degisikligi_id'])
                ? (int) $veri['sicil_degisikligi_id'] : null,
            'gorev_id'             => ! empty($veri['gorev_id'])
                ? (int) $veri['gorev_id'] : null,
            'dosya_adi'            => mb_substr((string) ($veri['dosya_adi'] ?? ''), 0, 255),
            'saklanan'             => (string) ($veri['saklanan'] ?? ''),
            'boyut'                => (int) ($veri['boyut'] ?? 0),
            'tur'                  => $veri['tur'] ?? null,
            'yukleyen_id'          => $yukleyenId,
        ]);

        if (! $ok) {
            return ['durum' => false, 'id' => null,
                    'hata'  => $this->errors() ? implode(' ', $this->errors()) : 'Kayıt başarısız.'];
        }

        return ['durum' => true, 'id' => (int) $this->getInsertID(), 'hata' => null];
    }

    /** Bir işleme ait tüm evraklar (yükleyen adıyla, yeniden eskiye). */
    public function islemEvraklari(int $degisiklikId): array
    {
        return $this->db->table('sicil_belgeleri b')
            ->select('b.*, k.ad_soyad AS yukleyen_adi')
            ->join('kullanicilar k', 'k.id = b.yukleyen_id', 'left')
            ->where('b.sicil_degisikligi_id', $degisiklikId)
            ->orderBy('b.id', 'DESC')
            ->get()->getResultArray();
    }

    public function bul(int $id): ?array
    {
        return $this->find($id);
    }

    /** Belgeyi ve diskteki dosyayı siler (dosya yolu dışarıdan verilir). */
    public function belgeSil(int $id, ?string $diskYolu = null): bool
    {
        $ok = $this->delete($id);

        if ($ok && $diskYolu !== null && is_file($diskYolu)) {
            @unlink($diskYolu);
        }

        return $ok;
    }
}
