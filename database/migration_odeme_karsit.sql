-- =====================================================================
--  GÜNCELLEME 3: Ödeme Listesi (damga vergisi) + Karşıt İnceleme Takibi
--  Mevcut kurulumlar için:
--    mysql -u KULLANICI -p beyanname_takip < migration_odeme_karsit.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) DAMGA VERGİSİ SABİT TUTARLARI  (beyanname türü × yıl)
--    Tahakkuk tutarı damga hariç girilir; ödeme listesinde buradaki
--    tutar otomatik eklenir.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `damga_tutarlari` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `beyanname_turu_id` INT UNSIGNED NOT NULL,
  `yil`               SMALLINT UNSIGNED NOT NULL,
  `tutar`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `aciklama`          VARCHAR(200) NULL,
  `created_at`        DATETIME NULL,
  `updated_at`        DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_damga_tur_yil` (`beyanname_turu_id`,`yil`),
  CONSTRAINT `fk_damga_tur` FOREIGN KEY (`beyanname_turu_id`)
     REFERENCES `beyanname_turleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) beyanname_takip: ödeme alanları
--    tahakkuk_tutari  = DAMGA HARİÇ girilen tutar
--    damga_tutari     = kaydedildiği anda kopyalanan damga (tarihsel doğruluk)
--    odendi / odeme_tarihi = ödeme takibi
-- ---------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='beyanname_takip' AND COLUMN_NAME='damga_tutari');
SET @s = IF(@c=0,
 'ALTER TABLE `beyanname_takip`
    ADD COLUMN `damga_tutari` DECIMAL(12,2) NOT NULL DEFAULT 0.00
      COMMENT ''Onay anında kopyalanan damga vergisi'' AFTER `tahakkuk_tutari`,
    ADD COLUMN `odendi` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tahakkuk_fis_no`,
    ADD COLUMN `odeme_tarihi` DATE NULL AFTER `odendi`,
    ADD KEY `idx_takip_odendi` (`odendi`)',
 'SELECT ''beyanname_takip alanları zaten var''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- tahakkuk_tutari açıklamasını netleştir
ALTER TABLE `beyanname_takip`
  MODIFY COLUMN `tahakkuk_tutari` DECIMAL(15,2) NULL
  COMMENT 'Damga vergisi HARİÇ tahakkuk tutarı';

-- ---------------------------------------------------------------------
-- 3) KARŞIT İNCELEME TUTANAKLARI
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `karsit_inceleme` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`    INT UNSIGNED NOT NULL,
  `ymm_adi`        VARCHAR(200) NOT NULL COMMENT 'Tutanağı gönderen YMM / büro',
  `gelis_tarihi`   DATE         NOT NULL,
  `son_cevap_tarihi` DATE       NULL COMMENT 'Cevaplanması gereken son tarih',
  `gonderim_tarihi`  DATE       NULL,
  `durum`          ENUM('CEVAP_BEKLIYOR','HAZIRLANIYOR','GONDERILDI','IPTAL')
                   NOT NULL DEFAULT 'CEVAP_BEKLIYOR',
  `not_metni`      TEXT         NULL,
  `kaydeden_id`    INT UNSIGNED NULL,
  `created_at`     DATETIME     NULL,
  `updated_at`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ki_mukellef` (`mukellef_id`),
  KEY `idx_ki_durum` (`durum`),
  KEY `idx_ki_gelis` (`gelis_tarihi`),
  CONSTRAINT `fk_ki_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ki_kaydeden` FOREIGN KEY (`kaydeden_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) Ayarlar
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `ayarlar` (`anahtar`,`deger`,`aciklama`) VALUES
('damga_otomatik_ekle','1','Ödeme listesinde damga vergisini tahakkuk tutarına ekle (1/0)'),
('karsit_uyari_gun','7','Karşıt inceleme cevabı için son X gün kala uyar');

SELECT 'Güncelleme tamamlandı.' AS sonuc;
