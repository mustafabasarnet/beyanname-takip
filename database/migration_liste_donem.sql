-- =====================================================================
--  GÜNCELLEME 7: Ödeme listeleri dönemden bağımsız hale getirildi
--
--  Önce: liste = mükellef grubu + SABİT dönem  → her ay yeni liste
--  Şimdi: liste = kalıcı mükellef grubu, dönem AÇILIRKEN seçilir
--
--  yil/ay alanları "varsayılan dönem" olarak korunur (opsiyonel).
--
--  mysql -u KULLANICI -p beyanname_takip < migration_liste_donem.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `odeme_listeleri`
  MODIFY COLUMN `yil` SMALLINT UNSIGNED NULL
    COMMENT 'Varsayılan yıl (boşsa açılışta içinde bulunulan yıl)',
  MODIFY COLUMN `ay` TINYINT UNSIGNED NULL
    COMMENT 'Varsayılan ay (boşsa açılışta içinde bulunulan ay)';

SELECT 'Güncelleme tamamlandı.' AS sonuc;
