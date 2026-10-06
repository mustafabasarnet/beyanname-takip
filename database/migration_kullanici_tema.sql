-- =====================================================================
--  KULLANICI GÖRÜNÜM TERCİHLERİ (TEMA) — v2
--  ---------------------------------------------------------------------
--  Kullanıcı bazlı renk şablonu: açık/karanlık/sistem teması, renk paleti
--  ve yan menü zemini. Tercih, Profil → 🎨 Görünüm kartından veya üst
--  bardaki 🌙/☀️ hızlı geçiş düğmesinden değiştirilir.
--
--  NE DEĞİŞTİ (v1 → v2)
--  --------------------
--  v1 sürümü, "kolon/indeks var mı?" kontrolünü `information_schema`
--  üzerinden yapıyordu. Bazı sunucular veritabanı kullanıcısına bu şemaya
--  erişim vermez; phpMyAdmin'de şu hata alınır:
--
--      #1044 - Access denied for user 'root'@'localhost'
--              to database 'information_schema'
--
--  v2 bu kontrolü TAMAMEN KALDIRIR: information_schema, PREPARE/EXECUTE ve
--  kullanıcı değişkeni (@var) KULLANILMAZ. Yerine MariaDB 10.0+ ile gelen
--  "ADD COLUMN IF NOT EXISTS" kullanılır (projedeki diğer migration'larla
--  aynı kalıp — bkz. migration_gv_kdv_indirim.sql, migration_sicil_sade.sql).
--
--  SONUÇ: dosya tek seferde çalışır, hata vermez, tekrar koşulabilir
--  (idempotent) ve ilk çalıştırmada da ikinci çalıştırmada da aynı sonucu verir.
--
--  NASIL ÇALIŞTIRILIR
--  ------------------
--  phpMyAdmin : Veritabanını seçin → SQL sekmesi → bu dosyanın tamamını
--               yapıştırıp "Git" / "Çalıştır" düğmesine basın.
--  Komut satırı: mysql -u KULLANICI -p beyanname_takip < migration_kullanici_tema.sql
--
--  MySQL KULLANIYORSANIZ (MariaDB değil)
--  -------------------------------------
--  MySQL "ADD COLUMN IF NOT EXISTS" sözdizimini desteklemez. Bu durumda
--  aşağıdaki 4 ALTER cümlesinden "IF NOT EXISTS" ifadesini silin. Dosya ilk
--  kez çalıştırılıyorsa sorunsuz geçer; tekrar çalıştırırsanız "Duplicate
--  column name" hatası alırsınız — bu, kolonun zaten eklendiği anlamına
--  gelir, hatayı veren satırı atlayıp kalanlarla devam edin.
--
--  NOT: Yeni tablo kurulmaz; mevcut `kullanicilar` tablosuna 4 kolon eklenir.
--       Mevcut kayıtlar varsayılan değerleri otomatik alır (veri kaybı yok).
-- =====================================================================

-- Arayüz Türkçe karakterler için (bağlantı utf8mb4 değilse düzeltir)
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1) tema — 'acik' | 'karanlik' | 'sistem'
--     Varsayılan 'sistem': işletim sistemi koyuysa karanlık tema açılır.
-- ---------------------------------------------------------------------
ALTER TABLE `kullanicilar`
  ADD COLUMN IF NOT EXISTS `tema` VARCHAR(10) NOT NULL DEFAULT 'sistem'
      COMMENT 'Görünüm: acik | karanlik | sistem'
      AFTER `telefon`;

-- ---------------------------------------------------------------------
--  2) palet — renk şablonu anahtarı
--     (KullaniciModel::PALETLER ile aynı anahtarlar)
-- ---------------------------------------------------------------------
ALTER TABLE `kullanicilar`
  ADD COLUMN IF NOT EXISTS `palet` VARCHAR(20) NOT NULL DEFAULT 'mavi'
      COMMENT 'Renk şablonu: mavi | turkuaz | yesil | mor | turuncu | bordo | grafit'
      AFTER `tema`;

-- ---------------------------------------------------------------------
--  3) yan_menu — 'koyu' (bugünkü lacivert menü) | 'acik'
-- ---------------------------------------------------------------------
ALTER TABLE `kullanicilar`
  ADD COLUMN IF NOT EXISTS `yan_menu` VARCHAR(10) NOT NULL DEFAULT 'koyu'
      COMMENT 'Yan menü zemini: koyu | acik'
      AFTER `palet`;

-- ---------------------------------------------------------------------
--  4) hizli_gecis — üst bardaki 🌙/☀️ düğmesi görünsün mü?
-- ---------------------------------------------------------------------
ALTER TABLE `kullanicilar`
  ADD COLUMN IF NOT EXISTS `hizli_gecis` TINYINT(1) NOT NULL DEFAULT 1
      COMMENT 'Üst barda hızlı tema geçiş düğmesi görünsün mü?'
      AFTER `yan_menu`;

-- ---------------------------------------------------------------------
--  DOĞRULAMA
--  ---------------------------------------------------------------------
--  SHOW COLUMNS yalnız kendi tablonuz için çalışır; information_schema
--  yetkisi gerektirmez. Dört satır listelenmiyorsa yukarıdaki ALTER
--  cümleleri çalışmamış demektir (hata mesajına bakın).
-- ---------------------------------------------------------------------
SHOW COLUMNS FROM `kullanicilar`
  WHERE `Field` IN ('tema', 'palet', 'yan_menu', 'hizli_gecis');
