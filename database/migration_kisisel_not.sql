-- =====================================================================
--  KİŞİSEL GÜNLÜK NOT + TO-DO LISTESİ (yalnızca sahibine özel)
--
--  Her kullanıcının kendine özel günlük notları ve yapılacaklar listesi.
--  VERİ TAMAMEN İZOLE: yönetici dahil hiçbir başka kullanıcı göremez,
--  URL ile de erişemez (tüm sorgular kullanici_id ile süzülür).
--
--  tur = 'not'   → tarihe bağlı günlük not (kullanici_id + tarih tekil)
--  tur = 'gorev' → to-do öğesi (başlık + isteğe bağlı not + tamamlandı)
--
--  mysql -u KULLANICI -p beyanname_takip < migration_kisisel_not.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `kisisel_notlar` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_id`      INT UNSIGNED NOT NULL COMMENT 'Kaydın sahibi (yalnız o görür)',
  `tur`               ENUM('not','gorev') NOT NULL DEFAULT 'not',
  `tarih`             DATE NULL COMMENT 'not türünde: notun ait olduğu gün',
  `baslik`            VARCHAR(200) NULL COMMENT 'gorev türünde: görev adı',
  `metin`             TEXT NULL COMMENT 'not metni veya görev açıklaması',
  `tamamlandi`        TINYINT(1) NOT NULL DEFAULT 0,
  `tamamlandi_tarihi` DATETIME NULL,
  `created_at`        DATETIME NULL,
  `updated_at`        DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kisisel_not_gun` (`kullanici_id`,`tarih`),
  KEY `idx_kisisel_sahip` (`kullanici_id`),
  CONSTRAINT `fk_kisisel_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Güncelleme tamamlandı.' AS sonuc;
