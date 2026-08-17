# 📋 Mükellef Beyanname & Evrak Takip Programı

CodeIgniter 4.7 · PHP 8.1+ · MySQL/MariaDB · Modern responsive arayüz

> ✅ **Canlı test edildi:** PHP 8.4 + MariaDB 11.8 ortamında kurulumdan mükellef
> eklemeye kadar tüm akış çalıştırıldı; 29 sayfa ve 6 AJAX ucu hatasız yanıt verdi.

## ⚙️ Gerekli PHP Eklentileri

```
intl, mbstring, mysqli (veya mysqlnd), json, curl
```
Eksikse: `sudo apt install php8.x-intl php8.x-mbstring php8.x-mysql php8.x-curl`
Ardından web sunucusunu yeniden başlatın. `composer install` bu eklentiler
olmadan hata verir.

---

## 🚀 Hızlı Kurulum (5 adım)

### 1. Dosyaları sunucuya atın
Tüm klasörü sunucunuza kopyalayın (örn. `/var/www/beyanname-takip`).

### 2. Bağımlılıkları yükleyin
```bash
cd beyanname-takip
composer install
```
> Composer yoksa: https://getcomposer.org/download/

### 3. Veritabanını oluşturun
phpMyAdmin veya terminal ile:
```bash
mysql -u root -p < database/beyanname_takip.sql
```
Bu dosya veritabanını, 17 tabloyu, 13 beyanname türünü, 8 evrak türünü ve
**2025-2027 resmi/dini tatillerini** otomatik yükler.

> **Zaten kurulu bir sisteminiz varsa** ayrıca şunu çalıştırın:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_kullanici_musavir.sql
> ```
> Kullanıcı–müşavir ilişkisini çoklu yapıya taşır, mevcut bağlantıları korur,
> birden çok kez çalıştırılabilir (idempotent).
>
> Ödeme listesi ve karşıt inceleme özellikleri için ayrıca:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_odeme_karsit.sql
> ```
>
> Muhasebe ücreti ve takip başlangıcı için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_ucret_takip.sql
> ```
>
> Ödeme tarihi ayrımı, özel ödeme kalemleri ve durum sadeleştirmesi için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_odeme_tarihi_ozel.sql
> ```
>
> Kullanıcıya özel ödeme listeleri için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_ozel_liste.sql
> ```
>
> Listelerin dönemden bağımsız hale gelmesi için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_liste_donem.sql
> ```
>
> Genç girişimci istisnası için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_genc_girisimci.sql
> ```
>
> Aylık tekrar eden ödeme kalemleri (bitiş tarihi) için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_tekrar_bitis.sql
> ```
>
> Evrak takip dönem kaydırması için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_evrak_donem.sql
> ```
>
> İndirim / kısıtlama rozetleri (Bağkur, Eğitim-Sağlık, Finansman) için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_indirimler.sql
> ```
>
> E-Defter berat takibi için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_edefter.sql
> ```
>
> Genç girişimci temizliği (tüzel kişilerde yanlış işaretlenmiş kayıtlar):
> ```bash
> mysql -u root -p beyanname_takip < database/migration_gg_tuzel_temizlik.sql
> ```
>
> Serbest meslek makbuzu takibi için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_makbuz.sql
> ```
>
> Gelir vergisi hesaplama (mali müşavir bazında) için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_gelir_vergisi.sql
> ```
> Bu güncelleme `vergi_tarifeleri` ve `musavir_gelir_gider` tablolarını
> oluşturur; **2024, 2025 ve 2026 gelir vergisi tarifelerini hazır yükler**
> (hem ücret hem ücret dışı gelirler için). Tarifeler ekrandan düzenlenebilir.
>
> Mevzuata bağlı indirimler + aylık KDV tablosu için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_gv_kdv_indirim.sql
> ```
> Şahıs sigorta primi (%15) ve eğitim-sağlık (%10) alanlarını ekler,
> `musavir_kdv` tablosunu oluşturur; kullanılmayan üç alanı pasifler.
>
> Yıllık ücret projeksiyonu (hesap kipi) için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_gv_ucret_modu.sql
> ```
> `hesap_kipi` sütununu ve ücretten stopaj/KDV oran ayarlarını ekler.
>
> İndirim belgelerini liste hâlinde girmek için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_gv_indirim_kalem.sql
> ```
> `musavir_indirim_kalem` tablosunu oluşturur — eğitim-sağlık harcamaları ve
> sigorta primleri artık **tarih · tür · açıklama · tutar** olarak belge belge girilir.
>
> Aylık gider tablosu için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_gv_aylik_gider.sql
> ```
> `musavir_aylik_gider` tablosunu oluşturur — mesleki gider ay ay girilir,
> toplamı elle girilen "Toplam Mesleki Gider" tutarına **eklenir**.
>
> Ajanda / hatırlatıcı için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_ajanda.sql
> ```
> `ajanda`, `ajanda_ek` ve `ajanda_uyari_okundu` tablolarını oluşturur.
>
> Evrak takibinde "bu mükellefte yok" (takip dışı) için:
> ```bash
> mysql -u root -p beyanname_takip < database/migration_evrak_muafiyet.sql
> ```
> `mukellef_evrak_muafiyet` tablosunu oluşturur ve `evrak_takip.durum`
> ENUM'una `YOK` değerini ekler — bankası/çeki olmayan mükelleflerin
> hücreleri artık kırmızı değil **taralı gri** görünür.

### 4. `.env` dosyasını hazırlayın
```bash
cp env.example .env
```
İçini kendi bilgilerinizle düzenleyin:
```ini
CI_ENVIRONMENT = production
app.baseURL = 'http://siteniz.com/'

database.default.hostname = localhost
database.default.database = beyanname_takip
database.default.username = KULLANICI
database.default.password = SIFRE
```

### 5. Yazma izni verin ve kurulumu tamamlayın
```bash
chmod -R 775 writable/
```
Tarayıcıdan **`http://siteniz.com/kurulum`** adresine gidin, yönetici hesabınızı
oluşturun. Kurulum tamamlanınca bu adres otomatik kilitlenir.

**Web kökü:** Mümkünse sunucunun document root'unu `public/` klasörüne yönlendirin.
Paylaşımlı hostingde bu mümkün değilse kökteki `.htaccess` zaten yönlendirmeyi yapar.

---

## ⭐ Gün Takibi — Programın Kalbi

Beyanname dönemleri **işe başlama** ve **terk** tarihine göre otomatik üretilir.
Kural basit ve şaşmaz:

> Bir dönem satırı, ancak mükellefin faaliyet aralığı ile beyanname döneminin
> aralığı **kesişiyorsa** oluşur.

### Sizin verdiğiniz senaryo — test edilmiş sonuç

**Mükellef: 01.03.2026 başladı → 31.03.2026 terk etti**

| Beyanname | Sonuç | Son Tarih |
|---|---|---|
| KDV1 Ocak / Şubat 2026 | ❌ Oluşmaz (henüz başlamamış) | — |
| KDV1 **Mart 2026** | ✅ Oluşur | 28.04.2026 |
| KDV1 Nisan ve sonrası | ❌ Oluşmaz (terk etmiş) | — |
| MUHSGK **Mart 2026** | ✅ Oluşur | 27.04.2026 *(26 Nisan Pazar → Pazartesi)* |
| MUHSGK Nisan ve sonrası | ❌ Oluşmaz | — |
| Damga / SGK Mart | ✅ Oluşur | 27.04 / 30.04.2026 |
| Geçici Vergi **1. Dönem** (Oca-Şub-Mar) | ✅ Oluşur (Mart kesişiyor) | 18.05.2026 *(17 Mayıs Pazar)* |
| Geçici Vergi **2. Dönem** (Nis-May-Haz) | ❌ **Oluşmaz** ✔ istediğiniz gibi | — |
| Geçici Vergi 3. Dönem | ❌ Oluşmaz | — |
| **Yıllık Gelir Vergisi 2026** | ✅ **Oluşur** ✔ izleyen yıl | 31.03.2027 |
| 2027 yılı hiçbir dönem | ❌ Hiç oluşmaz | — |

### Sonradan devralınan mükellefler — Takip Başlangıcı

İşe başlama tarihi eski olan bir mükellefi sonradan devraldıysanız, geçmiş dönemler
"gecikmiş" olarak görünür. İki çözüm var:

**1. Takip Başlangıcı (kalıcı çözüm — önerilen)**
Mükellef kartındaki **Takip Başlangıcı** alanına devraldığınız tarihi yazın.
Bu tarihten önceki dönemler **hiç oluşturulmaz**; çizelge tertemiz gelir.

| Mükellef | İşe Başlama | Takip Başlangıcı | 2026 dönem sayısı |
|---|---|---|---|
| Normal | 01.01.2024 | (boş) | 12 ay + geçmiş yıllar |
| Devralınan | 01.01.2024 | **01.03.2026** | **yalnızca Mart-Aralık (10 ay)** |

Bu kural evrak çizelgesinde de geçerlidir.

**2. Geçmişi Kapat (mevcut kayıtlar için)**
Dönemler zaten oluştuysa, mükellef detayındaki **🧹 Geçmişi Kapat** düğmesiyle
belirlediğiniz tarihten önceki **Bekliyor** durumundaki satırları tek tıkla
"Gönderildi" veya "Verilmeyecek" yapabilirsiniz.
Zaten işlem görmüş satırlara dokunulmaz.

Bu senaryonun tamamı otomatik testle doğrulanmıştır:
```bash
php tests/mantik_testi.php     # 72/72 — dönem üretimi, tatil kaydırma, takip başlangıcı
php tests/filtre_testi.php     # 18/18 — yıl/ay filtre mantığı
php tests/genc_girisimci_testi.php  # 23/23 — istisna dönem hesabı
```

---

## 🎌 Tatil Kaydırma

Son gün **hafta sonu veya resmi/dini tatile** denk gelirse, otomatik olarak
**tatil bitimini izleyen ilk iş gününe** kaydırılır. Çizelgede hem *yasal tarih*
hem *kaydırılmış tarih* ve kaydırma nedeni gösterilir.

Doğrulanmış örnekler:

| Kanuni Son Gün | Durum | Kaydırılan Tarih |
|---|---|---|
| 28.02.2026 | Cumartesi | **02.03.2026** Pazartesi |
| 28.03.2026 | Cumartesi | **30.03.2026** Pazartesi |
| 28.05.2026 | Kurban Bayramı 2. gün | **01.06.2026** Pazartesi |
| 28.06.2026 | Pazar | **29.06.2026** Pazartesi |
| 20.03.2026 | Ramazan Bayramı | **23.03.2026** Pazartesi |
| 29.10.2026 | Cumhuriyet Bayramı | **30.10.2026** Cuma |
| 28.04.2026 | Normal iş günü | 28.04.2026 *(değişmez)* |

**Tanımlar → Resmi Tatiller** menüsünden yeni tatil ekleyebilir, mevcutları
düzenleyebilirsiniz. Değişiklikten sonra **Toplu Dönem Üretimi** çalıştırın.

**Ayarlar** ekranından: Cumartesi/Pazar tatil sayılsın mı, arifeler tatil sayılsın mı,
mali tatil (1-20 Temmuz) uygulansın mı — kontrol edebilirsiniz.

---

## 🔀 Beyanname Çakışma Kuralı

Mükellef formunda beyanname türü seçerken çakışmalar **anlık** engellenir:

| Seçilen | Otomatik Pasifleşen |
|---|---|
| **Yıllık Gelir Vergisi** | Kurumlar Vergisi + Kurum Geçici Vergi |
| **Kurumlar Vergisi** | Yıllık Gelir Vergisi + Gelir Geçici Vergi |
| KDV1 Aylık | KDV1 Üç Aylık |
| KDV1 Üç Aylık | KDV1 Aylık |
| MUHSGK Aylık | MUHSGK Üç Aylık |

Ayrıca **mükellef tipi** (Gerçek/Tüzel) seçimine göre uygunsuz türler kilitlenir:
şahıs mükellefte Kurumlar Vergisi, kurumda Yıllık Gelir Vergisi seçilemez.

Bu kurallar veritabanından yönetilir → **Tanımlar → Beyanname Türleri → Çelişen Kodlar**.

---

---

## 📊 Kontrol Paneli — Beyanname Durum Kontrol Tablosu

Panelin üstünde, seçilen ay için **beyanname türü bazında** bir durum tablosu
bulunur. "Bu ay hangi beyannameden kaç tane kaldı?" sorusuna tek bakışta
cevap verir.

| Sütun | Anlamı |
|---|---|
| **Toplam** | O ay bu türden kaç kayıt var |
| **Onaylandı** | Tamamlananlar |
| **Hazır** | Onay bekleyenler |
| **Bekliyor** | Henüz işlem yapılmayanlar |
| **Gecikmiş** | Son günü geçmiş, hâlâ bitmemiş olanlar |
| **Kalan** | Bekliyor + Hazır (yapılacak iş) |
| **Durum** | Tamamlanma yüzdesi ve çubuk |

### Türler aya göre kendiliğinden değişir

Tablodaki satırlar **sabit bir listeden değil**, o ay gerçekten var olan
kayıtlardan üretilir. Sonuç:

| Seçilen ay | Tabloda görünen türler |
|---|---|
| **Ağustos** | KDV1, MUHSGK, **Gelir Geçici**, **Kurum Geçici**, Damga… |
| **Eylül** | KDV1, MUHSGK, Damga (geçici vergiler **listeden düşer**) |
| **Mart** | KDV1, MUHSGK, **Yıllık GV** |
| **Nisan** | KDV1, MUHSGK, **Kurumlar** |

Geçici vergiler yalnızca verildikleri aylarda (Şubat/Mayıs/Ağustos/Kasım)
görünür; ilgisiz aylarda tabloyu şişirmez.

### Sayılara tıklayınca liste açılır

Herhangi bir sayıya tıkladığınızda **panelden ayrılmadan** açılır pencerede
o mükelleflerin listesi gelir: ünvan, VKN/TCKN, dönem, son tarih ve durum.
Gecikmiş olanlar kırmızı ⏰ işaretiyle belirtilir.

Pencerenin üstündeki **"Takip ekranında aç"** bağlantısı aynı süzgeci
(tür + ay + durum) Beyanname Takip ekranına taşır. Kapatmak için ✕, dışarı
tıklama veya `Esc`.

Sıfır olan hücreler tıklanamaz — boş liste açılmaz.

### Tablo ekseni

Filtre çubuğundaki **Tablo Ekseni** seçicisi ayın neye göre sayılacağını
belirler:

- **Beyan Dönemi (son tarih)** — varsayılan; "bu ay ne vereceğim"
- **Ait Olduğu Dönem** — "bu aya ait beyannameler"

> **Not:** "Verilmeyecek" işaretli kayıtlar Toplam'a dahildir ama **Kalan'a ve
> yüzde hesabına girmez**. Böylece takip dışı bıraktığınız kayıtlar yüzünden
> oran hiç %100 olmama sorunu yaşanmaz.

---

## 🗓️ Takip Çizelgesi — Yıl/Ay Filtresi

Çizelgenin üstündeki **Görünüm** seçicisi, Yıl ve Ay filtrelerinin neye göre
çalışacağını belirler:

### 1. Beyan Dönemi (son tarih) — varsayılan
> *"Bu ay hangi beyannameleri vereceğim?"*

Yıl + Ay birlikte **son tarihe** bakar. Günlük iş takibi için doğru olan mod budur.

| Filtre | Listede çıkanlar |
|---|---|
| **Mart 2027** | Şubat 2027 KDV1/MUHSGK + **2026 Yıllık Gelir Vergisi** (31.03.2027) |
| **Nisan 2027** | Mart 2027 KDV1/MUHSGK/SGK + **2026 Kurumlar Vergisi** (30.04.2027) |
| **Mayıs 2027** | Nisan 2027 beyannameleri + 1. dönem geçici vergi (20.05.2027) |

Önceki yıla ait bir dönem listeye girdiğinde, Dönem sütununda mor bir
**"2026 dönemi"** rozetiyle işaretlenir; karışıklık olmaz.

> **Beyan Ayı** filtresi varsayılan olarak **içinde bulunduğunuz ayı** seçili getirir.
> Tüm yılı görmek için listeden **"Tüm Aylar"** seçin.

### Diğer filtreler

Çizelgenin üstünde ayrıca **Beyanname Türü**, **Durum**, **Defter Tipi**,
**Mali Müşavir** ve serbest **Ara** kutusu bulunur.

**Defter Tipi** filtresi (İşletme / Bilanço / Serbest Meslek / Basit Usul / Diğer)
seçildiğinde yalnızca o defteri tutan mükelleflerin beyannameleri listelenir;
üstte bilgi şeridi ve tek tıkla "Filtreyi kaldır" bağlantısı çıkar.
Özet sayaçları (Toplam, Gecikmiş, Hazır…) da bu filtreye göre yeniden hesaplanır.
Mükellef adının altında defter tipi kısaca gösterilir; Excel ve yazdırma
çıktılarında da ayrı sütun olarak yer alır.

**Mali Müşavir** filtresi, birden fazla müşavir portföyü görebilen her
kullanıcıda çıkar: admin'de her zaman, müşavir/personelde ise o kullanıcıya
birden çok müşavir tanımlanmışsa. Tek müşavire bağlı kullanıcıda gereksiz
olduğu için gizlenir. Seçim yapıldığında listede hangi müşavirin seçili
olduğu doğru görünür ve sayfa yenilendiğinde korunur.

### Özet kartları — filtreye bağlı sayaçlar

Çizelgenin üstündeki altı kart (**Toplam · Gecikmiş · Bekliyor · Hazır ·
Onaylandı · Verilmeyecek**) ekrandaki filtrenin **aynısıyla** hesaplanır:
yıl, ay, beyanname türü, defter tipi, mali müşavir ve arama kutusu dahil.

> **Örnek:** *Ağustos 2026 + KDV1 (Ay)* seçtiğinizde kartlar yalnızca
> Ağustos'ta son günü dolan KDV1 beyannamelerini sayar — "Onaylandı: 2".
> Türü *MUHSGK (Ay)* yaptığınızda aynı ay için "Bekliyor: 2, Hazır: 1,
> Onaylandı: 0" olur. Türü **Tümü** bırakırsanız o ayın tamamını sayar.

Böylece *"bu dönemde hangi beyannameyi kaç mükellefte onayladım, kaçı hazır"*
sorusunu tek bakışta görürsünüz.

**Kartlar aynı zamanda birer düğmedir.** Bir karta tıklayınca liste o duruma
süzülür (diğer filtreleriniz korunur), kart mavi çerçeveyle işaretlenir ve
sağ üstünde ✕ çıkar. Aynı karta tekrar tıklamak ya da **Toplam** kartına
basmak süzgeci kaldırır.

Durum süzgeci açıkken bile kartlar **tüm dağılımı göstermeye devam eder** —
"Onaylandı"ya tıkladığınızda "Hazır: 1" bilgisini kaybetmezsiniz. Kartların
altında *"liste ise Onaylandı olan 2 kaydı gösteriyor"* şeklinde bir açıklama
şeridi belirir. Bu tasarım sayesinde her zaman:

```
Bekliyor + Hazır + Onaylandı + Verilmeyecek = Toplam
```

### 2. Ait Olduğu Dönem
> *"2026 yılına ait beyannameler neler?"*

Yıl `dönem yılına`, Ay ise `dönem bitiş ayına` bakar. Yıl sonu kontrolü ve
dönem bazlı raporlama için kullanılır. Bu modda 2026 Kurumlar Vergisi,
son tarihi 30.04.2027 olsa da **2026** listesinde çıkar.

> **Not:** Kontrol paneli, gecikmiş listesi ve aylık grafik her zaman
> *beyan dönemi* ekseninde çalışır — çünkü hepsi "ne zaman vermem gerekiyor"
> sorusuna cevap verir.

---

## 📜 Uzun Listelerde Sayfalama

Mükellef sayısı arttıkça listelerin tek seferde yüklenmesi sayfayı yavaşlatır.
İki liste bu yüzden parça parça yüklenir.

### Beyanname Takip Çizelgesi — sonsuz kaydırma

Sayfa açıldığında ilk **100 kayıt** gelir. Aşağı kaydırdıkça sonraki kayıtlar
**kendiliğinden** eklenir; istersen alttaki **↓ Daha Fazla Yükle** düğmesini de
kullanabilirsin. Altta her zaman `250 / 814 kayıt gösteriliyor` bilgisi durur.

| Ayrıntı | Davranış |
|---|---|
| Sayfa başına | Sağ üstteki **Sayfa başına** kutusundan 25 / 50 / 100 / 250 seçilir; tercih **hatırlanır** (çerez) |
| Filtreler | Yıl, ay, tür, durum, defter tipi, arama — hepsi kaydırmada korunur |
| Üstteki sayaçlar | **Filtreye uyan tüm kayıtları** gösterir, ekrandakini değil |
| Excel / Yazdır | Sayfalamadan etkilenmez, **tüm kayıtları** dışa aktarır |

### Toplu işlem — ekranda olmayan kayıtlar

Başlıktaki kutuyu işaretleyip ekrandaki tüm satırları seçtiğinizde,
üstte şu bağlantı çıkar:

> **Filtredeki 814 kaydın hepsini seç**

Buna tıklarsanız henüz yüklenmemiş kayıtlar da seçilir ve toplu durum
değişikliği hepsine uygulanır. Böylece 800 kaydı tek tek yüklemek zorunda
kalmazsınız. Seçimi bozan bir tıklama yaparsanız "tümü" modundan otomatik çıkılır.

### Mükellef Listesi — alfabe şeridi

Liste üstünde Türkçe alfabe şeridi vardır; her harfin yanında **kaç mükellef
olduğu** yazar. Harfe tıklayınca yalnızca o harfle başlayanlar listelenir.

- **Tümü** düğmesi filtreyi kaldırır
- Kayıt olmayan harfler soluk ve tıklanamaz
- **#** düğmesi sayı/sembolle başlayan ünvanları toplar
- Harf seçimi diğer filtrelerle (durum, tip, müşavir, arama) **birlikte** çalışır

> **Türkçe harf ayrımı:** MySQL'in varsayılan karşılaştırması `Ş`=`S`, `İ`=`I`,
> `Ğ`=`G` sayar; bu yüzden "Ş"ye tıklayınca "S"liler de gelirdi. Program ilk harfi
> ikili (binary) karşılaştırma ile eşler — **Ş, İ, Ğ, Ü, Ö, Ç kendi grubunda kalır.**

---

## 🌱 Genç Girişimci Kazanç İstisnası

> **Yalnızca gerçek kişiler.** GVK mükerrer 20 istisnası şirketlere
> uygulanmaz. Mükellef tipi **Tüzel Kişi (Kurum)** seçildiğinde bu bölüm
> karttan tamamen kaldırılır; tüzel kişide istisna işaretlenemez, rozet
> çıkmaz ve Excel içe aktarımında "Evet" yazsa bile yok sayılır (uyarı verilir).
> Gerçek kişiden tüzele çevirdiğinizde varsa eski istisna bilgisi temizlenir.

Mükellef kartındaki **Genç Girişimci Kazanç İstisnası** kutusunu işaretleyin.
İstisna, GVK mükerrer 20'ye göre faaliyete başlanan takvim yılından itibaren
**3 vergilendirme dönemi** geçerlidir; sistem hangi dönemde olunduğunu hesaplar.

| Alan | Açıklama |
|---|---|
| İstisna Başlangıç Yılı | Boş bırakılırsa işe başlama yılı esas alınır |
| Not | Serbest açıklama (örn. "2024/1 dönemden itibaren") |

### Nerede görünür

| Ekran | Gösterim |
|---|---|
| Mükellef listesi | `🌱 GG 2/3` rozeti + **"Genç Girişimci"** filtresi |
| Mükellef kartı | Üstte renkli uyarı kutusu + geçerlilik aralığı |
| Beyanname takip çizelgesi | Mükellef adının yanında rozet |
| **Tahakkuk penceresi** | Gelir/Geçici Vergi onaylarken **belirgin uyarı** |
| Yazdırılabilir çizelge | Başlıkta istisna bilgisi |

Renk kodları: **yeşil** geçerli · **turuncu** son dönem · **kırmızı** süre doldu.

Tahakkuk uyarısı yalnızca **Yıllık Gelir Vergisi** ve **Gelir Geçici Vergi**
türlerinde çıkar — istisnanın uygulandığı beyannameler bunlardır. KDV, MUHSGK
gibi türlerde gereksiz uyarı gösterilmez.

Süre dolduğunda işaret kaybolmaz; "süresi doldu" uyarısıyla bilgi amaçlı kalır,
böylece geçmiş dönemleri incelerken de durumu görürsünüz.

