-- =====================================================================
--  GÜNCELLEME 22: AJANDA / HATIRLATICI
--
--  Beyanname, e-defter, evrak ve karşıt inceleme uyarıları zaten sistemde
--  otomatik üretiliyor. Ajanda bunları TEKRARLAMAZ; elle girilen işler
--  içindir: "vergi dairesine uğra", "sözleşme yenile", "müşteriyi ara".
--
--  Üç görünürlük düzeyi (kullanıcı kararı):
--    kisisel  → yalnız oluşturan görür
--    genel    → tüm büro görür
--    gorev    → atanan kişi + atayan görür
--    musavir  → seçilen mali müşavirin ekibi görür
--
--  Tekrar: tek seferlik / günlük / haftalık / aylık / yıllık.
--  Tekrarlı kayıtta "yapıldı" işaretlenince sonraki tarihe ötelenir.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_ajanda.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1) Ajanda kayıtları
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ajanda` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,

  `baslik`        VARCHAR(200) NOT NULL,
  `aciklama`      TEXT NULL,

  -- Zaman
  `tarih`         DATE NOT NULL COMMENT 'İşin yapılacağı gün',
  `saat`          TIME NULL COMMENT 'Boşsa gün boyu',
  `bitis_tarihi`  DATE NULL COMMENT 'Çok günlü işlerde son gün',

  -- Görünürlük
  `gorunurluk`    ENUM('kisisel','genel','gorev','musavir') NOT NULL DEFAULT 'kisisel',
  `atanan_id`     INT UNSIGNED NULL COMMENT 'gorev tipinde işi yapacak kullanıcı',
  `musavir_id`    INT UNSIGNED NULL COMMENT 'musavir tipinde hangi müşavirin ekibi',

  -- Sınıflandırma
  `oncelik`       ENUM('dusuk','normal','yuksek','acil') NOT NULL DEFAULT 'normal',
  `etiket`        VARCHAR(60) NULL COMMENT 'Serbest etiket: Toplantı, Ödeme, Arama…',
  `renk`          VARCHAR(9) NULL COMMENT 'Takvimde gösterim rengi (#rrggbb)',

  -- Bağlantı
  `mukellef_id`   INT UNSIGNED NULL COMMENT 'İş bir mükellefle ilgiliyse',

  -- Tekrar
  `tekrar`        ENUM('yok','gunluk','haftalik','aylik','yillik') NOT NULL DEFAULT 'yok',
  `tekrar_bitis`  DATE NULL COMMENT 'Tekrar bu tarihten sonra durur',

  -- Hatırlatma
  `hatirlat_gun`  TINYINT UNSIGNED NOT NULL DEFAULT 0
                    COMMENT 'Kaç gün önceden panelde uyarsın (0 = yalnız o gün)',

  -- Durum
  `durum`         ENUM('BEKLIYOR','YAPILDI','IPTAL') NOT NULL DEFAULT 'BEKLIYOR',
  `yapildi_at`    DATETIME NULL,
  `yapan_id`      INT UNSIGNED NULL,

  `olusturan_id`  INT UNSIGNED NOT NULL,
  `created_at`    DATETIME NULL,
  `updated_at`    DATETIME NULL,
  `deleted_at`    DATETIME NULL COMMENT 'Yumuşak silme (çöp kutusu)',

  PRIMARY KEY (`id`),
  KEY `idx_ajanda_tarih` (`tarih`,`durum`),
  KEY `idx_ajanda_olusturan` (`olusturan_id`,`tarih`),
  KEY `idx_ajanda_atanan` (`atanan_id`,`durum`),
  KEY `idx_ajanda_gorunurluk` (`gorunurluk`,`tarih`),
  KEY `idx_ajanda_mukellef` (`mukellef_id`),
  KEY `idx_ajanda_musavir` (`musavir_id`),

  CONSTRAINT `fk_ajanda_olusturan` FOREIGN KEY (`olusturan_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ajanda_atanan` FOREIGN KEY (`atanan_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ajanda_yapan` FOREIGN KEY (`yapan_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ajanda_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ajanda_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  2) Dosya ekleri
--     Dosyalar writable/uploads/ajanda/ altında saklanır; tabloda yalnız
--     üstveri tutulur.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ajanda_ek` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ajanda_id`  INT UNSIGNED NOT NULL,
  `dosya_adi`  VARCHAR(255) NOT NULL COMMENT 'Kullanıcının gördüğü ad',
  `saklanan`   VARCHAR(255) NOT NULL COMMENT 'Diskteki benzersiz ad',
  `boyut`      INT UNSIGNED NOT NULL DEFAULT 0,
  `tur`        VARCHAR(100) NULL COMMENT 'MIME türü',
  `yukleyen_id` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ek_ajanda` (`ajanda_id`),
  CONSTRAINT `fk_ek_ajanda` FOREIGN KEY (`ajanda_id`)
     REFERENCES `ajanda` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ek_yukleyen` FOREIGN KEY (`yukleyen_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  3) Giriş uyarısı okundu bilgisi
--     "Bugünkü işleriniz" penceresi her kullanıcıya GÜNDE BİR kez çıkar.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ajanda_uyari_okundu` (
  `kullanici_id` INT UNSIGNED NOT NULL,
  `tarih`        DATE NOT NULL,
  `created_at`   DATETIME NULL,
  PRIMARY KEY (`kullanici_id`,`tarih`),
  CONSTRAINT `fk_uyari_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  4) Ayarlar
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `ayarlar` (`anahtar`,`deger`,`aciklama`) VALUES
('ajanda_panel_gun','7','Panelde kaç günlük ajanda gösterilsin'),
('ajanda_giris_uyari','1','Girişte bugünün işleri penceresi açılsın mı (1=evet)'),
('ajanda_ek_boyut','5120','Ajanda dosya eki en büyük boyut (KB)');

SELECT 'Güncelleme tamamlandı.' AS sonuc;
