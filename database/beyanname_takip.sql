-- =====================================================================
--  MÜKELLEF BEYANNAME & EVRAK TAKİP PROGRAMI
--  Veritabanı Şeması + Başlangıç Verileri
--  MySQL 5.7+ / MariaDB 10.3+   |   utf8mb4_unicode_ci
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `beyanname_takip`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `beyanname_takip`;

-- ---------------------------------------------------------------------
-- 1) MALİ MÜŞAVİRLER
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `musavirler`;
CREATE TABLE `musavirler` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unvan`         VARCHAR(20)  NULL COMMENT 'SMMM / YMM',
  `ad_soyad`      VARCHAR(150) NOT NULL,
  `buro_adi`      VARCHAR(200) NULL,
  `tc_kimlik`     VARCHAR(11)  NULL,
  `ruhsat_no`     VARCHAR(50)  NULL,
  `oda_sicil_no`  VARCHAR(50)  NULL,
  `telefon`       VARCHAR(30)  NULL,
  `eposta`        VARCHAR(150) NULL,
  `adres`         VARCHAR(500) NULL,
  `renk`          VARCHAR(9)   NOT NULL DEFAULT '#2563eb' COMMENT 'Listelerde rozet rengi',
  `aktif`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NULL,
  `updated_at`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_musavir_aktif` (`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) KULLANICILAR  (admin / musavir / personel)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `kullanicilar`;
CREATE TABLE `kullanicilar` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `musavir_id`    INT UNSIGNED NULL COMMENT 'Varsayılan (birincil) mali müşavir',
  `ad_soyad`      VARCHAR(150) NOT NULL,
  `kullanici_adi` VARCHAR(60)  NOT NULL,
  `eposta`        VARCHAR(150) NOT NULL,
  `sifre`         VARCHAR(255) NOT NULL COMMENT 'password_hash()',
  `rol`           ENUM('admin','musavir','personel') NOT NULL DEFAULT 'personel',
  `telefon`       VARCHAR(30)  NULL,
  `aktif`         TINYINT(1)   NOT NULL DEFAULT 1,
  `son_giris`     DATETIME     NULL,
  `created_at`    DATETIME     NULL,
  `updated_at`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kullanici_adi` (`kullanici_adi`),
  UNIQUE KEY `uq_kullanici_eposta` (`eposta`),
  KEY `fk_kullanici_musavir` (`musavir_id`),
  CONSTRAINT `fk_kullanici_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2b) KULLANICI ↔ MALİ MÜŞAVİR ERİŞİMİ  (çoklu)
--     Mali müşavir = portföy/kurum tanımı
--     Kullanıcı    = sisteme giren kişi
--     Bir kullanıcı birden çok müşavirin portföyüne erişebilir.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `kullanici_musavirleri`;
CREATE TABLE `kullanici_musavirleri` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_id` INT UNSIGNED NOT NULL,
  `musavir_id`   INT UNSIGNED NOT NULL,
  `created_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kullanici_musavir` (`kullanici_id`,`musavir_id`),
  KEY `fk_km_musavir` (`musavir_id`),
  CONSTRAINT `fk_km_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_km_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) MÜKELLEFLER
--    ise_baslama_tarihi / terk_tarihi -> dönem üretiminin kalbi
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `mukellefler`;
CREATE TABLE `mukellefler` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `musavir_id`         INT UNSIGNED NOT NULL COMMENT 'Portföy sahibi mali müşavir',
  `sorumlu_kullanici_id` INT UNSIGNED NULL COMMENT 'Takipten sorumlu personel',
  `kod`                VARCHAR(30)  NULL COMMENT 'Büro içi mükellef kodu',
  `unvan`              VARCHAR(250) NOT NULL,
  `mukellef_tipi`      ENUM('gercek','tuzel') NOT NULL DEFAULT 'gercek',
  `vergi_kimlik_no`    VARCHAR(11)  NULL,
  `tc_kimlik_no`       VARCHAR(11)  NULL,
  `vergi_dairesi`      VARCHAR(150) NULL,
  `defter_tipi`        ENUM('isletme','bilanco','serbest_meslek','basit_usul','diger')
                       NOT NULL DEFAULT 'isletme',
  -- E-defter berat takibi (YOK = e-defter mükellefi değil)
  `edefter_donem`      ENUM('YOK','AYLIK','UC_AYLIK') NOT NULL DEFAULT 'YOK' COMMENT 'E-defter berat dönemi',
  `edefter_sorumlu_id` INT UNSIGNED NULL COMMENT 'E-defterden sorumlu personel',
  `edefter_baslangic`  DATE         NULL COMMENT 'Bu tarihten önceki e-defter dönemleri üretilmez',
  `genc_girisimci`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Genç girişimci kazanç istisnası (GVK mük. 20)',
  `gg_baslangic_yili`  SMALLINT UNSIGNED NULL COMMENT 'İstisnanın başladığı takvim yılı',
  `gg_not`             VARCHAR(300) NULL,
  -- Yıllık gelir/kurumlar ve geçici vergi beyannamelerinde takip edilen
  -- indirim/kısıtlamalar. Rozet olarak yalnızca ilgili beyannamelerde çıkar.
  `ind_bagkur`            TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Bağkur primi indirimi uygulanıyor',
  `ind_bagkur_not`        VARCHAR(200) NULL,
  `ind_egitim_saglik`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Eğitim ve sağlık harcamaları indirimi (GVK 89/2)',
  `ind_egitim_saglik_not` VARCHAR(200) NULL,
  `ind_finansman`         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Finansman gider kısıtlaması (GVK 41/9, KVK 11/1-i)',
  `ind_finansman_not`     VARCHAR(200) NULL,
  `faaliyet_konusu`    VARCHAR(300) NULL,
  `nace_kodu`          VARCHAR(20)  NULL,
  `sgk_isyeri_sicil`   VARCHAR(50)  NULL,
  `muhasebe_ucreti`    DECIMAL(12,2) NULL COMMENT 'Aylık muhasebe (sözleşme) ücreti',
  `ucret_aciklama`     VARCHAR(200) NULL,
  `ise_baslama_tarihi` DATE         NOT NULL,
  `takip_baslangic`    DATE         NULL COMMENT 'Bu tarihten önceki dönemler oluşturulmaz',
  `terk_tarihi`        DATE         NULL COMMENT 'NULL ise faaliyeti devam ediyor',
  `terk_nedeni`        VARCHAR(200) NULL,
  `telefon`            VARCHAR(30)  NULL,
  `eposta`             VARCHAR(150) NULL,
  `yetkili_kisi`       VARCHAR(150) NULL,
  `adres`              VARCHAR(500) NULL,
  `notlar`             TEXT         NULL,
  `aktif`              TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`         DATETIME     NULL,
  `updated_at`         DATETIME     NULL,
  `deleted_at`         DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_muk_musavir` (`musavir_id`),
  KEY `idx_muk_tarih` (`ise_baslama_tarihi`,`terk_tarihi`),
  KEY `idx_muk_aktif` (`aktif`),
  KEY `idx_muk_gg` (`genc_girisimci`),
  KEY `idx_muk_indirim` (`ind_bagkur`,`ind_egitim_saglik`,`ind_finansman`),
  KEY `idx_muk_edefter` (`edefter_donem`),
  KEY `idx_muk_edefter_sorumlu` (`edefter_sorumlu_id`),
  KEY `idx_muk_sorumlu` (`sorumlu_kullanici_id`),
  CONSTRAINT `fk_mukellef_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_mukellef_sorumlu` FOREIGN KEY (`sorumlu_kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mukellef_edefter_sorumlu` FOREIGN KEY (`edefter_sorumlu_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) BEYANNAME TÜRLERİ  (son gün kuralları veri tabanından yönetilir)
