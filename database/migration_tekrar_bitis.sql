-- =====================================================================
--  ÖZEL ÖDEME KALEMLERİ — TEKRAR BİTİŞ TARİHİ
--
--  "Her ay tekrar etsin mi? = Evet" seçilen kalemler artık izleyen aylarda
--  otomatik üretilir. Bu göç, tekrarın ne zaman duracağını belirten
--  `tekrar_bitis` sütununu ekler (boş = süresiz).
--
--  Ayrıca `tekrar_kaynak_id`: üretilen kalemin hangi kalemden çoğaldığını
--  tutar. Böylece bir seride tutar/başlık değişse bile zincir izlenebilir
--  ve mükerrer üretim engellenir.
--
--  Idempotent: birden fazla kez çalıştırılabilir.
-- =====================================================================

-- ---------- tekrar_bitis ----------
SET @vt := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `ozel_odemeler`
       ADD COLUMN `tekrar_bitis` DATE NULL
       COMMENT ''Tekrarın duracağı tarih (boş = süresiz)'' AFTER `tekrar`',
    'SELECT ''tekrar_bitis zaten var'''
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @vt
    AND TABLE_NAME   = 'ozel_odemeler'
    AND COLUMN_NAME  = 'tekrar_bitis'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- tekrar_kaynak_id ----------
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `ozel_odemeler`
       ADD COLUMN `tekrar_kaynak_id` INT UNSIGNED NULL
       COMMENT ''Bu kalem hangi tekrarlı kalemden üretildi'' AFTER `tekrar_bitis`',
    'SELECT ''tekrar_kaynak_id zaten var'''
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @vt
    AND TABLE_NAME   = 'ozel_odemeler'
    AND COLUMN_NAME  = 'tekrar_kaynak_id'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- indeks ----------
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `ozel_odemeler` ADD KEY `idx_oo_tekrar` (`tekrar`,`son_tarih`)',
    'SELECT ''idx_oo_tekrar zaten var'''
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @vt
    AND TABLE_NAME   = 'ozel_odemeler'
    AND INDEX_NAME   = 'idx_oo_tekrar'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `ozel_odemeler` ADD KEY `idx_oo_kaynak` (`tekrar_kaynak_id`)',
    'SELECT ''idx_oo_kaynak zaten var'''
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @vt
    AND TABLE_NAME   = 'ozel_odemeler'
    AND INDEX_NAME   = 'idx_oo_kaynak'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
