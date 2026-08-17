-- =====================================================================
--  GÜNCELLEME 4: Muhasebe Ücreti + Takip Başlangıç Tarihi
--  Mevcut kurulumlar için:
--    mysql -u KULLANICI -p beyanname_takip < migration_ucret_takip.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) mukellefler: muhasebe ücreti + takip başlangıç tarihi
--
--    muhasebe_ucreti     : Aylık sözleşme ücreti (ödeme bildiriminde
--                          isteğe bağlı eklenir)
--    takip_baslangic     : Bu tarihten ÖNCEKİ dönemler için beyanname
--                          satırı oluşturulmaz. İşe başlama eski olsa da
--                          takibi sonradan devraldıysanız kullanın.
-- ---------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler'
            AND COLUMN_NAME='muhasebe_ucreti');
SET @s = IF(@c=0,
 'ALTER TABLE `mukellefler`
    ADD COLUMN `muhasebe_ucreti` DECIMAL(12,2) NULL
      COMMENT ''Aylık muhasebe (sözleşme) ücreti'' AFTER `sgk_isyeri_sicil`,
    ADD COLUMN `ucret_aciklama` VARCHAR(200) NULL AFTER `muhasebe_ucreti`,
    ADD COLUMN `takip_baslangic` DATE NULL
      COMMENT ''Bu tarihten önceki dönemler oluşturulmaz'' AFTER `ise_baslama_tarihi`',
 'SELECT ''mukellefler alanları zaten var''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 2) Ayarlar
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `ayarlar` (`anahtar`,`deger`,`aciklama`) VALUES
('bildirim_ucret_varsayilan','0','Ödeme bildiriminde muhasebe ücreti varsayılan olarak işaretli gelsin (1/0)');

SELECT 'Güncelleme tamamlandı.' AS sonuc;
