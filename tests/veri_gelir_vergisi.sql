-- Gelir vergisi testleri için örnek veri (yalnızca test ortamı)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE makbuzlar;
TRUNCATE TABLE mukellef_ucretleri;
TRUNCATE TABLE musavir_gelir_gider;
DELETE FROM mukellefler;
DELETE FROM kullanici_musavirleri;
DELETE FROM kullanicilar;
DELETE FROM musavirler;
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE musavirler AUTO_INCREMENT = 1;
ALTER TABLE mukellefler AUTO_INCREMENT = 1;
ALTER TABLE makbuzlar AUTO_INCREMENT = 1;
ALTER TABLE kullanicilar AUTO_INCREMENT = 1;

INSERT INTO musavirler (id, ad_soyad, buro_adi, tc_kimlik, renk, aktif) VALUES
(1,'Ali Yılmaz','Yılmaz Mali Müşavirlik','11111111111','#2563eb',1),
(2,'Veli Demir','Demir Danışmanlık','22222222222','#059669',1);

-- Şifre: Test1234
INSERT INTO kullanicilar (id, kullanici_adi, ad_soyad, eposta, sifre, rol, musavir_id, aktif) VALUES
(1,'admin','Yönetici','admin@test.local','$2y$12$28iKMgsSOllSVWBYNOUhqOu2TDdLzrJzavQUPvf6KZmhQuFgqpNTW','admin',NULL,1),
(2,'personel','Personel Ayşe','personel@test.local','$2y$12$28iKMgsSOllSVWBYNOUhqOu2TDdLzrJzavQUPvf6KZmhQuFgqpNTW','personel',1,1),
(3,'musavir','Müşavir Ali','musavir@test.local','$2y$12$28iKMgsSOllSVWBYNOUhqOu2TDdLzrJzavQUPvf6KZmhQuFgqpNTW','musavir',1,1),
(4,'fatma','Fatma Kaya','fatma@test.local','$2y$12$28iKMgsSOllSVWBYNOUhqOu2TDdLzrJzavQUPvf6KZmhQuFgqpNTW','personel',1,1);

INSERT INTO kullanici_musavirleri (kullanici_id, musavir_id) VALUES (3,1);

INSERT INTO mukellefler (id, unvan, mukellef_tipi, vergi_kimlik_no, tc_kimlik_no, musavir_id, aktif, ise_baslama_tarihi) VALUES
(1,'ALFA TEKSTİL LTD ŞTİ','tuzel','1111111111',NULL,1,1,'2020-01-01'),
(2,'BETA GIDA A.Ş.','tuzel','2222222222',NULL,1,1,'2020-01-01'),
(3,'CEM ÖZKAN','gercek',NULL,'33333333333',1,1,'2020-01-01'),
(4,'DELTA İNŞAAT LTD','tuzel','4444444444',NULL,2,1,'2020-01-01'),
(5,'EMRE ŞAHİN','gercek',NULL,'55555555555',2,1,'2020-01-01');

INSERT INTO mukellef_ucretleri (mukellef_id, yil, tutar) VALUES
(1,2026,120000),(2,2026,96000),(3,2026,48000),(4,2026,144000),(5,2026,60000);

-- Ali Yılmaz (müşavir 1): 2026 içinde toplam brüt 500.000, stopaj 100.000
INSERT INTO makbuzlar (mukellef_id, musavir_id, yil, ay, makbuz_no, tarih, brut, stopaj, kdv, net, tahsil_edildi, tahsil_tarihi) VALUES
(1,1,2026,1,'A-001','2026-01-15',100000,20000,20000,100000,1,'2026-01-20'),
(1,1,2026,4,'A-002','2026-04-15',100000,20000,20000,100000,1,'2026-04-20'),
(2,1,2026,2,'A-003','2026-02-10', 80000,16000,16000, 80000,1,'2026-02-15'),
(2,1,2026,5,'A-004','2026-05-10', 80000,16000,16000, 80000,0,NULL),
(3,1,2026,3,'A-005','2026-03-05', 70000,14000,14000, 70000,1,'2026-03-10'),
(3,1,2026,6,'A-006','2026-06-05', 70000,14000,14000, 70000,0,NULL);

-- Veli Demir (müşavir 2): toplam brüt 150.000, stopaj 30.000
INSERT INTO makbuzlar (mukellef_id, musavir_id, yil, ay, makbuz_no, tarih, brut, stopaj, kdv, net, tahsil_edildi, tahsil_tarihi) VALUES
(4,2,2026,1,'V-001','2026-01-20',100000,20000,20000,100000,1,'2026-01-25'),
(5,2,2026,2,'V-002','2026-02-20', 50000,10000,10000, 50000,0,NULL);

SELECT 'Test verisi yüklendi.' AS sonuc;