--    Son tarih = (dönem bitiş ayı + son_gun_offset_ay) ayının
--                (son_gun_tipi = GUN ? son_gun : ayın son günü) günü
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `beyanname_turleri`;
CREATE TABLE `beyanname_turleri` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kod`               VARCHAR(30)  NOT NULL,
  `ad`                VARCHAR(150) NOT NULL,
  `kisa_ad`           VARCHAR(40)  NOT NULL,
  `periyot`           ENUM('AYLIK','UC_AYLIK','ALTI_AYLIK','YILLIK') NOT NULL,
  `son_gun_offset_ay` TINYINT UNSIGNED NOT NULL DEFAULT 1
                      COMMENT 'Dönem bitiş ayından itibaren kaç ay sonra',
  `son_gun_tipi`      ENUM('GUN','AY_SONU') NOT NULL DEFAULT 'GUN',
  `son_gun`           TINYINT UNSIGNED NOT NULL DEFAULT 28,
  `odeme_offset_ay`   TINYINT UNSIGNED NULL COMMENT 'Ödeme: dönem bitişinden kaç ay sonra (NULL = beyan ile aynı)',
  `odeme_son_gun_tipi` ENUM('GUN','AY_SONU') NULL,
  `odeme_son_gun`     TINYINT UNSIGNED NULL,
  `atlanan_donemler`  VARCHAR(30)  NULL COMMENT 'Örn: geçici vergi 4. dönem kaldırıldı -> 4',
  `celisen_kodlar`    VARCHAR(255) NULL COMMENT 'Aynı anda seçilemeyecek türler (virgüllü)',
  `mukellef_tipi`     ENUM('hepsi','gercek','tuzel') NOT NULL DEFAULT 'hepsi',
  `renk`              VARCHAR(9)   NOT NULL DEFAULT '#64748b',
  `aciklama`          VARCHAR(300) NULL,
  `sira`              SMALLINT     NOT NULL DEFAULT 0,
  `aktif`             TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`        DATETIME     NULL,
  `updated_at`        DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_beyanname_kod` (`kod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `beyanname_turleri`
(`kod`,`ad`,`kisa_ad`,`periyot`,`son_gun_offset_ay`,`son_gun_tipi`,`son_gun`,`odeme_offset_ay`,`odeme_son_gun_tipi`,`odeme_son_gun`,`atlanan_donemler`,`celisen_kodlar`,`mukellef_tipi`,`renk`,`aciklama`,`sira`) VALUES
('KDV1_A','KDV 1 Beyannamesi (Aylık)','KDV1 (Ay)','AYLIK',1,'GUN',28,NULL,NULL,NULL,NULL,'KDV1_3A','hepsi','#2563eb','İzleyen ayın 28''i',10),
('KDV1_3A','KDV 1 Beyannamesi (Üç Aylık)','KDV1 (3Ay)','UC_AYLIK',1,'GUN',28,NULL,NULL,NULL,NULL,'KDV1_A','hepsi','#3b82f6','Dönemi izleyen ayın 28''i',20),
('KDV2','KDV 2 Beyannamesi (Sorumlu Sıfatıyla)','KDV2','AYLIK',1,'GUN',21,NULL,NULL,NULL,NULL,NULL,'hepsi','#0ea5e9','İzleyen ayın 21''i',30),
('MUHSGK_A','Muhtasar ve Prim Hizmet Beyannamesi (Aylık)','MUHSGK (Ay)','AYLIK',1,'GUN',26,NULL,NULL,NULL,NULL,'MUHSGK_3A','hepsi','#7c3aed','İzleyen ayın 26''sı',40),
('MUHSGK_3A','Muhtasar ve Prim Hizmet Beyannamesi (Üç Aylık)','MUHSGK (3Ay)','UC_AYLIK',1,'GUN',26,NULL,NULL,NULL,NULL,'MUHSGK_A','hepsi','#8b5cf6','Dönemi izleyen ayın 26''sı',50),
('SGK','SGK Aylık Prim ve Hizmet Bildirgesi / Ödeme','SGK','AYLIK',1,'GUN',26,1,'AY_SONU',31,NULL,NULL,'hepsi','#059669','Onay: izleyen ayın 26''sı (MUHSGK ile) — Ödeme: ay sonu',60),
('YILLIK_GV','Yıllık Gelir Vergisi Beyannamesi','Yıllık GV','YILLIK',3,'AY_SONU',31,NULL,NULL,NULL,NULL,'KURUMLAR,KURUM_GECICI','gercek','#dc2626','İzleyen yıl Mart ayı sonu',70),
('KURUMLAR','Kurumlar Vergisi Beyannamesi','Kurumlar','YILLIK',4,'AY_SONU',30,NULL,NULL,NULL,NULL,'YILLIK_GV,GELIR_GECICI','tuzel','#b91c1c','İzleyen yıl Nisan ayı sonu',80),
('GELIR_GECICI','Gelir Geçici Vergi Beyannamesi','Gelir Geçici','UC_AYLIK',2,'GUN',17,NULL,NULL,NULL,'4','KURUMLAR,KURUM_GECICI','gercek','#ea580c','Dönemi izleyen 2. ayın 17''si (4. dönem kaldırıldı)',90),
('KURUM_GECICI','Kurum Geçici Vergi Beyannamesi','Kurum Geçici','UC_AYLIK',2,'GUN',17,NULL,NULL,NULL,'4','YILLIK_GV,GELIR_GECICI','tuzel','#c2410c','Dönemi izleyen 2. ayın 17''si (4. dönem kaldırıldı)',100),
('DAMGA','Damga Vergisi Beyannamesi','Damga','AYLIK',1,'GUN',26,NULL,NULL,NULL,NULL,NULL,'hepsi','#0f766e','İzleyen ayın 26''sı',110),
('GEKAP','Geri Kazanım Katılım Payı Beyannamesi','GEKAP','ALTI_AYLIK',1,'AY_SONU',31,NULL,NULL,NULL,NULL,NULL,'hepsi','#65a30d','6 aylık dönemi izleyen ayın son günü',120),
('TURIZM','Turizm Payı Beyannamesi','Turizm','AYLIK',1,'AY_SONU',31,NULL,NULL,NULL,NULL,NULL,'hepsi','#db2777','Dönemi izleyen ayın son günü',130);

