-- =====================================================================
--  GÜNCELLEME 13: Genç girişimci istisnası yalnızca GERÇEK KİŞİLERDE
--
--  GVK mükerrer 20 istisnası şirketlere (tüzel kişilere) uygulanmaz.
--  Uygulama artık tüzel kişide bu seçeneği hiç göstermiyor; bu betik
--  daha önce yanlışlıkla işaretlenmiş TÜZEL kayıtları temizler.
--
--  Yalnızca mukellef_tipi='tuzel' olan satırlara dokunur; gerçek kişi
--  mükelleflerin istisna bilgisi KORUNUR.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_gg_tuzel_temizlik.sql
--  Birden çok kez çalıştırılabilir (idempotent).
-- =====================================================================

SET NAMES utf8mb4;

-- Temizlik öncesi durum (bilgi amaçlı)
SELECT COUNT(*) AS 'Temizlenecek tüzel kayıt'
FROM `mukellefler`
WHERE `mukellef_tipi` = 'tuzel'
  AND (`genc_girisimci` = 1 OR `gg_baslangic_yili` IS NOT NULL OR `gg_not` IS NOT NULL);

UPDATE `mukellefler`
SET `genc_girisimci`    = 0,
    `gg_baslangic_yili` = NULL,
    `gg_not`            = NULL
WHERE `mukellef_tipi` = 'tuzel'
  AND (`genc_girisimci` = 1 OR `gg_baslangic_yili` IS NOT NULL OR `gg_not` IS NOT NULL);

SELECT 'Güncelleme tamamlandı.' AS sonuc;