> Mevzuat değişir de süre farklılaşırsa: **Tanımlar → Ayarlar → `gg_istisna_donem`**

---

## 🧾 İndirim ve Kısıtlama Rozetleri

Yıllık ve geçici vergi beyannamelerinde mükellefe göre değişen kalemler var:
biri Bağkur primini indiriyor, diğerinde finansman gider kısıtlaması
uygulanıyor. Bunları hatırlamak için mükellef kartına gidip gelmek yerine
çizelgede **rozet olarak** görürsünüz.

Mükellef kartında **🧾 İndirim ve Kısıtlamalar** bölümünden açıp kapatın:

| Kalem | Rozet | Hangi beyannamelerde görünür |
|---|---|---|
| Bağkur primi indirimi | 🏥 **BK** (mavi) | Yıllık GV, Gelir Geçici |
| Eğitim ve sağlık harcamaları (GVK 89/2) | 🎓 **EĞS** (mor) | Yıllık GV, Gelir Geçici |
| Finansman gider kısıtlaması (GVK 41/9, KVK 11/1-i) | 💰 **FGK** (turuncu) | Yıllık GV, Gelir Geçici, **Kurumlar, Kurum Geçici** |

Bağkur ve eğitim/sağlık **gerçek kişi** kalemleri olduğu için kurumlar
vergisinde rozet çıkmaz. Finansman gider kısıtlaması hem gelir hem kurumlar
tarafında uygulandığından dört beyannamede de görünür.

**KDV, MUHSGK, Damga gibi türlerde hiç rozet çıkmaz** — çizelge kalabalıklaşmaz.

### Nasıl görünür

Rozetler, beyanname türü etiketinin **altında** ince bir şerit oluşturur;
satır yüksekliğini bozmaz. Birden fazla kalem seçiliyse hepsi ayrı ayrı,
kendi renginde çıkar:

```
Yıllık GV
🏥 BK  🎓 EĞS  💰 FGK        ← üçü birden seçili mükellef

Kurumlar
💰 FGK                        ← aynı mükellef, yalnızca finansman geçerli

KDV1 (Ay)
                              ← rozet yok
```

Her kalem için isteğe bağlı **kısa not** girebilirsiniz (örn. "eş adına",
"2025'ten itibaren"). Not, rozetin üzerine gelince ipucu olarak görünür:

> *Finansman gider kısıtlaması (GVK 41/9, KVK 11/1-i) — 2025 yılından itibaren*

Bir kalemi kapattığınızda notu da temizlenir; "uygulanmıyor ama notu duruyor"
karışıklığı olmaz. Kutu işaretli değilken not alanı kilitli gelir.

Mükellef kartında ayrıca **İndirim / Kısıtlama** satırında açık olan tüm
kalemler bir arada listelenir.

> **Not:** Bu özellik `migration_indirimler.sql` çalıştırıldığında etkinleşir.
> Migration yapılmamışsa rozetler ve form bölümü görünmez, program normal
> çalışmaya devam eder — çizelge hata vermez.

## 📊 Beyanname Durumları

`Bekliyor` → `Hazır` → `Onaylandı` (+ `Verilmeyecek`)

> **Not:** Önceki sürümlerdeki "Gönderildi" durumu kaldırıldı. Onaylanan beyanname
> gönderilmiş kabul edilir; iki ayrı adım gereksiz işlem yaratıyordu. Mevcut
> "Gönderildi" kayıtları güncelleme betiğiyle otomatik "Onaylandı"ya taşınır.

- **Liste görünümü:** açılır menüden seçin, anında AJAX ile kaydedilir
- **Matris görünümü:** hücreye tıkladıkça durum ilerler (○ ◐ ◕ ●)
- **Toplu işlem:** satırları seçip tek tıkla topluca değiştirin
- Süresi geçmiş + tamamlanmamış satırlar otomatik **kırmızı** işaretlenir

### Onayı geri alma — tahakkuk bilgisine ne olur?

Durumu **Onaylandı**'dan `Bekliyor` / `Hazır` / `Verilmeyecek`'e çevirdiğinizde,
o satırda girilmiş bir tahakkuk tutarı varsa program **size sorar**:

| Seçenek | Sonuç |
|---|---|
| 🗑 **Evet, Sil** | Tutar, damga ve fiş no veritabanından tamamen temizlenir; hücre `—` olur |
| **Kalsın** | Bilgi korunur, çizelgede **soluk + üstü çizili + ⚠ pasif** görünür |

**Pasif tahakkuk ödeme listesine girmez.** Durumu yeniden *Onaylandı* yaptığınızda
eski tutar olduğu gibi geri gelir ve "pasif" işareti kalkar.

Toplu durum değişikliğinde de aynı soru sorulur; birden çok kaydın tahakkuku
tek seferde silinebilir.

Tahakkuk penceresindeki **🗑 Tahakkuku Sil** düğmesiyle de istediğiniz an
temizleyebilirsiniz (tutar alanını boşaltıp kaydetmek de aynı işi yapar —
bu durumda damga da sıfırlanır).

---

---

## 💰 Ödeme Listesi ve Damga Vergisi

### Nasıl çalışır

1. **Tanımlar → Damga Vergisi Tutarları** ekranından her beyanname türü için
   o yıla ait sabit damga tutarını girin. Boş bıraktığınız türlere damga eklenmez.
   Tutarlar **yıl bazlıdır** (her yıl yeniden değerleme yapılır);
   "Başka Yıldan Kopyala" ile yeni yılı hızlıca açabilirsiniz.

2. **Beyanname Takip** ekranında durumu **Onaylandı** (veya Gönderildi) yaptığınızda
   tahakkuk giriş penceresi otomatik açılır. Tutarı **damga vergisi hariç** girersiniz;
   pencerede damga ve ödenecek toplam anlık hesaplanır.
   İstediğiniz zaman satırdaki **₺** düğmesiyle de açabilirsiniz.

3. **Ödeme Listesi** menüsünde mükellef bazında gruplanmış liste oluşur:
   her satırda tahakkuk + damga = ödenecek, altında mükellef toplamı,
   en altta genel toplam.

| Sütun | Anlamı |
|---|---|
| Tahakkuk | Girdiğiniz tutar (damga hariç) |
| Damga | Tanımdan gelen sabit tutar |
| Ödenecek | Tahakkuk + Damga |
| Ödendi | İşaretlediğinizde ödeme tarihi damgalanır |

**Önemli:** Damga tutarı, tahakkuk kaydedildiği anda satıra kopyalanır.
Tanımı sonradan değiştirseniz bile geçmiş kayıtlar bozulmaz.
Tahakkuk tutarını silerseniz damga da otomatik sıfırlanır.

Liste yalnızca **Onaylandı** durumundaki beyannameleri gösterir. Durumu geri
alınmış ("pasif") tahakkuklar listeye **girmez**.
Excel'e aktarabilir, yazdırabilir veya tek mükellef için **Bildirim** çıktısı alıp
müşterinize verebilirsiniz.

Damga eklemeyi tamamen kapatmak için: **Tanımlar → Ayarlar → `damga_otomatik_ekle`**.

### Beyan tarihi ≠ ödeme tarihi (SGK)

Bazı yükümlülüklerde onay ve ödeme günü farklıdır. **Tanımlar → Beyanname Türleri**
ekranındaki *Ödeme Offset / Ödeme Son Gün* alanları boş bırakılırsa ödeme tarihi =
beyan tarihidir.

Hazır tanımlı örnek — **SGK**:

| | Tarih |
|---|---|
| Beyan / onay son günü | İzleyen ayın **26'sı** (MUHSGK ile birlikte) |
| Ödeme son günü | İzleyen ayın **son günü** |

Ödeme listesi **ödeme tarihine** göre gruplanır; satırın altında ayrıca
"beyan: gg.aa.yyyy" notu görünür. Beyanname takip çizelgesi ise onay tarihini esas alır.

### Özel ödeme kalemleri (Bağkur, MTV, harç…)

Ödeme listesindeki **+ Özel Ödeme** düğmesiyle beyanname dışı kalemleri ekleyin:

| Alan | Açıklama |
|---|---|
| Mükellef | Kalemin ekleneceği mükellef |
| Başlık | Bağkur Primi, MTV 1. Taksit… (öneri listesinden seçilebilir) |
| Tutar / Son Ödeme Tarihi | Zorunlu |
| Dönem Etiketi | Örn. "Nisan 2026" |
| Her ay tekrar etsin mi | Bağkur gibi düzenli ödemeler için |
| Tekrar Bitiş Tarihi | Yalnızca "Evet" seçilince görünür — boşsa süresiz |

Kalemler mükellefin listesinde **"Diğer Ödemeler"** başlığı altında ayrı gösterilir;
mükellef genel toplamına ve ödeme bildirimine dahil olur.

#### Aylık tekrar eden kalemler

**Her ay tekrar etsin mi? = Evet** seçtiğinizde kalem, izleyen aylarda
**kendiliğinden oluşur**. Ödeme listesinde bir ay seçtiğinizde o aya ait
tekrarlı kalemler yoksa anında üretilir — ekstra bir şey yapmanız gerekmez.

| Durum | Davranış |
|---|---|
| Ağustos'a "Bağkur 5.500 ₺ / aylık" eklediniz | Eylül, Ekim, Kasım… hepsinde otomatik çıkar |
| Arada bir ayı hiç açmadınız | Zincir kopmaz; o ayı açtığınızda eksik dönem tamamlanır |
| Ayın 31'i seçilmişti, şubat geldi | Tarih ayın son gününe çekilir (28/29) |
| Aynı ayı defalarca açtınız | **Mükerrer kayıt oluşmaz** |
| Aynı isimli kalemi elle eklediniz | Yine mükerrer oluşmaz |

**Tekrar Bitiş Tarihi:** Örn. Bağkur yıl sonunda bitecekse `31.12.2026` yazın;
o tarihten sonra üretim durur. Boş bırakırsanız siz durdurana kadar devam eder.

**Tekrarı durdurmak:** Listedeki kalemin yanındaki **🔁 aylık** rozetinin
altında **"tekrarı durdur"** bağlantısı vardır. Tıkladığınızda tekrar kapanır ve
gelecek aylara ait **ödenmemiş** kopyalar silinir — ödenmiş kayıtlar korunur.

**🔁 Tekrarlıları Getir** düğmesi: Tutarı değiştirdiyseniz veya emin olmak
istiyorsanız bu düğmeyle üretimi elle tetikleyebilirsiniz.

> **Not:** Üretim yalnızca **belirli bir ay** seçiliyken çalışır. "Tüm yıl"
> görünümündeyken yeni kalem oluşturulmaz.

### Ödeme listesi yazdırma

**🖨️ Yazdır** düğmesi **yatay (landscape) A4** düzeninde iki biçim sunar;
açılan sayfanın üstünden geçiş yapabilirsiniz:

| Biçim | İçerik |
|---|---|
| 📋 **Detaylı** | Her satır bir ödeme: Mükellef · VKN · Vergi Dairesi · Beyanname/Kalem · Dönem · Son Tarih · Tahakkuk · Damga · **Ödenecek**. Mükellef bilgisi birleştirilmiş hücrede, her mükellefin altında **ara toplam**, en altta **genel toplam**. |
| 📊 **Özet (Çapraz)** | Satırlar mükellef, sütunlar beyanname türleri + özel kalemler (KDV1, Kurumlar, Bağkur…). Her mükellef tek satır, **son sütun o mükellefin toplamı**, en altta sütun sütun genel toplam. |

Özel kalemler detaylı listede sarı zeminle ve **özel** rozetiyle ayrılır;
tekrarlı olanlarda ayrıca **aylık** rozeti görünür. Sayfa altında
Hazırlayan / Kontrol Eden / Teslim Alan imza alanları vardır.

### Kompakt liste görünümü

Beyannameler onaylandıkça listenin metrelerce uzamaması için ödeme listesi
**katlanabilir** çalışır:

- Her mükellef **tek satır**: ünvan · VKN · kalem sayısı · toplam tutar
- Satıra tıklayınca detay tablosu açılır (beyannameler, özel ödemeler,
  ara toplamlar, Bildirim düğmesi — hepsi yerinde)
- Varsayılan **kapalı**; üstteki **⊞ Tümünü Aç** ile hepsi bir anda açılır

Başlık satırındaki rozetler durumu özetler:

| Rozet | Anlamı |
|---|---|
| ✓ (yeşil) | Mükellefin tüm kalemleri ödendi — satır soluklaşır |
| `3/5` (sarı) | Kısmen ödendi |
| `+2` (mor) | Beyanname dışı özel ödeme kalemi sayısı |

Bir kalemi "ödendi" işaretlediğinizde rozet **anında** güncellenir; sayfayı
yenilemeniz gerekmez.

> 40 mükellefli bir listede sayfa yüksekliği **10.477 px → 1.722 px**'e
> iner (yaklaşık %84 kısalma).

Uzun listelerde **sayfa başına mükellef** seçici (25/50/100/250) ve sonsuz
kaydırma çalışır. **Özet kartları ve Genel Toplam her zaman listenin
tamamını** kapsar — sayfa değiştikçe değişmez.

### Kayıtlı ödeme listeleri (kullanıcıya özel)

**Ödeme Listelerim** menüsünden kendi seçtiğiniz mükelleflerden **kalıcı bir
mükellef grubu** oluşturun. Örn. *"Mustafa Başar Mükellefleri"*.

> **Dönem listeye gömülü değildir.** Listeyi bir kez oluşturursunuz; her ay
> açarken üstteki **Dönem Yılı / Ay** seçicisinden dönemi değiştirir,
> **Dönemi Getir** dersiniz. Her ay için yeni liste oluşturmanıza gerek yoktur.

| Ayar | Açıklama |
|---|---|
| Liste adı | Dönem adı yazmayın (örn. "Mustafa Başar Mükellefleri") |
| Varsayılan Yıl / Ay | Liste açılırken ön seçili gelir; boşsa içinde bulunulan ay |
| Mali Müşavir | Yazdırma başlığında görünür |
| Mükellefler | Arama kutusuyla filtreleyip çoklu seçim |
| Özel ödeme kalemlerim | Bağkur, MTV vb. eklensin mi |
| Muhasebe ücreti | Eklensin mi |

Liste yalnızca **mükellef seçimini** saklar; tutarlar seçtiğiniz döneme göre
onaylanmış beyanname tahakkuklarından, özel ödeme kalemlerinizden ve
(seçiliyse) muhasebe ücretinden **güncel olarak** hesaplanır.
Ay yerine **"Tüm Yıl"** seçerek yıllık toplam da alabilirsiniz.

Çıktılar: ekranda tablo, **Yazdır** (imza alanlı A4), **Detaylı yazdır**
(beyanname kırılımıyla), **Excel**, ayrıca her satırdan tek mükellefin
ödeme bildirimi.

> **Gizlilik:** Listeler ve özel ödeme kalemleri **oluşturan kişiye özeldir**.
> Başka bir kullanıcı bunları göremez, URL ile de erişemez.
> Yönetici denetim amacıyla tümünü görebilir (listede "Sahibi" sütunu çıkar).

### Muhasebe ücreti

Mükellef kartındaki **Muhasebe Ücreti (Aylık ₺)** alanına sözleşme ücretini yazın.
Ödeme listesinde mükellefin **Bildirim** düğmesine bastığınızda, üstteki
**"Muhasebe ücreti dahil edilsin"** kutusunu işaretleyerek ücreti toplama ekleyebilirsiniz.

- Kutu işaretlenince sayfa yenilenir ve ücret ayrı bir satır olarak eklenir.
- Genel toplam: `Tahakkuk + Damga + Muhasebe Ücreti`
- Seçenek çubuğu **yazdırmada görünmez**, çıktı temiz gelir.
- Beyannamesi olmayan bir mükellefe sadece muhasebe ücreti bildirimi de çıkarabilirsiniz.
- Varsayılan olarak işaretli gelmesini isterseniz:
  **Tanımlar → Ayarlar → `bildirim_ucret_varsayilan`**

---

## 🧾 Makbuz Takip (Serbest Meslek Makbuzu)

*"Hangi mükellefe yıl içinde ne kadar makbuz kestik, ne kadar kaldı?"*

Yıllık sözleşme ücreti **hedef**, kesilen makbuzlar **gerçekleşen** olarak
karşılaştırılır. Mali müşavir bazında da özetlenir.

### 1. Yıllık sözleşme ücreti

Tarife her yıl yeniden açıklandığı için ücret **yıl bazında** tutulur;
geçmiş yılların tutarı bozulmaz. Üç yolla girebilirsiniz:

| Yol | Ne zaman |
|---|---|
| **Mükellef kartı** → "Yıllık Sözleşme Ücreti" | Tek mükellef, içinde bulunulan yıl |
| **Listede tutara tıklama** | Hızlı düzeltme |
| **📥 Ücret Yükle** (Excel) | Yıl başında toplu giriş |
| **📋 Ücret Kopyala** | Önceki yılın ücretlerini zam oranıyla taşır |

> Ücret kopyalamada **hedef yılda kaydı olan mükelleflere dokunulmaz** —
> elle girdiğiniz özel tutarlar korunur.

### 2. Makbuz kaydı

Her makbuzda brüt, stopaj, KDV ve net ayrı ayrı tutulur:

```
Net = Brüt − Stopaj + KDV
```

Stopaj (%20) ve KDV (%20) **Ayarlar**'dan değiştirilebilir. Tutarlar makbuza
**kaydedilir**, her görüntülemede yeniden hesaplanmaz — oran sonradan
değişse bile geçmiş makbuzlar bozulmaz.

Makbuz ekranından tek tek girebilir ya da **📥 Makbuz Yükle** ile ay
sonunda toplu aktarabilirsiniz.

### 3. Excel içe aktarma

İki ayrı biçim var. Her ikisinde de mükellef **VKN/TCKN** ile eşleştirilir;
bulunamazsa ünvandan denenir ve önizlemede uyarı çıkar.

**Yıllık ücret dosyası:**

| VKN/TCKN | Unvan | Yillik Ucret | Aciklama |
|---|---|---|---|
| 1112223334 | ALFA LTD. | 36.000,00 | 2026 sözleşmesi |

**Makbuz dosyası:**

| VKN/TCKN | Unvan | Makbuz No | Tarih | Brut | Stopaj | KDV | Aciklama |
|---|---|---|---|---|---|---|---|
| 1112223334 | ALFA LTD. | 2026000145 | 15.03.2026 | 9.000,00 | 1.800,00 | 1.800,00 | |
| 33344455566 | MEHMET KAYA | 2026000146 | 20.03.2026 | 4.500,00 | | | boşsa hesaplanır |

Önizlemede her satır **Aktarılacak / Mükerrer / Hatalı** olarak sınıflanır;
hangilerinin aktarılacağını siz seçersiniz. Bu adımda hiçbir şey kaydedilmez.

- Aynı **makbuz numarası** daha önce kaydedildiyse mükerrer sayılır
- Stopaj/KDV boşsa oranlardan hesaplanır (uyarı verilir)
- Aynı yıl için ücret kaydı varsa **üzerine yazılır** (uyarı verilir)
- Sütun başlıkları büyük/küçük harf ve Türkçe karakter duyarsızdır
- Tutarlar `36.000,00` veya `36000.00` biçiminde olabilir

### 4. Durumlar

| Durum | Anlamı |
|---|---|
| Ücreti girilmemiş | Yıllık ücret tanımlı değil, kalan hesaplanamıyor |
| Hiç kesilmemiş | Ücret var, henüz makbuz yok |
| Kısmen kesilmiş | Devam ediyor |
| Tamamlanmış | Kesilen ≥ ücret |
| Ücreti aşmış | Kesilen > ücret (kalan negatif görünür) |

### 5. Mali müşavir özeti

Listenin üstünde her müşavir için portföy büyüklüğü, sözleşme toplamı,
kesilen, kalan, makbuz adedi ve stopaj toplamı görünür.

> **Not:** "Kesilen" sütunu makbuzu **kesen** müşaviri esas alır; mükellefin
> portföy sahibinden farklı olabilir (örn. izindeki meslektaş adına kesim).

> **İleride:** Bu modüldeki brüt toplamlar gelir vergisi hesaplamasında
> kullanılacak şekilde saklanıyor (stopaj ayrı tutulduğu için mahsup
> hesabı da hazır).

---

## 🔍 Karşıt İnceleme Tutanağı Takibi

YMM'lerden gelen karşıt inceleme tutanaklarının cevap takibi.

**Karşıt İnceleme** menüsünden **+ Yeni Tutanak** ile kaydedin:

| Alan | Açıklama |
|---|---|
| Mükellef | Tutanağın ilgili olduğu mükellef |
| YMM / Büro | Tutanağı gönderen YMM (önceki kayıtlardan otomatik tamamlanır) |
| Geliş Tarihi | Tutanağın size ulaştığı tarih |
| Son Cevap Tarihi | Cevaplanması gereken son tarih (opsiyonel) |
| Gönderim Tarihi | Cevabın gönderildiği tarih |
| Durum | Cevap Bekliyor → Hazırlanıyor → Gönderildi (veya İptal) |
| Not | Serbest not |

- Durumu **Gönderildi** yaptığınızda gönderim tarihi otomatik damgalanır.
- Son cevap tarihi geçmiş ve hâlâ gönderilmemiş kayıtlar **kırmızı** işaretlenir,
  "Kalan" sütununda kaç gün geçtiği görünür.
- Kontrol panelinde yaklaşan/geciken tutanaklar için ayrı bir uyarı bölümü çıkar
  (eşik: **Ayarlar → `karsit_uyari_gun`**, varsayılan 7 gün).
- Liste Excel'e aktarılabilir ve yazdırılabilir.

## 📁 Evrak Takibi

Sadece iki durum: **Geldi ✓ / Gelmedi ✕** — istediğiniz gibi sade tutuldu.

- Aylık matris: mükellefler × evrak türleri
- Hücreye tıkla → anında değişir
- `✓✓` butonu ile bir mükellefin tüm evraklarını tek tıkla işaretleyin
- Her mükellef için **aylık not** alanı
- O ay faal olmayan mükellefler çizelgede **hiç görünmez**

Evrak türleri (varsayılan 8 adet) **Tanımlar → Evrak Türleri** ile düzenlenebilir.

### Dönem mi, toplama ayı mı?

Evraklar bir ay gecikmeli toplanır: **Ağustos'ta Temmuz'un** evraklarını
alırsınız. Program bu ayrımı yapar:

| Kavram | Anlamı |
|---|---|
| **Toplama ayı** | Filtrede seçtiğiniz ay — evrakları topladığınız ay |
| **Evrak dönemi** | Evrakların ait olduğu ay — kayıtlar bu aya yazılır |

Filtrede **Ağustos 2026** seçtiğinizde **Temmuz 2026 dönemi** evrakları listelenir.
Başlıkta ve üstteki mavi şeritte bu açıkça yazar, karışıklık olmaz.

**Kaydırmayı değiştirmek:** **Tanımlar → Ayarlar → `evrak_donem_kaydirma`**

| Değer | Davranış |
|---|---|
| `1` (varsayılan) | Ağustos seçilir → Temmuz dönemi gelir |
| `0` | Kaydırma yok; seçilen ay = evrak dönemi |
| `2` | İki ay geri (Ağustos → Haziran) |

> ⚠️ **Mevcut kayıtlarınız varsa:** Eskiden "Ağustos" seçip Temmuz evraklarını
> işaretliyorduysanız, o kayıtlar Ağustos'a yazılmıştır ve yeni düzende bir ay
> kaymış görünür. `database/migration_evrak_donem.sql` dosyasının sonundaki
> yorumlu `UPDATE` bloğunu açıp çalıştırarak hepsini bir ay geri alabilirsiniz.
> **Önce yedek alın.** Alternatif olarak kaydırmayı `0` yapıp eski düzende
> devam edebilirsiniz.

### Sorumlu personel filtresi

Filtre çubuğundaki **Sorumlu Personel** menüsüyle yalnızca belirli bir personele
atanmış mükellefleri görebilirsiniz. Atama, **mükellef kartındaki "Sorumlu
Personel"** alanından yapılır. Filtre; listeye, üstteki sayaçlara, Excel ve
yazdırma çıktılarına birlikte uygulanır.

### Sayfalama

Çok mükellefli bürolarda çizelge parça parça yüklenir:

- İlk açılışta **50 mükellef** gelir, aşağı kaydırdıkça devamı kendiliğinden eklenir
- Sağ üstteki **Sayfa başına** kutusundan 25 / 50 / 100 / 250 seçilir (tercih hatırlanır)
- Üstteki **Faal Mükellef** sayacı filtreye uyan **tüm** kayıtları gösterir
- **Excel ve Yazdır çıktıları sayfalamadan etkilenmez** — tüm mükellefleri içerir

