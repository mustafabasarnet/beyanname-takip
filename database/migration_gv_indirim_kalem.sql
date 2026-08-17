-- =====================================================================
--  GÜNCELLEME 17: İNDİRİM KALEMLERİ (belge belge liste)
--
--  Mali müşavir artık eğitim-sağlık harcamalarını ve şahıs/hayat sigorta
--  primlerini TEK TUTAR yerine BELGE BELGE girer:
--      tarih · tür · açıklama · tutar
--
--  Listenin toplamı, gelir vergisi hesabındaki ilgili indirim alanına
--  otomatik yazılır ve mevzuat sınırı (%15 / %10) yine uygulanır.
--
--  ÖNCELİK KURALI (kullanıcı kararı):
--    Listede satır VARSA  → liste toplamı kullanılır
--    Liste BOŞSA          → musavir_gelir_gider'deki elle girilen tutar
--  Böylece eski kayıtlar bozulmaz, liste kullanmayan büro etkilenmez.
--
--  Tek tablo iki kalemi de tutar (`kalem` sütunu ayırır) — şema sade kalsın,
--  ileride yeni indirim türü eklemek kolay olsun diye.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_gv_indirim_kalem.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `musavir_indirim_kalem` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `musavir_id` INT UNSIGNED NOT NULL,
  `yil`        SMALLINT UNSIGNED NOT NULL COMMENT 'Hangi yılın beyanına sayılacak',
  `kalem`      ENUM('egitim_saglik','sigorta') NOT NULL DEFAULT 'egitim_saglik'
                 COMMENT 'egitim_saglik = GVK 89/2, sigorta = GVK 89/1',
  `tur`        ENUM('egitim','saglik','hayat','sahis','diger') NOT NULL DEFAULT 'egitim'
                 COMMENT 'Harcamanın alt türü (rapor için ayrı toplanır)',
  `tarih`      DATE NOT NULL,
  `aciklama`   VARCHAR(250) NULL COMMENT 'Belge/harcama açıklaması',
  `tutar`      DECIMAL(16,2) NOT NULL DEFAULT 0,
  `kaydeden_id` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ik_musavir_yil` (`musavir_id`,`yil`,`kalem`),
  KEY `idx_ik_tarih` (`tarih`),
  CONSTRAINT `fk_ik_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Güncelleme tamamlandı.' AS sonuc;
