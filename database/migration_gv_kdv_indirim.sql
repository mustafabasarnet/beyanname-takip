-- =====================================================================
--  GÜNCELLEME 16: GELİR VERGİSİ — MEVZUATA BAĞLI İNDİRİMLER + KDV TABLOSU
--
--  Üç değişiklik:
--
--  1) İki yeni indirim alanı (GVK md.89):
--       sigorta_primi      → Şahıs/hayat sigorta primi  (kârın %15'ini aşamaz)
--       egitim_saglik      → Eğitim ve sağlık harcaması (kârın %10'unu aşamaz)
--     Sınırlar KAYDEDİLMEZ, her hesapta yeniden uygulanır — gider/hasılat
--     değişince sınır da değişmelidir.
--
--  2) Kullanılmayan alanlar PASİFLENİR (silinmez!):
--       gecmis_yil_zarari, gecici_vergi, diger_indirim
--     Sütunlar veride kalır (geçmiş kayıt bozulmasın) ama ekranda görünmez
--     ve hesaba KATILMAZ.
--
--  3) Aylık KDV tablosu:
--       musavir_kdv → yıl + ay bazında ödenen ve indirilecek KDV
--     Yıllık toplamı, stopajla birlikte yıl içi vergi yükünü verir.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_gv_kdv_indirim.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1) Yeni indirim sütunları
--     ADD COLUMN IF NOT EXISTS: MariaDB 10.0+ destekler.
-- ---------------------------------------------------------------------
ALTER TABLE `musavir_gelir_gider`
  ADD COLUMN IF NOT EXISTS `sigorta_primi` DECIMAL(16,2) NOT NULL DEFAULT 0
      COMMENT 'Şahıs/hayat sigorta primi — beyan edilecek kârın %15''ini aşamaz (GVK 89/1)'
      AFTER `bagkur`,
  ADD COLUMN IF NOT EXISTS `egitim_saglik` DECIMAL(16,2) NOT NULL DEFAULT 0
      COMMENT 'Eğitim ve sağlık harcaması — beyan edilecek kârın %10''unu aşamaz (GVK 89/2)'
      AFTER `sigorta_primi`;

-- ---------------------------------------------------------------------
--  2) Pasiflenen sütunlar — VERİ SİLİNMEZ, yalnızca açıklama güncellenir.
--
--  Neden silmiyoruz? Bir büro bu alanları kullanmış olabilir; sütunu
--  düşürmek geçmiş kaydı yok eder. Uygulama artık okumuyor/yazmıyor.
-- ---------------------------------------------------------------------
ALTER TABLE `musavir_gelir_gider`
  MODIFY COLUMN `gecmis_yil_zarari` DECIMAL(16,2) NOT NULL DEFAULT 0
      COMMENT 'PASİF (16. güncelleme) — ekranda gösterilmez, hesaba katılmaz',
  MODIFY COLUMN `gecici_vergi` DECIMAL(16,2) NOT NULL DEFAULT 0
      COMMENT 'PASİF (16. güncelleme) — ekranda gösterilmez, hesaba katılmaz',
  MODIFY COLUMN `diger_indirim` DECIMAL(16,2) NOT NULL DEFAULT 0
      COMMENT 'PASİF (16. güncelleme) — ekranda gösterilmez, hesaba katılmaz';

-- Pasif alanları sıfırla: hesap dışı kaldıkları için eski değerler
-- raporlarda kafa karıştırmasın.
UPDATE `musavir_gelir_gider`
   SET `gecmis_yil_zarari` = 0, `gecici_vergi` = 0, `diger_indirim` = 0
 WHERE `gecmis_yil_zarari` <> 0 OR `gecici_vergi` <> 0 OR `diger_indirim` <> 0;

-- ---------------------------------------------------------------------
--  3) Aylık KDV tablosu
--
--  odenen      : o ay vergi dairesine ödenen KDV
--  indirilecek : o ayki indirilecek KDV
--
--  Kullanıcı kararı: İKİSİNİN TOPLAMI yıl içi vergi yükü sayılır ve
--  stopajla birlikte mahsuba girer.
--
--  Her müşavir + yıl + ay için TEK satır (benzersiz kısıt).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `musavir_kdv` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `musavir_id`  INT UNSIGNED NOT NULL,
  `yil`         SMALLINT UNSIGNED NOT NULL,
  `ay`          TINYINT UNSIGNED NOT NULL COMMENT '1-12',
  `odenen`      DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'O ay ödenen KDV',
  `indirilecek` DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'O ay indirilecek KDV',
  `aciklama`    VARCHAR(200) NULL,
  `created_at`  DATETIME NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kdv_musavir_donem` (`musavir_id`,`yil`,`ay`),
  KEY `idx_kdv_yil` (`yil`),
  CONSTRAINT `fk_kdv_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  4) Ayarlar
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `ayarlar` (`anahtar`,`deger`,`aciklama`) VALUES
('gv_sigorta_oran','15','Şahıs/hayat sigorta primi indirim üst oranı (%) — GVK 89/1'),
('gv_egitim_saglik_oran','10','Eğitim ve sağlık harcaması indirim üst oranı (%) — GVK 89/2');

SELECT 'Güncelleme tamamlandı.' AS sonuc;
