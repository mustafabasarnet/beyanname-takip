-- =============================================================================
--  SİCİL MODÜLÜ — SADE ŞABLON / İŞLEM / TODO DÖNÜŞÜMÜ  (2026-09-08)
--
--  Onaylı tasarım: SICIL_SADE_TASARIM.md (A1: mevcut tablolar dönüştürülür,
--  yeni tablo açılmaz). migration_sicil.sql'den SONRA işletilmelidir
--  (ortam kurulumunda alfabetik sıra bunu sağlar).
--
--  Dönüşüm özeti:
--    sicil_degisiklik_turleri  = ŞABLON        (değişiklik yok)
--    sicil_bildirim_kurallari  = ŞABLON TODO TANIMI  (+ serbest "ad",
--                                 kurum bağı kalkar, unique gevşer)
--    sicil_degisiklikleri      = İŞLEM         (değişiklik yok)
--    sicil_bildirim_gorevleri  = İŞLEM TODO'SU (+ üretim anında "ad" kopyası,
--                                 kurum bağı kalkar — mükerrer unique korunur)
--
--  İdempotenttir: her adım ayrı yürütülür; FK'lar bilgi_şeması korumalı
--  olarak yeniden eklenir. Güvenle birden çok kez çalıştırılabilir.
-- =============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) SİCİL BİLDİRİM KURALLARI → ŞABLON TODO TANIMI
-- ---------------------------------------------------------------------------

-- Todo'nun görünen adı (serbest metin — kurumdan bağımsız)
ALTER TABLE `sicil_bildirim_kurallari`
  ADD COLUMN IF NOT EXISTS `ad` VARCHAR(150) NULL COMMENT 'Todo görünen adı (serbest metin)'
  AFTER `degisiklik_turu_id`;

-- Kurum artık zorunlu değil (UI/kod kullanmaz; eski satırlar korunur)
ALTER TABLE `sicil_bildirim_kurallari`
  MODIFY `kurum_id` INT UNSIGNED NULL COMMENT 'Eski surum uyumu - yeni todo tanimlarinda kullanilmaz';

-- (tür,kurum) unique, fk_sicil_kural_tur'un destek indeksidir. Önce FK'lar
-- düşürülür, unique kaldırılıp yeni üretim indeksi kurulur; FK'lar aynı
-- davranışla aşağıda KOŞULLU yeniden eklenir (aynı isim çakışmasını önler).
ALTER TABLE `sicil_bildirim_kurallari`
  DROP FOREIGN KEY IF EXISTS `fk_sicil_kural_tur`;
ALTER TABLE `sicil_bildirim_kurallari`
  DROP FOREIGN KEY IF EXISTS `fk_sicil_kural_kurum`;
ALTER TABLE `sicil_bildirim_kurallari`
  DROP INDEX IF EXISTS `uq_sicil_kural_tur_kurum`,
  ADD INDEX IF NOT EXISTS `idx_sicil_kural_tur_aktif` (`degisiklik_turu_id`,`aktif`);

-- FK'ları yeniden kur (yalnızca yoksa — bilgi_şeması korumalı)
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
           WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'sicil_bildirim_kurallari'
             AND CONSTRAINT_NAME = 'fk_sicil_kural_tur');
SET @sql = IF(@fk = 0,
 'ALTER TABLE `sicil_bildirim_kurallari` ADD CONSTRAINT `fk_sicil_kural_tur`
  FOREIGN KEY (`degisiklik_turu_id`) REFERENCES `sicil_degisiklik_turleri` (`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT ''fk_sicil_kural_tur zaten var''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
           WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'sicil_bildirim_kurallari'
             AND CONSTRAINT_NAME = 'fk_sicil_kural_kurum');
SET @sql = IF(@fk = 0,
 'ALTER TABLE `sicil_bildirim_kurallari` ADD CONSTRAINT `fk_sicil_kural_kurum`
  FOREIGN KEY (`kurum_id`) REFERENCES `kurumlar` (`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT ''fk_sicil_kural_kurum zaten var''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Eski kayıtların ad'ını kurum adından türet (idempotent: ikinci çalışmada eşleşme yok)
UPDATE `sicil_bildirim_kurallari` k
LEFT JOIN `kurumlar` kr ON kr.id = k.kurum_id
SET k.ad = CONCAT(COALESCE(NULLIF(kr.ad, ''), 'Kurum'), ' Bildirimi')
WHERE k.ad IS NULL OR k.ad = '';

-- ---------------------------------------------------------------------------
-- 2) SİCİL BİLDİRİM GÖREVLERİ → İŞLEM TODO'SU
-- ---------------------------------------------------------------------------

-- Üretim anında todo adının kalıcı kopyası (şablon değişse geçmiş bozulmaz)
ALTER TABLE `sicil_bildirim_gorevleri`
  ADD COLUMN IF NOT EXISTS `ad` VARCHAR(150) NULL COMMENT 'Uretim anindaki todo adi (kalici kopya)'
  AFTER `kural_id`;

-- Kurum artık zorunlu değil
ALTER TABLE `sicil_bildirim_gorevleri`
  MODIFY `kurum_id` INT UNSIGNED NULL COMMENT 'Eski surum uyumu - yeni todo satirlarinda kullanilmaz';

-- Eski görevlerin ad'ını kural/kurum adından türet
UPDATE `sicil_bildirim_gorevleri` g
LEFT JOIN `sicil_bildirim_kurallari` k ON k.id = g.kural_id
LEFT JOIN `kurumlar` kr ON kr.id = COALESCE(g.kurum_id, k.kurum_id)
SET g.ad = COALESCE(NULLIF(k.ad, ''),
                    CONCAT(COALESCE(NULLIF(kr.ad, ''), 'Bildirim'), ' Bildirimi'))
WHERE g.ad IS NULL OR g.ad = '';

-- Not: Mükerrer koruma korunur: UNIQUE (sicil_degisikligi_id, kural_id)
--       + uq_sicil_gorev_degisiklik_kural adıyla yerinde durur.

SELECT 'Sade sablon donusumu tamamlandi.' AS sonuc;
