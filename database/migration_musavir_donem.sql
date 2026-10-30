-- =====================================================================
--  MÜKELLEF MÜŞAVİR SÖZLEŞME DÖNEMLERİ
--
--  Amaç: Bir mükellef yıl içinde mali müşavir değiştirebilir
--  (örn. Ocak–Şubat Ahmet, Mart–Aralık Mahmut). Yıllık sözleşme
--  ücreti (hedef) bu dönemlere AY bazında bölünür; makbuz kesen
--  müşavir ise makbuzun kendi musavir_id kolonunda kalır.
--
--  - Her mükellef için en az bir dönem olur (aşağıdaki backfill).
--  - Mevcut mükellefin bugünkü müşaviri, açık uçlu ilk dönem olur.
--    Geçmişte değişmiş mükelleflerin eski dönemlerini ekrandan
--    (Mükellef Detay → Müşavir Dönemleri) elle girmek gerekir.
--
--  phpMyAdmin / mysql ile çalıştırılabilir. Birden çok kez
--  çalıştırılabilir (idempotent). information_schema KULLANILMAZ.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `mukellef_musavir_donemleri` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id` INT UNSIGNED NOT NULL,
  `musavir_id`  INT UNSIGNED NOT NULL,
  `baslangic`   DATE NOT NULL COMMENT 'Dönemin ilk ayının 1. günü (YYYY-MM-01)',
  `bitis`       DATE NULL COMMENT 'Dönemin son ayının son günü; NULL = açık uçlu',
  `created_at`  DATETIME NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_donem_baslangic` (`mukellef_id`, `baslangic`),
  KEY `idx_donem_musavir` (`musavir_id`, `baslangic`),
  CONSTRAINT `fk_donem_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_donem_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: dönemi olmayan her mükellef için bugünkü müşaviriyle
-- açık uçlu ilk dönem. Zaten dönemi olanlara dokunulmaz.
INSERT INTO `mukellef_musavir_donemleri`
    (`mukellef_id`, `musavir_id`, `baslangic`, `bitis`, `created_at`, `updated_at`)
SELECT m.`id`, m.`musavir_id`, '2000-01-01', NULL, NOW(), NOW()
FROM `mukellefler` m
WHERE m.`musavir_id` IS NOT NULL
  AND NOT EXISTS (
        SELECT 1 FROM `mukellef_musavir_donemleri` d
        WHERE d.`mukellef_id` = m.`id`
  );

-- Eski makbuzlarda kesen müşavir boşsa (form varsayılanı eskiden "portföy sahibi"
-- idi), mükellefin o anki müşaviriyle doldurulur. Dolu kayıtlara dokunulmaz.
UPDATE `makbuzlar` mk
JOIN `mukellefler` m ON m.`id` = mk.`mukellef_id`
SET mk.`musavir_id` = m.`musavir_id`
WHERE mk.`musavir_id` IS NULL
  AND m.`musavir_id` IS NOT NULL;
