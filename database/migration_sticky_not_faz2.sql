-- =====================================================================
--  YAPIŞKAN NOTLAR — FAZ 2 (hatırlatma "tamam" işareti)
--  ---------------------------------------------------------------------
--  Faz 2'nin diğer özellikleri (arşiv, başlık, hatırlatma tarihi) Faz 1
--  tablosundaki kolonları kullanır; bu migration yalnız EK kolonu ekler:
--
--    hatirlat_tamam_at : hatırlatmanın "tamam" denme zamanı (NULL = bekliyor)
--
--  "Tamam" tarihi SİLMEZ; yalnız hatırlatmayı rozetten ve pencereden düşürür.
--
--  NOT: information_schema / PREPARE / EXECUTE KULLANILMAZ.
--       ADD COLUMN IF NOT EXISTS → tekrar koşulabilir (idempotent).
--       (MariaDB 10.0+; MySQL kullanıyorsanız IF NOT EXISTS'i silin.)
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `kisisel_sticky_notlar`
  ADD COLUMN IF NOT EXISTS `hatirlat_tamam_at` DATETIME NULL
      COMMENT 'Hatırlatmanın tamam denme zamanı (NULL = bekliyor)'
      AFTER `hatirlat_tarih`;

-- Doğrulama (yalnız kendi tablomuz; yetki gerektirmez)
SHOW COLUMNS FROM `kisisel_sticky_notlar`;
