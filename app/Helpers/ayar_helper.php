<?php

/**
 * AYAR ETİKETLERİ
 *
 * Ayarlar ekranında teknik anahtarlar (`gv_uyumlu_ust_sinir` gibi) doğrudan
 * gösteriliyordu; kullanıcı için okunaksızdı. Burada her anahtarın Türkçe
 * adı, açıklaması, hangi karta gireceği ve nasıl bir girdi alanı çizileceği
 * tek yerden tanımlanır.
 *
 * Yeni bir ayar eklendiğinde buraya bir satır yazmak yeterlidir; listede
 * olmayan ayarlar yine "Diğer Ayarlar" bölümünde ham hâliyle görünmeye
 * devam eder (hiçbir ayar arayüzden kaybolmaz).
 *
 * ALAN TİPLERİ
 *   metin   → düz yazı
 *   sayi    → sayı kutusu (min/max/adim ile)
 *   onay    → aç/kapa kutusu (1 = açık)
 *   secim   → açılır menü (secenekler dizisi)
 *   oran    → yüzde kutusu (0-100 arası sayı)
 *   para    → Türkçe biçimli tutar
 */

if (! function_exists('ayarTanimlari')) {
    /**
     * @return array<string,array{
     *     ad:string, grup:string, tip:string, aciklama?:string,
     *     min?:int, max?:int, adim?:string, secenekler?:array, birim?:string
     * }>
     */
    function ayarTanimlari(): array
    {
        return [
            // ---------------- AJANDA ----------------
            'ajanda_panel_gun' => [
                'ad'       => 'Panelde Gösterilecek Gün Sayısı',
                'grup'     => 'ajanda',
                'tip'      => 'sayi',
                'min'      => 1,
                'max'      => 90,
                'birim'    => 'gün',
                'aciklama' => 'Kontrol panelindeki ajanda kartı kaç günlük işi listelesin.',
            ],
            'ajanda_giris_uyari' => [
                'ad'       => 'Girişte Hatırlatma Penceresi',
                'grup'     => 'ajanda',
                'tip'      => 'onay',
                'aciklama' => 'Açıkken, o günün ve gecikmiş işlerin listesi giriş sonrası bir kez gösterilir.',
            ],
            'ajanda_ek_boyut' => [
                'ad'       => 'Dosya Eki Üst Sınırı',
                'grup'     => 'ajanda',
                'tip'      => 'sayi',
                'min'      => 128,
                'max'      => 51200,
                'birim'    => 'KB',
                'aciklama' => 'Ajanda kaydına eklenebilecek en büyük dosya boyutu. 5120 KB = 5 MB.',
            ],

            // ---------------- OTURUM / GÜVENLİK ----------------
            'hatirla_acik' => [
                'ad'       => '"Beni Hatırla" Kutusu',
                'grup'     => 'oturum',
                'tip'      => 'onay',
                'aciklama' => 'Giriş ekranında kalıcı oturum seçeneği gösterilsin mi.',
            ],
            'hatirla_gun' => [
                'ad'       => 'Hatırlama Süresi',
                'grup'     => 'oturum',
                'tip'      => 'sayi',
                'min'      => 1,
                'max'      => 365,
                'birim'    => 'gün',
                'aciklama' => '"Beni hatırla" işaretlendiğinde kaç gün şifre sorulmasın. '
                            . 'Şifre değiştirildiğinde tüm kalıcı oturumlar iptal olur.',
            ],

            // ---------------- EVRAK ----------------
            'evrak_muaf_etiket' => [
                'ad'       => 'Takip Dışı Hücre Etiketi',
                'grup'     => 'evrak',
                'tip'      => 'metin',
                'aciklama' => 'Mükellefte bulunmayan evrak türlerinin Excel ve yazdırma çıktısındaki karşılığı.',
            ],

            // ---------------- MAKBUZ ----------------
            'makbuz_stopaj_oran' => [
                'ad'       => 'Stopaj Oranı',
                'grup'     => 'makbuz',
                'tip'      => 'oran',
                'aciklama' => 'Serbest meslek makbuzunda brüt tutardan kesilen gelir vergisi stopajı.',
            ],
            'makbuz_kdv_oran' => [
                'ad'       => 'KDV Oranı',
                'grup'     => 'makbuz',
                'tip'      => 'oran',
                'aciklama' => 'Serbest meslek makbuzuna eklenen katma değer vergisi oranı.',
            ],
            'makbuz_kdv_dahil' => [
                'ad'       => 'Excel Tutarları KDV Dahil',
                'grup'     => 'makbuz',
                'tip'      => 'onay',
                'aciklama' => 'Açıkken, Excel’den aktarılan brüt tutarların KDV içerdiği varsayılır ve KDV ayrıştırılır.',
            ],

            // ---------------- VERGİ YÜKÜ ----------------
            'gv_varsayilan_kip' => [
                'ad'       => 'Varsayılan Hesap Kipi',
                'grup'     => 'vergi',
                'tip'      => 'secim',
                'secenekler' => [
                    'ucret'  => 'Yıllık sözleşme ücreti (projeksiyon)',
                    'makbuz' => 'Kesilen makbuzlar (gerçekleşen)',
                ],
                'aciklama' => 'Vergi Yükü ekranı yeni bir yıl için ilk açıldığında hangi kaynağı kullansın.',
            ],
            'gv_hasilat_kaynagi' => [
                'ad'       => 'Makbuz Kipinde Hasılat',
                'grup'     => 'vergi',
                'tip'      => 'secim',
                'secenekler' => [
                    'tum'    => 'Kesilen tüm makbuzlar',
                    'tahsil' => 'Yalnız tahsil edilenler',
                ],
                'aciklama' => 'Makbuz kipinde hasılata hangi makbuzların gireceği.',
            ],
            'gv_ucret_stopaj_oran' => [
                'ad'       => 'Ücret Projeksiyonu — Stopaj Oranı',
                'grup'     => 'vergi',
                'tip'      => 'oran',
                'aciklama' => 'Ücret kipinde yıllık sözleşme tutarından hesaplanan stopaj oranı.',
            ],
            'gv_ucret_kdv_oran' => [
                'ad'       => 'Ücret Projeksiyonu — KDV Oranı',
                'grup'     => 'vergi',
                'tip'      => 'oran',
                'aciklama' => 'Ücret kipinde yıllık sözleşme tutarına eklenen KDV oranı.',
            ],
            'gv_sigorta_oran' => [
                'ad'       => 'Şahıs / Hayat Sigortası İndirim Sınırı',
                'grup'     => 'vergi',
                'tip'      => 'oran',
                'aciklama' => 'GVK 89/1 — Bağ-Kur düşüldükten sonraki kazancın en çok bu kadarı indirilebilir.',
            ],
            'gv_egitim_saglik_oran' => [
                'ad'       => 'Eğitim / Sağlık Harcaması İndirim Sınırı',
                'grup'     => 'vergi',
                'tip'      => 'oran',
                'aciklama' => 'GVK 89/2 — Bağ-Kur düşüldükten sonraki kazancın en çok bu kadarı indirilebilir.',
            ],
            'gv_uyumlu_oran' => [
                'ad'       => 'Vergiye Uyumlu Mükellef İndirimi',
                'grup'     => 'vergi',
                'tip'      => 'oran',
                'aciklama' => 'GVK mükerrer 121 — Hesaplanan vergiden düşülen indirim oranı.',
            ],
            'gv_uyumlu_ust_sinir' => [
                'ad'       => 'Uyumlu Mükellef İndirimi Üst Sınırı',
                'grup'     => 'vergi',
                'tip'      => 'para',
                'birim'    => '₺',
                'aciklama' => 'İndirimin tavanı. 2026 yılı için 12.000.000 ₺.',
            ],
        ];
    }
}

