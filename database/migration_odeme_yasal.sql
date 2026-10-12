-- =====================================================================
--  ÖDEME LİSTESİ — AY ATAMASI (kanuni ödeme tarihi)
--  ---------------------------------------------------------------------
--  Sorun: SGK ödemesi ay sonunda yapılır (ör. Eylül dönemi → 31 Ekim).
--  31 Ekim hafta sonuna denk gelince son gün 2 Kasım'a kayıyor ve satır
--  EKİM yerine KASIM ödeme listesinde görünüyordu.
--
--  Çözüm: kaydırılmamış (kanuni) ödeme tarihi ayrı bir kolonda tutulur;
--  liste AY'ı buna göre belirler. Ekranda gösterilen son tarih yine
--  kaydırılmış gerçek tarihtir (odeme_son_tarih).
--
--  Bu migration:
--    1) odeme_yasal_tarih kolonunu ekler (yoksa)
--    2) MEVCUT SGK-benzeri satırları doldurur: kanuni ödeme = ayın son günü
--       (yalnız güvenli durumlarda: kaydırma en fazla 4 gün)
--
--  NOT: information_schema / PREPARE KULLANILMAZ. ADD COLUMN IF NOT EXISTS ve
--       UPDATE ... WHERE ... IS NULL → tekrar koşulabilir (idempotent).
--       Diğer dönemler "Toplu Dönem Üret" ile (durumlar korunarak) güncellenir.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `beyanname_takip`
  ADD COLUMN IF NOT EXISTS `odeme_yasal_tarih` DATE NULL
      COMMENT 'Kanuni ödeme son günü (kaydırılmamış) — ödeme listesi ay ataması'
      AFTER `odeme_son_tarih`;

-- Mevcut SGK-benzeri satırlar: kanuni ödeme = yasal beyan ayının son günü.
-- Koşul: ödeme türünde AY_SONU tanımı var, satır henüz doldurulmamış ve
-- kaydırma en fazla 4 gün (hafta sonu / tatil) ise.
UPDATE `beyanname_takip` bt
  JOIN `beyanname_turleri` t ON t.id = bt.beyanname_turu_id
   SET bt.odeme_yasal_tarih = LAST_DAY(bt.yasal_son_tarih)
 WHERE bt.odeme_yasal_tarih IS NULL
   AND bt.odeme_son_tarih IS NOT NULL
   AND bt.yasal_son_tarih IS NOT NULL
   AND t.odeme_offset_ay IS NOT NULL
   AND t.odeme_son_gun_tipi = 'AY_SONU'
   AND LAST_DAY(bt.yasal_son_tarih) <= bt.odeme_son_tarih
   AND DATEDIFF(bt.odeme_son_tarih, LAST_DAY(bt.yasal_son_tarih)) <= 4;

-- Doğrulama (yalnız kendi tablomuz)
SHOW COLUMNS FROM `beyanname_takip` LIKE 'odeme_yasal_tarih';