-- ---------------------------------------------------------------------
-- 5) MÜKELLEFİN VERDİĞİ BEYANNAMELER (mükellef <-> beyanname türü)
--    baslangic/bitis: tür bazında özel tarih (örn. sonradan KDV mükellefi oldu)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `mukellef_beyannameleri`;
CREATE TABLE `mukellef_beyannameleri` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`       INT UNSIGNED NOT NULL,
  `beyanname_turu_id` INT UNSIGNED NOT NULL,
  `periyot_override`  ENUM('AYLIK','UC_AYLIK','ALTI_AYLIK','YILLIK') NULL
                      COMMENT 'Boşsa tür varsayılanı kullanılır',
  `baslangic_tarihi`  DATE         NULL,
  `bitis_tarihi`      DATE         NULL,
  `aciklama`          VARCHAR(300) NULL,
  `aktif`             TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`        DATETIME     NULL,
  `updated_at`        DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mukellef_tur` (`mukellef_id`,`beyanname_turu_id`),
  KEY `fk_mb_tur` (`beyanname_turu_id`),
  CONSTRAINT `fk_mb_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mb_tur` FOREIGN KEY (`beyanname_turu_id`)
     REFERENCES `beyanname_turleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) BEYANNAME TAKİP (dönem satırları)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `beyanname_takip`;
CREATE TABLE `beyanname_takip` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`       INT UNSIGNED NOT NULL,
  `beyanname_turu_id` INT UNSIGNED NOT NULL,
  `yil`               SMALLINT UNSIGNED NOT NULL,
  `donem_no`          TINYINT UNSIGNED NOT NULL COMMENT 'Aylık:1-12, 3Aylık:1-4, 6Aylık:1-2, Yıllık:1',
  `donem_adi`         VARCHAR(60)  NOT NULL COMMENT 'Örn: Mart 2026 / 1. Dönem (Oca-Şub-Mar) 2026',
  `donem_baslangic`   DATE         NOT NULL,
  `donem_bitis`       DATE         NOT NULL,
  `yasal_son_tarih`   DATE         NOT NULL COMMENT 'Kaydırma öncesi kanuni tarih',
  `son_tarih`         DATE         NOT NULL COMMENT 'Beyan/onay son günü (tatil kaydırması sonrası)',
  `odeme_son_tarih`   DATE         NULL COMMENT 'Ödeme son günü (boşsa beyan son tarihi geçerli)',
  `kaydirma_nedeni`   VARCHAR(150) NULL,
  `durum`             ENUM('BEKLIYOR','HAZIR','ONAYLANDI','VERILMEYECEK')
                      NOT NULL DEFAULT 'BEKLIYOR',
  `tahakkuk_tutari`   DECIMAL(15,2) NULL COMMENT 'Damga vergisi HARİÇ tahakkuk tutarı',
  `damga_tutari`      DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Onay anında kopyalanan damga',
  `tahakkuk_fis_no`   VARCHAR(50)  NULL,
  `odendi`            TINYINT(1)   NOT NULL DEFAULT 0,
  `odeme_tarihi`      DATE         NULL,
  `gonderim_tarihi`   DATETIME     NULL,
  `onaylayan_id`      INT UNSIGNED NULL,
  `onay_tarihi`       DATETIME     NULL,
  `not_metni`         TEXT         NULL,
  `created_at`        DATETIME     NULL,
  `updated_at`        DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_takip` (`mukellef_id`,`beyanname_turu_id`,`yil`,`donem_no`),
  KEY `idx_takip_sontarih` (`son_tarih`),
  KEY `idx_takip_odeme_tarih` (`odeme_son_tarih`),
  KEY `idx_takip_durum` (`durum`),
  KEY `idx_takip_odendi` (`odendi`),
  KEY `idx_takip_yil` (`yil`,`donem_no`),
  KEY `fk_takip_tur` (`beyanname_turu_id`),
  CONSTRAINT `fk_takip_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_takip_tur` FOREIGN KEY (`beyanname_turu_id`)
     REFERENCES `beyanname_turleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) EVRAK TÜRLERİ
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `evrak_turleri`;
CREATE TABLE `evrak_turleri` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad`         VARCHAR(120) NOT NULL,
  `kisa_ad`    VARCHAR(40)  NOT NULL,
  `sira`       SMALLINT     NOT NULL DEFAULT 0,
  `aktif`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NULL,
  `updated_at` DATETIME     NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `evrak_turleri` (`ad`,`kisa_ad`,`sira`) VALUES
('Alış Faturaları','Alış Fat.',10),
('Satış Faturaları','Satış Fat.',20),
('Banka Ekstreleri','Banka',30),
('Kasa / Tahsilat Belgeleri','Kasa',40),
('Çek - Senet Belgeleri','Çek/Senet',50),
('Gider Belgeleri (Fiş/Makbuz)','Gider',60),
('Personel Bordro Bilgileri','Bordro',70),
('e-Fatura / e-Arşiv Listesi','e-Belge',80);

