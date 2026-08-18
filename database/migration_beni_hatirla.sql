-- =====================================================================
--  GİRİŞ — "BENİ HATIRLA" (kalıcı oturum)
--
--  Giriş sayfasındaki "Beni Hatırla" kutusu işaretlenirse, oturum
--  süresi dolduktan sonra da kullanıcıyı tanıyan kalıcı bir çerez
--  (bt_hatirla) oluşturulur. Çerezin doğrulama karşılığı (hash) bu
--  tabloda saklanır; veritabanı ele geçirilse bile çerez tek başına
--  işe yaramaz.
--
--  Idempotent: birden fazla kez çalıştırılabilir.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_beni_hatirla.sql
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `hatirlanan_oturumlar` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_id`  INT UNSIGNED NOT NULL,
  `token_hash`    CHAR(64) NOT NULL COMMENT 'Çerezin SHA-256 karşılığı (ham çerez saklanmaz)',
  `ip`            VARCHAR(45) NULL,
  `user_agent`    VARCHAR(255) NULL,
  `son_kullanma`  DATETIME NOT NULL,
  `olusturulma`   DATETIME NOT NULL,
  `updated_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hatirla_token` (`token_hash`),
  KEY `idx_hatirla_kullanici` (`kullanici_id`),
  CONSTRAINT `fk_hatirla_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`)
VALUES ('hatirla_sure_gun', '30',
        '"Beni hatırla" çerezinin geçerli kalacağı gün sayısı (0 = özellik kapalı)')
ON DUPLICATE KEY UPDATE `aciklama` = VALUES(`aciklama`);

SELECT 'Güncelleme tamamlandı.' AS sonuc;
