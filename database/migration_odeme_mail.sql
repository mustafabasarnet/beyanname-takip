-- =====================================================================
--  ÖDEME BİLDİRİMİ — E-POSTA GÖNDERME AYARLARI
--
--  Ödeme Listesi → Bildirim ekranındaki "Mail Gönder" düğmesi,
--  mükellef kartında tanımlı e-posta adresine o dönemin ödeme
--  bildirimini HTML olarak gönderir.
--
--  Bu göç yalnızca AYAR ekler; tablo yapısı değişmez.
--  Idempotent: birden fazla kez çalıştırılabilir.
--
--  mysql -u KULLANICI -p beyanname_takip < migration_odeme_mail.sql
-- =====================================================================

INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`)
VALUES ('mail_etkin', '0',
        'Ödeme bildirimi e-posta gönderimi açık mı (1/0). Açmadan önce Gönderici e-posta ve SMTP ayarlarını (app/Config/Email.php) yapılandırın')
ON DUPLICATE KEY UPDATE `aciklama` = VALUES(`aciklama`);

INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`)
VALUES ('mail_gonderici_eposta', '',
        'Bildirim e-postalarının GÖNDEREN adresi (boşsa app/Config/Email.php fromEmail kullanılır)')
ON DUPLICATE KEY UPDATE `aciklama` = VALUES(`aciklama`);

INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`)
VALUES ('mail_gonderici_ad', '',
        'Gönderen görünen adı (boşsa firma_adi, o da boşsa fromName kullanılır)')
ON DUPLICATE KEY UPDATE `aciklama` = VALUES(`aciklama`);

INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`)
VALUES ('mail_konu', 'Ödeme Bildirimi — {donem}',
        'E-posta konu satırı şablonu. {donem} = dönem etiketi (örn. Ağustos 2026), {unvan} = mükellef ünvanı')
ON DUPLICATE KEY UPDATE `aciklama` = VALUES(`aciklama`);

SELECT 'Güncelleme tamamlandı.' AS sonuc;
