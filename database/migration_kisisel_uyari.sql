-- =====================================================================
--  KİŞİSEL NOTLAR / TO-DO — GİRİŞ HATIRLATMASI AYARLARI
--
--  Yeni tablo AÇILMAZ: hatırlatma, mevcut `kisisel_notlar` tablosundaki
--  görevlerin `son_tarih` alanından üretilir. Bu migration yalnız iki ayar
--  anahtarı ekler (Ayarlar → "Kişisel Notlar ve To-Do" kartı).
--
--  Idempotent: INSERT IGNORE → tekrar koşulabilir, mevcut değeri ezmez.
-- =====================================================================

INSERT IGNORE INTO `ayarlar` (`anahtar`, `deger`, `aciklama`) VALUES
('kisisel_giris_uyari', '1', 'Girişte kişisel to-do hatırlatma penceresi açılsın mı (1=evet)'),
('kisisel_uyari_gun',   '3', 'Hatırlatmada kaç gün ilerisi "yaklaşan" olarak listelensin (0-30)');

-- ---------------------------------------------------------------------
--  "Bugün okundu" kaydı — ajanda_uyari_okundu ile aynı desen.
--  Böylece pencere günde BİR kez gösterilir; çıkış/giriş yapılsa da
--  aynı gün tekrar açılmaz. Kişi başına günde tek satır (PK).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kisisel_uyari_okundu` (
  `kullanici_id` INT UNSIGNED NOT NULL,
  `tarih`        DATE NOT NULL,
  `created_at`   DATETIME NULL,
  PRIMARY KEY (`kullanici_id`, `tarih`),
  CONSTRAINT `fk_kisisel_uyari_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Sorgu desteği: açık görevler son tarihe göre süzülür.
--  (kullanici_id, tur, tamamlandi) üzerinde indeks yoksa ekle.
-- ---------------------------------------------------------------------
SET @idx_var := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'kisisel_notlar'
     AND INDEX_NAME   = 'idx_kisisel_kul_tur_tamam'
);

SET @sql := IF(@idx_var = 0,
  'ALTER TABLE `kisisel_notlar`
     ADD INDEX `idx_kisisel_kul_tur_tamam` (`kullanici_id`, `tur`, `tamamlandi`, `son_tarih`)',
  'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
