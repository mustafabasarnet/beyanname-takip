-- =====================================================================
--  EVRAK TAKİP — DÖNEM / TOPLAMA AYI AYRIMI
--
--  Uygulamada artık şu ayrım yapılır:
--    • EVRAK DÖNEMİ  : evrakların ait olduğu ay (tabloda yil + ay)
--    • TOPLAMA AYI   : o evrakları topladığınız ay (dönem + kaydırma)
--
--  Filtrede "Ağustos 2026" seçtiğinizde TEMMUZ 2026 dönemi evrakları
--  listelenir (varsayılan kaydırma = 1 ay).
--
--  Bu göç yalnızca AYAR ekler; evrak_takip tablosunun yapısı değişmez.
--  Idempotent: birden fazla kez çalıştırılabilir.
-- =====================================================================

INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`)
VALUES ('evrak_donem_kaydirma', '1',
        'Evrak takipte seçilen ay ile evrak dönemi arasındaki fark (ay). 1 = Ağustos seçilince Temmuz dönemi gelir, 0 = kaydırma yok')
ON DUPLICATE KEY UPDATE `aciklama` = VALUES(`aciklama`);

INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`)
VALUES ('evrak_sayfa_adedi', '50',
        'Evrak çizelgesinde ilk açılışta yüklenen mükellef sayısı')
ON DUPLICATE KEY UPDATE `aciklama` = VALUES(`aciklama`);

-- ---------------------------------------------------------------------
--  MEVCUT VERİYİ TAŞIMA (İSTEĞE BAĞLI — VARSAYILAN OLARAK KAPALI)
-- ---------------------------------------------------------------------
--  Eskiden "Ağustos" seçip TEMMUZ evraklarını işaretliyorduysanız,
--  kayıtlarınız bir ay ileri yazılmış demektir. Yeni düzende bu kayıtlar
--  bir ay kaymış görünür.
--
--  Bu durumdaysanız aşağıdaki bloğun yorumunu kaldırıp ÇALIŞTIRIN.
--  ÖNCE MUTLAKA YEDEK ALIN (Sistem → Yedekleme).
--
--  Kontrol: taşımadan önce kaç kayıt etkilenecek?
--    SELECT COUNT(*) FROM evrak_takip;
-- ---------------------------------------------------------------------

/*
-- Tüm evrak kayıtlarını bir ay GERİ al (Ağustos'a yazılanlar Temmuz olur)
UPDATE `evrak_takip`
   SET `yil` = IF(`ay` = 1, `yil` - 1, `yil`),
       `ay`  = IF(`ay` = 1, 12, `ay` - 1);
*/
