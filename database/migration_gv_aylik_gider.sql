-- =====================================================================
--  GÜNCELLEME 18: AYLIK GİDER TABLOSU
--
--  Mali müşavir mesleki giderini ay ay girer (KDV tablosuyla aynı biçim):
--      Ocak … Aralık  ·  tutar  ·  açıklama
--
--  TOPLAMA KURALI (kullanıcı kararı):
--    Toplam gider = elle girilen "Toplam Mesleki Gider" + aylık liste toplamı
--  Yani liste EKLENİR, elle girileni değiştirmez. Böylece bir kısmı toplu
--  girilmiş bürolar için de çalışır.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_gv_aylik_gider.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `musavir_aylik_gider` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `musavir_id` INT UNSIGNED NOT NULL,
  `yil`        SMALLINT UNSIGNED NOT NULL,
  `ay`         TINYINT UNSIGNED NOT NULL COMMENT '1-12',
  `tutar`      DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'O ayın mesleki gideri',
  `aciklama`   VARCHAR(200) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gider_musavir_donem` (`musavir_id`,`yil`,`ay`),
  KEY `idx_gider_yil` (`yil`),
  CONSTRAINT `fk_agider_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Güncelleme tamamlandı.' AS sonuc;
