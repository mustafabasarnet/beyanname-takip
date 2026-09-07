-- =====================================================================
--  KİŞİSEL TO-DO — ÖNCELİK / ETİKET / SON TARİH ALANLARI
--
--  kisisel_notlar tablosundaki görevlere (tur='gorev') ek sınıflandırma
--  alanları ekler. Idempotent: birden fazla kez çalıştırılabilir.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_kisisel_todo_alan.sql
-- =====================================================================

SET NAMES utf8mb4;

-- Öncelik
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='kisisel_notlar' AND COLUMN_NAME='oncelik');
SET @s = IF(@c=0,
 'ALTER TABLE `kisisel_notlar` ADD COLUMN `oncelik` ENUM(''dusuk'',''normal'',''yuksek'',''acil'') NOT NULL DEFAULT ''normal'' COMMENT ''Görev önceliği'' AFTER `baslik`',
 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Etiket
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='kisisel_notlar' AND COLUMN_NAME='etiket');
SET @s = IF(@c=0,
 'ALTER TABLE `kisisel_notlar` ADD COLUMN `etiket` VARCHAR(60) NULL COMMENT ''Serbest etiket: Toplantı, Arama, Ödeme…'' AFTER `oncelik`',
 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Son tarih
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='kisisel_notlar' AND COLUMN_NAME='son_tarih');
SET @s = IF(@c=0,
 'ALTER TABLE `kisisel_notlar` ADD COLUMN `son_tarih` DATE NULL COMMENT ''Görevin tamamlanması gereken son gün'' AFTER `etiket`',
 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SELECT 'Güncelleme tamamlandı.' AS sonuc;