if (! function_exists('ayarGruplari')) {
    /**
     * Ayar kartlarının başlıkları.
     *
     * @return array<string,array{baslik:string,ikon:string,aciklama?:string}>
     */
    function ayarGruplari(): array
    {
        return [
            'oturum' => [
                'ikon'     => '🔐',
                'baslik'   => 'Giriş ve Oturum',
                'aciklama' => 'Kalıcı oturum ("beni hatırla") davranışı.',
            ],
            'ajanda' => [
                'ikon'     => '🗓️',
                'baslik'   => 'Ajanda ve Hatırlatmalar',
                'aciklama' => 'Panel kartı, giriş uyarısı ve dosya eki sınırı.',
            ],
            'evrak' => [
                'ikon'     => '📁',
                'baslik'   => 'Evrak Takip — Ek Ayarlar',
                'aciklama' => 'Takip dışı bırakılan evrak hücrelerinin çıktı karşılığı.',
            ],
            'makbuz' => [
                'ikon'     => '🧾',
                'baslik'   => 'Serbest Meslek Makbuzu',
                'aciklama' => 'Makbuz hesabında kullanılan varsayılan oranlar. '
                            . 'Net = Brüt − Stopaj + KDV.',
            ],
            'vergi' => [
                'ikon'     => '🧮',
                'baslik'   => 'Vergi Yükü Hesabı',
                'aciklama' => 'Yıllık gelir vergisi ve KDV yükü hesabında kullanılan '
                            . 'oranlar ile mevzuat sınırları.',
            ],
        ];
    }
}

if (! function_exists('ayarAdi')) {
    /** Anahtarın Türkçe adı (tanımlı değilse anahtarın kendisi) */
    function ayarAdi(string $anahtar): string
    {
        return ayarTanimlari()[$anahtar]['ad'] ?? $anahtar;
    }
}