---

## 📗 E-Defter Berat Takibi

Aylık ve üç aylık e-defter beratları, büronun gerçek iş akışına göre
**adım adım** izlenir. Amaç: "bu mükellefte banka işlendi mi, mizan bakıldı mı?"
sorusunu tek bakışta görmek.

### 1. Mükellefi tanımlayın

Mükellef kartındaki **📗 E-Defter Berat Takibi** bölümünden:

| Alan | Açıklama |
|---|---|
| **E-Defter Dönemi** | Yok / Aylık / Üç Aylık. "Yok" seçiliyse mükellef listeye hiç girmez |
| **Sorumlu Personel** | Banka/çek/mizan işini yürüten kişi; çizelgede filtrelenebilir |
| **Takip Başlangıcı** | Bu tarihten önceki dönemler oluşturulmaz (boş = tümü) |

Kaydettiğinizde dönemler otomatik üretilir. Otomatik üretimi kapattıysanız
E-Defter Takip ekranındaki **🔄 Dönem Üret** düğmesini kullanın.

### 2. Son tarihler — GİB berat yükleme takvimi

Beratın yükleneceği tarih mevzuata göre hesaplanır ve **tatil/hafta sonu
kaydırması** uygulanır. Gün, mükellef tipine göre değişir:

| | Gelir Vergisi Mükellefi (gerçek kişi) | Diğer Mükellefler (kurumlar) |
|---|---|---|
| **Gün** | ayın **10**'u | ayın **14**'ü |
| **Aylık** | dönem ayı **+4 ay** | dönem ayı **+4 ay** |
| **Üç Aylık** | dönem bitişi **+3 ay** | dönem bitişi **+3 ay** |

**Aylık örnek:**

| Dönem | Gelir Vergisi Mük. | Diğer Mükellefler |
|---|---|---|
| Ocak | 10 Mayıs | 14 Mayıs |
| Mayıs | 10 Eylül | 14 Eylül |
| Kasım | 10 Mart *(izleyen yıl)* | 14 Mart |
| **Aralık** | **10 Nisan** ⚠ | **14 Mayıs** ⚠ |

**Üç aylık örnek:**

| Dönem | Gelir Vergisi Mük. | Diğer Mükellefler |
|---|---|---|
| Oca-Şub-Mar | 10 Haziran | 14 Haziran |
| Nis-May-Haz | 10 Eylül | 14 Eylül |
| Tem-Ağu-Eyl | 10 Aralık | 14 Aralık |
| **Eki-Kas-Ara** | **10 Nisan** ⚠ | **14 Mayıs** ⚠ |

> ⚠ **Aralık dönemi istisnası:** Aralık'ta biten dönemlerin beratı, yıllık
> beyannamenin verileceği ayı **takip eden ayda** yüklenir. Gelir vergisi
> beyanı Mart'ta verildiği için berat Nisan'da; kurumlar beyanı Nisan'da
> verildiği için berat Mayıs'ta yüklenir.

Son tarih hafta sonu veya resmi tatile denk gelirse **ilk iş gününe** kaydırılır
ve çizelgede nedeni yazar (örn. *↷ Pazar*). Yasal tarih ayrıca saklanır.

Tüm bu değerler **Tanımlar → Ayarlar → E-Defter Berat Ayarları**'ndan
değiştirilebilir (mevzuat değişirse veya uzatma verilirse): ay sayıları,
gerçek/tüzel günleri ve Aralık istisnası ayrı ayrı ayarlanır.
Değişiklikten sonra **🔄 Dönem Üret** çalıştırın — tarihler güncellenir,
**işaretlediğiniz adımlar korunur**.

### 3. Kontrol listesi

Çizelgedeki her satır bir dönemdir; sütunlar iş akışının adımlarıdır:

```
🏦 Banka Temin → 💳 Banka İşleme → 🧾 Çek İşleme → 📊 Mizan Kontrol → ✅ Hazır → 🔒 Onaylandı
```

Kutuya tıklayınca adım işaretlenir, ilerleme çubuğu anında güncellenir ve
**durum kendiliğinden hesaplanır**:

| Durum | Ne zaman |
|---|---|
| Bekliyor | Hiçbir adım işaretli değil |
| Devam Ediyor | En az bir adım işaretli |
| Hazır | "Hazır" adımı işaretlendi |
| Onaylandı | "Onaylandı" adımı işaretlendi (berat tarihi otomatik düşülür) |
| Yüklenmeyecek | **Elle** seçilir; adım işaretlemesi bu durumu ezmez |

Kutunun üzerine gelince adımı **kimin ne zaman** işaretlediği görünür.
Onayı geri alırsanız durum ve berat tarihi de geri alınır.

### 4. Adımları kendinize göre düzenleyin

**Tanımlar → E-Defter Adımları** ekranından adım ekleyebilir, adını/ikonunu
değiştirebilir, sırasını düzenleyebilirsiniz. Örneğin araya "Kasa Kontrolü"
eklemek isterseniz sıra numarasını 45 verin — Mizan ile Hazır arasına girer.

> **Hazır** ve **Onaylandı** adımları kaydın durumunu belirlediği için
> kaldırılamaz. Diğer adımlar pasife alınabilir; geçmiş işaretler korunur.

### 5. Filtreler ve görünüm modu

Ekran açıldığında **içinde bulunulan ay** seçili gelir: "bu ay hangi beratları
yükleyeceğim" sorusuna doğrudan cevap verir.

Filtre çubuğundaki **Görünüm** seçicisi Yıl ve Ay'ın neye göre çalışacağını
belirler:

| Mod | Yıl + Ay neye bakar | Ne zaman kullanılır |
|---|---|---|
| **Berat Tarihi (yükleme)** — varsayılan | Beratın yükleneceği son tarih | "Mayıs 2027'de ne yükleyeceğim?" |
| **Ait Olduğu Dönem** | Defterin ait olduğu dönem | "2026 yılına ait defterler neler?" |

> **Önemli:** İki mod aynı listeyi farklı gösterir. Berat görünümünde
> **Mayıs 2027** seçerseniz, 2026 Aralık dönemi ve 2026 4. dönem (Eki-Ara)
> listelenir — çünkü bunların beratı o ay yüklenir. Dönem görünümünde
> **2026 + Aralık** seçerseniz aynı kayıtlar çıkar ama bu kez "dönemi Aralık'ta
> biten" ölçütüyle. Seçilen aya göre üstte açıklayıcı bir bilgi şeridi görünür.

Diğer filtreler: **Dönem Tipi (Aylık / Üç Aylık)** · Durum ·
**Sorumlu Personel** · Mali Müşavir · Arama · Sadece gecikmişler.
"Tüm Aylar" için ay seçicisinden ilgili seçeneği kullanın.

Uzun listelerde sonsuz kaydırma ve sayfa başına kayıt seçici (25/50/100/250)
çalışır.

> **Not:** 🔄 **Dönem Üret** düğmesi her zaman **dönem yılı** üzerinden çalışır
> (düğmenin üzerinde hangi yılın üretileceği yazar). 2026 dönemlerini
> ürettiğinizde beratları 2027'ye düşenler de birlikte oluşur.

### 6. Kontrol panelindeki kart

Seçilen ayda **yüklenecek berat varsa** panelde E-Defter kartı çıkar:
dönem etiketi, Toplam / Yüklenen / Hazır / Gecikmiş / Kalan sayıları ve
tamamlanma çubuğu.

Etiket, o ay hangi dönemlerin yükleneceğini tipiyle birlikte gösterir:

| Seçilen ay | Etiket | Anlamı |
|---|---|---|
| Eylül 2026 | `Aylık 2026.05 · 3 Aylık 2026.04-06` | Mayıs ayı defteri + 2. dönem (Nis-Haz) |
| Haziran 2026 | `Aylık 2026.02 · 3 Aylık 2026.01-03` | Şubat ayı defteri + 1. dönem (Oca-Mar) |
| Ağustos 2026 | `2026.04` | yalnızca aylık var, ön ek yazılmaz | Sayılara tıklayınca süzülmüş liste açılır.
Berat dönemi olmayan aylarda kart görünmez, panel sade kalır.

---

## 👥 Kullanıcılar ve Mali Müşavirler

Bu ikisi **ayrı kavramlardır**:

| | Mali Müşavir | Kullanıcı |
|---|---|---|
| **Nedir** | Portföy / kurum tanımı (SMMM–YMM kaydı) | Sisteme giren kişi (kullanıcı adı + şifre) |
| **Girişi var mı** | Hayır | Evet |
| **Nerede tanımlanır** | Mali Müşavirler menüsü | Kullanıcılar menüsü |
| **Mükellefteki karşılığı** | `Mali Müşavir` alanı (portföy sahibi) | `Takipten Sorumlu Personel` alanı |

Bir mali müşavir kaydının sisteme giriş yapan bir kullanıcısı **olmak zorunda değildir**;
tersine bir kullanıcı **birden fazla** mali müşavirin portföyüne erişebilir.

### Roller

| Rol | Yetki |
|---|---|
| **Yönetici (admin)** | Tüm müşavirler ve mükellefler; kullanıcı/müşavir yönetimi; tanımlar |
| **Mali Müşavir** | Yalnızca erişim verilen müşavirlerin mükellefleri + tanımlar; kendi müşavir kartını görüntüler |
| **Personel** | Yalnızca erişim verilen müşavirlerin mükellefleri — **mali bilgileri göremez** |

> **Personel kısıtlaması:** Ödeme Listesi ve Ödeme Listelerim menüleri görünmez,
> URL ile de açılamaz; mükellef kartında Muhasebe Ücreti alanı gizlidir
> (POST ile gönderilse bile yok sayılır).
>
> **Ancak personel tahakkuk tutarı girebilir** — tahakkuk beyannamenin bir parçasıdır
> ve beyannameyi hazırlayan kişi tutarı bilir. Beyanname takip çizelgesindeki
> Tahakkuk sütunu ve ₺ düğmesi personelde de görünür.

> **Yalnızca yöneticiye açık bölümler:** Yedekleme, Yedekten Geri Yükleme,
> Veri Yönetimi (toplu silme), Çöp Kutusu ve Kullanıcılar. **Mali müşavir rolü de
> bu ekranları göremez.** Menüde çıkmaz, adres çubuğuna yazılsa yönlendirilir,
> POST isteği gönderilse reddedilir. Mükellef listesindeki toplu seçim kutuları
> da yalnızca yöneticiye görünür.

### Erişim yetkisi verme

**Kullanıcılar → Düzenle** ekranındaki **"Mali Müşavir Erişim Yetkisi"** bölümünden
kullanıcının görebileceği müşavirleri işaretleyin (birden fazla seçilebilir).
Yönetici rolü bu seçimden bağımsız olarak tüm müşavirlere erişir.

**Birincil (Varsayılan) Mali Müşavir** alanı ise yalnızca yeni mükellef eklerken
formda hangi müşavirin ön seçili geleceğini belirler — erişim yetkisi vermez.

Yetki filtresi veri katmanında uygulanır: başka müşavirin kaydına URL ile erişim
denemesi de, formda yetkisiz `musavir_id` gönderme denemesi de engellenir.

---

## 🗂️ Tanımlı Beyanname Türleri

| Kod | Beyanname | Periyot | Son Gün |
|---|---|---|---|
| KDV1_A | KDV 1 (Aylık) | Aylık | İzleyen ayın 28'i |
| KDV1_3A | KDV 1 (Üç Aylık) | 3 Aylık | Dönemi izleyen ayın 28'i |
| KDV2 | KDV 2 (Sorumlu sıfatıyla) | Aylık | İzleyen ayın 21'i |
| MUHSGK_A | Muhtasar ve Prim Hizmet (Aylık) | Aylık | İzleyen ayın 26'sı |
| MUHSGK_3A | Muhtasar ve Prim Hizmet (Üç Aylık) | 3 Aylık | Dönemi izleyen ayın 26'sı |
| SGK | SGK Prim ve Hizmet Bildirgesi | Aylık | İzleyen ayın son günü |
| YILLIK_GV | Yıllık Gelir Vergisi | Yıllık | İzleyen yıl Mart sonu |
| KURUMLAR | Kurumlar Vergisi | Yıllık | İzleyen yıl Nisan sonu |
| GELIR_GECICI | Gelir Geçici Vergi | 3 Aylık | Dönemi izleyen 2. ayın 17'si |
| KURUM_GECICI | Kurum Geçici Vergi | 3 Aylık | Dönemi izleyen 2. ayın 17'si |
| DAMGA | Damga Vergisi | Aylık | İzleyen ayın 26'sı |
| GEKAP | Geri Kazanım Katılım Payı | 6 Aylık | Dönemi izleyen ayın son günü |
| TURIZM | Turizm Payı | Aylık | Dönemi izleyen ayın son günü |

> Geçici vergilerde **4. dönem kaldırılmıştır** (`atlanan_donemler = 4`).
> Tüm bu kurallar **Tanımlar → Beyanname Türleri** ekranından değiştirilebilir;
> kod değişikliği gerekmez.

**Son tarih formülü:** `dönem bitiş ayı + offset ay` → o ayın `son gün`'ü (veya ay sonu)

---

## 🔄 Toplu Dönem Üretimi

**Sistem → Toplu Dönem Üret** menüsünden bir yıl seçip çalıştırın.

Güvenlidir:
- İşlem görmüş (Hazır/Onaylandı/Gönderildi) ve **notlu** satırlar korunur
- Sadece tarihler güncellenir (tatil tanımı değiştiyse)
- Terk nedeniyle geçersiz kalan ve **henüz işlenmemiş** satırlar temizlenir

Şu durumlarda çalıştırın: yeni yıl başında, tatil tanımı değiştirdiğinizde,
beyanname türü kuralı güncellediğinizde.

---

## 📤 Dışa Aktarma

- **Excel (CSV):** Beyanname ve evrak çizelgeleri — UTF-8 BOM'lu, Türkçe karakterler düzgün
- **Yazdırma:** Yatay A4'e optimize, renkler korunur
- Mükellef bazlı yıllık çizelge ayrıca yazdırılabilir

---

## 📥 Excel’den Toplu Mükellef Aktarma

Mükelleflerinizi tek tek girmek yerine Excel’den toplu aktarabilirsiniz.
**Mükellefler → 📥 Excel’den Aktar** (yalnızca yönetici ve mali müşavir).

### 4 adımda

| Adım | Yapılacak |
|---|---|
| 1 | **Örnekli Şablon**u indirin (3 örnek satır dolu gelir) veya **Boş Şablon**u alın |
| 2 | Excel’de açıp doldurun — **sütun sırasını değiştirmeyin, sütun ekleyip silmeyin** |
| 3 | **Dosya → Farklı Kaydet → CSV (Ayırıcı sınırlı) (*.csv)** ile kaydedin |
| 4 | Dosyayı yükleyin, **önizlemeyi kontrol edin**, onaylayın |

> **Önizleme güvencesi:** Yükleme sırasında veritabanınıza **hiçbir şey yazılmaz**.
> Her satırın ne olacağını (eklenecek / atlanacak / hatalı) tek tek görür,
> istemediğiniz satırların işaretini kaldırır, ondan sonra onaylarsınız.

### Şablon sütunları

`Kod · Ünvan* · Tip · VKN · TCKN · Vergi Dairesi · Defter Tipi · İşe Başlama* ·
Takip Başlangıcı · Terk Tarihi · Beyannameler · Genç Girişimci · GG Başlangıç Yılı ·
Muhasebe Ücreti · Telefon · E-posta · Yetkili Kişi · Faaliyet Konusu · NACE Kodu ·
SGK Sicil · Adres · Notlar`

Yıldızlı (`*`) iki sütun zorunludur; diğerleri boş kalabilir.
Sütun açıklamalarının tamamı aktarma ekranında tablo hâlinde listelenir.

### Beyanname sütunu

Her mükellefin türlerini **kodlarla, virgülle ayırarak** yazın:

| Mükellef tipi | Yazılacak |
|---|---|
| Bilanço / Kurum | `KDV1_A,MUHSGK_A,KURUMLAR,KURUM_GECICI` |
| İşletme / Şahıs | `KDV1_A,MUHSGK_A,YILLIK_GV,GELIR_GECICI` |
| Serbest Meslek | `KDV1_A,MUHSGK_A,YILLIK_GV,GELIR_GECICI` |
| Basit Usul | `YILLIK_GV` |

Kod yerine kısa ad da yazabilirsiniz (`KDV1 (Ay)`, `Kurumlar`).
Tanınmayan bir tür atlanır ve önizlemede turuncu uyarı olarak gösterilir.
Sütunu boş bırakırsanız mükellef eklenir ama **dönem üretilmez**.

### Program neyi kendisi düzeltir?

Aşağıdakiler otomatik düzeltilir ve önizlemede ⚠ uyarı olarak bildirilir:

- **Tarih biçimi:** `01.03.2026`, `2026-03-01`, `1/3/2026` ve Excel’in sayısal tarihi
- **Tutar:** `5.000,00` · `5000.00` · `5000` · `₺5.000`
- **Yanlış sütun:** 11 haneli numara VKN sütunundaysa TCKN’ye taşınır (tersi de)
- **Boş tip/defter:** Kimlik numarasına bakarak Gerçek/Tüzel ve İşletme/Bilanço varsayar
- **Yazım farkları:** `Tüzel/tuzel/Şirket`, `Bilanço/bilanco` hepsi tanınır
- **Dosya kodlaması:** UTF-8 ve Windows-1254 (Excel’in Türkçe kaydı) otomatik algılanır
- **Ayraç:** Noktalı virgül, virgül ve sekme desteklenir

### Satır hangi durumda “hatalı” sayılır?

Ünvan boşsa, işe başlama tarihi okunamıyorsa, VKN 10 / TCKN 11 haneli değilse
veya terk tarihi işe başlamadan önceyse. Bu satırlar **aktarılmaz**, nedeni
önizlemede kırmızı yazıyla belirtilir — dosyayı düzeltip yeniden yükleyin.

### Çakışma kuralı

Aynı **VKN/TCKN** sistemde zaten kayıtlıysa o satır **atlanır**, mevcut kaydınız
değiştirilmez. Aynı numara dosya içinde iki kez geçiyorsa yalnızca ilki eklenir.

### Sınırlar

- Tek dosyada en fazla **2.000 satır** (fazlası için dosyayı bölün)
- Dosya boyutu en fazla **2 MB**
- Yaklaşık hız: **200 mükellef + dönem üretimi ≈ 10 saniye**

“Beyanname dönemlerini hemen üret” kutusunu işaretli bırakırsanız takip çizelgesi
satırları da oluşur. Kapatırsanız sonradan **Mükellef kartı → Dönem Üret** ile
üretebilirsiniz.

---

## 🗄️ Veritabanı Yapısı

```
musavirler ──┬─ kullanicilar
             └─ mukellefler ──┬─ mukellef_beyannameleri ─ beyanname_turleri
                              ├─ beyanname_takip ────────┘
                              ├─ evrak_takip ─ evrak_turleri
                              └─ mukellef_aylik_not
tatiller      (son gün kaydırma motoru)
ayarlar       (sistem ayarları)
```

Tüm tablolar InnoDB + utf8mb4, foreign key'ler tanımlı, mükelleflerde soft delete.

---

## 💾 Veritabanı Yedekleme ve Geri Yükleme

**Sistem → Yedekleme** — *yalnızca Yönetici*

### Yedek alma

Tabloları seçip **Yedeği İndir (.sql)** deyin; dosya bilgisayarınıza iner.
İçinde tablo yapısı (`CREATE TABLE`) ve tüm veriler (`INSERT INTO`) bulunur.

| Özellik | Açıklama |
|---|---|
| Biçim | Standart `.sql` — phpMyAdmin, MySQL Workbench, `mysql` komutuyla da açılır |
| Kapsam | Tablo tablo seçilebilir; “Yalnızca tablo yapısı” ile veri olmadan da alınabilir |
| Kodlama | utf8mb4 — Türkçe karakterler bozulmaz |
| Bağımlılık | **`mysqldump` gerekmez** — saf PHP ile üretilir, paylaşımlı hostingde de çalışır |

> ⚠️ Yedek dosyasında **mükellef bilgileriniz açık hâlde** durur. Şifrelenmiş bir
> klasörde veya harici diskte saklayın, e-posta ekiyle göndermeyin.

**Ne sıklıkla?** Beyanname döneminden önce/sonra, toplu veri girişi veya toplu
silme öncesinde ve ayda en az bir kez.

### Geri yükleme

**Sistem → Yedekleme → Yedekten Geri Yükle** ekranından `.sql` dosyasını yükleyin.

Üç kademeli koruma vardır:

1. Onay kutusuna **GERİ YÜKLE** yazmanız istenir
2. Tarayıcı ikinci bir “son uyarı” penceresi gösterir
3. Dosya çalıştırılmadan önce denetlenir — `DROP DATABASE`, `GRANT`,
   `CREATE USER`, `INTO OUTFILE`, `LOAD_FILE` içeren dosyalar **reddedilir**

İşlem bitince güvenlik için oturumunuz kapatılır (kullanıcı tablosu değişmiş olabilir).

> **Geri yükleme mevcut verinin üzerine yazar.** Önce bugünkü hâlin yedeğini alın.

Alternatif yollar: phpMyAdmin → *İçe Aktar*, veya
`mysql -u kullanici -p veritabani < yedek.sql`

---

## 🧹 Veri Yönetimi — Toplu Silme

**Sistem → Veri Yönetimi** — *yalnızca Yönetici*
(Mali müşavir ve personel bu menüyü **göremez**, doğrudan adres yazsalar bile giremez.)

### 1. Mükellefleri toplu silme

**Mükellefler** listesinde her satırın başında seçim kutusu çıkar (yalnızca yöneticide).
İstediklerinizi işaretleyip **🗑 Seçilenleri Sil** deyin.

Silinen mükellefler **çöp kutusuna** gider:

- Listede görünmezler ama **veritabanında dururlar**
- Beyanname, evrak ve tahakkuk kayıtları **korunur**
- İstediğiniz zaman geri yüklenebilir

### 2. Çöp Kutusu

**Sistem → Çöp Kutusu** ekranında silinen mükellefler, bağlı kayıt sayılarıyla
birlikte listelenir.

| İşlem | Sonuç |
|---|---|
| ♻️ **Seçilenleri Geri Yükle** | Mükellef eski hâline döner, hiçbir veri kaybı olmaz |
| 🗑 **Seçilenleri Kalıcı Sil** | Mükellef **ve tüm beyanname/evrak kayıtları** veritabanından tamamen silinir |
| 💥 **Çöp Kutusunu Boşalt** | Çöpteki bütün kayıtlar için kalıcı silme |

Kalıcı silmeler için onay kutusuna **SİL** yazmanız ve ikinci onayı geçmeniz gerekir.

### 3. Beyanname kayıtlarını filtreli temizleme

Yıl / tür / durum / müşavir seçip **🔍 Kaç Kayıt Etkilenecek?** deyin. Program:

- kaç kayıt silineceğini,
- durum dağılımını (kaç tanesi Onaylandı, kaç tanesi Bekliyor),
- örnek kayıtları ve **tahakkuk girilmiş olanları uyarı olarak**

gösterir. Ancak bundan sonra **SİL** yazıp silebilirsiniz.

> **Güvenlik:** Hiçbir filtre seçmeden silme yapılamaz — yanlışlıkla tüm tabloyu
> boşaltmayı engeller. Silinen dönemler **Toplu Dönem Üretimi** ile yeniden
> oluşturulabilir.

### 4. Evrak kayıtlarını temizleme

Yıl (zorunlu) ve isteğe bağlı ay seçip **SİL** yazın.

### Özet: hangi silme geri alınabilir?

| Veri | Silme türü | Geri alınabilir mi? |
|---|---|---|
| Mükellef | Çöp kutusu | ✅ Evet |
| Mükellef | Çöp kutusundan kalıcı sil | ❌ Hayır |
| Beyanname kayıtları | Doğrudan | ⚠️ Toplu Dönem Üretimi ile yeniden üretilir (tahakkuk tutarları gitmiş olur) |
| Evrak kayıtları | Doğrudan | ❌ Hayır |

**Her durumda:** silmeden önce yedek alırsanız her şey geri gelir.

---

## ⚙️ Sistem Ayarları

**Tanımlar → Ayarlar** — *yönetici ve mali müşavir*

Veritabanındaki **tüm ayarlar** bu ekrandan düzenlenir; hiçbiri gizli kalmaz.
Ayarlar konularına göre gruplanmıştır:

