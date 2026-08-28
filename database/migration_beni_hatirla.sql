-- =====================================================================
--  GÜNCELLEME 21: "BENİ HATIRLA" (kalıcı oturum)
--
--  Giriş ekranındaki "Beni hatırla" kutusu işaretlenirse tarayıcıya bir
--  jeton çerezi bırakılır; oturum süresi dolsa da kullanıcı 90 gün
--  boyunca yeniden şifre girmeden içeri alınır.
--
--  GÜVENLİK NOTU
--  Çerezde jetonun KENDİSİ değil, yalnızca doğrulayıcısının SHA-256
--  özeti saklanır. Veritabanı sızsa bile çerez üretilemez. Her kullanım
--  sonrası jeton yenilenir (rotation); çalınan bir çerez ikinci kez
--  kullanılırsa eşleşme bozulur.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_beni_hatirla.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `hatirlatma_jetonlari` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_id`   INT UNSIGNED NOT NULL,
  `secici`         CHAR(32)     NOT NULL COMMENT 'Çerezde açık taşınan arama anahtarı',
  `dogrulayici`    CHAR(64)     NOT NULL COMMENT 'Gizli parçanın SHA-256 özeti',
  `son_gecerlilik` DATETIME     NOT NULL,
  `tarayici`       VARCHAR(255) NULL COMMENT 'Bilgi amaçlı: user-agent özeti',
  `ip`             VARCHAR(45)  NULL,
  `created_at`     DATETIME     NULL,
  `updated_at`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_secici` (`secici`),
  KEY `idx_kullanici` (`kullanici_id`),
  KEY `idx_gecerlilik` (`son_gecerlilik`),
  CONSTRAINT `fk_hatirlatma_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Beni hatırla jetonları (kalıcı oturum)';

-- ---------------------------------------------------------------------
--  Ayarlar
-- ---------------------------------------------------------------------
INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`)
VALUES ('hatirla_gun', '90',
        'Beni hatırla seçildiğinde oturumun açık kalacağı gün sayısı')
ON DUPLICATE KEY UPDATE `aciklama` = VALUES(`aciklama`);

INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`)
VALUES ('hatirla_acik', '1',
        'Giriş ekranında "Beni hatırla" kutusu gösterilsin mi (1=evet)')
ON DUPLICATE KEY UPDATE `aciklama` = VALUES(`aciklama`);