-- ---------------------------------------------------------------------
-- 8) EVRAK TAKİP  (sadece: Geldi / Gelmedi)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `evrak_takip`;
CREATE TABLE `evrak_takip` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`   INT UNSIGNED NOT NULL,
  `evrak_turu_id` INT UNSIGNED NOT NULL,
  `yil`           SMALLINT UNSIGNED NOT NULL,
  `ay`            TINYINT UNSIGNED NOT NULL,
  `durum`         ENUM('GELMEDI','GELDI','YOK') NOT NULL DEFAULT 'GELMEDI'
                  COMMENT 'YOK = bu dönem bu mükellefte takip edilmiyor',
  `teslim_tarihi` DATE     NULL,
  `kaydeden_id`   INT UNSIGNED NULL,
  `created_at`    DATETIME NULL,
  `updated_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evrak` (`mukellef_id`,`evrak_turu_id`,`yil`,`ay`),
  KEY `idx_evrak_donem` (`yil`,`ay`),
  KEY `fk_evrak_tur` (`evrak_turu_id`),
  CONSTRAINT `fk_evrak_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_evrak_tur` FOREIGN KEY (`evrak_turu_id`)
     REFERENCES `evrak_turleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8a) EVRAK MUAFİYETİ  (mükellefte hiç bulunmayan evrak türleri)
--     Satır varsa o mükellefin o evrak türü çizelgede pasif görünür,
--     kırmızı "eksik" sayılmaz ve sayaçlara girmez.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `mukellef_evrak_muafiyet`;
CREATE TABLE `mukellef_evrak_muafiyet` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8b) E-DEFTER BERAT TAKİBİ
--     Adımlar kullanıcı tarafından düzenlenebilir (Tanımlar menüsü).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `edefter_adim_durum`;
DROP TABLE IF EXISTS `edefter_takip`;
DROP TABLE IF EXISTS `edefter_adimlari`;

