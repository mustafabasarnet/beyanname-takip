-- =====================================================================
--  GÜNCELLEME 12: E-DEFTER BERAT TAKİBİ
--
--  Mükellef bazında aylık / üç aylık e-defter berat dönemleri üretilir ve
--  büro iş akışına göre ADIM ADIM takip edilir:
--    Banka Temin → Banka İşleme → Çek İşleme → Mizan Kontrol → Hazır → Onay
--
--  Adımlar sabit değildir; Tanımlar menüsünden eklenip çıkarılabilir,
--  sırası değiştirilebilir (örn. "Kasa Kontrolü" eklemek).
--
--  Son tarihler mevzuata göre otomatik hesaplanır, Ayarlar'dan değiştirilir:
--    edefter_aylik_ay_sonra   = 3  → ilgili ayı izleyen 3. ayın son günü
--    edefter_ucaylik_ay_sonra = 2  → dönem bitişini izleyen 2. ayın son günü
--
--  mysql -u KULLANICI -p beyanname_takip < migration_edefter.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1) Mükellef kartı alanları
--     edefter_donem: YOK = e-defter mükellefi değil (listeye hiç girmez)
-- ---------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler'
            AND COLUMN_NAME='edefter_donem');
SET @s = IF(@c=0,
 'ALTER TABLE `mukellefler`
    ADD COLUMN `edefter_donem` ENUM(''YOK'',''AYLIK'',''UC_AYLIK'')
      NOT NULL DEFAULT ''YOK'' COMMENT ''E-defter berat dönemi'' AFTER `defter_tipi`,
    ADD COLUMN `edefter_sorumlu_id` INT UNSIGNED NULL
      COMMENT ''E-defterden sorumlu personel'' AFTER `edefter_donem`,
    ADD COLUMN `edefter_baslangic` DATE NULL
      COMMENT ''Bu tarihten önceki e-defter dönemleri üretilmez'' AFTER `edefter_sorumlu_id`,
    ADD KEY `idx_muk_edefter` (`edefter_donem`),
    ADD KEY `idx_muk_edefter_sorumlu` (`edefter_sorumlu_id`)',
 'SELECT ''edefter alanları zaten var''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Yabancı anahtar (kullanıcı silinirse sorumluluk boşalsın)
SET @c = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler'
            AND CONSTRAINT_NAME='fk_mukellef_edefter_sorumlu');
SET @s = IF(@c=0,
 'ALTER TABLE `mukellefler`
    ADD CONSTRAINT `fk_mukellef_edefter_sorumlu` FOREIGN KEY (`edefter_sorumlu_id`)
      REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
 'SELECT ''fk_mukellef_edefter_sorumlu zaten var''');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
--  2) Takip adımları (kullanıcı tarafından düzenlenebilir)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `edefter_adimlari` (
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

INSERT IGNORE INTO `edefter_adimlari` (`kod`,`ad`,`ikon`,`aciklama`,`sira`,`aktif`,`created_at`,`updated_at`) VALUES
('BANKA_TEMIN','Banka Temin','🏦','Mükelleften banka ekstreleri alındı',10,1,NOW(),NOW()),
('BANKA_ISLEME','Banka İşleme','💳','Banka hareketleri kayda geçildi',20,1,NOW(),NOW()),
('CEK_ISLEME','Çek İşleme','🧾','Çek/senet hareketleri işlendi',30,1,NOW(),NOW()),
('MIZAN','Mizan Kontrol','📊','Mizan incelendi, hatalar giderildi',40,1,NOW(),NOW()),
('HAZIR','Hazır','✅','Defter beratı yüklenmeye hazır',50,1,NOW(),NOW()),
('ONAY','Onaylandı','🔒','Berat yüklendi / onaylandı',60,1,NOW(),NOW());

-- ---------------------------------------------------------------------
--  3) E-defter dönem takibi
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `edefter_takip` (
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

-- ---------------------------------------------------------------------
--  4) Adım işaretleri (kontrol listesi)
--     Satır YOKSA adım tamamlanmamış sayılır; bu sayede yeni adım
--     eklendiğinde geçmiş kayıtlara toplu satır açmak gerekmez.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `edefter_adim_durum` (
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
--  5) Ayarlar
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `ayarlar` (`anahtar`,`deger`,`aciklama`) VALUES
('edefter_aylik_ay_sonra','4','Aylık berat: dönem ayını izleyen kaçıncı ayda yüklenir (Ocak -> Mayıs = 4)'),
('edefter_ucaylik_ay_sonra','3','Üç aylık berat: dönem bitişini izleyen kaçıncı ayda yüklenir (Mart -> Haziran = 3)'),
('edefter_gun_gercek','10','Gelir vergisi mükellefi (gerçek kişi) berat günü'),
('edefter_gun_tuzel','14','Diğer mükellefler (kurumlar) berat günü'),
('edefter_aralik_gercek_ay','4','Aralık dönemi istisnası - gerçek kişi: GV beyanını (Mart) izleyen ay = Nisan'),
('edefter_aralik_tuzel_ay','5','Aralık dönemi istisnası - tüzel kişi: Kurumlar beyanını (Nisan) izleyen ay = Mayıs'),
('edefter_otomatik_uret','1','Mükellef kaydedilince e-defter dönemleri otomatik üretilsin mi'),
('edefter_uyari_gun','10','E-defter beratı için kaç gün kala panelde uyarı verilsin');

-- Bu sürümden ÖNCE kurulmuş sistemlerde varsayılanlar "ay sonu" mantığına
-- göre 3 ve 2 olarak yazılmıştı. Mevzuata uygun değerlere yükseltilir.
-- (INSERT IGNORE mevcut satırı güncellemez, bu yüzden ayrıca UPDATE gerekir.)
UPDATE `ayarlar` SET `deger`='4',
  `aciklama`='Aylık berat: dönem ayını izleyen kaçıncı ayda yüklenir (Ocak -> Mayıs = 4)'
  WHERE `anahtar`='edefter_aylik_ay_sonra' AND `deger`='3';
UPDATE `ayarlar` SET `deger`='3',
  `aciklama`='Üç aylık berat: dönem bitişini izleyen kaçıncı ayda yüklenir (Mart -> Haziran = 3)'
  WHERE `anahtar`='edefter_ucaylik_ay_sonra' AND `deger`='2';

SELECT 'Güncelleme tamamlandı.' AS sonuc;
