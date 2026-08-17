<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Panel');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

// ---------------------------------------------------------------------
// KURULUM (ilk yönetici oluşturma) — kurulum tamamlanınca kendini kilitler
// ---------------------------------------------------------------------
$routes->get('kurulum', 'Kurulum::index');
$routes->post('kurulum/kaydet', 'Kurulum::kaydet');

// ---------------------------------------------------------------------
// GİRİŞ / ÇIKIŞ
// ---------------------------------------------------------------------
$routes->group('', ['filter' => 'guest'], static function ($routes) {
    $routes->get('giris', 'Auth::giris');
    $routes->post('giris', 'Auth::girisYap');
});
$routes->get('cikis', 'Auth::cikis');

// ---------------------------------------------------------------------
// UYGULAMA (giriş zorunlu)
// ---------------------------------------------------------------------
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    // Anasayfa / Panel
    $routes->get('/', 'Panel::index');
    $routes->get('panel', 'Panel::index');
    $routes->get('panel/takvim', 'Panel::takvim');
    $routes->get('panel/takvim-veri', 'Panel::takvimVeri');
    // Tür dağılımı tablosundaki sayıya tıklanınca açılan mükellef listesi
    $routes->get('panel/tur-listesi', 'Panel::turListesi');

    // ----------------- MÜKELLEFLER -----------------
    $routes->group('mukellefler', static function ($routes) {
        $routes->get('/', 'Mukellefler::index');
        $routes->get('yeni', 'Mukellefler::yeni');
        $routes->post('kaydet', 'Mukellefler::kaydet');
        $routes->get('duzenle/(:num)', 'Mukellefler::duzenle/$1');
        $routes->post('guncelle/(:num)', 'Mukellefler::guncelle/$1');
        $routes->get('detay/(:num)', 'Mukellefler::detay/$1');
        $routes->get('sil/(:num)', 'Mukellefler::sil/$1');
        $routes->post('terk/(:num)', 'Mukellefler::terk/$1');
        $routes->post('gecmisi-kapat/(:num)', 'Mukellefler::gecmisiKapat/$1');
        $routes->get('donem-uret/(:num)', 'Mukellefler::donemUret/$1');
        $routes->get('cizelge/(:num)', 'Mukellefler::cizelge/$1');

        // Excel/CSV'den toplu aktarma (yalnızca admin ve müşavir)
        $routes->get('ice-aktar', 'Mukellefler::iceAktar', ['filter' => 'auth:admin,musavir']);
        $routes->get('sablon-indir', 'Mukellefler::sablonIndir', ['filter' => 'auth:admin,musavir']);
        $routes->post('ice-aktar/onizle', 'Mukellefler::onizle', ['filter' => 'auth:admin,musavir']);
        $routes->post('ice-aktar/onayla', 'Mukellefler::aktarmaOnayla', ['filter' => 'auth:admin,musavir']);
    });

    // ----------------- MALİ MÜŞAVİRLER -----------------
    // Mali müşavir kayıtları: listeleme müşavir rolüne de açık,
    // ekleme/düzenleme/silme yalnızca yöneticide.
    $routes->group('musavirler', static function ($routes) {
        $routes->get('/', 'Musavirler::index', ['filter' => 'auth:admin,musavir']);
        $routes->get('yeni', 'Musavirler::yeni', ['filter' => 'auth:admin']);
        $routes->post('kaydet', 'Musavirler::kaydet', ['filter' => 'auth:admin']);
        $routes->get('duzenle/(:num)', 'Musavirler::duzenle/$1', ['filter' => 'auth:admin,musavir']);
        $routes->post('guncelle/(:num)', 'Musavirler::guncelle/$1', ['filter' => 'auth:admin,musavir']);
        $routes->get('sil/(:num)', 'Musavirler::sil/$1', ['filter' => 'auth:admin']);
    });

    // ----------------- BEYANNAME TAKİP -----------------
    $routes->group('takip', static function ($routes) {
        $routes->get('/', 'Takip::index');
        $routes->get('liste', 'Takip::index');
        $routes->post('durum', 'Takip::durumGuncelle');           // AJAX
        $routes->post('es-durum', 'Takip::esDurum');              // AJAX (MUHSGK↔SGK)
        $routes->post('not', 'Takip::notKaydet');                 // AJAX
        $routes->post('toplu-durum', 'Takip::topluDurum');        // AJAX
        $routes->post('tahakkuk-sil', 'Takip::tahakkukSil');      // AJAX
        $routes->get('daha-fazla', 'Takip::dahaFazla');           // AJAX (sonsuz kaydırma)
        $routes->post('tum-idler', 'Takip::tumIdler');            // AJAX (filtredeki hepsini seç)
        $routes->get('toplu-uret', 'Takip::topluUret');
        $routes->post('toplu-uret', 'Takip::topluUretCalistir');
        $routes->get('excel', 'Takip::excel');
        $routes->get('yazdir', 'Takip::yazdir');
    });

    // ----------------- MAKBUZ TAKİP (serbest meslek makbuzu) -----------------
    // Mali bilgi içerir: personel erişemez.
    $routes->group('makbuz', ['filter' => 'auth:admin,musavir'], static function ($routes) {
        $routes->get('/', 'Makbuz::index');
        $routes->get('daha-fazla', 'Makbuz::dahaFazla');          // AJAX
        $routes->get('detay/(:num)', 'Makbuz::detay/$1');
        $routes->post('kaydet', 'Makbuz::kaydet');
        $routes->get('sil/(:num)', 'Makbuz::sil/$1');
        $routes->post('tahsil', 'Makbuz::tahsil');                // AJAX
        $routes->post('ucret', 'Makbuz::ucret');                  // AJAX
        $routes->post('ucret-kopyala', 'Makbuz::ucretKopyala');
        $routes->get('ice-aktar', 'Makbuz::iceAktar');
        $routes->post('ice-aktar/onizle', 'Makbuz::onizle');
        $routes->post('ice-aktar/onayla', 'Makbuz::onayla');
        $routes->get('sablon', 'Makbuz::sablon');
        $routes->get('excel', 'Makbuz::excel');
        $routes->get('yazdir', 'Makbuz::yazdir');
        $routes->get('detay-yazdir/(:num)', 'Makbuz::detayYazdir/$1');
    });

    // ----------------- AJANDA / HATIRLATICI -----------------
    // Tüm roller erişir: herkesin kendi işi olur. Görünürlük kayıt bazında.
    $routes->group('ajanda', static function ($routes) {
        $routes->get('/', 'Ajanda::index');
        $routes->get('takvim', 'Ajanda::takvim');
        $routes->get('yeni', 'Ajanda::yeni');
        $routes->get('duzenle/(:num)', 'Ajanda::duzenle/$1');
        $routes->get('detay/(:num)', 'Ajanda::detay/$1');
        $routes->post('kaydet', 'Ajanda::kaydet');
        $routes->get('sil/(:num)', 'Ajanda::sil/$1');
        $routes->get('yazdir', 'Ajanda::yazdir');

        // AJAX
        $routes->post('yapildi', 'Ajanda::yapildi');
        $routes->post('geri-al', 'Ajanda::geriAl');
        $routes->post('iptal', 'Ajanda::iptal');
        $routes->post('ertele', 'Ajanda::ertele');
        $routes->get('giris-uyarisi', 'Ajanda::girisUyarisi');
        $routes->post('uyari-okundu', 'Ajanda::uyariOkundu');

        // Dosya ekleri
        $routes->get('ek/(:num)', 'Ajanda::ekIndir/$1');
        $routes->get('ek-sil/(:num)', 'Ajanda::ekSil/$1');
    });

    // ----------------- GELİR VERGİSİ HESABI (mali müşavir bazında) -----------------
    // Hasılat makbuzlardan gelir; kullanıcı gideri girer, tarife uygulanır.
    // Mali bilgi içerir: personel erişemez.
    $routes->group('gelir-vergisi', ['filter' => 'auth:admin,musavir'], static function ($routes) {
        $routes->get('/', 'GelirVergisi::index');
        $routes->get('detay/(:num)', 'GelirVergisi::detay/$1');
        $routes->post('kaydet', 'GelirVergisi::kaydet');
        $routes->post('hesapla', 'GelirVergisi::hesapla');        // AJAX canlı hesap
        $routes->post('kdv-kaydet', 'GelirVergisi::kdvKaydet');   // aylık KDV tablosu
        $routes->post('gider-kaydet', 'GelirVergisi::giderKaydet'); // aylık gider tablosu
        $routes->post('kip', 'GelirVergisi::kipDegistir');        // ücret ↔ makbuz kipi
        $routes->post('kalem-kaydet', 'GelirVergisi::kalemKaydet');   // indirim kalemi ekle/düzenle
        $routes->get('kalem-sil/(:num)', 'GelirVergisi::kalemSil/$1');
        $routes->post('kalem-kopyala', 'GelirVergisi::kalemKopyala');
        $routes->get('yazdir/(:num)', 'GelirVergisi::yazdir/$1');
        $routes->get('liste-yazdir', 'GelirVergisi::listeYazdir');
        $routes->get('tarife', 'GelirVergisi::tarife');
        $routes->post('tarife/kaydet', 'GelirVergisi::tarifeKaydet');
        $routes->post('tarife/kopyala', 'GelirVergisi::tarifeKopyala');
    });

    // ----------------- E-DEFTER BERAT TAKİBİ -----------------
    $routes->group('edefter', static function ($routes) {
        $routes->get('/', 'Edefter::index');
        $routes->post('adim', 'Edefter::adim');                   // AJAX
        $routes->post('hepsi', 'Edefter::hepsi');                 // AJAX
        $routes->post('durum', 'Edefter::durum');                 // AJAX
        $routes->post('not', 'Edefter::not');                     // AJAX
        $routes->get('daha-fazla', 'Edefter::dahaFazla');         // AJAX
        $routes->get('toplu-uret', 'Edefter::topluUret');
    });

    // ----------------- EVRAK TAKİP -----------------
    $routes->group('evrak', static function ($routes) {
        $routes->get('/', 'Evrak::index');
        $routes->post('durum', 'Evrak::durumGuncelle');           // AJAX
        $routes->post('tumu', 'Evrak::tumunuIsaretle');           // AJAX
        $routes->post('aylik-not', 'Evrak::aylikNot');            // AJAX
        $routes->post('donem-muaf', 'Evrak::donemMuaf');          // AJAX (yalnız bu ay)
        $routes->post('kalici-muaf', 'Evrak::kaliciMuaf');        // AJAX (tüm aylar)
        $routes->get('daha-fazla', 'Evrak::dahaFazla');           // AJAX (sonsuz kaydırma)
        $routes->get('excel', 'Evrak::excel');
        $routes->get('yazdir', 'Evrak::yazdir');
    });

    // ----------------- ÖDEME LİSTESİ -----------------
    // Mali bilgiler personel rolüne kapalıdır.
    // Tahakkuk girişi personele de açıktır (beyannamenin parçası).
    $routes->post('odeme/tahakkuk', 'Odeme::tahakkukKaydet');

    $routes->group('odeme', ['filter' => 'auth:admin,musavir'], static function ($routes) {
        $routes->get('/', 'Odeme::index');
        $routes->get('daha-fazla', 'Odeme::dahaFazla');           // AJAX (sonsuz kaydırma)
        $routes->post('odendi', 'Odeme::odemeIsaretle');          // AJAX
        $routes->get('excel', 'Odeme::excel');
        $routes->get('yazdir', 'Odeme::yazdir');
        $routes->get('bildirim/(:num)', 'Odeme::bildirim/$1');
        $routes->post('bildirim-mail/(:num)', 'Odeme::bildirimMail/$1');   // e-posta gönderimi

        // Kayıtlı ödeme listeleri (kullanıcıya özel)
        $routes->get('listeler', 'Odeme::listeler');
        $routes->post('liste-kaydet', 'Odeme::listeKaydet');
        $routes->get('liste/(:num)', 'Odeme::liste/$1');
        $routes->get('liste-yazdir/(:num)', 'Odeme::listeYazdir/$1');
        $routes->get('liste-excel/(:num)', 'Odeme::listeExcel/$1');
        $routes->get('liste-sil/(:num)', 'Odeme::listeSil/$1');

        // Özel ödeme kalemleri (Bağkur, MTV, harç vb.)
        $routes->post('ozel-kaydet', 'Odeme::ozelKaydet');
        $routes->get('ozel-sil/(:num)', 'Odeme::ozelSil/$1');
        $routes->post('ozel-odendi', 'Odeme::ozelOdendi');

        // Aylık tekrar eden kalemler
        $routes->get('tekrar-uret', 'Odeme::tekrarUret');
        $routes->get('tekrar-durdur/(:num)', 'Odeme::tekrarDurdur/$1');
    });

    // ----------------- KARŞIT İNCELEME -----------------
    $routes->group('karsit', static function ($routes) {
        $routes->get('/', 'Karsit::index');
        $routes->post('kaydet', 'Karsit::kaydet');
        $routes->post('durum', 'Karsit::durumGuncelle');          // AJAX
        $routes->post('not', 'Karsit::notKaydet');                // AJAX
        $routes->get('sil/(:num)', 'Karsit::sil/$1');
        $routes->get('excel', 'Karsit::excel');
        $routes->get('yazdir', 'Karsit::yazdir');
    });

    // ----------------- TANIMLAR -----------------
    $routes->group('tanimlar', ['filter' => 'auth:admin,musavir'], static function ($routes) {
        // Beyanname türleri
        $routes->get('beyanname-turleri', 'Tanimlar::beyannameTurleri');
        $routes->post('beyanname-turu-kaydet', 'Tanimlar::beyannameTuruKaydet');
        $routes->get('beyanname-turu-sil/(:num)', 'Tanimlar::beyannameTuruSil/$1');

        // Damga vergisi tutarları
        $routes->get('damga', 'Tanimlar::damga');
        $routes->post('damga-kaydet', 'Tanimlar::damgaKaydet');
        $routes->post('damga-kopyala', 'Tanimlar::damgaKopyala');

        // Evrak türleri
        $routes->get('evrak-turleri', 'Tanimlar::evrakTurleri');
        $routes->post('evrak-turu-kaydet', 'Tanimlar::evrakTuruKaydet');
        $routes->get('evrak-turu-sil/(:num)', 'Tanimlar::evrakTuruSil/$1');
        $routes->get('edefter-adimlari', 'Tanimlar::edefterAdimlari');
        $routes->post('edefter-adim-kaydet', 'Tanimlar::edefterAdimKaydet');
        $routes->get('edefter-adim-sil/(:num)', 'Tanimlar::edefterAdimSil/$1');

        // Tatiller
        $routes->get('tatiller', 'Tanimlar::tatiller');
        $routes->post('tatil-kaydet', 'Tanimlar::tatilKaydet');
        $routes->get('tatil-sil/(:num)', 'Tanimlar::tatilSil/$1');

        // Ayarlar
        $routes->get('ayarlar', 'Tanimlar::ayarlar');
        $routes->post('ayarlar', 'Tanimlar::ayarlarKaydet');
    });

    // ----------------- SİSTEM (yalnızca yönetici) -----------------
    // Veritabanı yedekleme, geri yükleme ve toplu veri silme.
    // Personel ve müşavir rolleri bu bölümü göremez.
    $routes->group('sistem', ['filter' => 'auth:admin'], static function ($routes) {
        // Yedekleme
        $routes->get('yedekleme', 'Sistem::yedekleme');
        $routes->post('yedek-indir', 'Sistem::yedekIndir');
        $routes->get('geri-yukleme', 'Sistem::geriYukleme');
        $routes->post('geri-yukle', 'Sistem::geriYukleCalistir');

        // Veri yönetimi / toplu silme
        $routes->get('veri-yonetimi', 'Sistem::veriYonetimi');
        $routes->post('mukellef-toplu-sil', 'Sistem::mukellefTopluSil');   // AJAX
        $routes->post('beyanname-onizle', 'Sistem::beyannameOnizle');      // AJAX
        $routes->post('beyanname-sil', 'Sistem::beyannameSil');
        $routes->post('evrak-sil', 'Sistem::evrakSil');

        // Çöp kutusu
        $routes->get('cop-kutusu', 'Sistem::copKutusu');
        $routes->post('cop-geri-yukle', 'Sistem::copGeriYukle');
        $routes->post('cop-kalici-sil', 'Sistem::copKaliciSil');
    });

    // ----------------- KULLANICILAR -----------------
    $routes->group('kullanicilar', ['filter' => 'auth:admin'], static function ($routes) {
        $routes->get('/', 'Kullanicilar::index');
        $routes->get('yeni', 'Kullanicilar::yeni');
        $routes->post('kaydet', 'Kullanicilar::kaydet');
        $routes->get('duzenle/(:num)', 'Kullanicilar::duzenle/$1');
        $routes->post('guncelle/(:num)', 'Kullanicilar::guncelle/$1');
        $routes->get('sil/(:num)', 'Kullanicilar::sil/$1');
    });

    // Profil (her kullanıcı kendi şifresini değiştirebilir)
    $routes->get('profil', 'Kullanicilar::profil');
    $routes->post('profil', 'Kullanicilar::profilKaydet');

    // ----------------- RAPORLAR -----------------
    $routes->group('raporlar', static function ($routes) {
        $routes->get('/', 'Raporlar::index');
        $routes->get('gecikmis', 'Raporlar::gecikmis');
        $routes->get('musavir-performans', 'Raporlar::musavirPerformans');
        $routes->get('mukellef-ozet', 'Raporlar::mukellefOzet');
    });
});
