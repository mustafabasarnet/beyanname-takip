<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * BENİ HATIRLA JETONLARI
 *
 * Giriş ekranındaki "Beni hatırla" kutusu işaretlendiğinde tarayıcıya bir
 * çerez bırakılır. Oturum süresi dolsa bile kullanıcı, jeton geçerli
 * olduğu sürece şifre girmeden içeri alınır.
 *
 * GÜVENLİK TASARIMI (selector + validator kalıbı)
 *   Çerez içeriği:  <secici>:<dogrulayici>
 *   Veritabanında:  secici açık, dogrulayici'nin SHA-256 ÖZETİ
 *
 *   • Veritabanı sızsa bile çerez üretilemez (özet geri çevrilemez).
 *   • Arama `secici` üzerinden yapılır; doğrulama sabit süreli
 *     karşılaştırmayla (hash_equals) yapılır, zamanlama saldırısı olmaz.
 *   • Her başarılı kullanımda jeton YENİLENİR. Çalınan bir çerez ikinci
 *     kez kullanılırsa eşleşme bozulur ve oturum açılmaz.
 *
 * GERİYE DÖNÜK UYUM
 *   migration_beni_hatirla.sql çalıştırılmamış kurulumlarda tablo yoktur;
 *   tüm metotlar sessizce boş döner ve program eski davranışıyla çalışır.
 */
class HatirlatmaJetonModel extends Model
{
    protected $table         = 'hatirlatma_jetonlari';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'kullanici_id', 'secici', 'dogrulayici', 'son_gecerlilik', 'tarayici', 'ip',
    ];

    /** Çerez adı */
    public const CEREZ = 'bt_hatirla';

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
     * Yeni jeton üretir ve çerez değerini döndürür.
     *
     * @return string|null "secici:dogrulayici" — tablo yoksa null
     */
    public function uret(int $kullaniciId, int $gun, ?string $tarayici = null, ?string $ip = null): ?string
    {
        if (! $this->kullanilabilir()) {
            return null;
        }

        $gun = max(1, min(365, $gun));

        $secici      = bin2hex(random_bytes(16));   // 32 karakter
        $dogrulayici = bin2hex(random_bytes(32));   // 64 karakter

        $this->insert([
            'kullanici_id'   => $kullaniciId,
            'secici'         => $secici,
            'dogrulayici'    => hash('sha256', $dogrulayici),
            'son_gecerlilik' => date('Y-m-d H:i:s', strtotime('+' . $gun . ' days')),
            'tarayici'       => $tarayici === null ? null : mb_substr($tarayici, 0, 255),
            'ip'             => $ip,
        ]);

        return $secici . ':' . $dogrulayici;
    }

    /**
     * Çerez değerini doğrular.
     *
     * Başarılıysa jeton kaydını döndürür; süresi dolmuş ya da eşleşmeyen
     * jetonlar silinir.
     *
     * @return array|null ['kullanici_id' => int, 'id' => int]
     */
    public function dogrula(?string $cerez): ?array
    {
        if (! $this->kullanilabilir() || $cerez === null || $cerez === '') {
            return null;
        }

        $parca = explode(':', $cerez, 2);

        if (count($parca) !== 2 || $parca[0] === '' || $parca[1] === '') {
            return null;
        }

        [$secici, $dogrulayici] = $parca;

        $kayit = $this->where('secici', $secici)->first();

        if ($kayit === null) {
            return null;
        }

        // Süresi dolmuşsa temizle
        if (strtotime($kayit['son_gecerlilik']) < time()) {
            $this->delete($kayit['id']);

            return null;
        }

        // Sabit süreli karşılaştırma — zamanlama saldırısına kapalı
        if (! hash_equals($kayit['dogrulayici'], hash('sha256', $dogrulayici))) {
            /*
             * Seçici doğru ama doğrulayıcı yanlış: büyük olasılıkla çalınmış
             * ya da yenilenmiş bir çerez tekrar kullanılıyor. Güvenli olan,
             * bu jetonu tamamen iptal etmektir.
             */
            $this->delete($kayit['id']);

            return null;
        }

        return $kayit;
    }

    /**
     * Jetonu yeniler (rotation) ve yeni çerez değerini döndürür.
     * Eski kayıt silinir; böylece her çerez yalnızca bir kez kullanılır.
     */
    public function yenile(array $kayit, int $gun, ?string $tarayici = null, ?string $ip = null): ?string
    {
        if (! $this->kullanilabilir()) {
            return null;
        }

        $this->delete($kayit['id']);

        return $this->uret((int) $kayit['kullanici_id'], $gun, $tarayici, $ip);
    }

    /** Tek bir jetonu (çerez değerinden) siler — çıkışta çağrılır */
    public function cerezSil(?string $cerez): void
    {
        if (! $this->kullanilabilir() || $cerez === null || $cerez === '') {
            return;
        }

        $secici = explode(':', $cerez, 2)[0] ?? '';

        if ($secici !== '') {
            $this->where('secici', $secici)->delete();
        }
    }

    /** Kullanıcının TÜM jetonlarını siler (şifre değişince çağrılır) */
    public function kullaniciyiTemizle(int $kullaniciId): void
    {
        if (! $this->kullanilabilir()) {
            return;
        }

        $this->where('kullanici_id', $kullaniciId)->delete();
    }

    /** Süresi dolmuş jetonları toplar (girişte fırsat buldukça) */
    public function suresiDolanlariSil(): int
    {
        if (! $this->kullanilabilir()) {
            return 0;
        }

        $this->where('son_gecerlilik <', date('Y-m-d H:i:s'))->delete();

        return $this->db->affectedRows();
    }
}
