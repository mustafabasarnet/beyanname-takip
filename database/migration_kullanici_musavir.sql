-- =====================================================================
--  GÜNCELLEME: Kullanıcı ↔ Mali Müşavir ilişkisinin ayrıştırılması
--  Mevcut kurulumlar için çalıştırın. Yeni kurulumlarda gerekmez
--  (beyanname_takip.sql zaten günceldir).
--
--  Çalıştırma:
--    mysql -u KULLANICI -p beyanname_takip < migration_kullanici_musavir.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) Kullanıcının erişebileceği mali müşavirler (çoklu)
--    Mali müşavir artık bir "kurum/portföy" tanımı, kullanıcı ise
--    sisteme giren kişidir. Bir kullanıcı birden fazla müşavirin
--    portföyüne erişebilir.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kullanici_musavirleri` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_id` INT UNSIGNED NOT NULL,
  `musavir_id`   INT UNSIGNED NOT NULL,
  `created_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kullanici_musavir` (`kullanici_id`,`musavir_id`),
  KEY `fk_km_musavir` (`musavir_id`),
  CONSTRAINT `fk_km_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_km_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) Mevcut tekil bağlantıları yeni tabloya taşı
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `kullanici_musavirleri` (`kullanici_id`,`musavir_id`,`created_at`)
SELECT `id`, `musavir_id`, NOW()
FROM `kullanicilar`
WHERE `musavir_id` IS NOT NULL;

-- ---------------------------------------------------------------------
-- 3) Mükellef kaydına "sorumlu kullanıcı" alanı (kimin takip ettiği)
--    Mali müşavir = portföy sahibi, sorumlu kullanıcı = işi yapan personel
-- ---------------------------------------------------------------------
SET @vr = (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mukellefler'
             AND COLUMN_NAME = 'sorumlu_kullanici_id');
SET @sql = IF(@vr = 0,
  'ALTER TABLE `mukellefler`
     ADD COLUMN `sorumlu_kullanici_id` INT UNSIGNED NULL COMMENT ''Takipten sorumlu personel''
       AFTER `musavir_id`,
     ADD KEY `idx_muk_sorumlu` (`sorumlu_kullanici_id`)',
  'SELECT ''sorumlu_kullanici_id zaten var''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Foreign key (varsa tekrar ekleme)
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mukellefler'
             AND CONSTRAINT_NAME = 'fk_mukellef_sorumlu');
SET @sql2 = IF(@fk = 0,
  'ALTER TABLE `mukellefler`
     ADD CONSTRAINT `fk_mukellef_sorumlu` FOREIGN KEY (`sorumlu_kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT ''fk_mukellef_sorumlu zaten var''');
PREPARE st2 FROM @sql2; EXECUTE st2; DEALLOCATE PREPARE st2;

-- ---------------------------------------------------------------------
-- 4) kullanicilar.musavir_id artık "birincil/varsayılan müşavir" anlamında
--    (geriye dönük uyumluluk için korunuyor)
-- ---------------------------------------------------------------------
ALTER TABLE `kullanicilar`
  MODIFY COLUMN `musavir_id` INT UNSIGNED NULL
  COMMENT 'Varsayılan (birincil) mali müşavir - erişim için kullanici_musavirleri kullanılır';

SELECT 'Güncelleme tamamlandı.' AS sonuc;