CREATE TABLE `edefter_adimlari` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kod`        VARCHAR(40)  NOT NULL COMMENT 'Benzersiz kısa kod',
  `ad`         VARCHAR(100) NOT NULL,
  `ikon`       VARCHAR(10)  NULL,
  `aciklama`   VARCHAR(200) NULL,
  `sira`       SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  `aktif`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_edefter_adim_kod` (`kod`),
  KEY `idx_edefter_adim_sira` (`sira`,`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `edefter_adimlari` (`kod`,`ad`,`ikon`,`aciklama`,`sira`,`aktif`,`created_at`,`updated_at`) VALUES
('BANKA_TEMIN','Banka Temin','🏦','Mükelleften banka ekstreleri alındı',10,1,NOW(),NOW()),
('BANKA_ISLEME','Banka İşleme','💳','Banka hareketleri kayda geçildi',20,1,NOW(),NOW()),
('CEK_ISLEME','Çek İşleme','🧾','Çek/senet hareketleri işlendi',30,1,NOW(),NOW()),
('MIZAN','Mizan Kontrol','📊','Mizan incelendi, hatalar giderildi',40,1,NOW(),NOW()),
('HAZIR','Hazır','✅','Defter beratı yüklenmeye hazır',50,1,NOW(),NOW()),
('ONAY','Onaylandı','🔒','Berat yüklendi / onaylandı',60,1,NOW(),NOW());

CREATE TABLE `edefter_takip` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`     INT UNSIGNED NOT NULL,
  `donem_tipi`      ENUM('AYLIK','UC_AYLIK') NOT NULL DEFAULT 'AYLIK',
  `yil`             SMALLINT UNSIGNED NOT NULL,
  `donem_no`        TINYINT UNSIGNED NOT NULL COMMENT 'Aylıkta 1-12, üç aylıkta 1-4',
  `donem_adi`       VARCHAR(60)  NOT NULL,
  `donem_baslangic` DATE NOT NULL,
  `donem_bitis`     DATE NOT NULL,
  `yasal_son_tarih` DATE NOT NULL COMMENT 'Kaydırma öncesi yasal tarih',
  `son_tarih`       DATE NOT NULL COMMENT 'Tatil kaydırması uygulanmış tarih',
  `kaydirma_nedeni` VARCHAR(120) NULL,
  `durum`           ENUM('BEKLIYOR','DEVAM','HAZIR','ONAYLANDI','YUKLENMEYECEK')
                    NOT NULL DEFAULT 'BEKLIYOR',
  `berat_tarihi`    DATE NULL COMMENT 'Beratın yüklendiği tarih',
  `not_metni`       VARCHAR(300) NULL,
  `created_at`      DATETIME NULL,
  `updated_at`      DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_edefter_takip` (`mukellef_id`,`donem_tipi`,`yil`,`donem_no`),
  KEY `idx_edefter_son_tarih` (`son_tarih`),
  KEY `idx_edefter_durum` (`durum`),
  KEY `idx_edefter_donem` (`yil`,`donem_no`),
  CONSTRAINT `fk_edefter_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satır YOKSA adım tamamlanmamış sayılır; yeni adım eklendiğinde geçmiş
-- kayıtlara toplu satır açmak gerekmez.
CREATE TABLE `edefter_adim_durum` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `takip_id`      INT UNSIGNED NOT NULL,
  `adim_id`       INT UNSIGNED NOT NULL,
  `tamam`         TINYINT(1) NOT NULL DEFAULT 0,
  `tamamlayan_id` INT UNSIGNED NULL,
  `tamam_tarihi`  DATETIME NULL,
  `created_at`    DATETIME NULL,
  `updated_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_edefter_adim` (`takip_id`,`adim_id`),
  KEY `fk_edefter_adim_adim` (`adim_id`),
  CONSTRAINT `fk_edefter_adim_takip` FOREIGN KEY (`takip_id`)
     REFERENCES `edefter_takip` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_edefter_adim_adim` FOREIGN KEY (`adim_id`)
     REFERENCES `edefter_adimlari` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8c) SERBEST MESLEK MAKBUZU TAKİBİ
--     Yıllık sözleşme ücreti (hedef) + kesilen makbuzlar (gerçekleşen)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `makbuzlar`;
DROP TABLE IF EXISTS `mukellef_ucretleri`;

CREATE TABLE `mukellef_ucretleri` (
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

CREATE TABLE `makbuzlar` (
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
-- 8d) GELİR VERGİSİ HESABI (mali müşavir bazında)
--     Tarife dilimleri yıl bazında; gider/mahsup kalemleri elle girilir.
--     Hasılat ve stopaj makbuzlar tablosundan otomatik gelir.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `musavir_gelir_gider`;
DROP TABLE IF EXISTS `vergi_tarifeleri`;

CREATE TABLE `vergi_tarifeleri` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `yil`         SMALLINT UNSIGNED NOT NULL,
  `ucret_mi`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=ücret dışı gelirler, 1=ücret gelirleri',
  `sira`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `taban`       DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Dilimin alt sınırı',
  `tavan`       DECIMAL(16,2) NULL COMMENT 'Dilimin üst sınırı; NULL = son dilim',
  `sabit_vergi` DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Tabana kadarki kümülatif vergi',
  `oran`        DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Tabanı aşan kısma uygulanan %',
  `created_at`  DATETIME NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tarife_dilim` (`yil`,`ucret_mi`,`sira`),
  KEY `idx_tarife_yil` (`yil`,`ucret_mi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `musavir_gelir_gider` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `musavir_id`         INT UNSIGNED NOT NULL,
  `yil`                SMALLINT UNSIGNED NOT NULL,
  `hesap_kipi`         ENUM('ucret','makbuz') NOT NULL DEFAULT 'ucret'
                         COMMENT 'ucret = yıllık sözleşme ücretleri (projeksiyon), makbuz = kesilen makbuzlar',
  `gider`              DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Elle girilen mesleki gider — musavir_aylik_gider toplamı EKLENİR',
  `gecmis_yil_zarari`  DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'PASİF (16. güncelleme) — kullanılmıyor',
  `bagkur`             DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Ödenen Bağ-Kur/SGK primi (sınırsız indirilir)',
  `sigorta_primi`      DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Şahıs/hayat sigorta primi (kârın %15''i) — musavir_indirim_kalem boşsa kullanılır',
  `egitim_saglik`      DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Eğitim-sağlık harcaması (kârın %10''u) — musavir_indirim_kalem boşsa kullanılır',
  `diger_indirim`      DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'PASİF (16. güncelleme) — kullanılmıyor',
  `gecici_vergi`       DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'PASİF (16. güncelleme) — kullanılmıyor',
  `diger_mahsup`       DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Diğer mahsup edilecek vergiler',
  `uyumlu_indirim`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'GVK mük.121 %5 indirimi uygulansın mı',
  `stopaj_elle`        DECIMAL(16,2) NULL COMMENT 'Doldurulursa makbuz stopajı yerine bu kullanılır',
  `hasilat_elle`       DECIMAL(16,2) NULL COMMENT 'Doldurulursa makbuz hasılatı yerine bu kullanılır',
  `aciklama`           VARCHAR(250) NULL,
  `kaydeden_id`        INT UNSIGNED NULL,
  `created_at`         DATETIME NULL,
  `updated_at`         DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_musavir_yil` (`musavir_id`,`yil`),
  KEY `idx_ggider_yil` (`yil`),
  CONSTRAINT `fk_ggider_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- İndirim kalemleri: eğitim-sağlık ve sigorta primi belgeleri (tarih/tür/açıklama/tutar)
-- Liste doluysa toplamı, boşsa musavir_gelir_gider'deki elle girilen tutar kullanılır.
DROP TABLE IF EXISTS `musavir_indirim_kalem`;
CREATE TABLE `musavir_indirim_kalem` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `musavir_id` INT UNSIGNED NOT NULL,
  `yil`        SMALLINT UNSIGNED NOT NULL,
  `kalem`      ENUM('egitim_saglik','sigorta') NOT NULL DEFAULT 'egitim_saglik'
                 COMMENT 'egitim_saglik = GVK 89/2, sigorta = GVK 89/1',
  `tur`        ENUM('egitim','saglik','hayat','sahis','diger') NOT NULL DEFAULT 'egitim',
  `tarih`      DATE NOT NULL,
  `aciklama`   VARCHAR(250) NULL,
  `tutar`      DECIMAL(16,2) NOT NULL DEFAULT 0,
  `kaydeden_id` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ik_musavir_yil` (`musavir_id`,`yil`,`kalem`),
  KEY `idx_ik_tarih` (`tarih`),
  CONSTRAINT `fk_ik_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aylık gider tablosu: mesleki gider ay ay girilir.
-- Toplam gider = musavir_gelir_gider.gider (elle) + bu tablonun toplamı.
DROP TABLE IF EXISTS `musavir_aylik_gider`;
CREATE TABLE `musavir_aylik_gider` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `musavir_id` INT UNSIGNED NOT NULL,
  `yil`        SMALLINT UNSIGNED NOT NULL,
  `ay`         TINYINT UNSIGNED NOT NULL COMMENT '1-12',
  `tutar`      DECIMAL(16,2) NOT NULL DEFAULT 0,
  `aciklama`   VARCHAR(200) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gider_musavir_donem` (`musavir_id`,`yil`,`ay`),
  KEY `idx_gider_yil` (`yil`),
  CONSTRAINT `fk_agider_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aylık KDV tablosu: yıl içinde ödenen KDV yükü (stopajdan düşülür)
DROP TABLE IF EXISTS `musavir_kdv`;
CREATE TABLE `musavir_kdv` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `musavir_id`  INT UNSIGNED NOT NULL,
  `yil`         SMALLINT UNSIGNED NOT NULL,
  `ay`          TINYINT UNSIGNED NOT NULL COMMENT '1-12',
  `odenen`      DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'O ay ödenen KDV',
  `indirilecek` DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'O ay indirilecek KDV',
  `aciklama`    VARCHAR(200) NULL,
  `created_at`  DATETIME NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kdv_musavir_donem` (`musavir_id`,`yil`,`ay`),
  KEY `idx_kdv_yil` (`yil`),
  CONSTRAINT `fk_kdv_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2024-2026 gelir vergisi tarifeleri (GVK md.103)
INSERT INTO `vergi_tarifeleri` (`yil`,`ucret_mi`,`sira`,`taban`,`tavan`,`sabit_vergi`,`oran`) VALUES
-- 2026 ücret dışı
(2026,0,1,0,190000,0,15),(2026,0,2,190000,400000,28500,20),(2026,0,3,400000,1000000,70500,27),
(2026,0,4,1000000,5300000,232500,35),(2026,0,5,5300000,NULL,1737500,40),
-- 2026 ücret
(2026,1,1,0,190000,0,15),(2026,1,2,190000,400000,28500,20),(2026,1,3,400000,1500000,70500,27),
(2026,1,4,1500000,5300000,367500,35),(2026,1,5,5300000,NULL,1697500,40),
-- 2025 ücret dışı
(2025,0,1,0,158000,0,15),(2025,0,2,158000,330000,23700,20),(2025,0,3,330000,800000,58100,27),
(2025,0,4,800000,4300000,185000,35),(2025,0,5,4300000,NULL,1410000,40),
-- 2025 ücret
(2025,1,1,0,158000,0,15),(2025,1,2,158000,330000,23700,20),(2025,1,3,330000,1200000,58100,27),
(2025,1,4,1200000,4300000,293000,35),(2025,1,5,4300000,NULL,1378000,40),
-- 2024 ücret dışı
(2024,0,1,0,110000,0,15),(2024,0,2,110000,230000,16500,20),(2024,0,3,230000,580000,40500,27),
(2024,0,4,580000,3000000,135000,35),(2024,0,5,3000000,NULL,982000,40),
-- 2024 ücret
(2024,1,1,0,110000,0,15),(2024,1,2,110000,230000,16500,20),(2024,1,3,230000,870000,40500,27),
(2024,1,4,870000,3000000,213300,35),(2024,1,5,3000000,NULL,958800,40);

-- ---------------------------------------------------------------------
-- 9) AYLIK MÜKELLEF NOTU (beyanname çizelgesindeki ay notu)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `mukellef_aylik_not`;
CREATE TABLE `mukellef_aylik_not` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id` INT UNSIGNED NOT NULL,
  `yil`         SMALLINT UNSIGNED NOT NULL,
  `ay`          TINYINT UNSIGNED NOT NULL,
  `not_metni`   TEXT     NULL,
  `created_at`  DATETIME NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aylik_not` (`mukellef_id`,`yil`,`ay`),
  CONSTRAINT `fk_not_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10) TATİLLER  (son gün kaydırma motoru bu tabloyu kullanır)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tatiller`;
CREATE TABLE `tatiller` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tarih`      DATE         NOT NULL,
  `ad`         VARCHAR(150) NOT NULL,
  `tip`        ENUM('RESMI','DINI','ARIFE','MALI_TATIL','IDARI_IZIN') NOT NULL DEFAULT 'RESMI',
  `yarim_gun`  TINYINT(1)   NOT NULL DEFAULT 0,
  `aktif`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NULL,
  `updated_at` DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tatil_tarih` (`tarih`),
  KEY `idx_tatil_aktif` (`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tatiller` (`tarih`,`ad`,`tip`,`yarim_gun`) VALUES
-- 2025
('2025-01-01','Yılbaşı','RESMI',0),
('2025-03-29','Ramazan Bayramı Arifesi','ARIFE',1),
('2025-03-30','Ramazan Bayramı 1. Gün','DINI',0),
('2025-03-31','Ramazan Bayramı 2. Gün','DINI',0),
('2025-04-01','Ramazan Bayramı 3. Gün','DINI',0),
('2025-04-23','Ulusal Egemenlik ve Çocuk Bayramı','RESMI',0),
('2025-05-01','Emek ve Dayanışma Günü','RESMI',0),
('2025-05-19','Atatürk''ü Anma, Gençlik ve Spor Bayramı','RESMI',0),
('2025-06-05','Kurban Bayramı Arifesi','ARIFE',1),
('2025-06-06','Kurban Bayramı 1. Gün','DINI',0),
('2025-06-07','Kurban Bayramı 2. Gün','DINI',0),
('2025-06-08','Kurban Bayramı 3. Gün','DINI',0),
('2025-06-09','Kurban Bayramı 4. Gün','DINI',0),
('2025-07-15','Demokrasi ve Millî Birlik Günü','RESMI',0),
('2025-08-30','Zafer Bayramı','RESMI',0),
('2025-10-28','Cumhuriyet Bayramı Arifesi','ARIFE',1),
('2025-10-29','Cumhuriyet Bayramı','RESMI',0),
-- 2026
('2026-01-01','Yılbaşı','RESMI',0),
('2026-03-19','Ramazan Bayramı Arifesi','ARIFE',1),
('2026-03-20','Ramazan Bayramı 1. Gün','DINI',0),
('2026-03-21','Ramazan Bayramı 2. Gün','DINI',0),
('2026-03-22','Ramazan Bayramı 3. Gün','DINI',0),
('2026-04-23','Ulusal Egemenlik ve Çocuk Bayramı','RESMI',0),
('2026-05-01','Emek ve Dayanışma Günü','RESMI',0),
('2026-05-19','Atatürk''ü Anma, Gençlik ve Spor Bayramı','RESMI',0),
('2026-05-26','Kurban Bayramı Arifesi','ARIFE',1),
('2026-05-27','Kurban Bayramı 1. Gün','DINI',0),
('2026-05-28','Kurban Bayramı 2. Gün','DINI',0),
('2026-05-29','Kurban Bayramı 3. Gün','DINI',0),
('2026-05-30','Kurban Bayramı 4. Gün','DINI',0),
('2026-07-15','Demokrasi ve Millî Birlik Günü','RESMI',0),
('2026-08-30','Zafer Bayramı','RESMI',0),
('2026-10-28','Cumhuriyet Bayramı Arifesi','ARIFE',1),
('2026-10-29','Cumhuriyet Bayramı','RESMI',0),
-- 2027
('2027-01-01','Yılbaşı','RESMI',0),
('2027-03-08','Ramazan Bayramı Arifesi','ARIFE',1),
('2027-03-09','Ramazan Bayramı 1. Gün','DINI',0),
('2027-03-10','Ramazan Bayramı 2. Gün','DINI',0),
('2027-03-11','Ramazan Bayramı 3. Gün','DINI',0),
('2027-04-23','Ulusal Egemenlik ve Çocuk Bayramı','RESMI',0),
('2027-05-01','Emek ve Dayanışma Günü','RESMI',0),
('2027-05-15','Kurban Bayramı Arifesi','ARIFE',1),
('2027-05-16','Kurban Bayramı 1. Gün','DINI',0),
('2027-05-17','Kurban Bayramı 2. Gün','DINI',0),
('2027-05-18','Kurban Bayramı 3. Gün','DINI',0),
('2027-05-19','Kurban Bayramı 4. Gün / Gençlik ve Spor Bayramı','DINI',0),
('2027-07-15','Demokrasi ve Millî Birlik Günü','RESMI',0),
('2027-08-30','Zafer Bayramı','RESMI',0),
('2027-10-28','Cumhuriyet Bayramı Arifesi','ARIFE',1),
('2027-10-29','Cumhuriyet Bayramı','RESMI',0);

-- ---------------------------------------------------------------------
-- 10b) DAMGA VERGİSİ SABİT TUTARLARI (beyanname türü × yıl)
--      Tahakkuk tutarı damga HARİÇ girilir; ödeme listesinde eklenir.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `damga_tutarlari`;
CREATE TABLE `damga_tutarlari` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `beyanname_turu_id` INT UNSIGNED NOT NULL,
  `yil`               SMALLINT UNSIGNED NOT NULL,
  `tutar`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `aciklama`          VARCHAR(200) NULL,
  `created_at`        DATETIME NULL,
  `updated_at`        DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_damga_tur_yil` (`beyanname_turu_id`,`yil`),
  CONSTRAINT `fk_damga_tur` FOREIGN KEY (`beyanname_turu_id`)
     REFERENCES `beyanname_turleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10c) KARŞIT İNCELEME TUTANAKLARI
--      YMM'lerden gelen tutanakların cevap takibi
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `karsit_inceleme`;
CREATE TABLE `karsit_inceleme` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`      INT UNSIGNED NOT NULL,
  `ymm_adi`          VARCHAR(200) NOT NULL COMMENT 'Tutanağı gönderen YMM / büro',
  `gelis_tarihi`     DATE         NOT NULL,
  `son_cevap_tarihi` DATE         NULL,
  `gonderim_tarihi`  DATE         NULL,
  `durum`            ENUM('CEVAP_BEKLIYOR','HAZIRLANIYOR','GONDERILDI','IPTAL')
                     NOT NULL DEFAULT 'CEVAP_BEKLIYOR',
  `not_metni`        TEXT         NULL,
  `kaydeden_id`      INT UNSIGNED NULL,
  `created_at`       DATETIME     NULL,
  `updated_at`       DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ki_mukellef` (`mukellef_id`),
  KEY `idx_ki_durum` (`durum`),
  KEY `idx_ki_gelis` (`gelis_tarihi`),
  CONSTRAINT `fk_ki_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ki_kaydeden` FOREIGN KEY (`kaydeden_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10d) ÖZEL ÖDEME KALEMLERİ (Bağkur, MTV, harç, ceza vb.)
