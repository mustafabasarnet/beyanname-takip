-- =====================================================================
--  SİCİL DEĞİŞİKLİKLERİ & BİLDİRİM TAKİP MODÜLÜ
--
--  Mükellef → Sicil Değişikliği → (kurallara göre) N adet Bildirim Görevi
--
--  Katmanlar:
--    Tanım  : sicil_degisiklik_turleri, kurumlar        (yönetilebilir)
--    Kural  : sicil_bildirim_kurallari                  (süreler YALNIZ burada)
--    Kayıt  : sicil_degisiklikleri                      (geçmiş, eski/yeni)
--    Görev  : sicil_bildirim_gorevleri                  (üretilen, takip edilen)
--    Belge  : sicil_belgeleri                           (ek dosyalar)
--
--  Tasarım: docs/SICIL_VT_TASARIMI.md — onaylanmış şemadır.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_sicil.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) DEĞİŞİKLİK TÜRLERİ (tanım) — başlangıç tohumları
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sicil_degisiklik_turleri` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kod`        VARCHAR(50) NOT NULL COMMENT 'Makine dili; değişmez',
  `ad`         VARCHAR(150) NOT NULL COMMENT 'Ekranda görünen ad',
  `aciklama`   VARCHAR(300) NULL,
  `sira`       INT NOT NULL DEFAULT 0 COMMENT 'Liste sırası',
  `aktif`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sicil_tur_kod` (`kod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sicil_degisiklik_turleri` (`kod`,`ad`,`aciklama`,`sira`,`aktif`) VALUES
('ADRES_DEGISIKLIGI','Adres Değişikliği','Mükellefin işyeri adresinin değişmesi',10,1),
('ORTAK_DEGISIKLIGI','Ortak Değişikliği','Ortaklık yapısında hisse devri / ortak giriş-çıkışı',20,1),
('HISSE_ORAN_DEGISIKLIGI','Hisse / Oran Değişikliği','Mevcut ortakların pay oranlarının değişmesi',30,1),
('MUDUR_YONETICI_DEGISIKLIGI','Müdür / Yönetici Değişikliği','Müdür veya yönetim kurulu değişikliği',40,1),
('UNVAN_DEGISIKLIGI','Unvan Değişikliği','Ticaret unvanının değişmesi',50,1),
('FAALIYET_KONUSU_DEGISIKLIGI','Faaliyet Konusu Değişikliği','Şirket ana sözleşmesindeki faaliyet konusu değişikliği',60,1),
('NACE_DEGISIKLIGI','NACE Değişikliği','Faaliyet kodu (NACE) değişikliği',70,1),
('SERMAYE_DEGISIKLIGI','Sermaye Değişikliği','Sermaye artırımı / azaltımı',80,1),
('ISYERI_ACILISI','İşyeri Açılışı','Yeni işyeri açılışı',90,1),
('ISYERI_KAPANISI','İşyeri Kapanışı','İşyeri kapanışı / terk',100,1),
('SUBE_ACILISI','Şube Açılışı','Yeni şube açılışı',110,1),
('SUBE_KAPANISI','Şube Kapanışı','Şube kapanışı',120,1),
('SGK_ISYERI_BILGISI','SGK İşyeri Bilgisi Değişikliği','SGK işyeri dosyası bilgilerindeki değişiklik',130,1),
('DIGER','Diğer','Yukarıdakilere girmeyen sicil değişikliği',999,1)
ON DUPLICATE KEY UPDATE `ad` = VALUES(`ad`), `aciklama` = VALUES(`aciklama`),
                            `sira` = VALUES(`sira`), `aktif` = VALUES(`aktif`);

-- ---------------------------------------------------------------------
-- 2) BİLDİRİM KURUMLARI (tanım) — başlangıç örnekleri
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kurumlar` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kod`        VARCHAR(50) NOT NULL COMMENT 'Makine dili; değişmez',
  `ad`         VARCHAR(150) NOT NULL,
  `kisa_ad`    VARCHAR(30) NULL,
  `aciklama`   VARCHAR(300) NULL,
  `aktif`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kurum_kod` (`kod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `kurumlar` (`kod`,`ad`,`kisa_ad`,`aciklama`,`aktif`) VALUES
('TICARET_SICILI','Ticaret Sicili Müdürlüğü','Sicil','Tescil işlemlerinin yapıldığı kurum',1),
('VERGI_DAIRESI','Vergi Dairesi','Vergi','Mükellefin bağlı olduğu vergi dairesi',1),
('SGK','SGK İl Müdürlüğü','SGK','Sosyal Güvenlik Kurumu',1),
('BELEDIYE','Belediye','Belediye','İşyeri açma ve çalışma ruhsatı',1),
('TICARET_ODASI','Ticaret / Esnaf Odası','Oda','Oda kaydı ve tescil bildirimleri',1),
('BAGKUR','Bağ-Kur (SGK)','Bağ-Kur','Serbest meslek / şahıs sigortalılık',1),
('DIGER','Diğer Kurum','Diğer','Yukarıdakilere girmeyen kurum',1)
ON DUPLICATE KEY UPDATE `ad` = VALUES(`ad`), `kisa_ad` = VALUES(`kisa_ad`),
                            `aciklama` = VALUES(`aciklama`), `aktif` = VALUES(`aktif`);

-- ---------------------------------------------------------------------
-- 3) BİLDİRİM KURALLARI — süreler YALNIZ burada; kodda sabit YOK
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sicil_bildirim_kurallari` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `degisiklik_turu_id` INT UNSIGNED NOT NULL COMMENT 'Kuralın bağlı olduğu tür',
  `kurum_id`           INT UNSIGNED NOT NULL COMMENT 'Bildirim yapılacak kurum',
  `sure_tipi`          ENUM('GUN','IS_GUNU','TAKVIM_GUNU','AY','BELIRLI_TARIH') NOT NULL DEFAULT 'GUN',
  `sure_deger`         INT UNSIGNED NULL COMMENT 'Gün/ay değeri (BELIRLI_TARIH te NULL)',
  `belirli_tarih`      DATE NULL COMMENT 'Yalnız BELIRLI_TARIH türünde',
  `oncelik`            TINYINT NOT NULL DEFAULT 0,
  `aciklama`           VARCHAR(300) NULL,
  `aktif`              TINYINT(1) NOT NULL DEFAULT 1,
  `olusturan_id`       INT UNSIGNED NULL COMMENT 'Kuralı tanımlayan kullanıcı',
  `created_at`         DATETIME NULL,
  `updated_at`         DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sicil_kural_tur_kurum` (`degisiklik_turu_id`,`kurum_id`),
  KEY `idx_sicil_kural_kurum` (`kurum_id`),
  KEY `idx_sicil_kural_olusturan` (`olusturan_id`),
  CONSTRAINT `fk_sicil_kural_tur` FOREIGN KEY (`degisiklik_turu_id`)
     REFERENCES `sicil_degisiklik_turleri` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sicil_kural_kurum` FOREIGN KEY (`kurum_id`)
     REFERENCES `kurumlar` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sicil_kural_olusturan` FOREIGN KEY (`olusturan_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) SİCİL DEĞİŞİKLİKLERİ (çekirdek kayıt / geçmiş)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sicil_degisiklikleri` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`        INT UNSIGNED NOT NULL COMMENT 'Değişikliğin sahibi mükellef',
  `turu_id`            INT UNSIGNED NOT NULL COMMENT 'Değişiklik türü',
  `degisiklik_tarihi`  DATE NOT NULL COMMENT 'Tescil/karar tarihi — süre başlangıcı',
  `konu`               VARCHAR(300) NULL COMMENT 'Kısa özet',
  `aciklama`           TEXT NULL,
  `eski_deger`         TEXT NULL,
  `yeni_deger`         TEXT NULL,
  `detay_alanlar`      JSON NULL COMMENT 'Çok alanlı değişiklik: [{"alan":...,"eski":...,"yeni":...}]',
  `referans_no`        VARCHAR(100) NULL COMMENT 'Tescil/karar/yazı no',
  `durum`              ENUM('BEKLIYOR','ISLEMDE','TAMAM') NOT NULL DEFAULT 'BEKLIYOR',
  `kaydeden_id`        INT UNSIGNED NULL,
  `created_at`         DATETIME NULL,
  `updated_at`         DATETIME NULL,
  `deleted_at`         DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sicil_deg_mukellef_tarih` (`mukellef_id`,`degisiklik_tarihi`),
  KEY `idx_sicil_deg_tur` (`turu_id`),
  KEY `idx_sicil_deg_kaydeden` (`kaydeden_id`),
  CONSTRAINT `fk_sicil_deg_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sicil_deg_tur` FOREIGN KEY (`turu_id`)
     REFERENCES `sicil_degisiklik_turleri` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sicil_deg_kaydeden` FOREIGN KEY (`kaydeden_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) BİLDİRİM GÖREVLERİ (üretim / takip)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sicil_bildirim_gorevleri` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sicil_degisikligi_id` INT UNSIGNED NOT NULL COMMENT 'Hangi değişiklikten üretildi',
  `kural_id`             INT UNSIGNED NULL COMMENT 'Üretim kaynağı kural (manuel görevde NULL)',
  `kurum_id`             INT UNSIGNED NOT NULL COMMENT 'Hedef kurum (denormalize)',
  `gorev_no`             VARCHAR(40) NULL,
  `son_tarih`            DATE NULL COMMENT 'Hesaplanmış son bildirim tarihi',
  `asil_tarih`           DATE NULL COMMENT 'Tatil kaydırması öncesi yasal tarih',
  `kaydirma_nedeni`      VARCHAR(100) NULL,
  `durum`                ENUM('BEKLIYOR','HAZIR','GONDERILDI','TAMAM','GEREKSIZ') NOT NULL DEFAULT 'BEKLIYOR',
  `tamamlanma_tarihi`    DATETIME NULL,
  `yapan_id`             INT UNSIGNED NULL COMMENT 'Bildirimi fiilen yapan kullanıcı',
  `not_metni`            TEXT NULL,
  `created_at`           DATETIME NULL,
  `updated_at`           DATETIME NULL,
  `deleted_at`           DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sicil_gorev_degisiklik_kural` (`sicil_degisikligi_id`,`kural_id`),
  KEY `idx_sicil_gorev_kurum_durum` (`kurum_id`,`durum`),
  KEY `idx_sicil_gorev_durum_son` (`durum`,`son_tarih`),
  KEY `idx_sicil_gorev_yapan` (`yapan_id`),
  CONSTRAINT `fk_sicil_gorev_degisiklik` FOREIGN KEY (`sicil_degisikligi_id`)
     REFERENCES `sicil_degisiklikleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sicil_gorev_kural` FOREIGN KEY (`kural_id`)
     REFERENCES `sicil_bildirim_kurallari` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sicil_gorev_kurum` FOREIGN KEY (`kurum_id`)
     REFERENCES `kurumlar` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sicil_gorev_yapan` FOREIGN KEY (`yapan_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) BELGELER (değişiklik veya görev düzeyinde ek)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sicil_belgeleri` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sicil_degisikligi_id` INT UNSIGNED NULL COMMENT 'Değişiklik düzeyinde belge',
  `gorev_id`             INT UNSIGNED NULL COMMENT 'Görev düzeyinde belge',
  `dosya_adi`            VARCHAR(255) NOT NULL COMMENT 'Orijinal dosya adı',
  `saklanan`             VARCHAR(255) NOT NULL COMMENT 'Diskteki rastgele ad',
  `boyut`                INT UNSIGNED NOT NULL DEFAULT 0,
  `tur`                  VARCHAR(100) NULL,
  `yukleyen_id`          INT UNSIGNED NULL,
  `created_at`           DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sicil_belge_degisiklik` (`sicil_degisikligi_id`),
  KEY `idx_sicil_belge_gorev` (`gorev_id`),
  KEY `idx_sicil_belge_yukleyen` (`yukleyen_id`),
  CONSTRAINT `fk_sicil_belge_degisiklik` FOREIGN KEY (`sicil_degisikligi_id`)
     REFERENCES `sicil_degisiklikleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sicil_belge_gorev` FOREIGN KEY (`gorev_id`)
     REFERENCES `sicil_bildirim_gorevleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sicil_belge_yukleyen` FOREIGN KEY (`yukleyen_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Güncelleme tamamlandı.' AS sonuc;
