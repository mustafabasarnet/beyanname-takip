-- =====================================================================
--  GÜNCELLEME 14: SERBEST MESLEK MAKBUZU TAKİBİ
--
--  Amaç: "Hangi mükellefe yıl içinde ne kadar makbuz kestik, ne kadar
--  kaldı?" sorusunu yanıtlamak. Mali müşavir bazında da özetlenir.
--
--  İki tablo:
--    mukellef_ucretleri  → yıl bazında sözleşme ücreti (hedef tutar)
--    makbuzlar           → kesilen serbest meslek makbuzları
--
--  Ücret YIL BAZINDA tutulur çünkü tarifeler her yıl yeniden açıklanır;
--  geçmiş yılların tutarı bozulmadan kalmalıdır.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_makbuz.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1) Yıllık sözleşme ücretleri
--     Excel'den toplu yüklenebilir (tarife her yıl değiştiği için).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mukellef_ucretleri` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id` INT UNSIGNED NOT NULL,
  `yil`         SMALLINT UNSIGNED NOT NULL,
  `tutar`       DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Yıllık sözleşme ücreti (brüt, KDV hariç)',
  `aciklama`    VARCHAR(200) NULL,
  `created_at`  DATETIME NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mukellef_ucret` (`mukellef_id`,`yil`),
  KEY `idx_ucret_yil` (`yil`),
  CONSTRAINT `fk_ucret_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  2) Kesilen serbest meslek makbuzları
--
--  brut     : makbuzun brüt tutarı (stopaj matrahı)
--  stopaj   : GVK 94 gereği tevkifat (varsayılan %20)
--  kdv      : hesaplanan KDV (varsayılan %20)
--  net      : mükellefin ödediği tutar = brut - stopaj + kdv
--  Tutarlar KAYDEDİLİR (hesaplanmaz); oran sonradan değişse bile
--  geçmiş makbuz bozulmaz.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `makbuzlar` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`  INT UNSIGNED NOT NULL,
  `musavir_id`   INT UNSIGNED NULL COMMENT 'Makbuzu kesen mali müşavir',
  `yil`          SMALLINT UNSIGNED NOT NULL COMMENT 'Hangi yılın ücretine sayılacak',
  `ay`           TINYINT UNSIGNED NULL COMMENT '1-12 (boş olabilir)',
  `makbuz_no`    VARCHAR(40)  NULL,
  `tarih`        DATE NOT NULL,
  `brut`         DECIMAL(14,2) NOT NULL DEFAULT 0,
  `stopaj`       DECIMAL(14,2) NOT NULL DEFAULT 0,
  `kdv`          DECIMAL(14,2) NOT NULL DEFAULT 0,
  `net`          DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'brut - stopaj + kdv',
  `tahsil_edildi` TINYINT(1)   NOT NULL DEFAULT 0,
  `tahsil_tarihi` DATE NULL,
  `aciklama`     VARCHAR(250) NULL,
  `kaydeden_id`  INT UNSIGNED NULL,
  `created_at`   DATETIME NULL,
  `updated_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_makbuz_mukellef_yil` (`mukellef_id`,`yil`),
  KEY `idx_makbuz_musavir` (`musavir_id`,`yil`),
  KEY `idx_makbuz_tarih` (`tarih`),
  KEY `idx_makbuz_no` (`makbuz_no`),
  CONSTRAINT `fk_makbuz_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_makbuz_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  3) Ayarlar
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `ayarlar` (`anahtar`,`deger`,`aciklama`) VALUES
('makbuz_stopaj_oran','20','Serbest meslek makbuzu stopaj oranı (%)'),
('makbuz_kdv_oran','20','Serbest meslek makbuzu KDV oranı (%)'),
('makbuz_kdv_dahil','0','Excel''den gelen brüt tutar KDV dahil mi (1=evet)');

SELECT 'Güncelleme tamamlandı.' AS sonuc;
