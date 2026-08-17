-- =====================================================================
--  GÜNCELLEME 6:
--   1) Personel de tahakkuk tutarı girebilsin (ödeme listesi yine kapalı)
--   2) Kullanıcıya özel, kayıtlı ödeme listeleri
--   3) Özel ödeme kalemleri kullanıcıya özel (sahibi + yönetici görür)
--
--  mysql -u KULLANICI -p beyanname_takip < migration_ozel_liste.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) ozel_odemeler: sahiplik alanı
--    kaydeden_id zaten vardı; NULL kalanları ilk yöneticiye devret.
-- ---------------------------------------------------------------------
UPDATE `ozel_odemeler`
   SET `kaydeden_id` = (SELECT MIN(`id`) FROM `kullanicilar` WHERE `rol` = 'admin')
 WHERE `kaydeden_id` IS NULL;

-- ---------------------------------------------------------------------
-- 2) KAYITLI ÖDEME LİSTELERİ (kullanıcıya özel)
--    Örn. "Nisan 2026 – Mustafa Başar Ödemeleri"
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `odeme_listeleri` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_id`  INT UNSIGNED NOT NULL COMMENT 'Listenin sahibi',
  `musavir_id`    INT UNSIGNED NULL COMMENT 'İlgili mali müşavir (başlıkta görünür)',
  `ad`            VARCHAR(200) NOT NULL,
  `aciklama`      VARCHAR(300) NULL,
  `yil`           SMALLINT UNSIGNED NOT NULL,
  `ay`            TINYINT UNSIGNED NULL COMMENT 'NULL = tüm yıl',
  `ucret_dahil`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Muhasebe ücreti eklensin mi',
  `ozel_dahil`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Özel ödeme kalemleri eklensin mi',
  `created_at`    DATETIME     NULL,
  `updated_at`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ol_kullanici` (`kullanici_id`),
  KEY `idx_ol_donem` (`yil`,`ay`),
  CONSTRAINT `fk_ol_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ol_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listeye dahil mükellefler
CREATE TABLE IF NOT EXISTS `odeme_listesi_mukellefleri` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liste_id`    INT UNSIGNED NOT NULL,
  `mukellef_id` INT UNSIGNED NOT NULL,
  `sira`        SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_olm` (`liste_id`,`mukellef_id`),
  KEY `fk_olm_mukellef` (`mukellef_id`),
  CONSTRAINT `fk_olm_liste` FOREIGN KEY (`liste_id`)
     REFERENCES `odeme_listeleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_olm_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Güncelleme tamamlandı.' AS sonuc;
