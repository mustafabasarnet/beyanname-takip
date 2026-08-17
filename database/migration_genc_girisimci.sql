-- =====================================================================
--  GÜNCELLEME 8: Genç Girişimci Kazanç İstisnası (GVK mükerrer 20)
--
--  İstisna, faaliyete başlanan takvim yılından itibaren
--  3 VERGİLENDİRME DÖNEMİ boyunca geçerlidir.
--  Sistem hangi dönemde olunduğunu hesaplar ve süre dolduğunda uyarır.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_genc_girisimci.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler'
            AND COLUMN_NAME='genc_girisimci');
SET @s = IF(@c=0,
 'ALTER TABLE `mukellefler`
    ADD COLUMN `genc_girisimci` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT ''Genç girişimci kazanç istisnası (GVK mük. 20)'' AFTER `defter_tipi`,
    ADD COLUMN `gg_baslangic_yili` SMALLINT UNSIGNED NULL
      COMMENT ''İstisnanın başladığı takvim yılı'' AFTER `genc_girisimci`,
    ADD COLUMN `gg_not` VARCHAR(300) NULL AFTER `gg_baslangic_yili`,
    ADD KEY `idx_muk_gg` (`genc_girisimci`)',
 'SELECT ''genç girişimci alanları zaten var''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Ayar: istisna süresi (mevzuat değişirse buradan güncellenir)
INSERT IGNORE INTO `ayarlar` (`anahtar`,`deger`,`aciklama`) VALUES
('gg_istisna_donem','3','Genç girişimci istisnasının geçerli olduğu vergilendirme dönemi sayısı');

SELECT 'Güncelleme tamamlandı.' AS sonuc;
