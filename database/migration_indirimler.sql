-- =====================================================================
--  GÜNCELLEME 11: Mükellef bazlı indirim/kısıtlama takibi
--
--  Yıllık gelir/kurumlar ve geçici vergi beyannamelerinde mükellefe göre
--  uygulanan üç kalem takip edilir:
--
--    1) Bağkur primi indirimi        (GVK 89/1-... ; beyanname üzerinden)
--    2) Eğitim & sağlık harcamaları  (GVK 89/2 — beyan edilen gelirin %10'u)
--    3) Finansman gider kısıtlaması  (GVK 41/9 ve KVK 11/1-i)
--
--  Her biri için "uygulansın/uygulanmasın" bayrağı ve isteğe bağlı kısa
--  not tutulur. Not, çizelgedeki rozetin üzerine gelindiğinde görünür.
--
--  Rozetlerin hangi beyannamede çıkacağı MEVZUATA GÖRE ayrılmıştır:
--    - Bağkur ve Eğitim/Sağlık : YILLIK_GV, GELIR_GECICI  (gerçek kişi)
--    - Finansman gider kıs.    : YILLIK_GV, GELIR_GECICI,
--                                KURUMLAR, KURUM_GECICI
--  Bu eşleme uygulama tarafında (beyanname_helper.php) tanımlıdır.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_indirimler.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1) Bağkur primi indirimi
-- ---------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler'
            AND COLUMN_NAME='ind_bagkur');
SET @s = IF(@c=0,
 'ALTER TABLE `mukellefler`
    ADD COLUMN `ind_bagkur` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT ''Bağkur primi indirimi uygulanıyor'' AFTER `gg_not`,
    ADD COLUMN `ind_bagkur_not` VARCHAR(200) NULL AFTER `ind_bagkur`',
 'SELECT ''ind_bagkur zaten var''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
--  2) Eğitim & sağlık harcamaları indirimi
-- ---------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler'
            AND COLUMN_NAME='ind_egitim_saglik');
SET @s = IF(@c=0,
 'ALTER TABLE `mukellefler`
    ADD COLUMN `ind_egitim_saglik` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT ''Eğitim ve sağlık harcamaları indirimi (GVK 89/2)'' AFTER `ind_bagkur_not`,
    ADD COLUMN `ind_egitim_saglik_not` VARCHAR(200) NULL AFTER `ind_egitim_saglik`',
 'SELECT ''ind_egitim_saglik zaten var''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
--  3) Finansman gider kısıtlaması
--     (indirim değil KISITLAMA — bu yüzden rozeti uyarı renginde)
-- ---------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler'
            AND COLUMN_NAME='ind_finansman');
SET @s = IF(@c=0,
 'ALTER TABLE `mukellefler`
    ADD COLUMN `ind_finansman` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT ''Finansman gider kısıtlaması uygulanıyor (GVK 41/9, KVK 11/1-i)'' AFTER `ind_egitim_saglik_not`,
    ADD COLUMN `ind_finansman_not` VARCHAR(200) NULL AFTER `ind_finansman`',
 'SELECT ''ind_finansman zaten var''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
--  Dizin: "finansman kısıtlaması olanları listele" gibi sorgular için.
--  Üç bayrak tek dizinde toplandı; hiçbiri seçili değilse satır atlanır.
-- ---------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler'
            AND INDEX_NAME='idx_muk_indirim');
SET @s = IF(@c=0,
 'ALTER TABLE `mukellefler`
    ADD KEY `idx_muk_indirim` (`ind_bagkur`,`ind_egitim_saglik`,`ind_finansman`)',
 'SELECT ''idx_muk_indirim zaten var''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SELECT 'Güncelleme tamamlandı.' AS sonuc;
