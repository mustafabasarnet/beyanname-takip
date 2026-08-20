-- =====================================================================
--  AYARLAR — KALDIRILAN ÖZELLİKLERİN ÖLÜ KAYITLARININ TEMİZLİĞİ
--
--  "Beni Hatırla" ve "Ödeme bildirimi e-posta gönderimi" özelliklerinin
--  kodları kaldırıldı. Bu özelliklere ait ayar satırları Ayarlar
--  ekranının "Diğer Ayarlar" bölümünde anlamsız anahtar adlarıyla
--  görünüyordu; temizlenir.
--
--  Idempotent: birden fazla kez çalıştırılabilir.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_ayar_temizligi.sql
-- =====================================================================

SET NAMES utf8mb4;

DELETE FROM `ayarlar`
 WHERE `anahtar` IN (
   -- "Beni Hatırla" (kaldırıldı)
   'hatirla_sure_gun',
   -- "Ödeme bildirimi e-posta gönderimi" (kaldırıldı)
   'mail_etkin', 'mail_gonderici_eposta', 'mail_gonderici_ad', 'mail_konu'
 );

SELECT 'Güncelleme tamamlandı.' AS sonuc;
