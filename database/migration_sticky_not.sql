-- =====================================================================
--  KİŞİSEL YAPIŞKAN NOTLAR (Sticky Not) — tablo
--  ---------------------------------------------------------------------
--  Günlük not / to-do'dan AYRI, tarihe bağlı olmayan kalıcı kartlar.
--  Yalnız sahibi görür (yönetici dâhil). Kullanıcı silinince kartlar
--  ON DELETE CASCADE ile gider.
--
--  Faz 1 kolonları: metin, renk, sabit, sira.
--  Faz 2 için (arşiv, başlık, hatırlatma) kolonlar şimdiden hazır ama
--  bu sürümde kullanılmaz.
--
--  NOT: information_schema / PREPARE / EXECUTE KULLANILMAZ (phpMyAdmin
--       ve kısıtlı veritabanı kullanıcılarında da çalışır).
--       CREATE TABLE IF NOT EXISTS → tekrar koşulabilir (idempotent).
--
--  Kurulum: phpMyAdmin → SQL sekmesi → yapıştır → Git
--       veya: mysql -u KULLANICI -p beyanname_takip < migration_sticky_not.sql
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `kisisel_sticky_notlar` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_id`   INT UNSIGNED NOT NULL COMMENT 'Sahip — yalnız bu kullanıcı görür',
  `baslik`         VARCHAR(40)  NULL COMMENT 'Faz 2: kart başlığı',
  `metin`          TEXT         NULL COMMENT 'Not içeriği (uygulama 1000 karakterle sınırlar)',
  `renk`           VARCHAR(12)  NOT NULL DEFAULT 'sari' COMMENT 'turkuaz | nane | sari | pembe | mavi | turuncu',
  `sabit`          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = 📌 sabitli, her zaman üstte',
  `sira`           INT          NOT NULL DEFAULT 0 COMMENT 'Sürükle-bırak sırası (küçük = önce)',
  `hatirlat_tarih` DATE         NULL COMMENT 'Faz 2: isteğe bağlı hatırlatma tarihi',
  `arsiv_at`       DATETIME     NULL COMMENT 'Faz 2: NULL = aktif, dolu = arşivde',
  `created_at`     DATETIME     NULL,
  `updated_at`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sticky_liste` (`kullanici_id`, `arsiv_at`, `sabit`, `sira`),
  CONSTRAINT `fk_sticky_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Doğrulama: yalnız kendi tablomuzu gösterir (yetki gerektirmez)
SHOW COLUMNS FROM `kisisel_sticky_notlar`;
