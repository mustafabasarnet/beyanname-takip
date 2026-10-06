-- =====================================================================
--  FİRMA / BÜRO KİMLİĞİ — LOGO AYARI
--  ---------------------------------------------------------------------
--  Sol üstteki başlık artık Ayarlar → "Firma / Büro Adı" alanından
--  geliyor; solunda ise yüklediğiniz logo görünüyor.
--
--  Bu dosya:
--    1) `logo_dosya` ayar anahtarını ekler (yüklenen dosyanın adı)
--    2) `firma_adi` açıklamasını günceller (artık gerçekten kullanılıyor)
--
--  NOT: Yeni tablo AÇILMAZ; mevcut `ayarlar` tablosuna anahtar eklenir.
--  `information_schema` KULLANILMAZ (phpMyAdmin uyumlu), PREPARE/EXECUTE
--  yoktur → tek seferde çalışır ve tekrar koşulabilir (idempotent).
--
--  Kurulum:  mysql -u KULLANICI -p beyanname_takip < migration_logo.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1) Logo dosya adı (boş = logo yüklenmemiş → varsayılan simge gösterilir)
--     INSERT IGNORE: anahtar zaten varsa değer EZİLMEZ.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `ayarlar` (`anahtar`, `deger`, `aciklama`, `updated_at`) VALUES
('logo_dosya', '', 'Sol üstte gösterilen firma/büro logosunun dosya adı (boş = varsayılan simge)', NOW());

-- ---------------------------------------------------------------------
--  2) firma_adi açıklaması — bu alan artık sol üstte ve giriş ekranında
--     başlık olarak kullanılıyor.
-- ---------------------------------------------------------------------
UPDATE `ayarlar`
   SET `aciklama` = 'Sol üstte ve giriş ekranında görünen firma/büro adı'
 WHERE `anahtar` = 'firma_adi';

-- ---------------------------------------------------------------------
--  3) Boş kayıt oluşmuşsa varsayılanı yaz (silinmiş/authorsuz kurulumlar)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `ayarlar` (`anahtar`, `deger`, `aciklama`, `updated_at`) VALUES
('firma_adi', 'Beyanname Takip', 'Sol üstte ve giriş ekranında görünen firma/büro adı', NOW());

-- ---------------------------------------------------------------------
--  DOĞRULAMA (information_schema yetkisi gerekmez)
-- ---------------------------------------------------------------------
SELECT `anahtar`, `deger`, `aciklama`
  FROM `ayarlar`
 WHERE `anahtar` IN ('firma_adi', 'logo_dosya');
