-- =====================================================================
--  GÜNCELLEME 19: YILLIK ÜCRET PROJEKSİYONU (gelir vergisi hesap kipi)
--
--  Mali müşavir yıl sonunu beklemeden "yıllık vergi yükümü" görmek istiyor.
--  Bunun için hesap iki kipte çalışır:
--
--    ucret     → Mükellef kartlarına girilen YILLIK SÖZLEŞME ÜCRETLERİ
--                makbuza dönüşmüş kabul edilir. Hasılat, stopaj ve KDV
--                bu ücretlerden HESAPLANIR. (varsayılan)
--    makbuz    → Yalnızca fiilen KESİLEN makbuzlar sayılır (eski davranış).
--
--  Kip, hesap ekranından tek tıkla değiştirilir; tercih müşavir+yıl
--  bazında saklanır.
--
--  Ayrıca yıllık ücretten stopaj/KDV hesabı için AYRI oran ayarları
--  eklenir (kullanıcı kararı) — makbuz modülünün oranlarından bağımsız.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_gv_ucret_modu.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1) Hesap kipi sütunu
-- ---------------------------------------------------------------------
ALTER TABLE `musavir_gelir_gider`
  ADD COLUMN IF NOT EXISTS `hesap_kipi` ENUM('ucret','makbuz') NOT NULL DEFAULT 'ucret'
      COMMENT 'ucret = yıllık sözleşme ücretleri (projeksiyon), makbuz = kesilen makbuzlar'
      AFTER `yil`;

-- ---------------------------------------------------------------------
--  2) Ayarlar — yıllık ücretten stopaj/KDV oranları
--     Makbuz modülündeki (makbuz_stopaj_oran / makbuz_kdv_oran)
--     ayarlardan BAĞIMSIZDIR; büro isterse farklı oran kullanabilir.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `ayarlar` (`anahtar`,`deger`,`aciklama`) VALUES
('gv_ucret_stopaj_oran','20','Yıllık sözleşme ücretinden stopaj oranı (%) — gelir vergisi projeksiyonu'),
('gv_ucret_kdv_oran','20','Yıllık sözleşme ücretinden KDV oranı (%) — gelir vergisi projeksiyonu'),
('gv_varsayilan_kip','ucret','Yeni kayıtlarda varsayılan hesap kipi: ucret | makbuz');

SELECT 'Güncelleme tamamlandı.' AS sonuc;
