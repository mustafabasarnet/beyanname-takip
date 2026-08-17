-- =====================================================================
--  GÜNCELLEME 20: EVRAK TAKİBİNDE "BU MÜKELLEFTE YOK" (MUAFİYET)
--
--  Amaç: Bankası olmayan, çek/senet kullanmayan ya da bordrosu bulunmayan
--  mükelleflerin ilgili hücreleri artık KIRMIZI (eksik) görünmez; taralı
--  gri "takip dışı" hücre olarak pasif görünür ve sayaçlara girmez.
--
--  İki katman vardır:
--    1) KALICI  : mukellef_evrak_muafiyet tablosu — her ay geçerli.
--                 (Mükellef kartı → "Takip Edilecek Evrak Türleri")
--    2) DÖNEMSEL: evrak_takip.durum = 'YOK' — yalnız o ayı etkiler ve
--                 kalıcı ayarı EZER. (Çizelgede hücreye sağ tık)
--
--  Etkin durum hesabı:
--    • O ay kaydı varsa      → kaydın durumu (GELDI / GELMEDI / YOK)
--    • O ay kaydı yoksa      → kalıcı muafiyet varsa YOK, yoksa GELMEDI
--
--  mysql -u KULLANICI -p beyanname_takip < migration_evrak_muafiyet.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1) Kalıcı muafiyet tablosu
--     Satır VARSA "bu mükellefte bu evrak türü yok" demektir.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mukellef_evrak_muafiyet` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`   INT UNSIGNED NOT NULL,
  `evrak_turu_id` INT UNSIGNED NOT NULL,
  `aciklama`      VARCHAR(200) NULL COMMENT 'Neden takip edilmiyor (örn. banka hesabı yok)',
  `created_at`    DATETIME NULL,
  `updated_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_muafiyet` (`mukellef_id`,`evrak_turu_id`),
  KEY `fk_muaf_tur` (`evrak_turu_id`),
  CONSTRAINT `fk_muaf_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_muaf_tur` FOREIGN KEY (`evrak_turu_id`)
     REFERENCES `evrak_turleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mükellefte hiç bulunmayan evrak türleri (takip dışı)';

-- ---------------------------------------------------------------------
--  2) evrak_takip.durum ENUM'una 'YOK' eklenir (dönemsel istisna)
--     Not: ENUM sırası bozulmaz, yalnızca yeni değer eklenir.
-- ---------------------------------------------------------------------
SET @t = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evrak_takip'
            AND COLUMN_NAME='durum');
SET @s = IF(@t IS NOT NULL AND LOCATE('YOK', @t) = 0,
 'ALTER TABLE `evrak_takip`
    MODIFY COLUMN `durum` ENUM(''GELMEDI'',''GELDI'',''YOK'')
      NOT NULL DEFAULT ''GELMEDI''
      COMMENT ''YOK = bu dönem bu mükellefte takip edilmiyor''',
 'SELECT ''evrak_takip.durum zaten YOK değerini içeriyor''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
--  3) Ayar: muaf hücreler yazdırma/Excel çıktısında nasıl görünsün
-- ---------------------------------------------------------------------
INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`)
VALUES ('evrak_muaf_etiket', 'Takip dışı',
        'Evrak çizelgesinde takip edilmeyen (muaf) hücrelerin Excel/yazdırma karşılığı')
ON DUPLICATE KEY UPDATE `aciklama` = VALUES(`aciklama`);

-- ---------------------------------------------------------------------
--  BİLGİ — Toplu muafiyet tanımlama örnekleri
--  (Gerekiyorsa yorumu kaldırıp çalıştırın.)
-- ---------------------------------------------------------------------
/*
-- Bordro bilgisi olmayan (hiç SGK sicili girilmemiş) mükellefleri
-- "Personel Bordro Bilgileri" türünden muaf tut:
INSERT IGNORE INTO mukellef_evrak_muafiyet (mukellef_id, evrak_turu_id, aciklama, created_at, updated_at)
SELECT m.id, t.id, 'SGK işyeri sicili yok', NOW(), NOW()
  FROM mukellefler m
  CROSS JOIN evrak_turleri t
 WHERE m.deleted_at IS NULL
   AND (m.sgk_isyeri_sicil IS NULL OR m.sgk_isyeri_sicil = '')
   AND t.kisa_ad = 'Bordro';

-- Muafiyet tanımlandıktan sonra geçmişteki BOŞ (gelmedi) kayıtları temizle;
-- gerçekten "geldi" işaretlenmiş kayıtlara dokunulmaz:
DELETE e FROM evrak_takip e
  JOIN mukellef_evrak_muafiyet mu
    ON mu.mukellef_id = e.mukellef_id AND mu.evrak_turu_id = e.evrak_turu_id
 WHERE e.durum = 'GELMEDI';
*/