| Grup | İçerik |
|---|---|
| ⚙️ **Son Tarih Hesaplama** | Cumartesi/Pazar tatili, arife, mali tatil, otomatik dönem üretimi |
| 📁 **Evrak Takip** | Dönem kaydırma (ay), sayfa başına mükellef |
| 💰 **Ödeme ve Tahakkuk** | Damga otomatik ekleme, bildirimde muhasebe ücreti |
| 🔔 **Uyarılar ve İstisnalar** | Beyanname uyarı günü, karşıt inceleme uyarısı, genç girişimci dönemi |
| 🏷️ **Genel** | Firma / büro adı |
| 🔧 **Diğer Ayarlar** | Özel alanı olmayan ayarlar otomatik listelenir |

### Ayarların anlamı

| Anahtar | Ne işe yarar |
|---|---|
| `evrak_donem_kaydirma` | Evrak takipte seçilen ay ile dönem arasındaki fark. `1` = Ağustos seçilince Temmuz dönemi gelir |
| `evrak_sayfa_adedi` | Evrak çizelgesinde ilk açılışta yüklenen mükellef sayısı (25/50/100/250) |
| `damga_otomatik_ekle` | Ödeme listesinde damga vergisinin tahakkuka eklenmesi |
| `bildirim_ucret_varsayilan` | Ödeme bildiriminde muhasebe ücretinin varsayılan olarak dahil olması |
| `uyari_gun_sayisi` | Beyanname son tarihine X gün kala "yaklaşıyor" uyarısı |
| `karsit_uyari_gun` | Karşıt inceleme cevabına X gün kala uyarı |
| `gg_istisna_donem` | Genç girişimci istisnasının kaç vergilendirme dönemi geçerli olduğu (kanuni: 3) |
| `otomatik_donem_uret` | Mükellef kaydedilince dönemlerin otomatik oluşması |

> **Yeni ayar eklerseniz:** Veritabanına eklediğiniz herhangi bir ayar, özel bir
> düzenleme alanı tanımlanmasa bile sayfanın sonundaki **"Diğer Ayarlar"**
> bölümünde otomatik görünür ve düzenlenebilir. Böylece hiçbir ayar arayüzden
> erişilemez kalmaz.

---

## 🔧 Sorun Giderme

**"Kurulum Tamamlanmamış" hatası** → `composer install` çalıştırın.

**Veritabanı bağlantı hatası** → `.env` bilgilerini ve SQL'in içe aktarıldığını kontrol edin.

**Sayfalar 404 veriyor** → Apache'de `mod_rewrite` açık olmalı, `AllowOverride All` olmalı.
Nginx için:
```nginx
location / { try_files $uri $uri/ /index.php$is_args$args; }
```

**Yazma hatası** → `chmod -R 775 writable/` ve doğru sahiplik (`www-data`).

**"The application environment is not set correctly."** → İki nedeni olabilir:
1. `.env` dosyasındaki `CI_ENVIRONMENT` değeri geçersiz. Sadece şu üçü kabul edilir:
   `production`, `development`, `testing`. (Başında boşluk/tırnak olmamalı.)
2. `public/index.php` içindeki `FCPATH` sabiti tanımlı değil veya
   `app/Config/Boot/` klasörü eksik. Bu sürümde ikisi de yerindedir —
   dosyaları elle değiştirdiyseniz geri alın.

**"forcehttps filter must have a matching alias defined."** → `app/Config/Filters.php`
içindeki `$aliases` dizisinden framework filtrelerini (`forcehttps`, `pagecache`,
`performance`, `cors`) silmeyin; `$required` dizisi bunları zorunlu kılar.

**"Type of Config\View::$filters must not be defined"** → `View.php` içinde
`$filters` ve `$plugins` özelliklerine tip yazmayın (üst sınıf tipsiz tanımlar).

**Filtrede yıl seçince yanlış dönemler geliyor / beklenen beyanname çıkmıyor**
→ Bu sürümde giderildi. Eski sürümde Yıl filtresi `dönem yılına`, Ay filtresi ise
`son tarihe` bakıyordu; iki farklı eksen karıştığı için "Mayıs 2027" filtresinde
son tarihi 01.05.**2028** olan kayıt görünüyor, buna karşılık Nisan 2027'de
verilecek 2026 Kurumlar Vergisi hiç görünmüyordu. Artık iki filtre aynı eksende
çalışıyor ve üstteki **Görünüm** seçicisiyle mod değiştirilebiliyor.
Ayrıntı için "Takip Çizelgesi — Yıl/Ay Filtresi" bölümüne bakın.

**Durum değiştirirken "Cannot set properties of null (setting 'className')"**
→ Bu sürümde giderildi. Tahakkuk penceresindeki genç girişimci uyarı kutusu
(`th-gg`) bulunamadığında JavaScript hata veriyor, durum kaydedilse bile
pencere bazen açılmıyordu. Artık üç katmanlı koruma var:
1. Kutu yoksa kod onu **kendisi oluşturur**,
2. Modal hiç yoksa sessizce çıkıp bilgilendirir,
3. Beklenmedik bir sorunda `try/catch` devreye girer — durum güncellemesi
   asla yarıda kalmaz.

Hata devam ederse tarayıcı önbelleğini temizleyin (**Ctrl+F5**);
`app/Views/takip/index.php` dosyasının güncel olduğundan emin olun.

**Excel aktarmada “Şablon sütunları eşleşmedi” hatası**
→ Dosyanızın başlık satırı şablonla uyuşmuyor. **Örnek Şablonu İndir** ile inen
dosyayı kullanın; sütun eklemeyin, silmeyin, adlarını değiştirmeyin.
Excel’de “Farklı Kaydet” yaparken **CSV (Ayırıcı sınırlı) (\*.csv)** seçtiğinizden
emin olun — “CSV UTF-8” veya “Metin (Sekmeyle ayrılmış)” de çalışır ama
`.xlsx` olarak kaydederseniz program dosyayı okuyamaz.

**Aktarılan mükelleflerde Türkçe karakterler bozuk (Ayşe → AyÅŸe)**
→ Program UTF-8 ve Windows-1254'ü otomatik algılar; buna rağmen bozulma varsa
dosya üçüncü bir kodlamada kaydedilmiş demektir. Excel’de dosyayı açıp
**CSV UTF-8 (virgülle ayrılmış)** olarak yeniden kaydedin.

**Aktarma sırasında sayfa zaman aşımına uğruyor / beyaz ekran**
→ Çok sayıda mükellefte dönem üretimi uzun sürer. Program kendi zaman sınırını
kaldırır (`set_time_limit(0)`), ancak sunucunuzda Nginx/Apache seviyesinde bir
zaman aşımı varsa yine kesilebilir. Çözüm: önizleme ekranında
**“Beyanname dönemlerini hemen üret”** kutusunun işaretini kaldırın, mükellefleri
aktarın, sonra **Sistem → Toplu Dönem Üret** ile dönemleri toplu oluşturun.

**Yedek indirirken sayfa boş kalıyor / dosya bozuk iniyor**
→ Genellikle sunucudaki bir eklentinin (gzip, çıktı tamponu) araya girmesindendir.
Program indirmeden önce tüm çıktı tamponlarını kapatır; yine de sorun sürerse
`.htaccess` içindeki `mod_deflate` ayarlarını geçici kapatın veya yedeği
phpMyAdmin → *Dışa Aktar* ile alın.

**Çok büyük veritabanında yedek zaman aşımına uğruyor**
→ Yedek satır satır akıtılır (bellek şişmez) ve `set_time_limit(0)` uygulanır.
Buna rağmen Nginx/Apache seviyesinde bir zaman aşımı varsa tabloları
**iki-üç grup hâlinde ayrı ayrı** yedekleyin (en büyüğü genelde `beyanname_takip`).

**Geri yüklemede "Table ... already exists" hataları**
→ Bu sürümde giderildi. Eski sürümde yedek dosyasındaki `DROP TABLE` satırları,
önlerindeki yorum satırı (`-- Tablo: x`) yüzünden atlanıyor, ardından gelen
`CREATE TABLE` "zaten var" hatası veriyordu. Artık her ifadenin başındaki
yorumlar ayıklanıp asıl SQL çalıştırılıyor.

**Sildiğim mükellefi geri getirebilir miyim?**
→ Evet. **Sistem → Çöp Kutusu** ekranından **Geri Yükle** deyin; beyanname ve
evrak kayıtları da olduğu gibi geri gelir. Yalnızca çöp kutusundan
"Kalıcı Sil" yaptıysanız geri dönüşü yoktur — o durumda yedekten dönmelisiniz.

**Beyanname çizelgesinde tüm kayıtlar görünmüyor / liste 100'de kesiliyor**
→ Bu bir hata değil, sayfalamadır. Aşağı kaydırdıkça kalanlar kendiliğinden
yüklenir. Tek seferde daha fazla görmek isterseniz sağ üstteki **Sayfa başına**
kutusundan 250'yi seçin. Excel ve yazdırma çıktıları her zaman tüm kayıtları içerir.

**Toplu durum değiştirmede yalnızca ekrandakiler değişiyor**
→ Başlıktaki kutuyu işaretleyin, çıkan **"Filtredeki N kaydın hepsini seç"**
bağlantısına tıklayın; sonra durumu değiştirin. Böylece henüz yüklenmemiş
kayıtlar da işleme dahil olur.

**Ayarlar sayfasında bazı ayarlar görünmüyor**
→ Bu sürümde giderildi. Eskiden veritabanındaki 13 ayardan yalnızca 7'si
ekranda vardı; `evrak_donem_kaydirma`, `damga_otomatik_ekle`, `karsit_uyari_gun`
gibi ayarlar yalnızca doğrudan veritabanından değiştirilebiliyordu. Artık
**tüm ayarlar** gruplu biçimde düzenlenebilir. Ayrıca sonradan eklenen ayarlar
sayfanın altındaki **"Diğer Ayarlar"** bölümünde otomatik listelenir.

**"Undefined variable $secilenYil" hatası (evrak takip)**
→ `app/Views/evrak/index.php` kopyalanmış ama `app/Controllers/Evrak.php`
kopyalanmamış demektir. Bu sürümde görünüm **savunmacı** hâle getirildi:
controller eski kalsa bile sayfa çökmez, eski davranışıyla çalışır ve üstte
sarı bir uyarı şeridi çıkar. Yine de tam işlevsellik (dönem kaydırma, sorumlu
personel filtresi, sayfalama) için **controller'ı da kopyalayın**.

> **Genel kural:** Bir güncellemede birden fazla dosya değişiyorsa hepsini
> birlikte kopyalayın. Eksik kalan dosya bu tür "Undefined variable"
> hatalarına yol açar. Her turun sonundaki "Sizin yapmanız gereken"
> listesindeki dosyaların tamamını aktardığınızdan emin olun.

**Evrak takipte Ağustos seçiyorum ama Temmuz'un evraklarını giriyorum**
→ Bu sürümde giderildi. Artık filtredeki ay **toplama ayı**, liste ise
**bir önceki dönemin** evraklarıdır. Başlıkta "Temmuz 2026 Dönemi" yazar,
üstteki mavi şeritte hangi ayda hangi dönemi işlediğiniz açıkça belirtilir.
Davranışı **Tanımlar → Ayarlar → `evrak_donem_kaydirma`** ile değiştirebilirsiniz
(`0` = kaydırma yok).

> Eski kayıtlarınız bir ay kaymış görünüyorsa
> `database/migration_evrak_donem.sql` içindeki yorumlu `UPDATE` bloğunu
> çalıştırın — **önce yedek alın**.

**Evrak listesinde sadece 50 mükellef görünüyor**
→ Sayfalamadır. Aşağı kaydırdıkça kalanlar yüklenir; sağ üstteki
**Sayfa başına** kutusundan 250'ye çıkarabilirsiniz. Excel ve yazdırma
çıktıları her zaman tüm mükellefleri içerir.

**Sorumlu Personel menüsü boş geliyor**
→ Menü üç kaynaktan doldurulur: kullanıcı-müşavir erişim tablosu, kullanıcının
birincil müşaviri ve mükelleflere fiilen atanmış sorumlular. Yine de boşsa
hiçbir kullanıcı o müşavire bağlı değil demektir — **Kullanıcılar → Düzenle**
ekranından müşavir ataması yapın.

**"Her ay tekrar etsin mi = Evet" dedim ama izleyen aylarda çıkmıyordu**
→ Bu sürümde giderildi. Eski sürümde tekrar üretimini yapan kod **hiçbir yerden
çağrılmıyordu**; kalem yalnızca seçtiğiniz son ödeme tarihinin ayında görünüyordu.
Artık ödeme listesinde bir ay seçtiğinizde eksik tekrarlar otomatik oluşur.
Ayrıca eski kod yalnızca "bir önceki ay"a baktığı için bir ay atlandığında zincir
kopuyordu; yeni kod serinin başlangıcından hedef aya kadar tüm boşlukları doldurur.
Gerekirse **🔁 Tekrarlıları Getir** düğmesiyle elle de tetikleyebilirsiniz.

> Şema değişikliği vardır: `database/migration_tekrar_bitis.sql` dosyasını
> içe aktarın (idempotent, birden fazla çalıştırılabilir).

**Tekrarlı kalem artık gerekmiyor, nasıl durdururum?**
→ Ödeme listesinde kalemin yanındaki **🔁 aylık** rozetinin altındaki
**"tekrarı durdur"** bağlantısına tıklayın. Gelecek aylardaki ödenmemiş kopyalar
silinir, ödenmiş olanlar durur. Baştan sınır koymak isterseniz kalemi eklerken
**Tekrar Bitiş Tarihi** girin.

**Alfabe şeridi biçimsiz görünüyor (harfler yan yana, kutucuk yok)**
→ `public/assets/css/stil.css` dosyası sunucuya kopyalanmamış demektir. Şeridin
kendi stilleri artık **görünüm dosyasının içine gömülüdür**, yani stil.css eksik
olsa bile kutucuklar doğru görünür. Yine de sayfanın geri kalanı (düğmeler,
kartlar) bozuk görünüyorsa `stil.css` dosyasını kopyalayıp **Ctrl+F5** yapın.

**Alfabe şeridinde "Ş" seçince "S" ile başlayanlar da geliyordu**
→ Bu sürümde giderildi. MySQL'in `utf8mb4_unicode_ci` karşılaştırması
`Ş`=`S`, `İ`=`I`, `Ğ`=`G` kabul ettiği için harfler karışıyordu. Artık ünvanın
ilk harfi `utf8mb4_bin` ile karşılaştırılıyor; Türkçe harfler kendi grubunda kalıyor.

**Sayfa başına seçtiğim adet hatırlanmıyor**
→ Tercih `bt_sayfa_adedi` çerezinde bir yıl saklanır. Tarayıcınız çerezleri
engelliyorsa veya gizli sekmede çalışıyorsanız her açılışta varsayılan 100 gelir.

**Onaylandı'yı geri aldığımda tahakkuk tutarı ekranda kalıyor**
→ Bu sürümde giderildi. Eski sürümde `durumDegistir()` tahakkuk alanlarına hiç
dokunmuyor, hücre de yalnızca tahakkuk kaydedilince yeniden çiziliyordu; bu yüzden
durum `Bekliyor`/`Hazır` yapılsa bile tutar ve damga aynen duruyordu.
Artık durum değişince hücre yeniden çizilir ve program size **"Tahakkuk bilgisi
silinsin mi?"** diye sorar. "Kalsın" derseniz bilgi korunur ama **soluk + ⚠ pasif**
gösterilir ve ödeme listesine girmez.

> Değişen dosyalar: `app/Views/takip/index.php`, `app/Controllers/Takip.php`,
> `app/Models/BeyannameTakipModel.php`, `app/Config/Routes.php`,
> `public/assets/css/stil.css`. Şema değişikliği **yoktur** — migration
> çalıştırmanız gerekmez, dosyaları kopyalayıp **Ctrl+F5** yapmanız yeterlidir.

**Mükellef kartında hep aynı/ilk mali müşavir görünüyor**
→ Bu sürümde giderildi. Eski sürümde mükellef kaydedilirken formdaki müşavir seçimi
yok sayılıp giriş yapan kullanıcının kendi müşaviri yazılıyordu (`musavirFiltresi()`
form seçimini eziyordu). Artık form seçimi esas alınır, yalnızca yetkisi doğrulanır.
Mevcut kurulumu güncellemek için `database/migration_kullanici_musavir.sql` dosyasını
içe aktarın.

**Düzenlerken "Bu kullanıcı adı zaten kullanılıyor" / "Bu e-posta zaten kayıtlı"**
→ Bu sürümde giderildi. Nedeni: CodeIgniter'ın `Model::update()` metodu doğrulamayı
yalnızca gönderilen veri dizisiyle yapar; dizide `id` bulunmadığı için
`is_unique[tablo.alan,id,{id}]` kuralındaki `{id}` yer tutucusu **boş kalır** ve
kayıt kendi kendisiyle çakışır.

Çözüm olarak güncelleme kuralları modelde ayrı bir metoda taşındı ve doğrulama
controller'da gerçek ID ile yapılıyor:

```php
// Model
public function kurallariGuncelle(int $id): array
{
    $kurallar = $this->validationRules;
    $kurallar['eposta'] = 'required|valid_email|is_unique[kullanicilar.eposta,id,' . $id . ']';
    return $kurallar;
}

// Controller
if (! $this->validate($this->model->kurallariGuncelle($id), $this->model->kurallarMesajlari())) {
    return redirect()->back()->withInput()->with('hatalar', $this->validator->getErrors());
}
$this->model->skipValidation(true)->update($id, $veri);
```

Aynı düzeltme **Kullanıcılar**, **Beyanname Türleri** ve **Resmi Tatiller**
ekranlarının üçüne birden uygulandı. Kendi bilgilerinizi koruyarak kayıt
düzenleyebilirsiniz; başkasının kullanıcı adı/e-posta/kod/tarih değerini almaya
çalışmak ise hâlâ engellenir.

**Dönemler oluşmuyor** → Mükellefte beyanname türü seçili mi? Faaliyet tarihi
seçtiğiniz yıl ile kesişiyor mu? Kontrol için Toplu Dönem Üretimi çalıştırın.

---

## 📂 Proje Yapısı

```
beyanname-takip/
├── app/
│   ├── Config/          Routes, Database, Filters, Session, Security...
│   ├── Controllers/     17 controller (Panel, Mukellefler, Takip, Evrak, Odeme, Makbuz, GelirVergisi, Karsit...)
│   ├── Models/          20 model (… MakbuzModel, GelirVergisiModel, VergiTarifeModel)
│   ├── Libraries/
│   │   ├── TatilHesaplayici.php   ← tatil/hafta sonu kaydırma motoru
│   │   ├── DonemUretici.php       ← dönem kesişim motoru (gün takibi)
│   │   ├── MukellefIceAktar.php   ← Excel/CSV toplu mükellef aktarma
│   │   ├── Yedekleyici.php        ← .sql yedek alma / geri yükleme
│   │   └── TopluSilici.php        ← toplu silme + çöp kutusu
│   ├── Helpers/         Türkçe tarih, rozet, format fonksiyonları
│   ├── Filters/         Yetki kontrolü
│   └── Views/           28 görünüm dosyası
├── public/
│   ├── index.php
│   └── assets/          stil.css (modern arayüz) + uygulama.js
├── database/
│   └── beyanname_takip.sql        ← şema + tatiller + türler
├── tests/
│   ├── mantik_testi.php           ← dönem/tatil mantığı (72 test)
│   ├── filtre_testi.php           ← yıl/ay filtre mantığı (18 test)
│   ├── genc_girisimci_testi.php   ← istisna dönem hesabı (23 test)
│   ├── tahakkuk_testi.sh          ← tahakkuk/durum akışı (17 test)
│   ├── ice_aktar_testi.sh         ← Excel aktarma (48 test)
│   ├── sistem_testi.sh            ← yedekleme/silme/yetki (82 test)
│   ├── sayfalama_testi.sh         ← sayfalama/alfabe (47 test)
│   ├── tekrar_yazdirma_testi.sh   ← aylık tekrar + yazdırma (50 test)
│   ├── evrak_testi.sh             ← evrak dönem/personel/sayfalama (58 test)
│   ├── ayarlar_testi.sh           ← tüm ayarlar düzenlenebilir mi (39 test)
│   ├── ozet_kart_testi.sh         ← özet sayaçları filtreyi izliyor mu (71 test)
│   ├── indirim_rozet_testi.sh     ← indirim/kısıtlama rozetleri (84 test)
│   ├── panel_dagilim_testi.sh     ← panel durum tablosu (76 test)
│   ├── musavir_filtre_testi.sh    ← mali müşavir filtresi (49 test)
│   ├── edefter_testi.sh           ← e-defter berat takibi (141 test)
│   ├── gg_tuzel_testi.sh          ← genç girişimci yalnızca gerçek kişi (52 test)
│   ├── odeme_kompakt_testi.sh     ← ödeme listesi kompakt görünüm (56 test)
│   ├── odeme_mukerrer_testi.sh    ← özel kalem mükerrer toplam (29 test)
│   ├── makbuz_testi.sh            ← makbuz takip modülü (99 test)
│   ├── gelir_vergisi_testi.php    ← vergi hesap motoru birim testi (67 test)
│   ├── gelir_vergisi_http_testi.sh ← gelir vergisi + yazdırma (123 test)
│   ├── tarayici_gelir_vergisi.py  ← canlı hesap, gerçek tarayıcı (46 test)
│   ├── tarayici_tahakkuk.py       ← tarayıcı uçtan uca (22 test)
│   ├── tarayici_ice_aktar.py      ← tarayıcı uçtan uca (21 test)
│   ├── tarayici_sayfalama.py      ← tarayıcı uçtan uca (23 test)
│   ├── tarayici_ozet_kart.py      ← kart tıklama, gerçek tarayıcı (29 test)
│   ├── tarayici_indirim_rozet.py  ← rozet görünümü, gerçek tarayıcı (26 test)
│   ├── tarayici_panel_dagilim.py  ← panel tablosu tıklama (36 test)
│   ├── tarayici_edefter.py        ← kontrol listesi tıklama (41 test)
│   └── tarayici_odeme_kompakt.py  ← katlanabilir liste (32 test)
└── KURULUM.md
```

> `.sh` ve `.py` testleri çalışan bir sunucu ister (`php -S 127.0.0.1:8099 -t public`).
> `.py` testleri için `chromium` ve `python3 -m pip install websocket-client` gerekir.
> Bu dosyalar yalnızca geliştirme içindir; canlı sunucuya kopyalamanız gerekmez.

---

## ✅ Test Sonuçları

### İş mantığı testi (`php tests/mantik_testi.php`)
```
TEST 1 — Tatil / hafta sonu kaydırması ......... 11/11 ✓
TEST 2 — Son tarih formülleri ................... 9/9  ✓
TEST 3 — Ana senaryo (01.03 başla, 31.03 terk) . 20/20 ✓
TEST 4 — Diğer senaryolar ...................... 22/22 ✓
TEST 5 — Takip başlangıcı (devralınan mükellef) 10/10 ✓
────────────────────────────────────────────────────────
TÜM TESTLER BAŞARILI                            72/72 ✓
```

