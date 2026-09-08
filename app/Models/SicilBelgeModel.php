<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SİCİL — BELGELER (değişiklik veya görev düzeyinde ek)
 *
 * Mevcut `ajanda_ek` deseniyle birebir uyumlu: dosyalar diskte rastgele
 * adla saklanır, satırda orijinal ad tutulur. Değişiklik ya da görev
 * bağlamından en az biri zorunludur.
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

    /** Kayıt türünü doğrular ve kaydeder (en az bir bağlam gerekir). */
    public function ekle(array $veri, int $yukleyenId): array
    {
        $degId = (int) ($veri['sicil_degisikligi_id'] ?? 0);
        $gorevId = (int) ($veri['gorev_id'] ?? 0);

        if ($degId === 0 && $gorevId === 0) {
            return ['durum' => false, 'hata' => 'Belge bir değişikliğe veya göreve bağlanmalı.'];
        }

        $ok = $this->insert([
            'sicil_degisikligi_id' => $degId > 0 ? $degId : null,
            'gorev_id'             => $gorevId > 0 ? $gorevId : null,
            'dosya_adi'            => (string) ($veri['dosya_adi'] ?? ''),
            'saklanan'             => (string) ($veri['saklanan'] ?? ''),
            'boyut'                => (int) ($veri['boyut'] ?? 0),
            'tur'                  => $veri['tur'] ?? null,
            'yukleyen_id'          => $yukleyenId,
        ]);

        if (! $ok) {
            return ['durum' => false, 'hata' => $this->errors() ? implode(' ', $this->errors()) : 'Kayıt başarısız.'];
        }

        return ['durum' => true, 'id' => (int) $this->getInsertID(), 'hata' => null];
    }

    /** Bir bağlama ait belgeler (yükleyen adıyla). */
    public function listele(?int $degisiklikId = null, ?int $gorevId = null): array
    {
        $b = $this->db->table('sicil_belgeleri b')
            ->select('b.*, k.ad_soyad AS yukleyen_adi')
            ->join('kullanicilar k', 'k.id = b.yukleyen_id', 'left');

        if ($degisiklikId !== null) {
            $b->where('b.sicil_degisikligi_id', $degisiklikId);
        }

        if ($gorevId !== null) {
            $b->where('b.gorev_id', $gorevId);
        }

        return $b->orderBy('b.id', 'DESC')->get()->getResultArray();
    }

    public function bul(int $id): ?array
    {
        return $this->find($id);
    }

    /** Belgeyi ve disk dosyasını siler (dosya yolu dışarıdan verilir). */
    public function belgeSil(int $id, ?string $diskYolu = null): bool
    {
        $ok = $this->delete($id);

        if ($ok && $diskYolu !== null && is_file($diskYolu)) {
            @unlink($diskYolu);
        }

        return $ok;
    }
}
