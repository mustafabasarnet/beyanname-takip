-- =====================================================================
--  GÜNCELLEME 15: GELİR VERGİSİ HESAPLAMA (mali müşavir bazında)
--
--  Amaç: Makbuz Takip'te toplanan hasılat (kesilen makbuzların brütü) ile
--  kullanıcının gireceği gider rakamından hareketle, YIL BAZINDA
--  düzenlenebilen GVK md.103 tarifesine göre gelir vergisini hesaplamak.
--
--  Üç tablo:
--    vergi_tarifeleri     → yıl bazında gelir vergisi dilimleri (elle düzenlenir)
--    musavir_gelir_gider  → müşavir + yıl için gider ve mahsup kalemleri
--    (makbuzlar tablosu zaten var; hasılat ve stopaj oradan gelir)
--
--  Dilimler YIL BAZINDA tutulur çünkü tarife her yıl yeniden açıklanır;
--  geçmiş yılın hesabı sonradan bozulmamalıdır.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_gelir_vergisi.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1) Gelir vergisi tarifesi (GVK md.103) — yıl + dilim sırası
--
--  taban        : dilimin başladığı tutar (bu tutarı AŞAN kısma oran uygulanır)
--  tavan        : dilimin bittiği tutar; NULL = son dilim (üst sınırsız)
--  sabit_vergi  : tabana kadar olan kısmın kümülatif vergisi
--  oran         : tabanı aşan kısma uygulanan yüzde
--
--  Örnek (2026, ücret dışı): taban=400.000, tavan=1.000.000,
--  sabit_vergi=70.500, oran=27  →  "1.000.000 TL'nin 400.000 TL'si için
--  70.500 TL, fazlası %27"
--
--  ucret_mi: 0 = ücret DIŞINDAKİ gelirler (serbest meslek kazancı buraya
--  girer), 1 = ücret gelirleri. Serbest meslek makbuzu hesabında 0
--  kullanılır; ücret tarifesi karşılaştırma/ileriki kullanım için tutulur.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vergi_tarifeleri` (
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

-- ---------------------------------------------------------------------
--  2) Mali müşavir gelir/gider kaydı (yıl bazında)
--
--  Hasılat MAKBUZLARDAN otomatik gelir, burada tutulmaz — makbuz eklendikçe
--  hesap kendiliğinden güncellensin diye. Burada yalnızca kullanıcının
--  elle girdiği gider ve mahsup kalemleri saklanır.
--
--  gecmis_yil_zarari : önceki yıldan devreden mahsup edilecek zarar
--  gecici_vergi      : yıl içinde ödenen geçici vergi (mahsup)
--  bagkur            : ödenen Bağ-Kur / şahıs sigorta primi (matrahtan indirim)
--  diger_indirim     : eğitim-sağlık, bağış vb. (matrahtan indirim)
--  uyumlu_indirim    : GVK mük.121 %5 uyumlu mükellef indirimi uygulansın mı
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `musavir_gelir_gider` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `musavir_id`         INT UNSIGNED NOT NULL,
  `yil`                SMALLINT UNSIGNED NOT NULL,
  `gider`              DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Toplam mesleki gider',
  `gecmis_yil_zarari`  DECIMAL(16,2) NOT NULL DEFAULT 0,
  `bagkur`             DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Ödenen Bağ-Kur/şahıs sigorta primi',
  `diger_indirim`      DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Eğitim-sağlık, bağış vb.',
  `gecici_vergi`       DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Yıl içinde ödenen geçici vergi',
  `diger_mahsup`       DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'Diğer mahsup edilecek vergiler',
  `uyumlu_indirim`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'GVK mük.121 %5 indirimi uygulansın mı',
  `stopaj_elle`        DECIMAL(16,2) NULL COMMENT 'Doldurulursa makbuzlardan gelen stopaj yerine bu kullanılır',
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

-- ---------------------------------------------------------------------
--  3) Ayarlar
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `ayarlar` (`anahtar`,`deger`,`aciklama`) VALUES
('gv_uyumlu_oran','5','Vergiye uyumlu mükellef indirimi oranı (%) — GVK mük.121'),
('gv_uyumlu_ust_sinir','12000000','Uyumlu mükellef indirimi üst sınırı (TL) — 2026: 12.000.000'),
('gv_hasilat_kaynagi','tum','Hasılat kaynağı: tum = kesilen tüm makbuzlar, tahsil = yalnız tahsil edilenler');

-- ---------------------------------------------------------------------
--  4) Hazır tarife verileri (2024-2026, ücret dışı + ücret)
--     Kaynak: GVK md.103, ilgili yılların Gelir Vergisi Genel Tebliğleri.
--     INSERT IGNORE → elle düzenlenmiş satırlar EZİLMEZ.
-- ---------------------------------------------------------------------

-- 2026 — ÜCRET DIŞINDAKİ GELİRLER (serbest meslek kazancı bu tarifeye tabi)
INSERT IGNORE INTO `vergi_tarifeleri` (`yil`,`ucret_mi`,`sira`,`taban`,`tavan`,`sabit_vergi`,`oran`) VALUES
(2026,0,1,        0,   190000,       0,15),
(2026,0,2,   190000,   400000,   28500,20),
(2026,0,3,   400000,  1000000,   70500,27),
(2026,0,4,  1000000,  5300000,  232500,35),
(2026,0,5,  5300000,     NULL, 1737500,40);

-- 2026 — ÜCRET GELİRLERİ
INSERT IGNORE INTO `vergi_tarifeleri` (`yil`,`ucret_mi`,`sira`,`taban`,`tavan`,`sabit_vergi`,`oran`) VALUES
(2026,1,1,        0,   190000,       0,15),
(2026,1,2,   190000,   400000,   28500,20),
(2026,1,3,   400000,  1500000,   70500,27),
(2026,1,4,  1500000,  5300000,  367500,35),
(2026,1,5,  5300000,     NULL, 1697500,40);

-- 2025 — ÜCRET DIŞINDAKİ GELİRLER
INSERT IGNORE INTO `vergi_tarifeleri` (`yil`,`ucret_mi`,`sira`,`taban`,`tavan`,`sabit_vergi`,`oran`) VALUES
(2025,0,1,        0,   158000,       0,15),
(2025,0,2,   158000,   330000,   23700,20),
(2025,0,3,   330000,   800000,   58100,27),
(2025,0,4,   800000,  4300000,  185000,35),
(2025,0,5,  4300000,     NULL, 1410000,40);

-- 2025 — ÜCRET GELİRLERİ
INSERT IGNORE INTO `vergi_tarifeleri` (`yil`,`ucret_mi`,`sira`,`taban`,`tavan`,`sabit_vergi`,`oran`) VALUES
(2025,1,1,        0,   158000,       0,15),
(2025,1,2,   158000,   330000,   23700,20),
(2025,1,3,   330000,  1200000,   58100,27),
(2025,1,4,  1200000,  4300000,  293000,35),
(2025,1,5,  4300000,     NULL, 1378000,40);

-- 2024 — ÜCRET DIŞINDAKİ GELİRLER
INSERT IGNORE INTO `vergi_tarifeleri` (`yil`,`ucret_mi`,`sira`,`taban`,`tavan`,`sabit_vergi`,`oran`) VALUES
(2024,0,1,       0,   110000,      0,15),
(2024,0,2,  110000,   230000,  16500,20),
(2024,0,3,  230000,   580000,  40500,27),
(2024,0,4,  580000,  3000000, 135000,35),
(2024,0,5, 3000000,     NULL, 982000,40);

-- 2024 — ÜCRET GELİRLERİ
INSERT IGNORE INTO `vergi_tarifeleri` (`yil`,`ucret_mi`,`sira`,`taban`,`tavan`,`sabit_vergi`,`oran`) VALUES
(2024,1,1,       0,   110000,      0,15),
(2024,1,2,  110000,   230000,  16500,20),
(2024,1,3,  230000,   870000,  40500,27),
(2024,1,4,  870000,  3000000, 213300,35),
(2024,1,5, 3000000,     NULL, 958800,40);

SELECT 'Güncelleme tamamlandı.' AS sonuc;