### Canlı ortam testi (PHP 8.4 + MariaDB 11.8)
| Test | Sonuç |
|---|---|
| SQL şeması içe aktarma | ✓ 11 tablo, 13 beyanname türü, 50 tatil |
| Kurulum → yönetici oluşturma | ✓ |
| Giriş / oturum / çıkış | ✓ |
| Mükellef ekleme (01.03→31.03 terk) | ✓ DB'ye tam 6 doğru dönem yazıldı |
| Sayfa taraması (29 sayfa) | ✓ hepsi HTTP 200, fatal error yok |
| AJAX: durum, not, evrak, toplu işaret | ✓ 6/6 |
| Terk uzatma (31.03 → 31.08) | ✓ 6 → 30 satır, işlenmiş kayıt korundu |
| Terk kısaltma (31.08 → 31.03) | ✓ 30 → 6 satır, onaylı+notlu kayıt korundu |
| Oturumsuz erişim engeli | ✓ giriş sayfasına yönlendirdi |
| CSRF'siz POST engeli | ✓ HTTP 403 |
| Kurulum ekranı kilidi | ✓ ikinci hesap oluşturulamadı |
| Excel / yazdırma çıktıları | ✓ |
| Kullanıcı düzenleme (kendi bilgileriyle) | ✓ benzersizlik hatası vermiyor |
| Başkasının kullanıcı adı/e-postasını alma | ✓ engellendi |
| Tatil / beyanname türü düzenleme | ✓ kendi değerini koruyor |
| Profil güncelleme + şifre değiştirme | ✓ |
| Kullanıcı ↔ müşavir ayrımı, çoklu erişim | ✓ 8/8 senaryo |
| Mükellefin doğru müşavire kaydedilmesi | ✓ form seçimi korunuyor |
| Yetkisiz `musavir_id` zorlama denemesi | ✓ reddedildi |
| Rol bazlı veri izolasyonu | ✓ 12/12 kontrol |
| Sayfa taraması (yeni yapı) | ✓ 25/25 HTTP 200 |
| Yıl/Ay filtre mantığı (beyan ↔ dönem) | ✓ 18/18 (`tests/filtre_testi.php`) |
| Nisan 2027 → 2026 Kurumlar Vergisi | ✓ listede çıkıyor |
| Mart 2027 → 2026 Yıllık Gelir Vergisi | ✓ listede çıkıyor |
| Mayıs 2027 → 2028 tarihli kayıt | ✓ artık eleniyor |
| Tahakkuk girişi (damga hariç) | ✓ DB'ye doğru yazıldı |
| Ödeme listesi damga toplamı | ✓ 23.750,25 + 2.200,50 = 25.950,75 |
| Damga tanımı + yıl kopyalama | ✓ 10/10 |
| Karşıt inceleme kayıt/durum/not | ✓ gönderim tarihi damgalandı |
| Sayfa taraması (ödeme + karşıt dahil) | ✓ 19/19 HTTP 200 |
| Muhasebe ücreti bildirimi (dahil/hariç) | ✓ 11.000 → 14.250,75 |
| Takip başlangıcı (geçmiş dönem üretilmiyor) | ✓ 48 → 20 dönem |
| Geçmişi Kapat (toplu işaretleme) | ✓ 49 satır, sonrakine dokunmadı |
| Beyan Ayı varsayılanı = bu ay | ✓ |
| Son tur sayfa taraması | ✓ 24/24 HTTP 200 |
| "Gönderildi" kaldırıldı (DB + arayüz) | ✓ 4/4 |
| Personel mali kısıtlaması (POST dahil) | ✓ 9/9 |
| SGK: onay 27.04 / ödeme 30.04 | ✓ |
| Özel ödeme kalemi (Bağkur) | ✓ toplama ve bildirime girdi |
| Bildirim: beyanname + özel + ücret | ✓ 14.500,50 → 17.000,50 |
| Personel tahakkuk girişi | ✓ 7/7 (ödeme listesi yine kapalı) |
| Kayıtlı liste + gizlilik | ✓ 9/9 |
| Liste hesabı (beyanname+özel+ücret) | ✓ 21.000 + 3.500 + 7.300 = 31.800 |
| Liste yazdırma / Excel | ✓ |
| Dönemsiz liste (tek liste, çok dönem) | ✓ Nisan 12.000 / Haziran 16.000 / Tüm yıl 28.000 |
| Dönem seçici + çıktılara dönem aktarımı | ✓ 16/16 sayfa |
| Genç girişimci dönem hesabı | ✓ 23/23 (`tests/genc_girisimci_testi.php`) |
| Rozet / filtre / kart uyarısı | ✓ 14/14 |
| Tahakkuk penceresi uyarısı (GV + Geçici) | ✓ yalnızca ilgili türlerde |
| Defter tipi filtresi | ✓ 14/14 (liste, sayaç, Excel, yazdırma) |
| Ardışık durum değişimi (8 tur) | ✓ hata=0, pencere her seferinde açıldı |
| Uyarı kutusu silinse bile kendini onarma | ✓ |
| Onay geri alınınca tahakkuk sorusu (sunucu) | ✓ 17/17 (`bash tests/tahakkuk_testi.sh`) |
| Onay geri alınınca tahakkuk (tarayıcı) | ✓ 22/22 (`python3 tests/tarayici_tahakkuk.py`) |
| "Kalsın" → soluk + ⚠ pasif, ödemeye girmez | ✓ |
| "Evet, Sil" → tutar + damga + fiş temizlendi | ✓ sayfa yenilendikten sonra da boş |
| Yeniden Onaylandı → eski tutar geri geldi | ✓ pasif işareti kalktı |
| Tutarı boşaltıp kaydetme → damga da 0 | ✓ |
| Toplu durum değişiminde toplu silme | ✓ `tahakkuk_kalanlar` doğru döndü |
| Personel rolünde aynı akış | ✓ 4/4 AJAX ucu |
| **Excel’den aktarma (sunucu)** | ✓ 48/48 (`bash tests/ice_aktar_testi.sh`) |
| **Excel’den aktarma (tarayıcı)** | ✓ 21/21 (`python3 tests/tarayici_ice_aktar.py`) |
| Şablon indirme (örnekli + boş) | ✓ UTF-8 BOM'lu, Excel'de Türkçe düzgün |
| Önizleme DB'ye yazmıyor | ✓ onaylanana kadar hiçbir kayıt oluşmadı |
| Satır sınıflandırma (8 ekle / 2 atla / 4 hata) | ✓ 14 satırlık zorlu dosyada birebir |
| Mevcut VKN/TCKN atlama | ✓ mevcut kayıt değiştirilmedi |
| Dosya içi mükerrer | ✓ yalnızca ilki eklendi |
| Tarih biçimleri + Excel seri no (44927) | ✓ 01.01.2023'e çevrildi |
| Para ayrıştırma (7.500,00 / 1234,56) | ✓ 7500.00 / 1234.56 |
| VKN↔TCKN sütun karışıklığı düzeltme | ✓ uyarıyla taşındı |
| Tanınmayan beyanname kodu | ✓ atlandı, diğerleri bağlandı |
| Kısa ad ile tür eşleşmesi ("KDV1 (Ay)") | ✓ 3/3 |
| Virgül ayraçlı + tırnak içinde virgül | ✓ "ŞAHİN GIDA, TİCARET LTD." bozulmadı |
| Windows-1254 kodlama | ✓ "ÇAĞLAYAN ŞİRKETİ ÖZĞÜR" doğru çevrildi |
| Boş seçimle onay | ✓ engellendi, hiçbir kayıt eklenmedi |
| Kısmi seçim | ✓ yalnızca işaretli 2 satır eklendi |
| PDF / yanlış başlık / boş dosya | ✓ 3/3 anlaşılır hata mesajı |
| 2000 satır sınırı | ✓ 2001 satırlık dosya reddedildi |
| Personel rolü (sayfa + POST + düğme) | ✓ 3/3 engellendi |
| Yetkisiz müşavir zorlama | ✓ kendi müşavirine bağlandı |
| Performans: 200 mükellef | ✓ önizleme 106 ms, aktarma+33.600 dönem 10,3 sn |
| Sayfa taraması (aktarma dahil) | ✓ 20/20 HTTP 200 |
| **Yedekleme modülü (sunucu)** | ✓ 82/82 (`bash tests/sistem_testi.sh`) |
| Yedek .sql üretimi | ✓ 17 tablo, CREATE + INSERT, utf8mb4 |
| Yedek başka veritabanına yüklendi | ✓ satır sayıları birebir aynı |
| Türkçe karakter / ondalık / NULL | ✓ "ÖZKAN İNŞAAT", 1500.50, NULL korundu |
| Yalnızca şema yedeği | ✓ CREATE var, INSERT yok |
| Geri yükleme (bozulan veri onarıldı) | ✓ 51 SQL ifadesi hatasız çalıştı |
| Geri yükleme sonrası oturum kapanıyor | ✓ |
| Yanlış onay metni | ✓ reddedildi, veri değişmedi |
| DROP DATABASE içeren dosya | ✓ reddedildi, veritabanı korundu |
| SQL olmayan dosya | ✓ reddedildi |
| Mükellef toplu silme → çöp kutusu | ✓ beyanname/evrak korundu |
| Çöp kutusundan geri yükleme | ✓ |
| Kalıcı silme (onaysız) | ✓ reddedildi |
| Kalıcı silme (onaylı) + CASCADE | ✓ bağlı kayıtlar da silindi |
| Beyanname filtresiz silme | ✓ reddedildi (tüm tabloyu boşaltma koruması) |
| Beyanname 2025 silindi, 2026 korundu | ✓ |
| Evrak yılsız silme | ✓ reddedildi |
| Personel: 4 sayfa + 4 POST | ✓ 10/10 engellendi |
| **Müşavir: 4 sayfa + 4 POST** | ✓ 10/10 engellendi |
| Yetkisiz denemelerde veri değişmedi | ✓ |
| Oturumsuz / CSRF'siz istekler | ✓ 3/3 engellendi |
| Sayfa taraması (sistem dahil) | ✓ 23/23 HTTP 200 |
| **Sayfalama / sonsuz kaydırma (sunucu)** | ✓ 47/47 (`bash tests/sayfalama_testi.sh`) |
| **Sayfalama (tarayıcı)** | ✓ 23/23 (`python3 tests/tarayici_sayfalama.py`) |
| 814 kayıt, 9 turda eksiksiz yüklendi | ✓ çakışma/tekrar yok, benzersiz 814 id |
| Sayfa adedi 25 / 50 / 100 / 250 | ✓ 4/4 doğru satır sayısı |
| Geçersiz adet (9999) → varsayılan 100 | ✓ |
| Kaydırınca otomatik yükleme | ✓ 200 → 300 satır |
| Sonradan yüklenen satırda durum değiştirme | ✓ olay bağlandı, sunucuya gitti |
| Üst sayaç filtreye uyan TÜM kayıtları gösteriyor | ✓ 814 (önceden 100 gösteriyordu) |
| Filtre + sayfalama birlikte (4 durum) | ✓ 4/4 DB ile birebir |
| "Filtredeki 814 kaydın hepsini seç" | ✓ toplu durum hepsine uygulandı |
| Excel / Yazdırma sayfalamadan etkilenmiyor | ✓ 814 satırın tamamı |
| Alfabe şeridi (29 harf + # + Tümü) | ✓ 31 bağlantı, adetler doğru |
| **Türkçe harf ayrımı Ş≠S, İ≠I, Ğ≠G, Ü≠U, Ö≠O, Ç≠C** | ✓ 12/12 DB ile birebir |
| "#" grubu (sayıyla başlayanlar) | ✓ |
| Harf + tip/durum/müşavir filtresi birlikte | ✓ |
| Harf seçimi form gönderiminde korunuyor | ✓ |
| Negatif / aşırı ofset | ✓ güvenli sonuç |
| Sayfa taraması (sayfalama dahil) | ✓ 21/21 HTTP 200 |
| Alfabe şeridi yeni tasarım (kart + kutucuk) | ✓ 31 bağlantı, 34×30 px kutular |
| **stil.css olmadan da doğru görünüyor** | ✓ gömülü stil: kenarlık 1px, köşe 8px |
| Seçili harf vurgusu + "Filtreyi kaldır" | ✓ başlık satırında |
| Mobil (430 px) görünüm | ✓ satırlara sarıyor, taşma yok |
| **Aylık tekrar + yazdırma** | ✓ 50/50 (`bash tests/tekrar_yazdirma_testi.sh`) |
| Tekrarlı kalem izleyen aylarda oluşuyor | ✓ Ağu→Eyl,Eki,Kas,Ara + 2027 Oca,Şub,Mar |
| Tutar / dönem etiketi / kaynak zinciri | ✓ 3/3 |
| Mükerrer üretim koruması | ✓ ay defalarca açıldı, kayıt artmadı |
| Tekrar bitiş tarihine uyum | ✓ 31.10 sonrası üretim durdu |
| Ay sonu taşması (31 → 30/28) | ✓ Eylül 30'a çekildi |
| Tekrarı durdurma | ✓ gelecek ödenmemişler silindi, ödenen korundu |
| Tekrarsız kalem çoğalmıyor | ✓ |
| Yazdırma: yatay A4, tek tablo | ✓ VKN + vergi dairesi sütunlu |
| Yazdırma: mükellef ara toplamları | ✓ 3/3 + genel toplam |
| Yazdırma: çapraz özet tablosu | ✓ sütunlar tür + özel kalem, son sütun toplam |
| **İki biçim aynı toplamı veriyor** | ✓ 78.765,25 ₺ = DB hesabı |
| Personel ödeme/yazdırma erişimi | ✓ 3/3 engellendi |
| Sayfa taraması (yazdırma dahil) | ✓ 21/21 HTTP 200 |
| **Evrak takip (dönem/personel/sayfalama)** | ✓ 51/51 (`bash tests/evrak_testi.sh`) |
| Ağustos seçilince Temmuz dönemi geliyor | ✓ başlık, şerit, sayaç ve JS değişkeni |
| Eylül → Ağustos, Ocak 2027 → Aralık 2026 | ✓ yıl sınırı doğru |
| Kaydırma=0 ayarı | ✓ seçilen ay = evrak dönemi |
| Hücre işareti doğru döneme yazılıyor | ✓ 2026-7 |
| Sayfalama 25/50/100/250 + geçersiz değer | ✓ 4/4 |
| Sonsuz kaydırma (60 mükellef, 3 tur) | ✓ benzersiz, çakışmasız |
| Sonradan yüklenen satırda hücre tıklama | ✓ konsol hatası 0 |
| Faal Mükellef sayacı TÜM kayıtları gösteriyor | ✓ 60 (sayfalamadan bağımsız) |
| Sorumlu personel filtresi (liste+sayaç) | ✓ 30/30 her iki personel |
| Personel menüsü 3 kaynaktan doluyor | ✓ bağlantı tablosu boş olsa da çalışır |
| Filtre + arama birlikte | ✓ değerler korunuyor |
| Excel / Yazdırma dönem + filtre uyumu | ✓ 60 satır, filtreli 30 |
| Sayfa taraması (evrak dahil) | ✓ 25/25 HTTP 200 |
| **Geriye dönük uyumluluk (evrak)** | ✓ 7/7 — eski controller ile çökmüyor |
| Eski controller: ErrorException / Undefined variable | ✓ hiç yok |
| Eski controller: sayfa çalışıyor + uyarı şeridi | ✓ |
| Eski controller: sayfalama/adet seçici gizleniyor | ✓ |
| Yeni controller: tam işlevsellik geri geliyor | ✓ |
| **Sistem ayarları ekranı** | ✓ 39/39 (`bash tests/ayarlar_testi.sh`) |
| DB'deki 13 ayarın tamamı düzenlenebilir | ✓ 13/13 (önceden 7/13) |
| Değerler doğru yükleniyor (select + input) | ✓ |
| Kaydetme + Türkçe karakter | ✓ "SOYGÜDEN MÜŞAVİRLİK" bozulmadı |
| İşaretsiz checkbox'lar 0 oluyor | ✓ 6/6 |
| Ayarlar gerçekten işe yarıyor | ✓ kaydırma 1↔2, sayfa adedi 25 |
| Bilinmeyen ayar otomatik görünüyor | ✓ "Diğer Ayarlar" bölümü |
| Personel erişemiyor / kaydedemiyor | ✓ 3/3 |
| Sayfa taraması (ayarlar dahil) | ✓ 20/20 HTTP 200 |
| **Özet kartları filtreyi izliyor** | ✓ 71/71 (`bash tests/ozet_kart_testi.sh`) |
| Tür + ay seçilince sayaç yıl toplamını göstermiyor | ✓ 67 → 2 (asıl kusur) |
| Her tür için sayaç = veritabanı | ✓ 12/12 (3 tür × 4 durum) |
| Bekliyor+Hazır+Onaylandı+Verilmeyecek = Toplam | ✓ 5/5 senaryo |
| Tür=Tümü eski davranışı koruyor | ✓ 9 = 3+3+3 |
| Durum süzgeci açıkken dağılım görünüyor | ✓ Hazır 0'a düşmüyor |
| Defter tipi / arama / dönem modu sayaca yansıyor | ✓ |
| "Tüm Aylar" kart bağlantısında kaybolmuyor | ✓ ay=0 tuzağı kapatıldı |
| Müşavir kapsamı sızmıyor | ✓ 36 ≠ 108 |
| Panel / Raporlar / Excel / Yazdır bozulmadı | ✓ 8/8 |
| **Kart tıklama (tarayıcı)** | ✓ 29/29 (`python3 tests/tarayici_ozet_kart.py`) |
| Karta tıklayınca liste süzülüyor, filtreler korunuyor | ✓ 3 → 2 satır |
| Tekrar tıklayınca süzgeç kalkıyor | ✓ |
| Seçili kart vurgulanıyor, diğerleri soluk | ✓ |
| **İndirim/kısıtlama rozetleri** | ✓ 84/84 (`bash tests/indirim_rozet_testi.sh`) |
| Bağkur/Eğitim yalnızca gelir beyannamesinde | ✓ Kurumlar'da çıkmıyor |
| Finansman dört beyannamede de | ✓ 4/4 |
| KDV / MUHSGK'da hiç rozet yok | ✓ |
| Üç kalem birden → üç ayrı rozet | ✓ BK,EĞS,FGK |
| Not rozet ipucunda görünüyor | ✓ mevzuat maddesiyle birlikte |
| Kalem kapatılınca notu da siliniyor | ✓ hayalet not kalmıyor |
| Sonsuz kaydırma (AJAX) parçasında da doğru | ✓ |
| Migration yapılmamış kurulumda çökmüyor | ✓ 13/13 sayfa + kaydetme çalışıyor |
| **Rozet görünümü (tarayıcı)** | ✓ 26/26 (`python3 tests/tarayici_indirim_rozet.py`) |
| Satır yüksekliği bozulmuyor, taşma yok | ✓ |
| Üç rozet üç ayrı renkte | ✓ |
| İşaretsiz kalemin not kutusu kilitli | ✓ |
| **Panel durum tablosu** | ✓ 76/76 (`bash tests/panel_dagilim_testi.sh`) |
| Türler aya göre değişiyor (geçiciler Eylül'de düşüyor) | ✓ Ağustos 6 tür → Eylül 3 tür |
| Mart→Yıllık GV, Nisan→Kurumlar | ✓ |
| Sayılar veritabanıyla birebir | ✓ 16/16 (4 tür × 4 durum) |
| Kalan = Bekliyor + Hazır | ✓ 3/3 |
| Verilmeyecek oran paydasına girmiyor | ✓ %100 |
| Tıklanınca açılır liste doğru geliyor | ✓ AJAX 7/7 |
| Sıfır hücreler tıklanamaz | ✓ |
| Dönem modu (mod=donem) | ✓ |
| Boş ayda çökmüyor, mesaj çıkıyor | ✓ |
| Yetki kapsamı sızmıyor (panel + AJAX) | ✓ 13 ≠ 52 |
| **Panel tablosu (tarayıcı)** | ✓ 36/36 (`python3 tests/tarayici_panel_dagilim.py`) |
| Tablo aylık grafiğin üstünde | ✓ |
| Ay değişince satır sayısı 6 → 3 | ✓ |
| Açılır pencere + "Takip ekranında aç" | ✓ |
| Konsol hatası yok | ✓ |
| **Mali müşavir filtresi** | ✓ 49/49 (`bash tests/musavir_filtre_testi.sh`) |
| Seçilen müşavir listede doğru görünüyor | ✓ (int)[2]=1 hatası düzeltildi |
| Takip / Evrak / Karşıt / Ödeme / Mükellefler | ✓ 5/5 ekranda seçim korunuyor |
| Karşıt İnceleme seçimi artık korunuyor | ✓ (eskiden hiç korunmuyordu) |
| Çok müşavirli kullanıcıda filtre açılıyor | ✓ 3 seçenek |
| Tek müşavirli kullanıcıda gizli | ✓ |
| Yetki sızıntısı yok (URL zorlaması) | ✓ |
| `secilenMusavirId()` birim testi | ✓ 8/8 |
| **E-Defter berat takibi** | ✓ 101/101 (`bash tests/edefter_testi.sh`) |
| Şema + idempotent migration | ✓ 3 tablo, 3 mükellef alanı |
| Aylık son tarih (+3 ay sonu) | ✓ Mayıs 2026 → 31.08.2026 |
| Üç aylık son tarih (+2 ay sonu) | ✓ 1. dönem → 01.06.2026 (Pazar kaydırması) |
| Tatil/hafta sonu kaydırması + neden | ✓ |
| "Yok" seçili mükellef listeye girmiyor | ✓ |
| Takip başlangıcı öncesi dönem üretilmiyor | ✓ 12 → 6 |
| Tekrar üretimde mükerrer kayıt yok | ✓ |
| Kontrol listesi akışı (6 adım) | ✓ %17→%33→%50→%67→%83→%100 |
| Durum otomatiği + berat tarihi | ✓ DEVAM → HAZIR → ONAYLANDI |
| Adım geri alınca durum/tarih geri alınıyor | ✓ |
| "Yüklenmeyecek" adımlarca ezilmiyor | ✓ |
| Filtreler (aylık/üç aylık/durum/sorumlu/ay/arama) | ✓ 7/7 |
| Ayar değişince tarihler güncelleniyor, işaretler korunuyor | ✓ |
| Adım ekleme/pasife alma; HAZIR-ONAY korumalı | ✓ 9/9 |
| Panel kartı yalnızca berat olan ayda | ✓ |
| Yetki: müşavir kapsamı, personel erişimi, CSRF | ✓ 7/7 |
| **Kontrol listesi (tarayıcı)** | ✓ 41/41 (`python3 tests/tarayici_edefter.py`) |
| Kutuya tıklayınca ilerleme anında güncelleniyor | ✓ |
| Sayfa yenilenince işaretler kalıcı | ✓ |
| Panel kartından süzülü listeye geçiş | ✓ |
| Konsol hatası yok | ✓ |

### E-Defter takvimi revizyonu (GİB tablosuna göre)

| Test | Sonuç |
|---|---|
| **E-Defter berat takvimi** | ✓ 122/122 (`bash tests/edefter_testi.sh`) |
| Aylık / diğer mükellef: Ocak→14.05 … Kasım→14.03 | ✓ 6/6 |
| Aylık / gelir vergisi mük.: Ocak→10.05 … Ekim→10.02 | ✓ 5/5 |
| Üç aylık / diğer: Q1→14.06, Q2→14.09, Q3→14.12 | ✓ 3/3 |
| Üç aylık / gelir vergisi: Q1→10.06, Q2→10.09, Q3→10.12 | ✓ 3/3 |
| **Aralık istisnası** — aylık: gerçek 10.04, tüzel 14.05 | ✓ |
| **Aralık istisnası** — Q4: gerçek 10.04, tüzel 14.05 | ✓ |
| Gerçek/tüzel farkı gerçekten uygulanıyor | ✓ 14 ≠ 10 |
| Tatil kaydırması + neden (10.05.2026 Pazar → 11.05) | ✓ |
| Kaydırma gerekmeyende neden boş (14.07 Salı) | ✓ |
| Ay / gün / Aralık ayarları tarihi değiştiriyor | ✓ 6/6 |
| Ayar değişince adım işaretleri korunuyor | ✓ |

### E-Defter tarih ekseni düzeltmesi

| Test | Sonuç |
|---|---|
| **Tarih ekseni tutarlılığı** | ✓ 136/136 (`bash tests/edefter_testi.sh`) |
| Kusur: yıl dönemden, ay son tarihten okunuyordu | ✓ düzeltildi |
| "2026 + Mayıs" listesinde 2027 tarihi yok | ✓ 0 kayıt |
| 2026/Q4 (tüzel) Mayıs 2027'de görünüyor | ✓ |
| 2026/Q4 (gerçek) Nisan 2027'de görünüyor | ✓ |
| 2026/Q4 Mayıs 2026'da GÖRÜNMÜYOR | ✓ |
| Dönem modu (mod=donem) ay'ı dönem bitişine bakıyor | ✓ |
| Mod seçicisi ekranda ve seçim korunuyor | ✓ |
| Özet sayaçları listeyle aynı eksende | ✓ |
| **Varsayılan ay = içinde bulunulan ay** | ✓ Ağustos 2026 |
| Varsayılan mod 'berat' | ✓ |
| ay=0 ile "Tüm Aylar" hâlâ seçilebiliyor | ✓ |

### Genç girişimci — yalnızca gerçek kişi (GVK mükerrer 20)

| Test | Sonuç |
|---|---|
| **Tüzel kişide istisna yok** | ✓ 52/52 (`bash tests/gg_tuzel_testi.sh`) |
| Yardımcı işlev tüzelde `var=false` döndürüyor | ✓ 5/5 |
| Tüzel kartta bölüm gizli, checkbox işaretsiz | ✓ |
| Gerçek kişide bölüm ve işaret korunuyor | ✓ |
| POST elle zorlansa bile kaydedilmiyor | ✓ 0 / NULL / NULL |
| Gerçek → tüzel çevirince otomatik temizleniyor | ✓ |
| Eski hatalı veri hiçbir ekranda görünmüyor | ✓ kart, liste, çizelge |
| "Genç Girişimci" filtresi tüzel getirmiyor | ✓ |
| Excel içe aktarımda yok sayılıyor + uyarı | ✓ |
| Migration eski tüzel kayıtları temizliyor | ✓ gerçek kişi korunuyor |
| Migration idempotent | ✓ |
| Tarayıcı: tip değişince bölüm anında gizleniyor | ✓ konsol hatası yok |

### Panel dönem etiketi düzeltmesi

| Test | Sonuç |
|---|---|
| **Etiket tip + aralık gösteriyor** | ✓ 141/141 (`bash tests/edefter_testi.sh`) |
| Kusur: yalnızca başlangıç ayı yazılıyordu (`2026.04`) | ✓ düzeltildi |
| Eylül → `Aylık 2026.05 · 3 Aylık 2026.04-06` | ✓ |
| Haziran → `Aylık 2026.02 · 3 Aylık 2026.01-03` | ✓ |
| Tek tip varsa sade etiket (`2026.04`) | ✓ |
| Etiket veritabanıyla birebir | ✓ |

### Ödeme listesi kompakt tasarım

| Test | Sonuç |
|---|---|
| **Kompakt / katlanabilir liste** | ✓ 56/56 (`bash tests/odeme_kompakt_testi.sh`) |
| Her mükellef tek satır, varsayılan kapalı | ✓ 25/25 |
| Detay içeriği korundu (tablo, ara toplam, bildirim) | ✓ 7/7 |
| Durum rozetleri (✓ / kısmi / özel kalem) | ✓ |
| Mükellef bazlı sayfalama + sonsuz kaydırma | ✓ 8/8 |
| Genel toplam sayfadan etkilenmiyor | ✓ adet=25 ile adet=50 aynı |
| **Özet kartı tüm listeyi sayıyor** | ✓ 25 → 40 (düzeltilen kusur) |
| Filtreler (ödendi, arama, boş ay) çalışıyor | ✓ 4/4 |
| Yetki: müşavir kapsamı, personel engeli, CSRF | ✓ 4/4 |
| Excel / Yazdır / Bildirim / Listelerim bozulmadı | ✓ 12/12 |
| **Katlama (tarayıcı)** | ✓ 32/32 (`python3 tests/tarayici_odeme_kompakt.py`) |
| Sayfa yüksekliği 10.477 px → 1.722 px (%84 kısalma) | ✓ |
| Ödendi işareti başlık rozetini anında güncelliyor | ✓ |
| Sonradan yüklenen gruplarda katlama ve kutular çalışıyor | ✓ |
| Konsol hatası yok | ✓ |

### Ödeme listesi — mükerrer toplam düzeltmesi

Özel ödeme kalemleri (Bağkur, MTV…) hem **beyanname ara toplamına** karışıyor
hem de **mükellef genel toplamında iki kez** sayılıyordu. Model artık üç ayrı
değer tutar: `genel` (yalnızca beyannameler), `ozel` (beyanname dışı),
`genel_tum` (ikisinin toplamı — tek doğru kaynak).

| Test | Sonuç |
|---|---|
| **Mükerrer toplam kusuru** | ✓ 29/29 (`bash tests/odeme_mukerrer_testi.sh`) |
| Beyanname ara toplamı yalnızca beyanname | ✓ 41.820,55 (Bağkur karışmıyor) |
| Mükellef geneli = beyanname + özel | ✓ 51.976,55 (eskiden 62.132,55) |
| Hatalı tutar hiçbir ekranda yok | ✓ liste, Excel, yazdırma, bildirim |
| Özet "Tahakkuk" kartı özel kalem içermiyor | ✓ DB ile birebir |
| Yalnızca özel kalemi olan mükellef doğru | ✓ iki kez sayılmıyor |
| Beyanname + Diğer = Mükellef geneli (her grup) | ✓ |
| Mükellef genelleri = Sayfa geneli | ✓ |
| Kayıtlı ödeme listeleri etkilenmedi | ✓ |
| Ödendi işaretlemesi sonrası tutar sabit | ✓ |

### Makbuz Takip modülü

| Test | Sonuç |
|---|---|
| **Makbuz takip modülü** | ✓ 99/99 (`bash tests/makbuz_testi.sh`) |
| Şema + idempotent migration | ✓ 2 tablo, 3 ayar |
| Ücret / kesilen / kalan hesabı | ✓ DB ile birebir |
| Beş durum sınıflandırması | ✓ 6/6 filtre |
| Mali müşavir bazında özet | ✓ kesen müşavir esas alınıyor |
| Stopaj / KDV / net hesabı | ✓ Net = Brüt − Stopaj + KDV |
| Excel: yıllık ücret aktarma | ✓ ekleme + güncelleme |
| Excel: makbuz aktarma | ✓ mükerrer + hatalı tarih yakalanıyor |
| Stopaj/KDV boşsa otomatik hesaplanıyor | ✓ uyarı veriliyor |
| Önizlemede veritabanı değişmiyor | ✓ |
| Türkçe sayı biçimi (42.000,00) | ✓ |
| Ücret kopyalama (%25 zam) | ✓ mevcut kayda dokunmuyor |
| Yıl bazında ayrım | ✓ 2026 ve 2027 bağımsız |
| Mükellef kartından ücret girişi | ✓ |
| Excel dışa aktarma + TOPLAM satırı | ✓ |
| Yetki: personel kapalı, müşavir kapsamı | ✓ 6/6 |

---

## 🧮 Gelir Vergisi Hesaplama (mali müşavir bazında)

Makbuz Takip'te biriken hasılattan hareketle, mali müşavirin **yıllık gelir
vergisini** hesaplar. Siz yalnızca **gider rakamını** girersiniz; hasılat ve
stopaj makbuzlardan otomatik gelir.

**Menü:** Takip → 🧮 Vergi Yükü  (personel göremez)

> **Adlandırma (20. güncelleme):** Ekran yalnız gelir vergisini değil, KDV
> dahil **yıl içi toplam vergi yükünü** gösterdiği için menü ve başlıklar
> "Vergi Yükü" olarak değiştirildi.

### Nasıl çalışır?

```
   Serbest Meslek Hasılatı      ← makbuzların BRÜT toplamı (otomatik)
 − Mesleki Gider                ← elle girilen + aylık gider tablosu
 ─────────────────────────
 = Serbest Meslek Kazancı  ("beyan edilecek kâr")
 − Bağ-Kur / SGK primi                        (sınırsız)
 − Şahıs / hayat sigorta primi                (kârın %15'ini aşamaz)
 − Eğitim ve sağlık harcaması                 (kârın %10'unu aşamaz)
 ─────────────────────────
 = VERGİ MATRAHI
   → GVK md.103 tarifesi uygulanır
 = Hesaplanan Gelir Vergisi
 − %5 vergiye uyumlu mükellef indirimi (GVK mük.121, isteğe bağlı)
 ─────────────────────────
 = Ödenmesi Gereken Vergi
 − Stopaj (makbuzlardan otomatik)
 − Diğer mahsuplar
 + Kalan KDV borcu              ← makbuz KDV'si − ödenen (ay toplamları)
 ─────────────────────────
 = Net Mahsup
 ─────────────────────────
   YIL İÇİ VERGİ YÜKÜ = Gelir vergisi dengesi + Ödenen KDV
```

### 💡 Yıl içi vergi yükü — asıl bakılacak rakam

Ekranın en altındaki koyu satır, *"yıl içinde devlete net ne kadar ödeyeceğim?"*
sorusunu yanıtlar. İki kalem ayrı gösterilip toplanır:

| Satır | Anlamı |
|---|---|
| **Gelir Vergisi: Devletten Alacak / Borç** | vergi − stopaj − diğer mahsuplar |
| **KDV: Ödenmemiş Borç / Fazla Ödeme** | makbuz KDV'si − fiilen ödenen |
| **YIL İÇİ VERGİ YÜKÜ** | ikisinin mahsuplaşması — ödenecek, iade ya da sıfır |

**Örnek (20.000 matrah):** Gelir vergisi 3.000 ₺, stopaj 4.000 ₺ →
**1.000 ₺ devletten alacaklısınız**. Makbuzlarda 4.000 ₺ KDV var, bunun
3.000 ₺'sini ödemişsiniz → **1.000 ₺ KDV borcunuz kaldı**. İkisi mahsuplaşır:
**alacak/verecek yok, 0,00**.

> **İşaret kuralı:** Eksi işareti kullanılmaz — kafa karıştırıyordu. Lehinize
> olan tutarlar **yeşil** renkte ve *"Devletten Alacak" / "Fazla Ödeme (alacak)"*
> etiketiyle, aleyhinize olanlar normal renkte *"Borç"* etiketiyle gösterilir.
> Sonuç tam sıfırsa satır gri olur ve *ALACAK/VERECEK YOK* yazar.

### Mevzuata bağlı indirim sınırları (GVK md.89)

| Kalem | Sınır | Taban |
|---|---|---|
| Bağ-Kur / SGK primi | **Sınırsız** | — |
| Şahıs / hayat sigorta primi | **%15** | Kazanç − **Bağ-Kur** |
| Eğitim ve sağlık harcaması | **%10** | Kazanç − **Bağ-Kur** |

> **Taban (19. güncelleme):** Önce Bağ-Kur/SGK primi indirilir, kalan tutarın
> %15 / %10'u üst sınır olur. Bağ-Kur arttıkça tavan da düşer.

Girdiğiniz tutar **ham olarak saklanır**, sınır **her hesapta yeniden**
uygulanır — gider veya hasılat değişince tavan da kayar. Sınırı aşan kısım
indirilmez ve ekranda `⚠ X ₺ sınırı aştığı için indirilemedi` uyarısıyla
gösterilir. Zararda (kâr negatifse) tavan 0 olur.

#### 📋 Belge listeleri — tarih · tür · açıklama · tutar

Eğitim-sağlık harcamalarını ve sigorta primlerini tek tutar yerine **belge
belge** girebilirsiniz. Hesap ekranının altındaki iki kartta:

| Sütun | Açıklama |
|---|---|
| **Tarih** | Harcama / ödeme tarihi |
| **Tür** | Eğitim-sağlıkta *Eğitim / Sağlık / Diğer*, sigortada *Hayat / Şahıs / Diğer* |
| **Açıklama** | Belge açıklaması (örn. "Özel okul 2. taksit") |
| **Tutar** | Belge tutarı |

Listenin toplamı, vergi hesabındaki ilgili indirim satırına **otomatik yazılır**
ve mevzuat sınırı (%15 / %10) yine uygulanır. Kart üstünde üç kutu görünür:
*Liste Toplamı*, *Mevzuat Üst Sınırı*, *Hesaba Giren*.

**Öncelik kuralı:**

| Durum | Kullanılan tutar |
|---|---|
| Listede belge **varsa** | Liste toplamı (elle giriş kutusu kilitlenir 🔒) |
| Liste **boşsa** | Yukarıdaki tek tutarlı giriş kutusu |

Elle girdiğiniz değer silinmez; listeyi boşaltırsanız otomatik geri döner.
Böylece liste kullanmayan bürolar etkilenmez.

- **Düzenle** düğmesi alttaki formu o satırla doldurur; *Vazgeç* ile çıkılır.
- **Başka yıldan kopyala** — liste boşken görünür; her yıl tekrarlayan
  harcamaları (okul taksiti, yıllık sigorta primi) hedef yıla taşır,
  tarihleri o yıla kaydırır.
- Belgeler **yazdırma çıktısına** da eklenir; her listenin altında
  "mevzuat üst sınırı" ve "hesaba giren" satırı yer alır.

Oranlar Ayarlar'dan değiştirilebilir: `gv_sigorta_oran`, `gv_egitim_saglik_oran`.

### 📒 Aylık gider tablosu

KDV tablosunun altındaki tabloya mesleki giderinizi **ay ay** girersiniz
(tutar + açıklama). Toplama kuralı:

```
Toplam gider = Elle girilen "Toplam Mesleki Gider" + Aylık tablo toplamı
```

Liste elle girileni **ezmez, eklenir** — bir kısmını toplu, bir kısmını ay ay
tutan büro için de doğru çalışır. Kart üstünde üç kutu görünür:
*Elle Girilen Gider · Aylık Tablo Toplamı · Hesaba Giren Toplam Gider*.
Toplamlar yazdıkça canlı güncellenir; tutarı 0 ve açıklaması boş olan ay
kaydedilmez. Tablo yazdırma çıktısına da eklenir.

### 🧾 Aylık KDV tablosu — yıl içi net vergi

Hesap ekranının altındaki tabloya her ay için **Ödenen KDV** ve
**İndirilecek KDV** rakamlarını elle girersiniz. İkisinin **toplamı**
yıl içinde katlanılan vergi yükü sayılır ve **stopajdan düşülür**:

```
Net Mahsup = Stopaj + Diğer mahsuplar − KDV toplamı
Sonuç      = Ödenmesi gereken vergi − Net Mahsup
```

| Durum | Sonuç |
|---|---|
| Stopaj > KDV | Mahsup fazlası → **İADE** |
| KDV > Stopaj | Mahsup negatif → **ÖDENECEK** artar |

Böylece *"yıl içinde stopajdan iade alıyorum ama KDV ödüyorum — net ne kadar
vergi ödeyeceğim?"* sorusu tek rakamda yanıtlanır.

- Toplamlar **yazdıkça canlı** güncellenir; kaydedince hesaba girer.
- İki değeri de boş olan ay **kaydedilmez** (gereksiz satır tutulmaz).
- Her müşavir + yıl + ay için tek satır; yıllar birbirini etkilemez.

> **KDV matraha GİRMEZ.** KDV, gelir vergisi matrahının parçası değildir;
> yalnızca mahsup aşamasında yıl içi ödeme olarak sayılır.

> **Kaldırılan alanlar:** Geçmiş Yıl Zararı, Ödenen Geçici Vergi ve Diğer
> İndirimler alanları büro ihtiyacı olmadığı için ekrandan kaldırıldı.
> Veritabanı sütunları **duruyor** (geçmiş kayıt bozulmasın) ama okunmuyor,
> yazılmıyor ve hesaba katılmıyor.

### Ekranlar

| Ekran | Ne yapar |
|---|---|
| **Gelir Vergisi** (liste) | Tüm müşavirlerin hasılat / gider / matrah / vergi / ödenecek özeti |
| **Hesap ekranı** (müşavire tıklayınca) | Gider girişi + adım adım hesap dökümü + dilim dağılımı + aylık makbuz grafiği |
| **Tarife Tanımları** | Yıl bazında GVK md.103 dilimleri (düzenlenebilir) |

### Canlı hesap

Gider kutusuna yazdıkça sonuç **kaydetmeden** güncellenir (yazma bitiminden
~0,4 sn sonra). Hesap her zaman **sunucuda** yapılır; böylece ekran, kayıt ve
yazdırma çıktısı birbirinden asla sapmaz. Kaydetmeden ayrılırsanız değerler
kaybolur — sağ üstte *"güncel (kaydedilmedi)"* uyarısı görünür.

### Tarife yönetimi

Tarife her yıl yeniden açıklandığı için dilimler **yıl bazında** tutulur;
geçmiş yılın hesabı sonradan bozulmaz. Kurulumda **2024–2026** tarifeleri
hazır gelir.

Bir dilim satırı tarife metniyle birebir eşleşir:

> *"1.000.000 TL'nin 400.000 TL'si için 70.500 TL, fazlası %27"*
> → **Taban** 400.000 · **Tavan** 1.000.000 · **Tabana kadarki vergi** 70.500 · **Oran** 27

- Son dilimde **tavan boş** bırakılır (sınırsız).
- **Oranı boş** bırakılan satır kaydedilmez (silme yolu budur).
- Tarifeyi yalnızca **yönetici** düzenleyebilir; müşavir salt okur.
- **Başka yıldan kopyala:** artış oranı vererek gelecek yıla taşır. Hedef yılda
  tarife varsa **dokunulmaz**. Kopya, resmi tebliğ çıkana kadar geçici tahmindir.
- **Ücret** ve **ücret dışı** tarifeler ayrı tutulur. Serbest meslek kazancı
  **ücret dışı** tarifeye tabidir; hesap bunu kullanır.

### İsteğe bağlı alanlar

| Alan | Boş bırakılırsa |
|---|---|
| Stopaj / Tevkifat | Makbuzlardan gelen stopaj kullanılır |
| Hasılatı elle gir | Makbuzların brüt toplamı kullanılır |
| KDV tablosu ayı | O ay 0 sayılır, satır oluşturulmaz |
| Eğitim-sağlık / sigorta kutusu | Belge listesi doluysa liste toplamı kullanılır |

Sisteme girilmemiş makbuzlarınız varsa hasılatı elle yazabilirsiniz.

### İlgili ayarlar

| Anahtar | Varsayılan | Anlamı |
|---|---|---|
| `gv_uyumlu_oran` | `5` | Uyumlu mükellef indirimi oranı (%) |
| `gv_uyumlu_ust_sinir` | `12000000` | İndirim üst sınırı (2026: 12.000.000 TL) |
| `gv_hasilat_kaynagi` | `tum` | `tum` = kesilen tüm makbuzlar · `tahsil` = yalnız tahsil edilenler |
| `gv_sigorta_oran` | `15` | Şahıs/hayat sigorta primi indirim üst oranı (%) |
| `gv_egitim_saglik_oran` | `10` | Eğitim ve sağlık harcaması indirim üst oranı (%) |

> Serbest meslek kazancı esasen **tahsil esaslıdır**. Varsayılan `tum`
> seçilmiştir (kesilen tüm makbuzlar); tahsil esasına geçmek için ayarı
> `tahsil` yapın.

### Yazdırma

- **Tek müşavir dökümü** — hesap zinciri + dilim dağılımı + aylık makbuz tablosu
  + **aylık KDV tablosu**, A4 dikey
- **Liste** — tüm müşavirlerin özeti, A4 yatay

Çıktılarda *"bilgilendirme amaçlıdır, resmi beyan yerine geçmez"* dipnotu yer alır.

---

## 🖨️ Makbuz Takip — Yazdırma

**Makbuz Takip → 🖨️ Yazdır** düğmesi. Ekrandaki filtre (yıl, müşavir, durum,
arama, pasifler) çıktıya taşınır; **sayfalama uygulanmaz** — kâğıda tam liste
dökülür. Çıktı üstündeki araç çubuğu yalnızca ekranda görünür, yazıcıya gitmez.

| Biçim | İçerik | Sayfa |
|---|---|---|
| **Mükellef Listesi** (varsayılan) | Ünvan, VKN, müşavir, yıllık ücret, kesilen, kalan, adet, son makbuz, oran, durum + TOPLAM | A4 yatay |
| **Müşavir Özeti** | Müşavir başına mükellef sayısı, sözleşme, kesilen, kalan, makbuz adedi, stopaj, KDV, oran | A4 dikey |
| **Mükellef Dökümü** | Tek mükellefin yıl içindeki tüm makbuzları: tarih, no, kesen müşavir, brüt, stopaj, KDV, net, tahsilat | A4 dikey |

Mükellef dökümüne, mükellefin makbuz ekranındaki **🖨️ Yazdır** düğmesinden
ulaşılır. Tüm yazdırma stilleri **görünüm dosyasına gömülüdür**; `stil.css`
kopyalanmasa bile çıktı düzgün çıkar.

---

### Gelir vergisi + yazdırma testleri

| Test | Sonuç |
|---|---|
| **Hesap motoru birim testi** | ✓ 67/67 (`php tests/gelir_vergisi_testi.php`) |
| **Uçtan uca HTTP testi** | ✓ 123/123 (`bash tests/gelir_vergisi_http_testi.sh`) |
| **Tarayıcı testi (gerçek yazma/AJAX)** | ✓ 46/46 (`python3 tests/tarayici_gelir_vergisi.py`) |
| 2026 tarifesi dilim sınırları | ✓ 190.000 → 28.500 · 400.000 → 70.500 · 1.000.000 → 232.500 · 5.300.000 → 1.737.500 |
| Tarife metni ↔ kümülatif hesap tutarlılığı | ✓ her dilim tabanında sabit vergi = kırılım toplamı |
| 2025 + 2024 tarifeleri | ✓ ücret / ücret dışı ayrı |
| Hasılat ve stopajın makbuzlardan gelmesi | ✓ yeni makbuzda kendiliğinden artıyor |
| Hesap zinciri (hasılat→matrah→vergi→ödenecek) | ✓ DB ile birebir |
| %5 uyumlu mükellef indirimi + 12.000.000 üst sınırı | ✓ |
| Zarar / indirim fazlası → matrah 0 | ✓ negatif matrah oluşmuyor |
| Stopaj fazlaysa **İADE** olarak gösterim | ✓ |
| Elle hasılat / elle stopaj geçersiz kılma | ✓ |
| AJAX canlı hesap veritabanına yazmıyor | ✓ |
| Tahsil esası ayarı (`gv_hasilat_kaynagi`) | ✓ 250.000 / 500.000 ayrımı |
| Tarife kopyalama (%10 artış) + dolu yılı koruma | ✓ |
| Elle tarife düzenleme hesaba yansıyor | ✓ |
| Tarifesiz yılda çökme yok, uyarı veriliyor | ✓ |
| Makbuz yazdırma: liste / müşavir özeti / mükellef dökümü | ✓ filtre çıktıya taşınıyor |
| Yazdırma stilleri gömülü (stil.css bağımsız) | ✓ |
| Yetki: personel kapalı, müşavir kapsamı sızmıyor | ✓ 8/8 |
| Tarifeyi yalnız yönetici değiştirebiliyor | ✓ |

#### Bu turda bulunan ve düzeltilen GERÇEK kusurlar

**1. Türkçe binlik ayırıcı yanlış okunuyordu (ağır kusur).**
Eski `paraCoz()` yalnızca *virgül görürse* noktaları binlik ayırıcı sayıyordu.
Virgülsüz yazılan `400.000` değeri **400,00 TL** olarak okunuyordu:

| Girdi | Eski sonuç | Doğru sonuç |
|---|---|---|
| `400.000` | 400,00 ❌ | 400.000,00 ✓ |
| `1.000.000` | 1,00 ❌ | 1.000.000,00 ✓ |
| `12.500` | 12,50 ❌ | 12.500,00 ✓ |

Kusur yalnızca yeni ekranda değil, **Makbuz, Mükellef ve Ödeme** modüllerinin
tutar alanlarında ve **Excel içe aktarmada** da vardı. Ortak `trParaCoz()`
yardımcısı yazıldı (`app/Helpers/beyanname_helper.php`); altı yerdeki yerel
kopya buna yönlendirildi. Yeni kural: ayırıcıdan sonra 1–2 hane varsa ondalık,
değilse binlik; iki ayırıcı birlikte kullanılırsa **sonda duran** ondalıktır.
20 örnekle doğrulandı (`1.234.567,89`, `1,234,567.89`, `1234,5`, `₺ 2.750,25`…).

**2. `musavirler` tablosunda `deleted_at` sütunu yok.**
Liste sorgusu `mukellefler` tablosuna bakarak yazılmıştı; gelir vergisi liste ve
yazdırma ekranları **HTTP 500** veriyordu. Sorgu, sütun varlığını denetleyecek
biçimde düzeltildi (`getFieldNames`), böylece şema sürümünden bağımsız çalışır.

#### Test disiplini notu — yanlış alarmlar

İlk koşuda 11 test kırmızı yandı; **hiçbiri kod hatası değildi**, testin kendi
kurgusu yanlıştı. Gerçek doğrulanıp test düzeltildi:

- Toplam tutarlar çıktıda **iki kez** geçiyor (üstteki özet kutusu + tablo
  `tfoot`'u) — beklenen davranış, test 1 bekliyordu.
- Ünvan hem `<title>` hem `<h1>` içinde geçiyor.
- `DİLİM DAĞILIMI` başlığı büyük harfe **CSS `text-transform`** ile dönüyor;
  kaynakta "Dilim Dağılımı" yazıyor.
- "İADE / MAHSUP EDİLECEK" metni iki kez var: sunucunun bastığı satır + AJAX'ın
  kullandığı JS dizesi.

Ayrıca bu testin bıraktığı `kullanici_musavirleri` kaydı, sonradan çalışan
`makbuz_testi` / `odeme_kompakt_testi` / `edefter_testi` koşularını yanlış yere
düşürüyordu (`musavir` kullanıcısı onlarda **musavir_id=2** olmalı). Teste
**temizlik adımı** eklendi; artık testler birbirini bozmuyor.

#### Doğrulanan mevcut testler (bu turdan sonra)

`mantik_testi` 72/72 · `filtre_testi` 18/18 · `genc_girisimci_testi` 23/23 ·
`makbuz_testi` 99/99 · `odeme_mukerrer_testi` 29/29 · `odeme_kompakt_testi` 56/56 ·
`ice_aktar_testi` 48/48 · `sistem_testi` 82/82 · `ayarlar_testi` 52/52 ·
`tekrar_yazdirma_testi` 50/50 · `evrak_testi` 58/58 · `edefter_testi` 141/141 ·
`indirim_rozet_testi` 84/84 · `ozet_kart_testi` 71/71 · `panel_dagilim_testi` 76/76 ·
`gg_tuzel_testi` 52/52 · `musavir_filtre_testi` 49/49 ·
`tarayici_edefter` 41/41 · `tarayici_ozet_kart` 29/29 ·
`tarayici_odeme_kompakt` 32/32 · `tarayici_panel_dagilim` 36/36

> **Test sırası uyarısı (değişmedi):** `ice_aktar_testi` id=1'de
> "MEVCUT FIRMA LTD" ve `musavir`→musavir_id=1 bekler; `sistem_testi` ve
> `ozet_kart_testi` ise `musavir`→musavir_id=2 bekler. Her test kendi ön
> koşuluyla ayrı çalıştırılmalıdır. Tarayıcı testleri, eşlik eden `.sh`
> testi **önce** çalıştırıldığında geçer (veriyi o kurar).

---

### Mevzuata bağlı indirimler + KDV mahsubu testleri

| Test | Sonuç |
|---|---|
| **Hesap motoru birim testi** | ✓ 104/104 (`php tests/gelir_vergisi_testi.php`) |
| **Uçtan uca HTTP testi** | ✓ 187/187 (`bash tests/gelir_vergisi_http_testi.sh`) |
| **Tarayıcı testi (canlı tavan + KDV)** | ✓ 83/83 (`python3 tests/tarayici_gelir_vergisi.py`) |
| Sigorta primi kârın %15'i ile sınırlanıyor | ✓ 60.000 talep → 45.000 indi, 15.000 aşım raporlandı |
| Eğitim-sağlık kârın %10'u ile sınırlanıyor | ✓ tavan altı tutar tam indiriliyor |
| Sınır tabanı KAZANÇ (Bağ-Kur öncesi) | ✓ gider artınca tavan da düşüyor |
| Bağ-Kur sınırsız indiriliyor | ✓ 250.000 tamamı indi |
| Zararda tavan 0 | ✓ negatif kârda indirim yok |
| Talep edilen tutar ham saklanıyor | ✓ sınır her hesapta yeniden uygulanıyor |
| Pasif alanlar ekranda yok | ✓ zarar / geçici vergi / diğer indirim kaldırıldı |
| Pasif alan POST edilse bile hesaba girmiyor | ✓ matrah değişmiyor |
| Pasif sütunlar veritabanında duruyor | ✓ geçmiş kayıt korunuyor |
| KDV tablosu 12 ay, iki sütun | ✓ ödenen + indirilecek |
| KDV yıllık toplamı stopajdan düşülüyor | ✓ net mahsup = stopaj + diğer − KDV |
| KDV > stopaj → **ÖDENECEK** doğuyor | ✓ kullanıcının istediği mantık |
| KDV < stopaj → iade azalıyor | ✓ |
| KDV matraha girmiyor | ✓ KDV değişse de matrah sabit |
| Boş ay kaydedilmiyor, sıfırlanan ay siliniyor | ✓ |
| Aynı ay mükerrer olmuyor, güncelleniyor | ✓ benzersiz kısıt |
| Yıl ayrımı korunuyor | ✓ 2025 KDV'si 2026'yı etkilemiyor |
| Canlı toplam (JS) Türkçe binliği doğru okuyor | ✓ `1.000.000` = bir milyon |
| Yazdırmada yeni satırlar + KDV tablosu | ✓ pasif alanlar çıktıda yok |
| Yetki: müşavir başkasının KDV'sini yazamıyor | ✓ personel erişemiyor |

#### Test disiplini notu — yanlış alarmlar

Yeni bölümler ilk koşuda tam geçti; **4 + 11 = 15 kırmızı**, mevcut testlerin
eski beklentilerinden geldi ve **hiçbiri kod hatası değildi**. Gerçek elle
doğrulanıp test düzeltildi:

- 14. bölüm 7. makbuzu eklediği için hasılat **550.000**'dir (500.000 değil);
  matrah beklentisi buna göre 277.500 olmalıydı.
- Tarayıcı testinin eski bölümleri yeni `sigorta_primi` / `egitim_saglik`
  alanlarını sıfırlamıyordu; HTTP testinin bıraktığı 60.000 / 20.000 değerleri
  matrahı düşürüyordu. Eski senaryolara sıfırlama eklendi.
- `Ödenen KDV` metni liste çıktısında iki kez geçiyor (özet kutusu + sütun
  başlığı) — beklenen davranış.

Elle doğrulama örneği: kazanç 250.000 → sigorta tavanı 37.500, eğitim tavanı
25.000 → indirim 57.500 → matrah 192.500. Ekranın gösterdiği rakamla birebir.

---

### İndirim belgesi listesi testleri

| Test | Sonuç |
|---|---|
| **Hesap motoru birim testi** | ✓ 104/104 (`php tests/gelir_vergisi_testi.php`) |
| **Uçtan uca HTTP testi** | ✓ 231/231 (`bash tests/gelir_vergisi_http_testi.sh`) |
| **Tarayıcı testi** | ✓ 117/117 (`python3 tests/tarayici_gelir_vergisi.py`) |
| Belge ekleme (tarih/tür/açıklama/tutar) | ✓ Türkçe açıklama ve binlikli tutar bozulmuyor |
| Liste toplamı hesaba yazılıyor | ✓ 3 belge = 24.500 → indirim satırına geçti |
| **Öncelik: liste doluysa liste, boşsa elle** | ✓ her iki yön de doğrulandı |
| Liste doluyken elle kutu kilitleniyor | ✓ `readonly` + "listeden geliyor" notu |
| Liste boşalınca elle değere geri dönüyor | ✓ eski değer silinmiyor |
| Mevzuat sınırı listede de uygulanıyor | ✓ 55.000 liste → 52.500 tavan → 2.500 aşım |
| Düzenleme mükerrer satır oluşturmuyor | ✓ aynı kayıt güncelleniyor |
| Silme hesaba anında yansıyor | ✓ |
| İki kalem birbirine karışmıyor | ✓ eğitim ve sigorta ayrı toplanıyor |
| Yıl ayrımı korunuyor | ✓ 2025 belgesi 2026'yı etkilemiyor |
| Başka yıldan kopyalama | ✓ tarihler hedef yıla kayıyor, tutar korunuyor |
| Geçersiz giriş reddi | ✓ sıfır tutar, boş tarih, uydurma tür |
| Yetki | ✓ müşavir başkasına yazamıyor, personel erişemiyor |
| Yazdırmada belge listeleri | ✓ "hesaba giren" ve sınır notu ile |
| Tarayıcıda Düzenle/Vazgeç akışı | ✓ form doluyor, satır vurgulanıyor, kip dönüyor |

#### Test disiplini notu — yanlış alarmlar

3 test kırmızı yandı, **hiçbiri kod hatası değildi**; gerçek elle doğrulanıp
test düzeltildi:

- `data-kalem-satir` deseni **JS seçicisinde de** geçiyordu → 3 satır 4 sayıldı.
  Sayım `<tr[^>]*data-kalem-satir=` desenine çevrildi.
- Sigorta tavanı beklentisi yanlıştı: hasılat **550.000**'dir (bir önceki bölüm
  7. makbuzu ekler), kazanç 350.000 → tavan 52.500. Kod doğruydu.
- Tarayıcı testinde gider o noktada **250.000**'dir (5. bölüm kaydeder) →
  kazanç 300.000 → tavan 45.000. Yine kod doğruydu.

#### Doğrulanan mevcut testler

`mantik_testi` 72/72 · `makbuz_testi` 99/99 · `edefter_testi` 141/141 ·
`odeme_kompakt_testi` 56/56 · `odeme_mukerrer_testi` 29/29 ·
`ayarlar_testi` 54/54 · `sistem_testi` 82/82

---

### Vergi yükü + aylık gider testleri

| Test | Sonuç |
|---|---|
| **Hesap motoru birim testi** | ✓ 128/128 (`php tests/gelir_vergisi_testi.php`) |
| **Uçtan uca HTTP testi** | ✓ 267/267 (`bash tests/gelir_vergisi_http_testi.sh`) |
| **Tarayıcı testi** | ✓ 134/134 (`python3 tests/tarayici_gelir_vergisi.py`) |
| **Kullanıcı senaryosu** | ✓ vergi 3.000 · stopaj 4.000 → 1.000 alacak · KDV 2.500 → **yük 1.500 ödenecek** |
| KDV küçükse yük iadeye dönüyor | ✓ KDV 500 → 500 iade |
| KDV tam dengeyi kapatıyorsa 0 | ✓ |
| Stopaj yoksa GV borcu + KDV toplanıyor | ✓ 3.000 + 1.000 = 4.000 |
| Ekranda GV dengesi / KDV kırılımı | ✓ "Devletten Alacak" ve "KDV: Ödenen" satırları |
| Aylık gider: elle + tablo TOPLANIYOR | ✓ 480.000 + 15.000 = 495.000 |
| Elle girilen kutu tablodan etkilenmiyor | ✓ ezilmiyor |
| Boş ay kaydedilmiyor, sıfırlanan siliniyor | ✓ |
| Aynı ay mükerrer olmuyor | ✓ benzersiz kısıt |
| Yıl ayrımı | ✓ 2025 gideri 2026'yı etkilemiyor |
| Canlı toplam (JS) Türkçe binliği okuyor | ✓ `1.000.000` = bir milyon |
| Yetki | ✓ müşavir başkasına yazamıyor, personel erişemiyor |
| Yazdırmada gider tablosu + vergi yükü | ✓ |

#### Önemli tespit — mantık zaten doğruymuş

Kullanıcı "mantık değişti" dedi, ancak inceleme sonucu **hesap motoru zaten
istenen şekilde çalışıyordu**: `sonuç = vergi − (stopaj + diğer − KDV)`, yani
GV dengesi + KDV. Ekran görüntüsündeki 1.000 TL sonucu doğruydu — o ekranda
KDV **2.000** girilmişti (1.500 + 500), kullanıcının anlattığı 2.500 değil.

Bu yüzden formül **değiştirilmedi**; bunun yerine sonucun *nereden geldiği*
ekranda görünür kılındı: "Gelir Vergisi: Devletten Alacak" ve "KDV: Ödenen"
satırları eklendi, sonuç etiketi **"YIL İÇİ VERGİ YÜKÜ"** olarak netleştirildi.

#### Test disiplini notu — yanlış alarmlar

11 test kırmızı yandı, **hiçbiri kod hatası değildi**:

- 3'ü sonuç etiketi metnini değiştirmemden ("ÖDENECEK GELİR VERGİSİ" →
  "YIL İÇİ VERGİ YÜKÜ — ÖDEYECEKSİNİZ"); eski metni arayan testler güncellendi.
- 8'i yeni 21. bölümün bıraktığı `stopaj_elle=4.000` ve `gider=530.000`
  kalıntısından; tarayıcı testinin eski senaryoları bu alanları sıfırlamıyordu.
  İlgili adımlara temizleme eklendi.

#### Doğrulanan mevcut testler

`mantik_testi` 72/72 · `filtre_testi` 18/18 · `makbuz_testi` 99/99 ·
`edefter_testi` 141/141 · `odeme_kompakt_testi` 56/56 ·
`odeme_mukerrer_testi` 29/29 · `ayarlar_testi` 54/54 · `sistem_testi` 82/82 ·
`evrak_testi` 58/58

---

### KDV mantığı düzeltmesi ve işaret kuralı

| Test | Sonuç |
|---|---|
| **Hesap motoru birim testi** | ✓ 135/135 (`php tests/gelir_vergisi_testi.php`) |
| **Uçtan uca HTTP testi** | ✓ 280/280 (`bash tests/gelir_vergisi_http_testi.sh`) |
| **Tarayıcı testi** | ✓ 140/140 (`python3 tests/tarayici_gelir_vergisi.py`) |
| **Kullanıcı senaryosu** | ✓ vergi 3.000 · stopaj 4.000 → 1.000 alacak · makbuz KDV 4.000, ödenen 3.000 → 1.000 borç → **yük TAM 0** |
| Hiç KDV ödenmemişse tüm yükümlülük borç | ✓ 4.000 borç → 3.000 ödenecek |
| Tamamı ödenmişse yalnız GV alacağı kalır | ✓ 1.000 iade |
| Fazla ödeme KDV alacağı doğuruyor | ✓ 1.000 GV + 1.000 KDV = 2.000 iade |
| İndirilecek KDV mahsuba GİRMİYOR | ✓ yalnız bilgi sütunu |
| KDV matrahı değiştirmiyor | ✓ |
| Yükümlülük hasılat ayarına uyuyor | ✓ tahsil esasında yalnız tahsil edilenler |
| **Eksi işareti kaldırıldı** | ✓ lehe tutarlar yeşil + "alacak" etiketi |
| Sonuç sıfırsa "ALACAK/VERECEK YOK" | ✓ gri satır |

#### Neyi düzelttik?

Önceki sürümde KDV tablosuna girilen **ödenen + indirilecek** toplamı doğrudan
yıl içi vergi yükü sayılıyordu. Doğrusu şu: makbuz kesildiğinde KDV
**yükümlülüğü** doğar, tabloya girilen ise bunun **ödenmiş kısmıdır**. Artık:

```
Kalan KDV borcu = Makbuz KDV'si − Fiilen ödenen
```

ve bu kalan, stopaj alacağıyla mahsuplaşır. Kullanıcının örneğinde
(1.000 alacak / 1.000 kalan borç) sonuç **tam sıfır** çıkıyor.

Ayrıca `−1.000,00` biçimindeki eksi işareti kaldırıldı; lehe olan tutarlar
yeşil renk ve açık etiketle ("Devletten Alacak", "Fazla Ödeme") gösteriliyor.

#### Test disiplini notu — yanlış alarmlar

21 test kırmızı yandı, **hiçbiri kod hatası değildi**. Kök neden: makbuz KDV
yükümlülüğü artık *her* hesaba giriyor, eski testler ise "KDV girilmezse
mahsup = stopaj" varsayımıyla yazılmıştı. Her biri elle doğrulanıp güncellendi:

- Bölüm 5: makbuzlarda 100.000 KDV borcu doğduğu için mahsup 0 → sonuç 44.500
  (eski beklenti 55.500 iadeydi).
- AJAX bölümü: mahsup 420.000 değil 320.000 (110.000 KDV borcu düşüldü).
- "ödeme yoksa" testimde stopajı 110.000 sanmıştım; o noktada elle 4.000'di.
- 3 test kaldırılan `kdv-t-toplam` kutusunu arıyordu → `kdv-f-toplam`'a çevrildi.
- 19. bölüm, 18. bölümün sıfırladığı sigorta/eğitim alanlarını arıyordu →
  bölüm kendi ön koşulunu kurar hâle getirildi.

#### Doğrulanan mevcut testler

`mantik_testi` 72/72 · `filtre_testi` 18/18 · `makbuz_testi` 99/99 ·
`edefter_testi` 141/141 · `odeme_kompakt_testi` 56/56 ·
`odeme_mukerrer_testi` 29/29 · `ayarlar_testi` 54/54 · `sistem_testi` 82/82 ·
`evrak_testi` 58/58

---

### KDV ödemesi = ay toplamı (ödenen + indirilecek)

| Test | Sonuç |
|---|---|
| **Hesap motoru birim testi** | ✓ 144/144 (`php tests/gelir_vergisi_testi.php`) |
| **Uçtan uca HTTP testi** | ✓ 281/281 (`bash tests/gelir_vergisi_http_testi.sh`) |
| **Tarayıcı testi** | ✓ 141/141 (`python3 tests/tarayici_gelir_vergisi.py`) |
| **Kullanıcı tablosu** | ✓ Ocak 3.000+1.000, Şubat 5.000+1.000 → **ay toplamı 10.000** mahsuba girdi |
| Makbuz KDV 10.000 = ödeme 10.000 → borç 0 | ✓ |
| İndirilecek girilmezse borç artıyor | ✓ 8.000 ödeme → 2.000 borç |
| Sütun dağılımı sonucu değiştirmiyor | ✓ 8.000+2.000 ile 2.000+8.000 aynı sonuç |
| Ay toplamı yükümlülüğü aşarsa KDV alacağı | ✓ 12.000 ödeme → 2.000 alacak |
| Canlı toplam ay toplamını hesaplıyor | ✓ kart özeti ve kalan borç anında güncelleniyor |
| Kırılım (ödenen / indirilecek) ayrı görünüyor | ✓ dördüncü kutuda |

#### Ne değişti?

Bir önceki sürümde mahsuba yalnızca **"Ödenen KDV"** sütunu giriyordu.
Kullanıcının belirttiği gibi doğrusu, tablodaki **Ay Toplamı** yani
**Ödenen + İndirilecek** rakamıdır — tfoot'taki genel toplam. Model, görünüm,
yazdırma ve canlı toplam JS'i bu kurala göre güncellendi.

`kdv_odenen` alanı artık ay toplamını taşır; kırılım için
`kdv_odenen_sutun` ve `kdv_indirilecek` alanları eklendi (ekranda ve
yazdırmada ayrıntı olarak gösterilir).

#### Test disiplini notu

13 test kırmızı yandı, **hiçbiri kod hatası değildi** — hepsi "yalnız ödenen
sütunu sayılır" varsayımıyla yazılmış eski beklentilerdi. Her biri elle
hesaplanıp doğrulandı (örn. 110.000 yükümlülük − 31.000 ay toplamı = 79.000
kalan borç) ve testler yeni kurala göre güncellendi.

---

## 📅 Hesap kipi: Yıllık Ücret Projeksiyonu

Mali müşavir yıl sonunu beklemeden **yıllık vergi yükünü** görmek ister.
Bunun için hesap ekranının üstünde iki kip düğmesi vardır:

| Kip | Hasılat / Stopaj / KDV kaynağı |
|---|---|
| **📅 Yıllık Ücret Projeksiyonu** *(varsayılan)* | Mükellef kartlarına girilen **yıllık sözleşme ücretleri** — makbuza dönüşmüş kabul edilir |
| **🧾 Kesilen Makbuzlar** | Yalnızca fiilen kesilmiş makbuzlar (bugüne kadarki gerçekleşme) |

Kip müşavir + yıl bazında saklanır; değiştirmek gider, Bağ-Kur, indirim gibi
girdilerinizi **etkilemez**.

### Ücret kipinde hesaplama

```
Hasılat = Σ yıllık sözleşme ücretleri   (o yıl ücreti girilmiş tüm mükellefler)
Stopaj  = Hasılat × gv_ucret_stopaj_oran   (varsayılan %20)
KDV     = Hasılat × gv_ucret_kdv_oran      (varsayılan %20)
```

**Yıllık Sözleşme Ücretleri** kartı sayfanın **en altındadır** ve varsayılan
olarak **kapalı** gelir — mükellef sayısı arttıkça sayfa uzamasın diye.
Başlıkta özet görünür (mükellef sayısı, toplam, stopaj, KDV); tıklayınca açılır.

Açıldığında hangi mükelleften ne kadar ücret / stopaj / KDV doğduğu satır satır
listelenir:

- **25 satır/sayfa** — Önceki / Sonraki düğmeleri ve sayfa sayacı
- **Arama kutusu** — ünvan veya VKN ile anlık süzme (25'ten fazla kayıtta görünür)
- Pasif (terk etmiş) mükellefler de ücret kaydı varsa sayılır, *pasif* rozetiyle işaretlenir
- **Yazdırma çıktısında sayfalama yoktur** — tüm satırlar basılır

KDV tablosundaki **Yükümlülük** kutusu bu kipte ücretlerden doğan KDV'yi
gösterir; **Kalan KDV Borcu** = ücret KDV'si − ödenen (ay toplamları).

> Oranlar makbuz modülünden **bağımsızdır**: `gv_ucret_stopaj_oran` ve
> `gv_ucret_kdv_oran` ayarlarından değiştirilir.

---

### Ücret projeksiyonu + Bağ-Kur sonrası taban testleri

| Test | Sonuç |
|---|---|
| **Hesap motoru birim testi** | ✓ 168/168 (`php tests/gelir_vergisi_testi.php`) |
| **Uçtan uca HTTP testi** | ✓ 321/321 (`bash tests/gelir_vergisi_http_testi.sh`) |
| **Tarayıcı testi** | ✓ 167/167 (`python3 tests/tarayici_gelir_vergisi.py`) |
| Ücret toplamı hasılata dönüşüyor | ✓ 120.000+96.000+48.000 = **264.000** |
| Stopaj/KDV ücretten türetiliyor | ✓ %20 → **52.800** / **52.800** |
| Mükellef bazında döküm | ✓ ekranda ve yazdırmada, pasifler işaretli |
| KDV yükümlülüğü ücretten geliyor | ✓ 52.800 − 40.000 ödeme = **12.800** borç |
| Kip değiştirme kayıtları koruyor | ✓ gider, Bağ-Kur, indirimler bozulmuyor |
| Ücreti olmayan müşavirde çökme yok | ✓ hasılat 0, döküm gizli |
| **İndirim tabanı = kazanç − Bağ-Kur** | ✓ 164.000−20.000 → tavan 21.600 / 14.400 |
| Bağ-Kur artınca tavan düşüyor | ✓ Bağkur 64.000 → tavan 15.000 / 10.000 |
| Bağ-Kur kazancı aşarsa tavan 0 | ✓ |
| Yetki | ✓ müşavir başkasının kipini değiştiremiyor, personel erişemiyor |

#### Test disiplini notu — yanlış alarmlar

Toplam 68 test kırmızı yandı, **hiçbiri kod hatası değildi**:

- **61'i** varsayılan kip değişikliğinden: mevcut testler kesilen makbuzlara
  göre yazılmıştı. `veriKur` artık `gv_varsayilan_kip='makbuz'` ayarlıyor,
  POST'lara `hesap_kipi=makbuz` eklendi; ücret kipi 23. bölümde ayrıca sınanıyor.
- **5'i** Bağ-Kur sonrası taban değişikliğinden (tavan 45.000 → 37.500 gibi) —
  elle hesaplanıp doğrulandı.
- **1'i** kendi test kurgumdaki hataydı: `yuk()` çağrısına sigorta/eğitim
  talebini geçirmeyi unutmuştum.
- **1'i** CSS `text-transform:uppercase` yüzündendi — `innerText` büyük harf
  döndürüyordu, `textContent` ile karşılaştırıldı.

#### Doğrulanan mevcut testler

`mantik_testi` 72/72 · `filtre_testi` 18/18 · `makbuz_testi` 99/99 ·
`edefter_testi` 141/141 · `odeme_kompakt_testi` 56/56 ·
`odeme_mukerrer_testi` 29/29 · `ayarlar_testi` 57/57 · `sistem_testi` 82/82 ·
`evrak_testi` 58/58

---

### Adlandırma + ücret dökümü sayfalama testleri

| Test | Sonuç |
|---|---|
| **Hesap motoru birim testi** | ✓ 168/168 (`php tests/gelir_vergisi_testi.php`) |
| **Uçtan uca HTTP testi** | ✓ 347/347 (`bash tests/gelir_vergisi_http_testi.sh`) |
| **Tarayıcı testi** | ✓ 178/178 (`python3 tests/tarayici_gelir_vergisi.py`) |
| Menü ve başlıklar "Vergi Yükü" oldu | ✓ menü, hesap kartı, liste, iki yazdırma ekranı |
| Eski "Gelir Vergisi Hesabı" başlığı kalmadı | ✓ |
| Liste sütunu "Kalan KDV Borcu" | ✓ eski "Ödenen KDV" başlığı yok |
| Liste "Kaynak" sütunu kipi gösteriyor | ✓ 📅 ücret / 🧾 makbuz rozeti |
| Ücret dökümü **sayfanın en altında** | ✓ KDV ve gider tablolarından sonra |
| Kart varsayılan **kapalı**, tıklayınca açılıyor | ✓ aria-expanded, "göster/gizle" |
| 25 satır/sayfa + Önceki/Sonraki | ✓ 33 mükellefle doğrulandı |
| Ünvan/VKN ile arama | ✓ eşleşme yoksa "bulunamadı" satırı |
| 25 ve altında sayfalama gizli | ✓ kart yine katlanır |
| **Yazdırmada sayfalama yok** | ✓ 33 satırın tamamı basılıyor |

#### Neden değişti?

- **Başlık:** Ekran artık yalnız gelir vergisini değil, KDV dahil yıl içi
  toplam vergi yükünü gösteriyor. "Gelir Vergisi Hesabı" yanıltıcıydı.
- **KDV sütunu:** Liste ekranındaki "Ödenen KDV" aslında *kalan borcu*
  gösteriyordu — ücret kipinde bu "yıl sonuna kadar ödenmesi gereken KDV"dir.
  Başlık "Kalan KDV Borcu" olarak düzeltildi (her iki kipte de doğru).
- **Sayfalama:** Portföy büyüdükçe (63 mükelleflik testte) sayfa aşırı
  uzuyordu. Kart en alta alındı, kapalı başlıyor, açılınca 25'erli sayfalanıyor.

#### Test disiplini notu — yanlış alarmlar

7 test kırmızı yandı, **hiçbiri kod hatası değildi**:

- 2'si sütun adının değişmesinden (`Ödenen KDV` → `Kalan KDV Borcu`).
- 1'i tabloya eklenen gizli "bulunamadı" satırından (3 yerine 4 `<tr>` sayıldı)
  → sayım `data-uc-satir` desenine çevrildi.
- 3'ü kartın artık kapalı başlamasından: `innerText` gizli içeriği okumuyor,
  `textContent` ile karşılaştırıldı.
- 1'i rozet metninin çok satırlı basılmasından (`ücret</span>` eşleşmedi).

#### Doğrulanan mevcut testler

`mantik_testi` 72/72 · `filtre_testi` 18/18 · `makbuz_testi` 99/99 ·
`edefter_testi` 141/141 · `odeme_kompakt_testi` 56/56 ·
`odeme_mukerrer_testi` 29/29 · `ayarlar_testi` 57/57 · `sistem_testi` 82/82 ·
`evrak_testi` 58/58 · `tekrar_yazdirma_testi` 50/50

---

### "Net Mahsup" ara satırının kaldırılması (21. güncelleme)

Hesap dökümündeki `= Net Mahsup (stopaj + diğer − kalan KDV borcu)` satırı
**kaldırıldı**. Matematiksel olarak doğruydu ama kavramsal olarak yanıltıcıydı:

- **"Mahsup" alacak demektir**; KDV ise bir **borçtur**. İkisini tek satırda
  toplamak, KDV borcunu "mahsup" gibi gösteriyordu.
- Ücret kipinde stopaj ile KDV yükümlülüğü **aynı orandan** (ücretin %20'si)
  hesaplandığı için bu satır tesadüfen *"ödenen KDV"ye* eşit çıkıyordu
  (örnekte 69.055,00) — okuyan için hiçbir anlam taşımıyordu.

Yerine zaten var olan iki kırılım satırı kaldı ve **rakamlı açıklama** eklendi:

```
Gelir Vergisi: Devletten Alacak    136.047,23
  189.147,42 vergi − 325.194,65 stopaj

KDV: Ödenmemiş Borç                256.139,65
  325.194,65 ücret KDV'si − 69.055,00 ödenen

YIL İÇİ VERGİ YÜKÜ — ÖDEYECEKSİNİZ 120.092,42
```

> **Sonuç DEĞİŞMEDİ.** Aynı senaryo yeniden hesaplandı: matrah 839.434,89 ·
> vergi 189.147,42 · stopaj 325.194,65 · kalan KDV borcu 256.139,65 ·
> **vergi yükü 120.092,42** — ekran görüntüsündeki rakamlarla birebir aynı.

`c-mahsup_toplam` değeri gizli bir satırda **korunur**, çünkü AJAX canlı hesap
bu id'yi günceller; kaldırılsaydı JS hata verirdi.

| Test | Sonuç |
|---|---|
| **Hesap motoru birim testi** | ✓ 168/168 |
| **Uçtan uca HTTP testi** | ✓ 356/356 |
| **Tarayıcı testi** | ✓ 178/178 |
| Ekranda ve yazdırmada ara satır yok | ✓ |
| Kırılım satırları duruyor + rakamlı açıklama | ✓ |
| Gizli değer AJAX için korunuyor | ✓ |
| Sonuç rakamı değişmedi | ✓ 120.092,42 |

#### Test disiplini notu

3 test kırmızı yandı, **hiçbiri kod hatası değildi**: biri yeni eklenen rakamlı
açıklama tutarı bir kez daha bastığı için (3 → 4 eşleşme), ikisi de kendi
bıraktığım HTML yorumundaki "Net Mahsup" kelimesine takıldığı için. Yorum PHP
yorumuna çevrildi, testler gerçeğe göre güncellendi.

---

## 🗓️ Ajanda / Hatırlatıcı

Beyanname, e-defter, evrak ve karşıt inceleme uyarıları sistemde **zaten
otomatik** üretiliyor. Ajanda bunları tekrarlamaz; **elle girilen işler**
içindir: "vergi dairesine uğra", "sözleşme yenile", "müşteriyi ara".

**Menü:** Genel → 🗓️ Ajanda (tüm roller erişir — herkesin kendi işi olur)

### Görünürlük — kim neyi görür?

| Tip | Görenler | Örnek |
|---|---|---|
| 🔒 **Kişisel** | Yalnız oluşturan | "Bankaya uğra" |
| 👥 **Büro geneli** | Tüm kullanıcılar | "3 Mart ofis kapalı" |
| 📌 **Görev** | Atanan kişi + atayan | "Fatma: SGK bildirimi" |
| 👨‍💼 **Mali müşavir ekibi** | O müşavire erişimi olanlar | "Ali'nin ekibi: dönem kapanışı" |

Yönetici tüm kayıtları görür. **Düzenleme** yetkisi yalnız oluşturan, atanan
ve yöneticidedir — başkası kaydı görebilir ama değiştiremez.

> Görünürlükle tutarsız kombinasyonlar otomatik düzeltilir: "Görev" seçilip
> kişi seçilmezse kayıt kişisele düşer, kişiselde atanan alanı temizlenir.
> Erişiminiz olmayan bir kullanıcıya/müşavire atama yapılamaz.

### Görünümler

| Ekran | İçerik |
|---|---|
| **📋 Liste** | Tarih sıralı, filtreli, sayfalı döküm. Bekleyenler önce. |
| **📅 Takvim** | Aylık ızgara; renkler önceliği gösterir. Hücredeki **+** ile o güne kayıt eklenir, 3'ten çok iş varsa **+N daha** o günün listesini açar. |

Filtreler: tarih aralığı, durum, öncelik, görünürlük, atanan, etiket, arama.
Arama başlık, açıklama, etiket ve mükellef ünvanında çalışır.

### Tekrar eden işler

Tekrar seçenekleri: günlük / haftalık / aylık / yıllık. Tekrarlı bir işte
**"Yapıldı"** denince kayıt kapanmaz; tarihi bir sonraki döneme **ötelenir**
ve beklemede kalır. "Tekrar bitişi" tarihi geçilmişse kayıt kapanır.

> **Ay sonu taşması çözülmüştür:** 31 Ocak + 1 ay = **28 Şubat**
> (PHP'nin varsayılanı 3 Mart olurdu). 29 Şubat + 1 yıl = 28 Şubat.

### Hatırlatma

- **Panel kartı** — "Yaklaşan İşler"; gecikmişler kırmızı, bugünküler turuncu.
- **Menü rozeti** — gecikmiş + bugünkü iş sayısı, her sayfada görünür.
- **Giriş uyarısı** — gün içinde işi olana **günde bir kez** açılır pencere.
  Kapatılınca aynı gün tekrar çıkmaz.
- **Erken hatırlatma** — kayıt başına "kaç gün önceden" ayarlanabilir.

### Diğer

- **Mükellefe bağlama** — iş bir firmayla ilgiliyse seçilir, listede ve
  filtrede görünür.
- **Dosya ekleri** — PDF, resim, Excel, Word, CSV, ZIP. İzinsiz uzantı
  (örn. `.php`) reddedilir; dosyalar `writable/uploads/ajanda/` altında
  rastgele adla saklanır, indirme yetkiye tabidir.
- **Hızlı erteleme** — yarına / 1 hafta / 1 ay / seçilen tarihe.
- **Yumuşak silme** — silinen kayıt veritabanında kalır, listede görünmez.
- **Yazdırma** — filtreli döküm, A4 dikey, imza bloklu.

### İlgili ayarlar

| Anahtar | Varsayılan | Anlamı |
|---|---|---|
| `ajanda_panel_gun` | `7` | Panelde kaç günlük ajanda gösterilsin |
| `ajanda_giris_uyari` | `1` | Girişte uyarı penceresi açılsın mı |
| `ajanda_ek_boyut` | `5120` | Dosya eki üst sınırı (KB) |

---

### Ajanda testleri

| Test | Sonuç |
|---|---|
| **Ajanda regresyon testi** | ✓ 108/108 (`bash tests/ajanda_testi.sh`) |
| Şema + idempotent migration | ✓ 3 tablo, 3 ayar |
| **Görünürlük izolasyonu** | ✓ 4 rolde ayrı ayrı doğrulandı |
| Doğrudan URL ile sızma engelli | ✓ kişisel/başkasının görevi 302 |
| Filtre görünürlüğü delmiyor | ✓ |
| Düzenleme yetkisi (oluşturan/atanan/admin) | ✓ POST zorlaması da engelli |
| Tutarsız kombinasyon temizleniyor | ✓ kişisel+atanan, kişisiz görev |
| Geçersiz renk / bitiş tarihi reddi | ✓ |
| Durum: yapıldı / geri al / iptal / ertele | ✓ |
| **Tekrar mantığı** | ✓ günlük, haftalık, aylık, yıllık |
| Ay sonu taşması | ✓ 31 Oca → 28 Şub · 29 Şub → 28 Şub |
| Tekrar bitişinde kapanma | ✓ |
| Sayaçlar ve menü rozeti | ✓ her sayfada, kullanıcıya göre |
| Takvim (bugün, +N daha, geçersiz ay) | ✓ görünürlüğe uyuyor |
| Giriş uyarısı günde bir kez | ✓ ayar kapalıyken hiç çıkmıyor |
| Dosya ekleri | ✓ `.php` reddi, yetkisiz indirme engeli |
| Panel kartı | ✓ görünürlüğe uyuyor |
| Yazdırma | ✓ stil gömülü, görünürlük geçerli |
| Yumuşak silme | ✓ |

#### Komşu modüller (BaseController ve menü değişti)

`mantik_testi` 72/72 · `makbuz_testi` 99/99 · `edefter_testi` 141/141 ·
`odeme_kompakt_testi` 56/56 · `ayarlar_testi` 61/61 · `sistem_testi` 82/82 ·
`evrak_testi` 58/58 · `gelir_vergisi_http_testi` 356/356 ·
`gelir_vergisi_testi` 168/168 — hiçbiri bozulmadı.

> **Geriye dönük uyum:** Menü rozeti ve panel kartı, `ajanda` tablosu yoksa
> sessizce 0/boş döner (`tableExists` denetimi + `try/catch`). Migration
> çalıştırılmamış bir kurulumda sistem çökmez.


---

## 📥 Evrak Takibinde "Takip Dışı" (Bu Mükellefte Yok)

Bazı mükelleflerin **banka hesabı yoktur**, bazıları **çek/senet
kullanmaz**, bazılarının **personeli yoktur**. Bu hücreler her ay kırmızı
"eksik evrak" olarak görünüyor, listeyi kirletiyor ve tamamlanma yüzdesini
yanlış gösteriyordu. Artık bu türler **takip dışı** işaretlenebilir.

### Görünüm

| Durum | Hücre | Anlamı |
|---|---|---|
| Geldi | yeşil ✓ | Evrak teslim alındı |
| Gelmedi | kırmızı ✕ | Bekleniyor |
| **Takip dışı** | **taralı gri —** | Mükellefte bu evrak türü yok, **eksik sayılmaz** |

Kalıcı muafiyetlerde hücrenin sağ üst köşesinde küçük bir **üçgen im**
bulunur; böylece "her ay geçerli" ile "yalnız bu ay" ayırt edilir.

### İki katman

| Katman | Nerede | Kapsam | Kullanım |
|---|---|---|---|
| **Kalıcı** | Mükellef kartı → *Takip Edilmeyen Evrak Türleri* | Tüm aylar | Bankası hiç olmayan mükellef |
| **Dönemsel** | Çizelgede hücreye **sağ tık** | Yalnız seçili ay | O ay istisnaen banka hareketi yok |

**Öncelik kuralı:** O aya ait bir kayıt varsa **kalıcı ayarı ezer**.
Bankası kalıcı muaf bir mükellefe tek bir ayda evrak geldiyse hücre
normal şekilde ✓ işaretlenebilir; diğer aylar etkilenmez.

### Sayaçlara etkisi

Takip dışı hücreler **toplamdan düşülür** — hiç var olmamış gibi davranır:

```
Beklenen hücre = (faal mükellef × evrak türü) − takip dışı hücre
Tamamlanma %   = gelen ÷ beklenen
```

Sayı sıfırdan büyükse özet satırında ayrıca **"Takip Dışı"** kartı çıkar.
Panel'deki *"evrakı gelmeyenler"* uyarı listesinde de takip dışı türler
sayılmaz; **tüm türleri muaf** olan mükellef bu listeye hiç girmez.

### Diğer davranışlar

- **"Tümü geldi" (✓✓)** düğmesi kalıcı muaf türleri **atlar** — bankası
  olmayan mükellefe "banka ekstresi geldi" yazılmaz.
- Bir tür kalıcı muaf yapıldığında geçmiş aylardaki **boş (gelmedi)**
  kayıtlar temizlenir; **"geldi" işaretlenmiş kayıtlara dokunulmaz**.
- Takip dışı hücre **sol tıkla açılmaz** (yanlışlıkla işaretlemeyi önler);
  geri almak için sağ tık menüsü kullanılır.
- **Excel** çıktısında `Takip dışı`, **yazdırmada** `—` olarak çıkar;
  yazdırma başlığında kaç hücrenin takip dışı olduğu belirtilir.

### Ayar

| Anahtar | Varsayılan | Anlamı |
|---|---|---|
| `evrak_muaf_etiket` | `Takip dışı` | Excel/yazdırma çıktısındaki karşılık |

### Toplu tanımlama

`database/migration_evrak_muafiyet.sql` dosyasının sonunda yorum içinde
hazır bir örnek vardır: **SGK işyeri sicili girilmemiş** tüm mükellefleri
tek sorguda "Bordro" türünden muaf tutar. Yorumu kaldırıp çalıştırmadan
önce **yedek alın**.

---

### Evrak muafiyeti testleri

| Test | Sonuç |
|---|---|
| **Evrak muafiyet regresyon testi** | ✓ 86/86 (`bash tests/evrak_muafiyet_testi.sh`) |
| Şema + idempotent migration | ✓ tablo, ENUM `YOK`, ayar |
| Kalıcı muafiyet (AJAX + mükellef kartı) | ✓ mükerrer istek satır çoğaltmıyor |
| Dönemsel istisna yalnız o ayı etkiliyor | ✓ komşu dönem bozulmadı |
| **Dönemsel kayıt kalıcıyı eziyor** | ✓ geri alınca kalıcıya dönüyor |
| Sayaçlar: muaf hücre toplamdan düşülüyor | ✓ beklenen, bekleyen, % |
| "Tümü geldi" muaf türü atlıyor | ✓ kayıt bile açmıyor |
| Boş kayıt temizliği | ✓ `GELDI` kayıtları korunuyor |
| Tüm türleri muaf mükellef | ✓ satır kayboluyor değil, panelde uyarı yok |
| Excel / yazdırma çıktısı | ✓ etiket + alt açıklama |
| Yetki | ✓ başka büronun mükellefi 403 |
| Geriye dönük uyum | ✓ tablo/ENUM yoksa eski davranış |

#### Bu turda bulunan gerçek kusur

Mükellef kartındaki çelişki denetimi JS'i `document.querySelectorAll('.tur-kutu')`
ile **belge genelini** tarıyordu. Yeni eklenen *Takip Edilmeyen Evrak Türleri*
kutuları da aynı sınıfı kullandığı için mükellef tipine göre **kilitleniyor**
(soluk + "Şahıs"/"Kurum" rozeti) ve tıklanamıyordu. İki ızgara artık
`#beyanname-tur-grid` ve `#evrak-muaf-grid` kimlikleriyle ayrılır; seçici
yalnızca kendi ızgarasını tarar. Regresyon testi eklendi.

#### Komşu modüller

`mantik_testi` 72/72 · `filtre_testi` 18/18 · `genc_girisimci_testi` 23/23 ·
`gelir_vergisi_testi` 168/168 · `gelir_vergisi_http_testi` 356/356 ·
`evrak_testi` 58/58 · `ajanda_testi` 108/108 · `edefter_testi` 141/141 ·
`makbuz_testi` 99/99 · `sistem_testi` 82/82 · `ayarlar_testi` 61/61 ·
`odeme_kompakt_testi` 56/56 · `odeme_mukerrer_testi` 29/29 ·
`ozet_kart_testi` 71/71 · `panel_dagilim_testi` 76/76 ·
`musavir_filtre_testi` 49/49 · `indirim_rozet_testi` 84/84 ·
`gg_tuzel_testi` 52/52 · `tekrar_yazdirma_testi` 50/50 ·
`sayfalama_testi` 47/47 · `tahakkuk_testi` 17/17 ·
`ice_aktar_testi` 48/48 — hiçbiri bozulmadı.

> **Geriye dönük uyum:** `mukellef_evrak_muafiyet` tablosu ya da ENUM'daki
> `YOK` değeri yoksa modül sessizce devre dışı kalır (`tableExists` +
> `information_schema` denetimi); çizelge eski davranışıyla (Geldi/Gelmedi)
> çalışır, mükellef kartındaki bölüm hiç görünmez. Mükellef kartı eski
> sürümdeyse gizli `evrak_muaf_gonderildi` alanı bulunmadığı için mevcut
> muafiyetler **silinmez**.

---

## 🤝 MUHSGK ile SGK'yı Tek Ekrandan Yönetme

Sigortalı işçi çalıştıran mükelleflerde **Muhtasar ve Prim Hizmet
Beyannamesi (MUHSGK)** ile **SGK prim bildirgesi** aynı işlemin iki
parçasıdır; birlikte verilir. Program bunları iki ayrı satır olarak
tuttuğu için kullanıcı **aynı işi iki kez** yapıyordu: MUHSGK'yı onayla,
tutarı gir → SGK satırına git, onu da onayla, tutarı tekrar gir.

Artık **MUHSGK satırı "ana" kayıttır**: onay ve tahakkuk tek ekrandan
girilir, SGK satırı buna bağlı olarak güncellenir.

### Nasıl çalışır

| Durum | Davranış |
|---|---|
| MUHSGK **Onaylandı** seçilir | Eşleşen SGK satırı da **kendiliğinden onaylanır**, bildirim çıkar |
| Tahakkuk penceresi açılır | İçinde ayrıca **"SGK Prim Tutarı"** alanı vardır |
| **Kaydet** basılır | İki tutar **tek istekte** yazılır, iki satır da dolar |
| MUHSGK onayı **geri alınır** | SGK'ya dokunulmaz — *"SGK kaydı da geri alınsın mı?"* diye **sorulur** |
| SGK **tek başına** onaylanır | Engellenmez ama *"MUHSGK henüz onaylanmadı"* **uyarısı** verilir |

Ödenecek toplam penceresinde **MUHSGK + damga + SGK primi** birlikte
gösterilir; "bu ay ne ödeyeceğim" tek yerden görünür.

### Çizelgedeki rozetler

| Rozet | Anlamı |
|---|---|
| <span>MUHSGK (Ay)</span> **+ SGK** | Bu satırdan SGK primi de girilebilir |
| <span>SGK</span> **⇄ MUHSGK (Ay) ile bağlı** | MUHSGK ile birlikte verilir; onayı oradan da yapılabilir |

SGK satırı **çizelgede durmaya devam eder** (gizlenmez): tutarı, ödeme
durumu ve geçmişi görünür kalsın diye. İsterseniz eskisi gibi doğrudan o
satırdan da yönetebilirsiniz.

### Eşleşme kuralı

Eşleşme **aynı mükellef + kesişen dönem** ölçütüyle bulunur; elle
tanımlama gerekmez, mükellef kartında ayar yoktur.

**Üç aylık MUHSGK** bir dönemde **üç SGK satırıyla** eşleşir. Bu durumda:

- Onay **üçüne birden** uygulanır.
- Tahakkuk tutarı **yalnız ilk satıra** yazılır ve *"bu dönemde 2 SGK
  satırı daha var"* uyarısı verilir. Program tutarı **kendiliğinden
  aylara bölmez** — hangi aya ne düştüğünü yalnızca kullanıcı bilir,
  bölmeye kalkışmak yanlış veri üretirdi.

### Şema değişikliği

**Yoktur.** Bu özellik için migration çalıştırmanıza gerek yok; eşleşme
mevcut `beyanname_turleri.kod` değerlerinden (`MUHSGK_A`, `MUHSGK_3A`,
`SGK`) ve dönem tarihlerinden hesaplanır.

---

### MUHSGK ↔ SGK testleri

| Test | Sonuç |
|---|---|
| **MUHSGK–SGK regresyon testi** | ✓ 61/61 (`bash tests/muhsgk_sgk_testi.sh`) |
| Eşleşme tespiti (mükellef + kesişen dönem) | ✓ |
| MUHSGK onayı → SGK da onaylanıyor | ✓ onay tarihi dahil |
| İlgisiz türler (KDV) etkilenmiyor | ✓ |
| **Tek istekte iki tahakkuk** | ✓ tutar + fiş no ayrı ayrı |
| Türkçe binlik ayırıcı (`4.500,00` = 4500) | ✓ |
| SGK alanı gönderilmezse eş kayda dokunulmuyor | ✓ eski davranış korunur |
| SGK alanı boş gönderilirse temizleniyor | ✓ |
| Geri alma: SGK'ya dokunulmuyor, soruluyor | ✓ |
| Kullanıcı onayıyla toplu geri alma | ✓ tahakkuk korunuyor |
| SGK tek başına onayı: uyarı var, engel yok | ✓ gereksiz uyarı çıkmıyor |
| Üç aylık MUHSGK → 3 SGK satırı | ✓ tutar bölünmüyor, kalan bildiriliyor |
| Yetki (başka büro) | ✓ 403, eş kayıt da değişmiyor |
| Geriye dönük uyum | ✓ iki yönde de çökmüyor |

#### Test disiplini notu

İki kırmızı çıktı, **ikisi de test kusuruydu**, kod doğruydu:

1. **Bash tırnak kaçışı** — `grep -c "if (!kutu) { return; }"` içindeki
   parantezler sözdizimi hatası verdi; `grep -cF` ile sabit dizge araması
   yapıldı. (Geçen turda da yaşanan tuzak.)
2. **Rozet sayımı** — test tüm yılı (58 satır) sayıp sayfayla (5 satır)
   karşılaştırıyordu. Çizelge `ay=8` filtresiyle açıldığı için sorguya ay
   koşulu eklendi. Kodda hata yoktu.

#### Komşu modüller

`mantik_testi` 72/72 · `filtre_testi` 18/18 · `genc_girisimci_testi` 23/23 ·
`gelir_vergisi_testi` 168/168 · `gelir_vergisi_http_testi` 356/356 ·
`evrak_testi` 58/58 · `evrak_muafiyet_testi` 86/86 · `ajanda_testi` 108/108 ·
`edefter_testi` 141/141 · `makbuz_testi` 99/99 · `sistem_testi` 82/82 ·
`ayarlar_testi` 61/61 · `odeme_kompakt_testi` 56/56 ·
`odeme_mukerrer_testi` 29/29 · `ozet_kart_testi` 71/71 ·
`panel_dagilim_testi` 76/76 · `musavir_filtre_testi` 49/49 ·
`indirim_rozet_testi` 84/84 · `gg_tuzel_testi` 52/52 ·
`tekrar_yazdirma_testi` 50/50 · `sayfalama_testi` 47/47 ·
`tahakkuk_testi` 17/17 · `ice_aktar_testi` 48/48 — hiçbiri bozulmadı.

> **Geriye dönük uyum:** İki yönde de doğrulandı. Yalnızca görünüm
> kopyalanırsa (`esHarita` gelmez) rozetler çizilmez, sayfa çalışır.
> Yalnızca controller kopyalanırsa SGK penceresi bulunamaz, `sgk_tutar`
> gönderilmez ve eş kayda **dokunulmaz** — eski davranış aynen sürer.
