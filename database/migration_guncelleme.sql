-- =====================================================================
--  GÜNCELLEME LOGLARI (sürüm notları)
--
--  Ne yapar?
--    • Yönetici "Güncellemeler" ekranından sürüm notu girer (başlık + maddeler)
--    • Kullanıcı girişte, OKUMADIĞI güncellemeleri modern bir pencere olarak görür
--    • "Okudum" deyince o kullanıcı için bir daha gösterilmez (kişi bazlı kayıt)
--
--  İçerik biçimi (satır başı işareti):
--      + eklenen      ~ değişen      - kaldırılan      ! düzeltilen
--    İşaretsiz satırlar "not" olarak normal madde sayılır.
--
--  Idempotent: tekrar koşulabilir; mevcut kayıtları bozmaz (seed INSERT IGNORE).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `guncellemeler` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `versiyon`     VARCHAR(20)  NOT NULL COMMENT 'Sürüm etiketi (örn. 1.4.0)',
  `tarih`        DATE         NOT NULL COMMENT 'Yayın tarihi',
  `baslik`       VARCHAR(200) NOT NULL COMMENT 'Güncelleme başlığı',
  `icerik`       TEXT         NOT NULL COMMENT 'Maddeler; satır başı: + eklenen · ~ değişen · - kaldırılan · ! düzeltilen',
  `aktif`        TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = yayında değil (kullanıcıya gösterilmez)',
  `olusturan_id` INT UNSIGNED NULL,
  `created_at`   DATETIME     NULL,
  `updated_at`   DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_guncel_aktif_tarih` (`aktif`, `tarih`),
  CONSTRAINT `fk_guncel_kullanici` FOREIGN KEY (`olusturan_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kim hangi güncellemeyi okudu? (kişi + güncelleme = tek satır)
CREATE TABLE IF NOT EXISTS `guncelleme_okundu` (
  `kullanici_id`  INT UNSIGNED NOT NULL,
  `guncelleme_id` INT UNSIGNED NOT NULL,
  `okundu_at`     DATETIME     NULL,
  PRIMARY KEY (`kullanici_id`, `guncelleme_id`),
  CONSTRAINT `fk_gok_kullanici` FOREIGN KEY (`kullanici_id`)
     REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_gok_guncelleme` FOREIGN KEY (`guncelleme_id`)
     REFERENCES `guncellemeler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Başlangıç kaydı: son geliştirme turunda eklenenler
--  (Yönetici bu kaydı düzenleyebilir/silebilir; bir kere eklenir.)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `guncellemeler` (`id`, `versiyon`, `tarih`, `baslik`, `icerik`, `aktif`, `created_at`, `updated_at`) VALUES
(1, '1.4.0', CURDATE(), 'Kişisel notlar, ajanda gizliliği ve makbuz formu',
"+ Menüde **Kişisel Notlar** rozeti: yapılmamış görev sayısı görünür, görev tamamlanınca anında düşer\n+ Kişisel To-Do hatırlatması: son tarihi geçen ve bugün son gün olan görevler girişte pencere olarak çıkar\n+ Makbuz Takip: makbuzlar artık **➕ Makbuz Ekle** formuyla da girilebilir (Excel'e ek olarak)\n~ Kişisel notlar sekmesi açılmadan da günlük görevler size ulaşır\n! Ajanda gizliliği: kişisel kayıtlar yönetici dahil kimse başkası adına görülemez/değiştirilemez",
1, NOW(), NOW());
