-- =====================================================================
--  KULLANICI GÖRÜNÜM TERCİHLERİ (TEMA)
--  ---------------------------------------------------------------------
--  Kullanıcı bazlı renk şablonu: açık/karanlık/sistem modu, renk paleti
--  ve yan menü zemini. Tercih profil sayfasından (🎨 Görünüm kartı) veya
--  üst bardaki hızlı geçiş düğmesinden değiştirilir.
--
--  NOT: Yeni tablo kurulmaz; mevcut `kullanicilar` tablosuna 4 kolon
--       eklenir. Tekrar koşulabilir (kolon varsa atlanır).
--
--  Varsayılanlar:
--    tema      = 'sistem'  → işletim sistemi koyuysa karanlık tema
--    palet     = 'mavi'    → bugünkü vurgu rengi
--    yan_menu  = 'koyu'    → bugünkü lacivert yan menü
--    hizli_gecis = 1       → üst barda 🌙/☀️ düğmesi görünür
--
--  Kurulum:  mariadb beyanname_takip < database/migration_kullanici_tema.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) tema — 'acik' | 'karanlik' | 'sistem'
-- ---------------------------------------------------------------------
SET @var = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'kullanicilar'
              AND COLUMN_NAME  = 'tema');

SET @sql = IF(@var = 0,
  "ALTER TABLE `kullanicilar`
     ADD COLUMN `tema` VARCHAR(10) NOT NULL DEFAULT 'sistem'
     COMMENT 'Görünüm: acik | karanlik | sistem' AFTER `telefon`",
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 2) palet — renk şablonu anahtarı (KullaniciModel::PALETLER ile aynı)
-- ---------------------------------------------------------------------
SET @var = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'kullanicilar'
              AND COLUMN_NAME  = 'palet');

SET @sql = IF(@var = 0,
  "ALTER TABLE `kullanicilar`
     ADD COLUMN `palet` VARCHAR(20) NOT NULL DEFAULT 'mavi'
     COMMENT 'Renk şablonu: mavi | turkuaz | yesil | mor | turuncu | bordo | grafit'
     AFTER `tema`",
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 3) yan_menu — 'koyu' | 'acik'
-- ---------------------------------------------------------------------
SET @var = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'kullanicilar'
              AND COLUMN_NAME  = 'yan_menu');

SET @sql = IF(@var = 0,
  "ALTER TABLE `kullanicilar`
     ADD COLUMN `yan_menu` VARCHAR(10) NOT NULL DEFAULT 'koyu'
     COMMENT 'Yan menü zemini: koyu | acik' AFTER `palet`",
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 4) hizli_gecis — üst bardaki tema düğmesi görünsün mü?
-- ---------------------------------------------------------------------
SET @var = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'kullanicilar'
              AND COLUMN_NAME  = 'hizli_gecis');

SET @sql = IF(@var = 0,
  "ALTER TABLE `kullanicilar`
     ADD COLUMN `hizli_gecis` TINYINT(1) NOT NULL DEFAULT 1
     COMMENT 'Üst barda hızlı tema geçiş düğmesi görünsün mü?' AFTER `yan_menu`",
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 5) Mevcut kayıtlar varsayılanı alsın (kolonlar yeni eklendiyse)
-- ---------------------------------------------------------------------
UPDATE `kullanicilar`
   SET `tema`        = COALESCE(NULLIF(`tema`, ''), 'sistem'),
       `palet`       = COALESCE(NULLIF(`palet`, ''), 'mavi'),
       `yan_menu`    = COALESCE(NULLIF(`yan_menu`, ''), 'koyu'),
       `hizli_gecis` = COALESCE(`hizli_gecis`, 1);

-- ---------------------------------------------------------------------
-- 6) Doğrulama
-- ---------------------------------------------------------------------
SELECT COLUMN_NAME AS `kolon`, COLUMN_TYPE AS `tip`,
       COLUMN_DEFAULT AS `varsayilan`, COLUMN_COMMENT AS `aciklama`
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'kullanicilar'
   AND COLUMN_NAME IN ('tema', 'palet', 'yan_menu', 'hizli_gecis')
 ORDER BY ORDINAL_POSITION;
