-- =====================================================================
--  GÜNCELLEME 5:
--   1) Beyanname durumlarından "Gönderildi" kaldırıldı (Onaylandı yeterli)
--   2) Beyan (onay) son tarihi ile ÖDEME son tarihi ayrıldı
--      Örn. SGK: onay 26'sı (MUHSGK ile aynı), ödeme ay sonu
--   3) Özel ödeme kalemleri (Bağkur, MTV, harç vb.)
--
--  mysql -u KULLANICI -p beyanname_takip < migration_odeme_tarihi_ozel.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) beyanname_turleri: AYRI ÖDEME TARİHİ KURALI
--    NULL ise ödeme tarihi = beyan son tarihi (çoğu beyanname böyledir)
-- ---------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='beyanname_turleri'
            AND COLUMN_NAME='odeme_offset_ay');
SET @s = IF(@c=0,
 'ALTER TABLE `beyanname_turleri`
    ADD COLUMN `odeme_offset_ay` TINYINT UNSIGNED NULL
      COMMENT ''Ödeme: dönem bitişinden kaç ay sonra (NULL = beyan ile aynı)'' AFTER `son_gun`,
    ADD COLUMN `odeme_son_gun_tipi` ENUM(''GUN'',''AY_SONU'') NULL AFTER `odeme_offset_ay`,
    ADD COLUMN `odeme_son_gun` TINYINT UNSIGNED NULL AFTER `odeme_son_gun_tipi`',
 'SELECT ''beyanname_turleri ödeme alanları zaten var''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- SGK: beyan/onay son günü MUHSGK ile aynı (26), ödeme ay sonu
UPDATE `beyanname_turleri`
   SET `son_gun_tipi` = 'GUN',
       `son_gun` = 26,
       `odeme_offset_ay` = 1,
       `odeme_son_gun_tipi` = 'AY_SONU',
       `odeme_son_gun` = 31,
       `aciklama` = 'Onay: izleyen ayın 26''sı (MUHSGK ile birlikte) — Ödeme: izleyen ayın son günü'
 WHERE `kod` = 'SGK';

-- ---------------------------------------------------------------------
-- 2) beyanname_takip: hesaplanmış ödeme son tarihi
-- ---------------------------------------------------------------------
SET @c2 = (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='beyanname_takip'
             AND COLUMN_NAME='odeme_son_tarih');
SET @s2 = IF(@c2=0,
 'ALTER TABLE `beyanname_takip`
    ADD COLUMN `odeme_son_tarih` DATE NULL
      COMMENT ''Ödeme son günü (boşsa beyan son tarihi geçerli)'' AFTER `son_tarih`,
    ADD KEY `idx_takip_odeme_tarih` (`odeme_son_tarih`)',
 'SELECT ''odeme_son_tarih zaten var''');
PREPARE st2 FROM @s2; EXECUTE st2; DEALLOCATE PREPARE st2;

-- ---------------------------------------------------------------------
-- 3) "Gönderildi" durumunu kaldır -> Onaylandı'ya taşı
-- ---------------------------------------------------------------------
UPDATE `beyanname_takip` SET `durum` = 'ONAYLANDI' WHERE `durum` = 'GONDERILDI';

ALTER TABLE `beyanname_takip`
  MODIFY COLUMN `durum` ENUM('BEKLIYOR','HAZIR','ONAYLANDI','VERILMEYECEK')
  NOT NULL DEFAULT 'BEKLIYOR';

-- ---------------------------------------------------------------------
-- 4) ÖZEL ÖDEME KALEMLERİ (Bağkur, MTV, harç, ceza vb.)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ozel_odemeler` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`   INT UNSIGNED NOT NULL,
  `baslik`        VARCHAR(200) NOT NULL COMMENT 'Örn: Bağkur Primi, MTV 1. Taksit',
  `aciklama`      VARCHAR(300) NULL,
  `tutar`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `son_tarih`     DATE         NOT NULL,
  `donem_etiketi` VARCHAR(60)  NULL COMMENT 'Örn: Nisan 2026',
  `durum`         ENUM('BEKLIYOR','ONAYLANDI','IPTAL') NOT NULL DEFAULT 'ONAYLANDI',
  `odendi`        TINYINT(1)   NOT NULL DEFAULT 0,
  `odeme_tarihi`  DATE         NULL,
  `tekrar`        ENUM('YOK','AYLIK') NOT NULL DEFAULT 'YOK',
  `kaydeden_id`   INT UNSIGNED NULL,
  `created_at`    DATETIME     NULL,
  `updated_at`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oo_mukellef` (`mukellef_id`),
  KEY `idx_oo_tarih` (`son_tarih`),
  KEY `idx_oo_odendi` (`odendi`),
  CONSTRAINT `fk_oo_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oo_kaydeden` FOREIGN KEY (`kaydeden_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Güncelleme tamamlandı.' AS sonuc;