--      Beyanname dışında kalan, listeye elle eklenen ödemeler
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `ozel_odemeler`;
CREATE TABLE `ozel_odemeler` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mukellef_id`   INT UNSIGNED NOT NULL,
  `baslik`        VARCHAR(200) NOT NULL COMMENT 'Örn: Bağkur Primi, MTV 1. Taksit',
  `aciklama`      VARCHAR(300) NULL,
  `tutar`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `son_tarih`     DATE         NOT NULL,
  `donem_etiketi` VARCHAR(60)  NULL COMMENT 'Örn: Nisan 2026',
  `durum`         ENUM('BEKLIYOR','ONAYLANDI','IPTAL') NOT NULL DEFAULT 'ONAYLANDI',
  `odendi`        TINYINT(1)   NOT NULL DEFAULT 0,
  `odeme_tarihi`  DATE         NULL,
  `tekrar`        ENUM('YOK','AYLIK') NOT NULL DEFAULT 'YOK',
  `tekrar_bitis`  DATE         NULL COMMENT 'Tekrarın duracağı tarih (boş = süresiz)',
  `tekrar_kaynak_id` INT UNSIGNED NULL COMMENT 'Bu kalem hangi tekrarlı kalemden üretildi',
  `kaydeden_id`   INT UNSIGNED NULL COMMENT 'Kalemin sahibi (kullanıcıya özel)',
  `created_at`    DATETIME     NULL,
  `updated_at`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oo_kaydeden` (`kaydeden_id`),
  KEY `idx_oo_mukellef` (`mukellef_id`),
  KEY `idx_oo_tarih` (`son_tarih`),
  KEY `idx_oo_odendi` (`odendi`),
  KEY `idx_oo_tekrar` (`tekrar`,`son_tarih`),
  KEY `idx_oo_kaynak` (`tekrar_kaynak_id`),
  CONSTRAINT `fk_oo_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oo_kaydeden` FOREIGN KEY (`kaydeden_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10e) KAYITLI ÖDEME LİSTELERİ (kullanıcıya özel)
--      Liste kalıcı bir MÜKELLEF GRUBUDUR; dönem açılışta seçilir.
--      Tutarlar her açılışta güncel olarak hesaplanır.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `odeme_listesi_mukellefleri`;
DROP TABLE IF EXISTS `odeme_listeleri`;
CREATE TABLE `odeme_listeleri` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_id`  INT UNSIGNED NOT NULL COMMENT 'Listenin sahibi',
  `musavir_id`    INT UNSIGNED NULL COMMENT 'İlgili mali müşavir (başlıkta görünür)',
  `ad`            VARCHAR(200) NOT NULL,
  `aciklama`      VARCHAR(300) NULL,
  `yil`           SMALLINT UNSIGNED NULL COMMENT 'Varsayılan yıl (boşsa açılışta bu yıl)',
  `ay`            TINYINT UNSIGNED NULL COMMENT 'Varsayılan ay (boşsa açılışta bu ay)',
  `ucret_dahil`   TINYINT(1)   NOT NULL DEFAULT 0,
  `ozel_dahil`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NULL,
  `updated_at`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ol_kullanici` (`kullanici_id`),
  KEY `idx_ol_donem` (`yil`,`ay`),
  CONSTRAINT `fk_ol_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ol_musavir` FOREIGN KEY (`musavir_id`)
     REFERENCES `musavirler` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `odeme_listesi_mukellefleri` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liste_id`    INT UNSIGNED NOT NULL,
  `mukellef_id` INT UNSIGNED NOT NULL,
  `sira`        SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_olm` (`liste_id`,`mukellef_id`),
  KEY `fk_olm_mukellef` (`mukellef_id`),
  CONSTRAINT `fk_olm_liste` FOREIGN KEY (`liste_id`)
     REFERENCES `odeme_listeleri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_olm_mukellef` FOREIGN KEY (`mukellef_id`)
     REFERENCES `mukellefler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11) AYARLAR
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `ayarlar`;
CREATE TABLE `ayarlar` (
  `anahtar`    VARCHAR(60)  NOT NULL,
  `deger`      VARCHAR(255) NULL,
  `aciklama`   VARCHAR(255) NULL,
  `updated_at` DATETIME     NULL,
  PRIMARY KEY (`anahtar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ayarlar` (`anahtar`,`deger`,`aciklama`) VALUES
('firma_adi','Beyanname Takip Sistemi','Üst menüde görünen başlık'),
('cumartesi_tatil','1','Cumartesi iş günü sayılmasın (1/0)'),
('pazar_tatil','1','Pazar iş günü sayılmasın (1/0)'),
('arife_tatil_sayilsin','1','Yarım gün arifeler son gün hesabında tatil sayılsın (1/0)'),
('mali_tatil_uygula','0','1-20 Temmuz mali tatil kaydırması uygulansın (1/0)'),
('uyari_gun_sayisi','3','Son X gün kala satır "yaklaşıyor" olarak işaretlenir'),
('otomatik_donem_uret','1','Mükellef kaydedilince dönemler otomatik üretilsin'),
('damga_otomatik_ekle','1','Ödeme listesinde damga vergisini tahakkuk tutarına ekle (1/0)'),
('bildirim_ucret_varsayilan','0','Ödeme bildiriminde muhasebe ücreti varsayılan işaretli gelsin (1/0)'),
('gg_istisna_donem','3','Genç girişimci istisnasının geçerli olduğu vergilendirme dönemi sayısı'),
('karsit_uyari_gun','7','Karşıt inceleme cevabı için son X gün kala uyar'),
('edefter_aylik_ay_sonra','4','Aylık berat: dönem ayını izleyen kaçıncı ayda yüklenir (Ocak -> Mayıs = 4)'),
('edefter_ucaylik_ay_sonra','3','Üç aylık berat: dönem bitişini izleyen kaçıncı ayda yüklenir (Mart -> Haziran = 3)'),
('edefter_gun_gercek','10','Gelir vergisi mükellefi (gerçek kişi) berat günü'),
('edefter_gun_tuzel','14','Diğer mükellefler (kurumlar) berat günü'),
('edefter_aralik_gercek_ay','4','Aralık dönemi istisnası - gerçek kişi: GV beyanını (Mart) izleyen ay = Nisan'),
('edefter_aralik_tuzel_ay','5','Aralık dönemi istisnası - tüzel kişi: Kurumlar beyanını (Nisan) izleyen ay = Mayıs'),
('edefter_otomatik_uret','1','Mükellef kaydedilince e-defter dönemleri otomatik üretilsin mi'),
('edefter_uyari_gun','10','E-defter beratı için kaç gün kala panelde uyarı verilsin'),
('makbuz_stopaj_oran','20','Serbest meslek makbuzu stopaj oranı (%)'),
('makbuz_kdv_oran','20','Serbest meslek makbuzu KDV oranı (%)'),
('makbuz_kdv_dahil','0','Excel''den gelen brüt tutar KDV dahil mi (1=evet)'),
('gv_uyumlu_oran','5','Vergiye uyumlu mükellef indirimi oranı (%) — GVK mük.121'),
('gv_uyumlu_ust_sinir','12000000','Uyumlu mükellef indirimi üst sınırı (TL) — 2026: 12.000.000'),
('gv_hasilat_kaynagi','tum','Hasılat kaynağı: tum = kesilen tüm makbuzlar, tahsil = yalnız tahsil edilenler'),
('gv_sigorta_oran','15','Şahıs/hayat sigorta primi indirim üst oranı (%) — GVK 89/1'),
('gv_egitim_saglik_oran','10','Eğitim ve sağlık harcaması indirim üst oranı (%) — GVK 89/2'),
('gv_ucret_stopaj_oran','20','Yıllık sözleşme ücretinden stopaj oranı (%) — gelir vergisi projeksiyonu'),
('gv_ucret_kdv_oran','20','Yıllık sözleşme ücretinden KDV oranı (%) — gelir vergisi projeksiyonu'),
('gv_varsayilan_kip','ucret','Yeni kayıtlarda varsayılan hesap kipi: ucret | makbuz'),
('ajanda_panel_gun','7','Panelde kaç günlük ajanda gösterilsin'),
('ajanda_giris_uyari','1','Girişte bugünün işleri penceresi açılsın mı (1=evet)'),
('ajanda_ek_boyut','5120','Ajanda dosya eki en büyük boyut (KB)');

-- ---------------------------------------------------------------------
-- 22) AJANDA / HATIRLATICI
--     Elle girilen işler: toplantı, arama, sözleşme yenileme…
--     Beyanname/e-defter/evrak uyarıları ayrı modüllerde üretilir.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `ajanda_uyari_okundu`;
DROP TABLE IF EXISTS `ajanda_ek`;
DROP TABLE IF EXISTS `ajanda`;

CREATE TABLE `ajanda` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `baslik`        VARCHAR(200) NOT NULL,
  `aciklama`      TEXT NULL,
  `tarih`         DATE NOT NULL,
  `saat`          TIME NULL COMMENT 'Boşsa gün boyu',
  `bitis_tarihi`  DATE NULL,
  `gorunurluk`    ENUM('kisisel','genel','gorev','musavir') NOT NULL DEFAULT 'kisisel',
  `atanan_id`     INT UNSIGNED NULL,
  `musavir_id`    INT UNSIGNED NULL,
  `oncelik`       ENUM('dusuk','normal','yuksek','acil') NOT NULL DEFAULT 'normal',
  `etiket`        VARCHAR(60) NULL,
  `renk`          VARCHAR(9) NULL,
  `mukellef_id`   INT UNSIGNED NULL,
  `tekrar`        ENUM('yok','gunluk','haftalik','aylik','yillik') NOT NULL DEFAULT 'yok',
  `tekrar_bitis`  DATE NULL,
  `hatirlat_gun`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `durum`         ENUM('BEKLIYOR','YAPILDI','IPTAL') NOT NULL DEFAULT 'BEKLIYOR',
  `yapildi_at`    DATETIME NULL,
  `yapan_id`      INT UNSIGNED NULL,
  `olusturan_id`  INT UNSIGNED NOT NULL,
  `created_at`    DATETIME NULL,
  `updated_at`    DATETIME NULL,
  `deleted_at`    DATETIME NULL,
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

CREATE TABLE `ajanda_ek` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ajanda_id`   INT UNSIGNED NOT NULL,
  `dosya_adi`   VARCHAR(255) NOT NULL,
  `saklanan`    VARCHAR(255) NOT NULL,
  `boyut`       INT UNSIGNED NOT NULL DEFAULT 0,
  `tur`         VARCHAR(100) NULL,
  `yukleyen_id` INT UNSIGNED NULL,
  `created_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ek_ajanda` (`ajanda_id`),
  CONSTRAINT `fk_ek_ajanda` FOREIGN KEY (`ajanda_id`)
     REFERENCES `ajanda` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ek_yukleyen` FOREIGN KEY (`yukleyen_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ajanda_uyari_okundu` (
  `kullanici_id` INT UNSIGNED NOT NULL,
  `tarih`        DATE NOT NULL,
  `created_at`   DATETIME NULL,
  PRIMARY KEY (`kullanici_id`,`tarih`),
  CONSTRAINT `fk_uyari_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  NOT: Yönetici kullanıcı, tarayıcıdan  /kurulum  adresi ile oluşturulur
--       (şifre password_hash ile güvenli şekilde saklanır).
-- =====================================================================
