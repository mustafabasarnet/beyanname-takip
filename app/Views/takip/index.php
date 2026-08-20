<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<!-- ============ FİLTRE ============ -->
<?php $mod = $filtre['tarih_modu'] ?? 'beyan'; ?>
<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Görünüm</label>
    <select name="mod" data-oto-filtre title="Yıl ve Ay filtresinin neye göre çalışacağı">
      <option value="beyan" <?= $mod === 'beyan' ? 'selected' : '' ?>>Beyan Dönemi (son tarih)</option>
      <option value="donem" <?= $mod === 'donem' ? 'selected' : '' ?>>Ait Olduğu Dönem</option>
    </select>
  </div>

  <div class="form-grup">
    <label><?= $mod === 'donem' ? 'Dönem Yılı' : 'Beyan Yılı' ?></label>
    <select name="yil" data-oto-filtre>
      <?php foreach (yilSecenekleri() as $y): ?>
        <option value="<?= $y ?>" <?= (int) $filtre['yil'] === $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-grup">
    <label><?= $mod === 'donem' ? 'Dönem Ayı' : 'Beyan Ayı (son tarih)' ?></label>
    <select name="ay" data-oto-filtre>
      <option value="0" <?= $filtre['ay'] === null ? 'selected' : '' ?>>Tüm Aylar</option>
      <?php for ($a = 1; $a <= 12; $a++): ?>
        <option value="<?= $a ?>" <?= (int) $filtre['ay'] === $a ? 'selected' : '' ?>><?= ayAdi($a) ?></option>
      <?php endfor; ?>
    </select>
  </div>

  <div class="form-grup tur-coklu-grup">
    <label>Beyanname Türü
      <?php if (is_array($filtre['tur_id'] ?? null) && count($filtre['tur_id']) > 1): ?>
        <span class="coklu-bilgi">(<?= count($filtre['tur_id']) ?> seçili)</span>
      <?php endif; ?>
    </label>
    <div class="tur-coklu" id="tur-coklu">
      <?php
      $seciliTurler = $filtre['tur_id'] ?? null;
      $seciliTurler = is_array($seciliTurler)
          ? array_map('intval', $seciliTurler)
          : ($seciliTurler !== null && $seciliTurler !== '' ? [(int) $seciliTurler] : []);
      ?>
      <label class="onay" title="Filtreyi kaldır (tüm türler)">
        <input type="checkbox" name="tur_tumu" value="1" data-tur-tumu
               <?= $seciliTurler === [] ? 'checked' : '' ?>>
        <span>Tümü</span>
      </label>
      <?php foreach ($turler as $t): ?>
        <label class="onay">
          <input type="checkbox" name="tur_id[]" value="<?= (int) $t['id'] ?>" data-tur-kutu
                 <?= in_array((int) $t['id'], $seciliTurler, true) ? 'checked' : '' ?>>
          <span><?= esc($t['kisa_ad']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="form-grup">
    <label>Durum</label>
    <select name="durum" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($durumlar as $k => $v): ?>
        <option value="<?= $k ?>" <?= $filtre['durum'] === $k ? 'selected' : '' ?>><?= esc($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Defter Tipi</label>
    <select name="defter_tipi" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach (defterTipleri() as $dk => $dv): ?>
        <option value="<?= $dk ?>" <?= ($filtre['defter_tipi'] ?? '') === $dk ? 'selected' : '' ?>>
          <?= esc($dv) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php
  /*
   * MALİ MÜŞAVİR FİLTRESİ
   *
   * Birden fazla müşavire erişebilen kullanıcılar da (müşavir rolü, çok
   * müşavirli personel) aralarında süzebilmeli; bu yüzden koşul "admin"
   * değil, "seçilebilecek en az 2 müşavir var mı" olmalıdır.
   *
   * DİKKAT: $filtre['musavir_id'] kapsamBelirle()'den DİZİ olarak gelir
   * (örn. [2]). PHP'de boş olmayan bir diziyi (int)'e çevirmek HER ZAMAN 1
   * verir; eski kod bu yüzden seçim ne olursa olsun listedeki ilk müşaviri
   * "selected" gösteriyordu. Değer önce diziden çıkarılır.
   */
  $secMusavir = secilenMusavirId($filtre['musavir_id'] ?? null);
  ?>
  <?php if (count($musavirler) > 1): ?>
    <div class="form-grup">
      <label>Mali Müşavir</label>
      <select name="musavir_id" data-oto-filtre>
        <option value="">Tümü</option>
        <?php foreach ($musavirler as $mid => $mad): ?>
          <option value="<?= $mid ?>" <?= $secMusavir === (int) $mid ? 'selected' : '' ?>><?= esc($mad) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="form-grup" style="min-width:180px">
    <label>Ara</label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>" placeholder="Mükellef / VKN">
  </div>

  <div class="form-grup">
    <label class="onay" style="margin-top:18px">
      <input type="checkbox" name="gecikmis" value="1" <?= ! empty($filtre['gecikmis']) ? 'checked' : '' ?> data-oto-filtre>
      Sadece gecikmişler
    </label>
  </div>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url('takip') ?>" class="btn ikincil kucuk">Sıfırla</a>
    <?php
    // Dışa aktarma linkleri ekrandaki filtrenin AYNISINI taşımalı.
    // Not: filtre dizisinde anahtar 'tarih_modu', URL parametresi ise 'mod'.
    $disaAktar = array_filter((array) $filtre, static fn ($v) => $v !== null && $v !== '');
    unset($disaAktar['tarih_modu']);
    $disaAktar['mod'] = $mod;
    $qs = http_build_query($disaAktar);
    ?>
    <a href="<?= site_url('takip/excel?' . $qs) ?>" class="btn yesil kucuk">📊 Excel</a>
    <a href="<?= site_url('takip/yazdir?' . $qs) ?>" target="_blank" class="btn ikincil kucuk">🖨️ Yazdır</a>
  </div>
</form>

<?php if (! empty($filtre['defter_tipi'])): ?>
  <div class="uyari bilgi" style="padding:9px 14px;font-size:13px">
    <span class="ik">📒</span>
    <div>
      Yalnızca <b><?= esc(defterTipiAdi($filtre['defter_tipi'])) ?></b> tutan
      mükelleflerin beyannameleri listeleniyor.
      <a href="<?= site_url('takip?' . http_build_query(array_diff_key(
          array_filter((array) $filtre, static fn ($v) => $v !== null && $v !== '' && ! is_array($v)),
          ['defter_tipi' => 1, 'tarih_modu' => 1]
      ) + ['mod' => $mod])) ?>">Filtreyi kaldır</a>
    </div>
  </div>
<?php endif; ?>

<?php if (! empty($filtre['ay'])): ?>
  <div class="uyari bilgi" style="padding:9px 14px;font-size:13px">
    <span class="ik">ℹ</span>
    <div>
      <?php if ($mod === 'beyan'): ?>
        <b><?= ayAdi((int) $filtre['ay']) ?> <?= $filtre['yil'] ?></b> içinde
        <b>son günü dolan</b> beyannameler listeleniyor.
        Bu nedenle önceki yıla ait dönemler de görünebilir
        (örn. Nisan <?= $filtre['yil'] ?> → <?= (int) $filtre['yil'] - 1 ?> Kurumlar Vergisi).
      <?php else: ?>
        <b><?= $filtre['yil'] ?></b> yılına <b>ait</b> dönemler listeleniyor;
        son tarihleri izleyen yıla düşebilir
        (örn. <?= $filtre['yil'] ?> Kurumlar Vergisi → 30.04.<?= (int) $filtre['yil'] + 1 ?>).
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ============ ÖZET ============ -->
<?php
/*
 * Sayaçlar artık ekrandaki filtrenin (tür, ay, yıl, müşavir, defter tipi,
 * mükellef, arama) AYNISIYLA hesaplanır. Yalnızca "durum" ve "sadece
 * gecikmişler" hesaba katılmaz; böylece Bekliyor+Hazır+Onaylandı+Verilmeyecek
 * toplamı her zaman "Toplam" kartına eşit olur ve dağılım görünür kalır.
 *
 * Kartlar aynı zamanda birer filtre düğmesidir: tıklayınca o duruma süzer,
 * tekrar tıklayınca süzgeci kaldırır.
 */
$ozetTaban = array_filter(
    (array) $filtre,
    static fn ($v) => $v !== null && $v !== '' && ! is_array($v)
);
unset($ozetTaban['tarih_modu'], $ozetTaban['durum'], $ozetTaban['gecikmis']);
$ozetTaban['mod'] = $mod;

// Çoklu beyanname türü seçimi dizi olduğu için array_filter onu atlar;
// kart bağlantılarında korunması için açıkça geri eklenir.
if (! empty($filtre['tur_id']) && is_array($filtre['tur_id'])) {
    $ozetTaban['tur_id'] = $filtre['tur_id'];
}

// "Tüm Aylar" seçiliyken ay=null olur ve array_filter anahtarı siler.
// Adreste ay parametresi hiç yoksa ayBelirle() İÇİNDE BULUNULAN AYA döner;
// yani karta tıklayınca ay filtresi sessizce değişirdi. ay=0 açıkça yazılır.
$ozetTaban['ay'] = $filtre['ay'] ?? 0;

// musavir_id kapsamBelirle()'den dizi gelir; kart bağlantısında tek id lazım.
// Tam olarak bir müşavir seçiliyse adrese taşınır, aksi halde "Tümü" kalır.
$ozetMusavir = secilenMusavirId($filtre['musavir_id'] ?? null);

if ($ozetMusavir !== null) {
    $ozetTaban['musavir_id'] = $ozetMusavir;
} else {
    unset($ozetTaban['musavir_id']);
}

/** Kart bağlantısı üretir; kart zaten aktifse filtreyi kaldıran adresi verir */
$ozetBag = static function (array $ek) use ($ozetTaban) {
    $q = $ozetTaban;

    foreach ($ek as $k => $v) {
        if ($v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }

    return site_url('takip?' . http_build_query($q));
};

$aktifDurum   = $filtre['durum'] ?? '';
$aktifGecikme = ! empty($filtre['gecikmis']);
$suzguVar     = $aktifDurum !== '' || $aktifGecikme;

$kartlar = [
    ['anahtar' => '',             'sinif' => '',        'etiket' => 'Toplam',       'deger' => (int) ($ozet['toplam'] ?? 0),        'alt' => 'Filtreye uyan kayıt'],
    ['anahtar' => 'GECIKMIS',     'sinif' => 'kirmizi', 'etiket' => 'Gecikmiş',     'deger' => (int) ($ozet['gecikmis'] ?? 0),      'alt' => 'Süresi geçti'],
    ['anahtar' => 'BEKLIYOR',     'sinif' => 'turuncu', 'etiket' => 'Bekliyor',     'deger' => (int) ($ozet['bekliyor'] ?? 0),      'alt' => 'İşlem yapılmadı'],
    ['anahtar' => 'HAZIR',        'sinif' => 'sari',    'etiket' => 'Hazır',        'deger' => (int) ($ozet['hazir'] ?? 0),         'alt' => 'Onay bekliyor'],
    ['anahtar' => 'ONAYLANDI',    'sinif' => 'yesil',   'etiket' => 'Onaylandı',    'deger' => (int) ($ozet['onaylandi'] ?? 0),     'alt' => 'Tamamlandı'],
    ['anahtar' => 'VERILMEYECEK', 'sinif' => 'gri',     'etiket' => 'Verilmeyecek', 'deger' => (int) ($ozet['verilmeyecek'] ?? 0),  'alt' => 'Takip dışı'],
];
?>

<style>
/* Özet kartları filtre düğmesi olduğu için stil görünüm dosyasına gömüldü —
   stil.css kopyalanmasa bile tıklanabilirlik görsel olarak anlaşılsın. */
a.stat{text-decoration:none;color:inherit;display:block;cursor:pointer}
a.stat:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(0,0,0,.13)}
.stat.secili{box-shadow:0 0 0 2px var(--ana,#2563eb) inset,0 3px 10px rgba(0,0,0,.10)}
.stat.secili .etiket{color:var(--gri-900,#111827)}
.stat .kart-isaret{position:absolute;right:11px;top:10px;font-size:11px;font-weight:700;
  color:var(--ana,#2563eb);letter-spacing:.3px}
.stat.sonuk{opacity:.62}
.ozet-not{font-size:12.5px;color:var(--gri-500,#6b7280);margin:-8px 0 16px;display:flex;
  align-items:center;gap:8px;flex-wrap:wrap}
.ozet-not a{color:var(--ana,#2563eb);font-weight:600}

/* İndirim/kısıtlama rozetleri — stil.css kopyalanmasa bile doğru görünsün.
   Tür rozetinin altında ince bir şerit oluşturur; satır yüksekliğini
   bozmaması için yazı küçük ve dolgu dar tutuldu. */
.indirim-serit{display:flex;flex-wrap:wrap;gap:3px;margin-top:4px}
.rozet-indirim{padding:1px 6px;font-size:10px;font-weight:700;letter-spacing:.2px;cursor:help}
.rozet-indirim.mavi{background:var(--ana-acik,#dbeafe);color:var(--ana-koyu,#1d4ed8)}
.rozet-indirim.mor{background:var(--mor-acik,#ede9fe);color:var(--mor,#7c3aed)}
.rozet-indirim.turuncu{background:var(--turuncu-acik,#ffedd5);color:var(--turuncu,#ea580c)}
</style>

<div class="stat-grid">
  <?php foreach ($kartlar as $k): ?>
    <?php
    if ($k['anahtar'] === 'GECIKMIS') {
        $buAktif = $aktifGecikme;
        $adres   = $ozetBag($buAktif ? [] : ['gecikmis' => 1]);
    } elseif ($k['anahtar'] === '') {
        $buAktif = ! $suzguVar;
        $adres   = $ozetBag([]);
    } else {
        $buAktif = $aktifDurum === $k['anahtar'];
        $adres   = $ozetBag($buAktif ? [] : ['durum' => $k['anahtar']]);
    }

    $sinifTop = trim('stat ' . $k['sinif']
        . ($buAktif ? ' secili' : '')
        . ($suzguVar && ! $buAktif ? ' sonuk' : ''));
    ?>
    <a href="<?= $adres ?>" class="<?= $sinifTop ?>"
       title="<?= $buAktif ? 'Süzgeci kaldırmak için tıklayın' : esc($k['etiket']) . ' olanları listele' ?>">
      <div class="etiket"><?= esc($k['etiket']) ?></div>
      <div class="deger"><?= number_format($k['deger'], 0, ',', '.') ?></div>
      <div class="alt"><?= esc($k['alt']) ?></div>
      <?php if ($buAktif && $k['anahtar'] !== ''): ?><span class="kart-isaret">✕</span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($suzguVar): ?>
  <div class="ozet-not">
    <span>
      Kartlar <b><?= $mod === 'donem' ? 'dönem' : 'beyan' ?></b> filtresinin tamamını sayar;
      liste ise
      <b><?= $aktifGecikme ? 'gecikmiş' : esc($durumlar[$aktifDurum] ?? $aktifDurum) ?></b>
      olan <b><?= number_format((int) $toplamKayit, 0, ',', '.') ?></b> kaydı gösteriyor.
    </span>
    <a href="<?= $ozetBag([]) ?>">Süzgeci kaldır</a>
  </div>
<?php endif; ?>

<!-- ============ TOPLU İŞLEM ============ -->
<div id="toplu-islem-kutusu" class="uyari bilgi gizle">
  <span class="ik">☑</span>
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;width:100%">
    <b><span id="secili-sayi">0</span> kayıt seçildi</b>

    <!-- Sayfalama nedeniyle ekranda olmayan kayıtlar da seçilebilsin -->
    <span id="tum-filtre-alani" class="gizle">
      <a href="#" onclick="tumFiltreyiSec(event)" class="kalin">
        Filtredeki <b><?= number_format((int) $toplamKayit, 0, ',', '.') ?></b> kaydın hepsini seç
      </a>
    </span>
    <span id="tum-secili-rozet" class="rozet mor gizle">
      Filtredeki tüm kayıtlar seçili
      <a href="#" onclick="secimiTemizle(event)" style="color:inherit;text-decoration:underline">temizle</a>
    </span>

    <span class="metin-gri">→ Durumu değiştir:</span>
    <button class="btn sari mini" onclick="topluDurum('HAZIR')">Hazır</button>
    <button class="btn mini" onclick="topluDurum('ONAYLANDI')">Onaylandı</button>
    <button class="btn ikincil mini" onclick="topluDurum('BEKLIYOR')">Bekliyor</button>
    <button class="btn ikincil mini" onclick="topluDurum('VERILMEYECEK')">Verilmeyecek</button>
  </div>
</div>

<!-- ============ ÇİZELGE ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>📝 Beyanname Takip Çizelgesi</h2>
    <div class="sag" style="display:flex;align-items:center;gap:12px;margin-left:auto">
      <span class="kucuk-yazi">Durum hücresine tıklayarak değiştirebilirsiniz</span>
      <label class="kucuk-yazi" style="display:flex;align-items:center;gap:6px;white-space:nowrap">
        Sayfa başına
        <select id="adet-sec" class="girdi" style="padding:3px 6px;font-size:12px;width:auto"
                onchange="adetDegistir(this.value)">
          <?php foreach ($adetSecenek as $a): ?>
            <option value="<?= $a ?>" <?= (int) $sayfaAdedi === $a ? 'selected' : '' ?>><?= $a ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($kayitlar === []): ?>
      <div class="tablo-bos">
        <span class="ikon">📭</span>
        Kayıt bulunamadı.<br>
        <span class="kucuk-yazi">Mükellef ekleyip beyanname türlerini seçtiğinizde dönemler otomatik oluşur.</span>
        <div class="mt16"><a href="<?= site_url('takip/toplu-uret') ?>" class="btn kucuk">🔄 Toplu Dönem Üret</a></div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo" id="takip-tablosu">
          <thead>
            <tr>
              <th style="width:34px"><input type="checkbox" data-tumunu-sec=".satir-sec"></th>
              <th>Mükellef</th>
              <th>Beyanname</th>
              <th>Dönem</th>
              <th>Yasal Tarih</th>
              <th>Son Tarih</th>
              <th>Kalan</th>
              <th>Durum</th>
              <?php if (! empty($tahakkukYetki)): ?>
                <th class="sag" style="min-width:110px">Tahakkuk</th>
              <?php endif; ?>
              <th style="min-width:150px">Not</th>
            </tr>
          </thead>
          <tbody id="cizelge-govde">
          <?= $this->include('takip/_satirlar') ?>
          </tbody>
        </table>
      </div>

      <!-- ---------- SONSUZ KAYDIRMA ---------- -->
      <!-- Stil gömülü: stil.css kopyalanmasa bile gösterge doğru görünür -->
      <style>
      .kaydir-alani{
        padding:16px 20px;text-align:center;
        border-top:1px solid var(--gri-100, #f1f5f9);
        background:var(--gri-50, #f8fafc);
      }
      .kaydir-alani .kucuk-yazi{color:var(--gri-500, #64748b)}
      .donen{
        display:inline-block;width:14px;height:14px;vertical-align:-2px;
        border:2px solid var(--gri-300, #cbd5e1);
        border-top-color:var(--ana, #2563eb);
        border-radius:50%;animation:donusDon .7s linear infinite;margin-right:6px
      }
      @keyframes donusDon{to{transform:rotate(360deg)}}
      </style>
      <div id="kaydir-alani" class="kaydir-alani"
           data-ofset="<?= count($kayitlar) ?>"
           data-toplam="<?= (int) $toplamKayit ?>"
           data-adet="<?= (int) $sayfaAdedi ?>">

        <div id="kaydir-yukleniyor" class="gizle">
          <span class="donen"></span> Yükleniyor…
        </div>

        <button type="button" class="btn ikincil" id="daha-fazla-btn"
                onclick="dahaFazlaYukle()" <?= empty($dahaVar) ? 'style="display:none"' : '' ?>>
          ↓ Daha Fazla Yükle
        </button>

        <div class="kucuk-yazi mt8" id="kaydir-sayac">
          <b id="gosterilen-sayi"><?= count($kayitlar) ?></b> /
          <?= number_format((int) $toplamKayit, 0, ',', '.') ?> kayıt gösteriliyor
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ TAHAKKUK GİRİŞ MODALI ============ -->
<?php if (! empty($tahakkukYetki)): ?>
<div class="modal-arka" id="tahakkuk-modal">
  <div class="modal">
    <div class="modal-baslik">
      <h3>₺ Tahakkuk Tutarı</h3>
      <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
    </div>
    <div class="modal-govde">
      <div class="uyari bilgi" style="padding:10px 14px;font-size:13px">
        <span class="ik">ℹ</span>
        <div>
          Tutarı <b>damga vergisi HARİÇ</b> giriniz.
          Damga vergisi, <a href="<?= site_url('tanimlar/damga') ?>" target="_blank">tanımlardaki</a>
          sabit tutardan <b>ödeme listesinde otomatik eklenir</b>.
        </div>
      </div>

      <div id="th-gg" class="uyari basari mb16 gizle">
        <span class="ik">🌱</span><div id="th-gg-metin"></div>
      </div>

      <div id="th-bilgi" class="bilgi-liste mb16"></div>

      <input type="hidden" id="th-id">

      <div class="form-grid">
        <div class="form-grup">
          <label>Tahakkuk Tutarı (Damga Hariç)</label>
          <input type="text" id="th-tutar" class="girdi" inputmode="decimal"
                 placeholder="0,00" style="font-size:17px;font-weight:700;text-align:right">
        </div>
        <div class="form-grup">
          <label>Tahakkuk Fiş No</label>
          <input type="text" id="th-fis" class="girdi" placeholder="İsteğe bağlı">
        </div>
      </div>

      <?php /*
        MUHSGK ↔ SGK BİRLEŞİK GİRİŞ
        Sigortalı işçi çalıştıran mükelleflerde MUHSGK ile SGK birlikte
        verilir. Bu bölüm yalnızca eşleşen bir SGK kaydı olan MUHSGK
        satırlarında açılır; kullanıcı iki satırı ayrı ayrı dolaşmaz.
      */ ?>
      <div id="th-sgk-kutu" class="gizle" style="margin-top:14px;padding:12px 14px;
           background:#f0f9ff;border:1px solid #7dd3fc;border-radius:10px">
        <div style="font-weight:700;margin-bottom:3px">🤝 SGK Prim Bildirgesi</div>
        <div class="kucuk-yazi" style="margin-bottom:10px" id="th-sgk-aciklama"></div>

        <div class="form-grid">
          <div class="form-grup">
            <label>SGK Prim Tutarı</label>
            <input type="text" id="th-sgk-tutar" class="girdi" inputmode="decimal"
                   placeholder="0,00" style="font-size:16px;font-weight:700;text-align:right">
            <span class="yardim">Boş bırakırsanız SGK tarafı temizlenir.</span>
          </div>
          <div class="form-grup">
            <label>SGK Fiş No</label>
            <input type="text" id="th-sgk-fis" class="girdi" placeholder="İsteğe bağlı">
          </div>
        </div>

        <div id="th-sgk-uyari" class="kucuk-yazi gizle"
             style="margin-top:8px;color:var(--turuncu)"></div>
      </div>

      <div class="bolucu"></div>

      <div class="satir arali" style="font-size:14px">
        <span>Damga Vergisi <span class="kucuk-yazi">(sabit tanım)</span></span>
        <b id="th-damga" style="color:var(--turuncu)">0,00 ₺</b>
      </div>

      <?php /* SGK tutarı girildiğinde toplam ayrı satırda gösterilir */ ?>
      <div class="satir arali gizle" id="th-sgk-satir" style="font-size:14px">
        <span>SGK Primi <span class="kucuk-yazi">(birlikte kaydedilir)</span></span>
        <b id="th-sgk-goster" style="color:var(--ana-koyu)">0,00 ₺</b>
      </div>
      <div class="satir arali mt8" style="font-size:16px">
        <b>Ödenecek Toplam</b>
        <b id="th-toplam" style="color:var(--yesil);font-size:19px">0,00 ₺</b>
      </div>
    </div>
    <div class="modal-alt">
      <button type="button" class="btn kirmizi gizle" id="th-sil-btn"
              onclick="tahakkukSilTek(document.getElementById('th-id').value, true)"
              style="margin-right:auto">🗑 Tahakkuku Sil</button>
      <button type="button" class="btn ikincil" data-modal-kapat>İptal</button>
      <button type="button" class="btn yesil" onclick="tahakkukKaydet()">💾 Kaydet</button>
    </div>
  </div>
</div>

<!-- ==== DURUM GERİ ALINDIĞINDA TAHAKKUK SİLME ONAYI ==== -->
<div class="modal-arka" id="th-onay-modal">
  <div class="modal" style="max-width:470px">
    <div class="modal-baslik">
      <h3>⚠ Tahakkuk bilgisi ne olsun?</h3>
      <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
    </div>
    <div class="modal-govde">
      <div class="uyari dikkat mb16">
        <span class="ik">⚠</span>
        <div id="th-onay-metin"></div>
      </div>
      <p class="kucuk-yazi mb0">
        <b>Sil:</b> Tutar, damga ve fiş no tamamen temizlenir.<br>
        <b>Kalsın:</b> Bilgi korunur; çizelgede <span class="rozet gri">⚠ pasif</span> olarak soluk görünür
        ve durum yeniden <b>Onaylandı</b> yapılana kadar ödeme listesine girmez.
      </p>
      <input type="hidden" id="th-onay-idler">
    </div>
    <div class="modal-alt">
      <button type="button" class="btn ikincil" onclick="thOnayKapat()">Kalsın</button>
      <button type="button" class="btn kirmizi" onclick="thOnaySil()">🗑 Evet, Sil</button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php /*
  MUHSGK ONAYI GERİ ALINDIĞINDA SGK SORUSU

  Bu pencere tahakkuk yetkisinin DIŞINDADIR: durum bağlama personel
  rolünde de çalışır (tahakkuk girişi kapalı olsa bile onay geri alınır).
*/ ?>
<div class="modal-arka" id="es-geri-modal">
  <div class="modal" style="max-width:480px">
    <div class="modal-baslik">
      <h3>🤝 SGK kaydı da geri alınsın mı?</h3>
      <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
    </div>
    <div class="modal-govde">
      <div class="uyari dikkat mb16">
        <span class="ik">⚠</span>
        <div id="es-geri-metin"></div>
      </div>
      <p class="kucuk-yazi mb0">
        MUHSGK ile SGK aynı işlemin iki parçasıdır; genellikle birlikte
        geri alınır. <b>Dokunma</b> derseniz SGK <b>Onaylandı</b> kalır
        ve ödeme listesinde görünmeye devam eder.
      </p>
      <input type="hidden" id="es-geri-idler">
      <input type="hidden" id="es-geri-durum">
    </div>
    <div class="modal-alt">
      <button type="button" class="btn ikincil" onclick="esGeriKapat()">Dokunma</button>
      <button type="button" class="btn turuncu" onclick="esGeriUygula()">↩ Evet, Geri Al</button>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
// ---------- Durum değiştirme ----------
// Sonsuz kaydırmada sonradan eklenen satırlara da bağlanabilmesi için
// fonksiyon hâline getirildi (dataset.bagli ile ikinci kez bağlanmaz).
function durumSecBagla() {
document.querySelectorAll('.durum-sec').forEach(function (sel) {
  if (sel.dataset.bagli === '1') { return; }
  sel.dataset.bagli = '1';
  sel.dataset.eski = sel.value;
  sel.addEventListener('change', function () {
    var id = sel.dataset.id, yeni = sel.value;
    sel.disabled = true;

    BT.post('<?= site_url('takip/durum') ?>', { id: id, durum: yeni })
      .then(function (j) {
        BT.bildir(j.mesaj, 'basari');
        sel.dataset.eski = yeni;
        var tr = sel.closest('tr');
        if (yeni === 'ONAYLANDI' || yeni === 'VERILMEYECEK') {
          tr.classList.remove('gecikmis-satir');
        }

        // "Kalan" rozeti de durumla birlikte tazelenir; yoksa iş bittiği
        // hâlde "3 gün gecikti" yazmaya devam ediyordu.
        try { kalanRozetYenile(id, yeni); } catch (eK) { console.error(eK); }
        // Onaylandı seçilince tahakkuk girişi aç.
        // try/catch: pencerede beklenmedik bir sorun olsa bile durum
        // güncellemesi başarılı sayılır, kullanıcı akışı kesilmez.
        // ---- MUHSGK ↔ SGK bağı ----
        // Bu blok tahakkuk yetkisinden BAĞIMSIZDIR: durum bağlama
        // personel rolünde de çalışmalı (tahakkuk girişi kapalı olsa bile).
        try { esDurumIsle(j); } catch (eSgk) { console.error(eSgk); }

        <?php if (! empty($tahakkukYetki)): ?>
        try { thHucreYenile(id); } catch (e2) { console.error(e2); }

        if (yeni === 'ONAYLANDI') {
          try { tahakkukAc(id); }
          catch (hata) {
            console.error('Tahakkuk penceresi açılamadı:', hata);
            BT.bildir('Durum kaydedildi, tahakkuk penceresi açılamadı. Satırdaki ₺ düğmesini kullanın.', 'bilgi');
          }
        } else if (j.tahakkuk_kaldi) {
          // Onay geri alındı ama tahakkuk bilgisi duruyor → kullanıcıya sor
          try { thSilOnayiAc([id], j.tahakkuk_f, j.damga_f); }
          catch (hata) { console.error(hata); }
        }
        <?php endif; ?>
      })
      .catch(function (e) {
        BT.bildir(e.message, 'hata');
        sel.value = sel.dataset.eski;
      })
      .finally(function () { sel.disabled = false; });
  });
});
}

durumSecBagla();

/**
 * "Kalan" sütunundaki rozeti durum değişince yeniden çizer.
 *
 * Sunucudaki kalanGunMetni() ile AYNI kuralları uygular:
 *   ONAYLANDI                  → ✓ Verildi   (yeşil)
 *   VERILMEYECEK               → Takip dışı  (gri)
 *   diğer                      → gün sayısına göre geri sayım
 */
function kalanRozetYenile(id, durum) {
  var td = document.querySelector('.kalan-hucre[data-id="' + id + '"]');
  if (!td) { return; }                       // eski şablon: sessizce geç

  var gun = parseInt(td.dataset.gun, 10);
  if (isNaN(gun)) { return; }

  var metin, sinif;

  if (durum === 'ONAYLANDI') {
    metin = '✓ Verildi';  sinif = 'yesil';
  } else if (durum === 'VERILMEYECEK') {
    metin = 'Takip dışı'; sinif = 'gri';
  } else if (gun < 0) {
    metin = Math.abs(gun) + ' gün gecikti'; sinif = 'kirmizi';
  } else if (gun === 0) {
    metin = 'BUGÜN SON GÜN'; sinif = 'kirmizi';
  } else {
    metin = gun + ' gün kaldı';
    sinif = gun <= 3 ? 'turuncu' : (gun <= 7 ? 'sari' : 'yesil');
  }

  td.innerHTML = '<span class="rozet ' + sinif + '">' + metin + '</span>';

  // Geri alındığında gecikmiş vurgusu yeniden konur
  var tr = td.closest('tr');
  if (tr) {
    var gecikti = gun < 0 && durum !== 'ONAYLANDI' && durum !== 'VERILMEYECEK';
    tr.classList.toggle('gecikmis-satir', gecikti);
    tr.classList.toggle('bugun-satir',
      gun === 0 && durum !== 'ONAYLANDI' && durum !== 'VERILMEYECEK');
  }
}

// =================================================================
//  MUHSGK ↔ SGK DURUM BAĞI
//
//  MUHSGK onaylandığında sunucu eşleşen SGK satırını da onaylar;
//  burada yalnızca ekran tazelenir. Onay GERİ ALINDIĞINDA ise karar
//  kullanıcıya bırakılır: "SGK da geri alınsın mı?" penceresi açılır.
// =================================================================
function esDurumIsle(j) {
  if (!j || !j.es_var) { return; }

  // 1) Sunucu SGK'yı da onayladıysa satırları tazele
  if (j.es_guncellenen && j.es_guncellenen.length) {
    var degisen = 0;

    j.es_guncellenen.forEach(function (e) {
      if (esSatirDurumYaz(e.id, e.durum) && e.degisti) { degisen++; }
    });

    if (degisen > 0) {
      BT.bildir(degisen + ' SGK kaydı da onaylandı.', 'basari');
    }
  }

  // 2) SGK tek başına onaylandı, MUHSGK bekliyor → uyar (engelleme yok)
  if (j.es_uyari) {
    BT.bildir(j.es_uyari, 'bilgi');
  }

  // 3) Onay geri alındı → SGK için kullanıcıya sor
  if (j.es_geri_sor && j.es_geri_sor.kayitlar && j.es_geri_sor.kayitlar.length) {
    esGeriSorAc(j.es_geri_sor);
  }
}

/** Eş satırın durum menüsünü ekranda günceller */
function esSatirDurumYaz(esId, durum) {
  var sel = document.querySelector('.durum-sec[data-id="' + esId + '"]');
  if (!sel) { return false; }        // satır bu sayfada görünmüyor olabilir

  sel.value = durum;
  sel.dataset.eski = durum;

  var tr = sel.closest('tr');
  if (tr && (durum === 'ONAYLANDI' || durum === 'VERILMEYECEK')) {
    tr.classList.remove('gecikmis-satir');
  }

  // Eş (SGK) satırının "Kalan" rozeti de tazelenir
  try { kalanRozetYenile(esId, durum); } catch (e) { console.error(e); }

  // Tahakkuk hücresindeki "pasif" uyarısı durum değişince yeniden çizilir
  if (typeof thHucreYenile === 'function') {
    try { thHucreYenile(esId); } catch (e) { console.error(e); }
  }

  return true;
}

function esGeriSorAc(bilgi) {
  var m = document.getElementById('es-geri-modal');
  if (!m) { return; }

  var idler = bilgi.kayitlar.map(function (k) { return k.id; });
  document.getElementById('es-geri-idler').value = idler.join(',');
  document.getElementById('es-geri-durum').value = bilgi.durum;

  var liste = bilgi.kayitlar.map(function (k) {
    return '<li><b>' + k.tur + '</b> — ' + k.donem +
           (k.tutar_f ? ' <span class="kucuk-yazi">(' + k.tutar_f + ' ₺)</span>' : '') + '</li>';
  }).join('');

  document.getElementById('es-geri-metin').innerHTML =
    'MUHSGK onayı kaldırıldı. Bu beyanname ile birlikte verilen ' +
    '<b>' + bilgi.kayitlar.length + ' SGK kaydı</b> hâlâ <b>Onaylandı</b> durumunda:' +
    '<ul style="margin:8px 0 0 18px">' + liste + '</ul>';

  BT.modalAc('es-geri-modal');
}

function esGeriKapat() {
  BT.modalKapat('es-geri-modal');
  BT.bildir('SGK kayıtlarına dokunulmadı.', 'bilgi');
}

function esGeriUygula() {
  var ham = document.getElementById('es-geri-idler').value;
  var durum = document.getElementById('es-geri-durum').value;
  var idler = ham ? ham.split(',') : [];

  if (!idler.length) { BT.modalKapat('es-geri-modal'); return; }

  BT.post('<?= site_url('takip/es-durum') ?>', { idler: idler, durum: durum })
    .then(function (j) {
      BT.bildir(j.mesaj, 'basari');
      (j.esler || []).forEach(function (e) { esSatirDurumYaz(e.id, e.durum); });
      BT.modalKapat('es-geri-modal');
    })
    .catch(function (e) { BT.bildir(e.message, 'hata'); });
}

// ---------- Not düzenleme ----------
function notDuzenle(td) {
  if (td.querySelector('input')) return;

  var id      = td.dataset.id;
  var span    = td.querySelector('.not-metin');
  var mevcut  = span.classList.contains('not-bos') ? '' : span.textContent.replace('📌 ', '').trim();

  var inp = document.createElement('input');
  inp.type = 'text';
  inp.className = 'girdi';
  inp.value = mevcut;
  inp.style.cssText = 'padding:4px 7px;font-size:12px';
  inp.maxLength = 500;

  td.innerHTML = '';
  td.appendChild(inp);
  inp.focus();
  inp.select();

  function kaydet() {
    var yeni = inp.value.trim();
    BT.post('<?= site_url('takip/not') ?>', { id: id, not: yeni })
      .then(function () {
        BT.bildir('Not kaydedildi.', 'basari');
        yaz(yeni);
      })
      .catch(function (e) {
        BT.bildir(e.message, 'hata');
        yaz(mevcut);
      });
  }

  function yaz(deger) {
    td.classList.toggle('dolu', deger !== '');
    td.innerHTML = deger !== ''
      ? '<span class="not-metin">📌 ' + deger.replace(/</g, '&lt;') + '</span>'
      : '<span class="not-metin not-bos">+ not ekle</span>';
  }

  inp.addEventListener('blur', kaydet);
  inp.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); inp.blur(); }
    if (e.key === 'Escape') { inp.removeEventListener('blur', kaydet); yaz(mevcut); }
  });
}

// ---------- Tahakkuk girişi ----------
<?php if (empty($tahakkukYetki)): ?>
function tahakkukAc() {}
<?php else: ?>
var TH_SATIR = {};
<?php foreach ($kayitlar as $k): ?>
<?php
$ggBilgi = gencGirisimciDurum($k, (int) $k['yil']);
// Uyarı yalnızca gelir/geçici vergi türlerinde gösterilir
$ggIlgili = in_array($k['tur_kodu'], ['YILLIK_GV', 'GELIR_GECICI'], true);
?>
TH_SATIR[<?= $k['id'] ?>] = {
  mukellef: <?= json_encode($k['mukellef_unvan'], JSON_UNESCAPED_UNICODE) ?>,
  tur: <?= json_encode($k['tur_kisa'], JSON_UNESCAPED_UNICODE) ?>,
  gg: <?= $ggBilgi['var'] && $ggIlgili ? 'true' : 'false' ?>,
  ggGecerli: <?= $ggBilgi['gecerli'] ? 'true' : 'false' ?>,
  ggMetin: <?= json_encode($ggBilgi['metin'], JSON_UNESCAPED_UNICODE) ?>,
  ggNot: <?= json_encode($k['gg_not'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
  ggAralik: <?= json_encode($ggBilgi['baslangic'] !== null
      ? $ggBilgi['baslangic'] . ' – ' . $ggBilgi['bitis'] : '') ?>,
  donem: <?= json_encode($k['donem_adi'], JSON_UNESCAPED_UNICODE) ?>,
  sonTarih: <?= json_encode(trTarih($k['son_tarih']), JSON_UNESCAPED_UNICODE) ?>,
  tutar: <?= $k['tahakkuk_tutari'] !== null ? json_encode(number_format((float) $k['tahakkuk_tutari'], 2, ',', '.')) : "''" ?>,
  fis: <?= json_encode($k['tahakkuk_fis_no'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
  damga: <?= (float) $k['damga_tutari'] ?>,
  <?php
  /*
   * MUHSGK ↔ SGK eşleşmesi. 'ana' rolündeki (MUHSGK) satırlarda tahakkuk
   * penceresi SGK alanını da açar; 'bagli' (SGK) satırlarda yalnızca
   * bilgilendirme yapılır.
   */
  $esK = $esHarita[(int) $k['id']] ?? null;
  ?>
  es: <?= $esK === null ? 'null' : json_encode([
      'rol'   => $esK['rol'],
      'esler' => array_map(static fn ($e) => [
          'id'    => (int) $e['id'],
          'tur'   => $e['tur_kisa'],
          'donem' => $e['donem_adi'],
          'durum' => $e['durum'],
          'tutar' => $e['tahakkuk_tutari'] === null
              ? '' : number_format((float) $e['tahakkuk_tutari'], 2, ',', '.'),
          'fis'   => $e['tahakkuk_fis_no'] ?? '',
          'damga' => (float) $e['damga_tutari'],
          'turId' => (int) $e['beyanname_turu_id'],
      ], $esK['esler']),
  ], JSON_UNESCAPED_UNICODE) ?>
};
<?php endforeach; ?>

// Türlerin bu yıla ait sabit damga tutarları
var DAMGA_TANIM = <?= json_encode($damgaTanim ?? [], JSON_UNESCAPED_UNICODE) ?>;
var TUR_ID = {};
<?php foreach ($kayitlar as $k): ?>
TUR_ID[<?= $k['id'] ?>] = <?= (int) $k['beyanname_turu_id'] ?>;
<?php endforeach; ?>

function paraCoz(m) {
  if (!m) return 0;
  m = String(m).replace(/\s/g, '');
  if (m.indexOf(',') > -1) { m = m.replace(/\./g, '').replace(',', '.'); }
  var s = parseFloat(m);
  return isNaN(s) ? 0 : s;
}
function paraYaz(s) {
  return Number(s).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ---------- Tahakkuk hücresi çizimi ----------
// Hücre içeriği TEK yerden üretilir; durum değişince de tahakkuk kaydedilince de
// aynı fonksiyon çağrılır. Böylece ekrandaki tutar her zaman gerçeği gösterir.
function thSatirDurum(id) {
  var s = document.querySelector('.durum-sec[data-id="' + id + '"]');
  return s ? s.value : '';
}

function thHucreYenile(id) {
  var td = document.querySelector('.tahakkuk-hucre[data-id="' + id + '"]');
  if (!td) return;

  var d     = TH_SATIR[id] || {};
  var tutar = d.tutar || '';
  var damga = Number(d.damga || 0);
  var atil  = tutar !== '' && thSatirDurum(id) !== 'ONAYLANDI';

  var html = tutar
    ? '<b class="tahakkuk-deger">' + tutar + '</b>'
    : '<span class="kucuk-yazi metin-gri tahakkuk-deger">—</span>';

  if (tutar && damga > 0) {
    html += '<div class="kucuk-yazi damga-satiri">+' + paraYaz(damga) + ' damga</div>';
  }

  if (atil) {
    html += '<div class="kucuk-yazi atil-not" title="Durum &quot;Onaylandı&quot; olmadığı için '
          + 'bu tutar ödeme listesine girmez.">⚠ pasif</div>';
  }

  html += '<button type="button" class="btn ikincil mini" style="margin-top:3px" '
        + 'onclick="tahakkukAc(' + id + ')">₺</button>';

  td.innerHTML = html;
  td.classList.toggle('atil', atil);
}

// ---------- Tahakkuk silme onayı ----------
function thSilOnayiAc(idler, tutarF, damgaF) {
  var m = document.getElementById('th-onay-modal');
  if (!m) { return; }

  document.getElementById('th-onay-idler').value = idler.join(',');

  var metin;
  if (idler.length === 1) {
    metin = 'Bu kayıt için daha önce <b>' + (tutarF || '0,00') + ' ₺</b> tahakkuk girilmişti'
          + (damgaF && damgaF !== '0,00' ? ' (+' + damgaF + ' ₺ damga)' : '') + '.<br>'
          + 'Durum artık <b>Onaylandı</b> değil. Tahakkuk bilgisi silinsin mi?';
  } else {
    metin = '<b>' + idler.length + '</b> kayıtta girilmiş tahakkuk bilgisi var ve durumları '
          + 'artık <b>Onaylandı</b> değil. Bu tahakkuklar silinsin mi?';
  }

  document.getElementById('th-onay-metin').innerHTML = metin;
  BT.modalAc('th-onay-modal');
}

function thOnayKapat() {
  BT.modalKapat('th-onay-modal');
  BT.bildir('Tahakkuk bilgisi korundu, "pasif" olarak işaretlendi.', 'bilgi');

  if (window.__thTopluYenile) {
    window.__thTopluYenile = false;
    setTimeout(function () { location.reload(); }, 600);
  }
}

function thOnaySil() {
  var ham = document.getElementById('th-onay-idler').value;
  var idler = ham ? ham.split(',') : [];
  if (!idler.length) { BT.modalKapat('th-onay-modal'); return; }

  BT.post('<?= site_url('takip/tahakkuk-sil') ?>', { idler: idler })
    .then(function (j) {
      BT.bildir(j.mesaj, 'basari');
      (j.idler || []).forEach(function (id) {
        if (TH_SATIR[id]) { TH_SATIR[id].tutar = ''; TH_SATIR[id].fis = ''; TH_SATIR[id].damga = 0; }
        thHucreYenile(id);
      });
      BT.modalKapat('th-onay-modal');

      if (window.__thTopluYenile) {
        window.__thTopluYenile = false;
        setTimeout(function () { location.reload(); }, 600);
      }
    })
    .catch(function (e) { BT.bildir(e.message, 'hata'); });
}

/** Modal içindeki "Tahakkuku Sil" düğmesi */
function tahakkukSilTek(id, sor) {
  if (sor && !confirm('Bu kaydın tahakkuk tutarı, damgası ve fiş numarası silinecek. Onaylıyor musunuz?')) {
    return;
  }

  BT.post('<?= site_url('takip/tahakkuk-sil') ?>', { id: id })
    .then(function (j) {
      BT.bildir(j.mesaj, 'basari');
      if (TH_SATIR[id]) { TH_SATIR[id].tutar = ''; TH_SATIR[id].fis = ''; TH_SATIR[id].damga = 0; }
      thHucreYenile(id);
      BT.modalKapat('tahakkuk-modal');
    })
    .catch(function (e) { BT.bildir(e.message, 'hata'); });
}

function tahakkukAc(id) {
  var d = TH_SATIR[id];
  if (!d) return;

  // Modal DOM'da yoksa sessizce çık — durum güncellemesi zaten yapıldı.
  if (!document.getElementById('tahakkuk-modal')) {
    BT.bildir('Tahakkuk penceresi bulunamadı. Sayfayı yenileyin (Ctrl+F5).', 'hata');
    return;
  }

  document.getElementById('th-id').value = id;
  document.getElementById('th-tutar').value = d.tutar || '';
  document.getElementById('th-fis').value = d.fis || '';

  // "Tahakkuku Sil" düğmesi yalnızca kayıtlı bir tutar varsa görünür
  var silBtn = document.getElementById('th-sil-btn');
  if (silBtn) { silBtn.className = 'btn kirmizi' + (d.tutar ? '' : ' gizle'); }

  // Kayıtlı damga yoksa tanımdan oku
  var damga = d.damga > 0 ? d.damga : (DAMGA_TANIM[TUR_ID[id]] || 0);
  document.getElementById('th-modal-damga') && 0;
  window.__thDamga = damga;

  // Genç girişimci uyarısı (yalnızca gelir/geçici vergide)
  // Not: Eleman bulunamazsa (eski şablon, kısmi güncelleme vb.) hata fırlatmak
  // yerine kutuyu kendimiz oluştururuz; tahakkuk girişi her hâlükârda açılmalı.
  var ggKutu = document.getElementById('th-gg');

  if (!ggKutu) {
    var govde = document.querySelector('#tahakkuk-modal .modal-govde');
    if (govde) {
      ggKutu = document.createElement('div');
      ggKutu.id = 'th-gg';
      ggKutu.className = 'uyari mb16 gizle';
      ggKutu.innerHTML = '<span class="ik">🌱</span><div id="th-gg-metin"></div>';
      govde.insertBefore(ggKutu, govde.firstChild);
    }
  }

  if (ggKutu) {
    var ggMetin = document.getElementById('th-gg-metin');

    if (d.gg) {
      // Sınıf ve görünürlük birlikte ayarlanır (gizle sınıfı kaldırılır)
      ggKutu.className = 'uyari ' + (d.ggGecerli ? 'basari' : 'dikkat') + ' mb16';
      ggKutu.style.display = 'flex';

      var h = '<b>Genç Girişimci Kazanç İstisnası</b> — ' + d.ggMetin;
      if (d.ggAralik) h += '<br>Geçerlilik: <b>' + d.ggAralik + '</b>';
      if (d.ggNot) h += '<br><span class="kucuk-yazi">' + d.ggNot.replace(/</g, '&lt;') + '</span>';
      if (!d.ggGecerli) h += '<br><b>Dikkat:</b> İstisna süresi dolmuş, tutarı buna göre hesaplayın.';
      else h += '<br><span class="kucuk-yazi">Tahakkuk tutarını istisnayı uygulayarak giriniz.</span>';

      if (ggMetin) { ggMetin.innerHTML = h; }
    } else {
      ggKutu.className = 'uyari mb16 gizle';
      ggKutu.style.display = '';
    }
  }

  document.getElementById('th-bilgi').innerHTML =
    '<div class="oge"><div class="et">Mükellef</div><div class="dg">' + d.mukellef + '</div></div>' +
    '<div class="oge"><div class="et">Beyanname</div><div class="dg">' + d.tur + '</div></div>' +
    '<div class="oge"><div class="et">Dönem</div><div class="dg">' + d.donem + '</div></div>' +
    '<div class="oge"><div class="et">Son Tarih</div><div class="dg">' + d.sonTarih + '</div></div>';

  thSgkHazirla(id, d);
  thHesapla();
  BT.modalAc('tahakkuk-modal');
}

/**
 * MUHSGK ↔ SGK bölümünü pencereye yerleştirir.
 *
 *  • MUHSGK satırı  → SGK prim tutarı alanı AÇILIR (tek ekrandan giriş)
 *  • SGK satırı     → yalnızca "MUHSGK ile bağlı" bilgisi gösterilir
 *  • Eşleşme yoksa  → bölüm hiç görünmez
 */
function thSgkHazirla(id, d) {
  var kutu = document.getElementById('th-sgk-kutu');
  if (!kutu) { return; }          // eski şablon: sessizce geç

  var tutarAlan = document.getElementById('th-sgk-tutar');
  var fisAlan   = document.getElementById('th-sgk-fis');
  var aciklama  = document.getElementById('th-sgk-aciklama');
  var uyari     = document.getElementById('th-sgk-uyari');

  window.__thSgk = null;
  kutu.className = 'gizle';
  if (uyari) { uyari.className = 'kucuk-yazi gizle'; }

  var es = d.es;
  if (!es || !es.esler || !es.esler.length) { thSgkGoster(); return; }

  if (es.rol !== 'ana') {
    // SGK satırı: kullanıcıyı MUHSGK'ya yönlendir
    kutu.className = '';
    kutu.style.background = '#f8fafc';
    kutu.style.borderColor = '#e2e8f0';
    if (tutarAlan) { tutarAlan.closest('.form-grid').style.display = 'none'; }
    if (aciklama) {
      aciklama.innerHTML = 'Bu kayıt <b>' + es.esler[0].tur + '</b> (' + es.esler[0].donem +
        ') ile birlikte verilir. İki tutarı <b>tek ekrandan</b> girmek için '
        + 'MUHSGK satırındaki ₺ düğmesini kullanabilirsiniz.';
    }
    thSgkGoster();
    return;
  }

  // MUHSGK satırı: SGK girişini aç
  var hedef = es.esler[0];
  window.__thSgk = hedef;

  kutu.className = '';
  kutu.style.background = '#f0f9ff';
  kutu.style.borderColor = '#7dd3fc';
  if (tutarAlan) {
    tutarAlan.closest('.form-grid').style.display = '';
    tutarAlan.value = hedef.tutar || '';
  }
  if (fisAlan) { fisAlan.value = hedef.fis || ''; }

  if (aciklama) {
    var m = 'Eşleşen kayıt: <b>' + hedef.tur + '</b> — ' + hedef.donem +
            '. Tutarı buraya girin, <b>ayrı satıra gitmenize gerek yok</b>.';
    if (es.esler.length > 1) {
      m += '<br><b>Not:</b> Bu dönemde ' + es.esler.length + ' SGK satırı var; '
         + 'tutar ilkine (' + hedef.donem + ') yazılır, diğerlerini ayrıca giriniz.';
    }
    aciklama.innerHTML = m;
  }

  thSgkGoster();
}

/** SGK tutarını alt toplam satırında gösterir */
function thSgkGoster() {
  var satir = document.getElementById('th-sgk-satir');
  var alan  = document.getElementById('th-sgk-tutar');
  if (!satir || !alan) { return; }

  var aktif = window.__thSgk && paraCoz(alan.value) > 0;
  satir.className = 'satir arali' + (aktif ? '' : ' gizle');

  if (aktif) {
    document.getElementById('th-sgk-goster').textContent = paraYaz(paraCoz(alan.value)) + ' ₺';
  }
}

function thHesapla() {
  var t = paraCoz(document.getElementById('th-tutar').value);
  var d = window.__thDamga || 0;

  // SGK primi girilmişse ödenecek toplama O DA eklenir; kullanıcı
  // "bu ay toplam ne ödeyeceğim" sorusunun yanıtını tek yerde görür.
  var sgkAlan = document.getElementById('th-sgk-tutar');
  var sgk = (window.__thSgk && sgkAlan) ? paraCoz(sgkAlan.value) : 0;

  document.getElementById('th-damga').textContent = paraYaz(d) + ' ₺';
  document.getElementById('th-toplam').textContent = paraYaz(t + d + sgk) + ' ₺';

  thSgkGoster();
}

document.getElementById('th-tutar').addEventListener('input', thHesapla);

(function () {
  var sgkAlan = document.getElementById('th-sgk-tutar');
  if (!sgkAlan) { return; }
  sgkAlan.addEventListener('input', thHesapla);
  sgkAlan.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); tahakkukKaydet(); }
  });
})();
document.getElementById('th-tutar').addEventListener('keydown', function (e) {
  if (e.key === 'Enter') { e.preventDefault(); tahakkukKaydet(); }
});

function tahakkukKaydet() {
  var id = document.getElementById('th-id').value;
  var tutar = document.getElementById('th-tutar').value.trim();
  var fis = document.getElementById('th-fis').value.trim();

  var veri = { id: id, tutar: tutar, fis_no: fis };

  // SGK alanı yalnızca MUHSGK satırında gönderilir. Gönderilmezse
  // sunucu eş kayda hiç dokunmaz (eski davranış korunur).
  var sgkAlan = document.getElementById('th-sgk-tutar');
  var sgkFis  = document.getElementById('th-sgk-fis');

  if (window.__thSgk && sgkAlan) {
    veri.sgk_tutar   = sgkAlan.value.trim();
    veri.sgk_fis_no  = sgkFis ? sgkFis.value.trim() : '';
  }

  BT.post('<?= site_url('odeme/tahakkuk') ?>', veri)
    .then(function (j) {
      BT.bildir(j.mesaj, 'basari');
      if (!TH_SATIR[id]) { TH_SATIR[id] = {}; }
      TH_SATIR[id].tutar = j.tutar_f;
      TH_SATIR[id].fis = j.tutar_f ? fis : '';
      TH_SATIR[id].damga = j.damga;

      thHucreYenile(id);

      // ---- SGK tarafı ----
      if (j.sgk) {
        if (j.sgk.yazildi) {
          thEsSatirTazele(j.sgk.id, j.sgk.tutar_f, j.sgk.damga);
          thEsVeriTazele(id, j.sgk.id, j.sgk.tutar_f, j.sgk.damga,
                         veri.sgk_fis_no || '');

          if (!j.sgk.silindi) {
            BT.bildir('SGK primi de kaydedildi: ' + j.sgk.tutar_f + ' ₺', 'basari');
          }

          if (j.sgk.kalan > 0) {
            BT.bildir('Bu dönemde ' + j.sgk.kalan + ' SGK satırı daha var, '
                    + 'onları ayrıca giriniz.', 'bilgi');
          }
        } else if (j.sgk.neden) {
          BT.bildir('SGK tarafı yazılamadı: ' + j.sgk.neden, 'hata');
        }
      }

      // Tutar girildiği hâlde durum "Onaylandı" değilse kullanıcıyı uyar:
      // bu tutar ödeme listesine girmez.
      if (j.tutar_f && thSatirDurum(id) !== 'ONAYLANDI') {
        BT.bildir('Tutar kaydedildi ancak durum "Onaylandı" değil — ödeme listesine girmez.', 'bilgi');
      }

      BT.modalKapat('tahakkuk-modal');
    })
    .catch(function (e) { BT.bildir(e.message, 'hata'); });
}

/** Eş (SGK) satırının tahakkuk hücresini ekranda tazeler */
function thEsSatirTazele(esId, tutarF, damga) {
  if (!TH_SATIR[esId]) { TH_SATIR[esId] = {}; }
  TH_SATIR[esId].tutar = tutarF || '';
  TH_SATIR[esId].damga = Number(damga || 0);
  thHucreYenile(esId);
}

/** MUHSGK satırının belleğindeki SGK kopyasını günceller */
function thEsVeriTazele(anaId, esId, tutarF, damga, fis) {
  var d = TH_SATIR[anaId];
  if (!d || !d.es || !d.es.esler) { return; }

  d.es.esler.forEach(function (e) {
    if (Number(e.id) === Number(esId)) {
      e.tutar = tutarF || '';
      e.damga = Number(damga || 0);
      e.fis   = fis || '';
    }
  });
}

<?php endif; ?>

// ================= SONSUZ KAYDIRMA =================
var KAYDIR = {
  alan:     document.getElementById('kaydir-alani'),
  yukleniyor: false,
  bitti:    false
};

function kaydirDurum() {
  if (!KAYDIR.alan) return null;
  return {
    ofset:  parseInt(KAYDIR.alan.dataset.ofset, 10) || 0,
    toplam: parseInt(KAYDIR.alan.dataset.toplam, 10) || 0
  };
}

/** Mevcut filtreyi koruyarak sonraki parçayı ister */
function dahaFazlaYukle() {
  if (!KAYDIR.alan || KAYDIR.yukleniyor || KAYDIR.bitti) return;

  var d = kaydirDurum();
  if (d.ofset >= d.toplam) { kaydirBitir(); return; }

  KAYDIR.yukleniyor = true;
  document.getElementById('kaydir-yukleniyor').className = '';
  document.getElementById('daha-fazla-btn').style.display = 'none';

  // Adres çubuğundaki filtreler aynen taşınır
  var p = new URLSearchParams(window.location.search);
  p.set('ofset', d.ofset);
  p.set('adet', KAYDIR.alan.dataset.adet);

  fetch('<?= site_url('takip/daha-fazla') ?>?' + p.toString(), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (!j.durum) { throw new Error(j.mesaj || 'Kayıtlar yüklenemedi.'); }

      // Satırları ekle
      var gecici = document.createElement('tbody');
      gecici.innerHTML = j.html;

      var govde = document.getElementById('cizelge-govde');
      while (gecici.firstChild) { govde.appendChild(gecici.firstChild); }

      // Yeni satırların tahakkuk verisini JS tarafına aktar
      <?php if (! empty($tahakkukYetki)): ?>
      Object.keys(j.satirVeri || {}).forEach(function (id) {
        var v = j.satirVeri[id];
        TH_SATIR[id] = v;
        TUR_ID[id] = v.turId;
      });
      <?php endif; ?>

      // Yeni durum açılır listelerine olay bağla
      durumSecBagla();

      KAYDIR.alan.dataset.ofset  = j.ofset;
      KAYDIR.alan.dataset.toplam = j.toplam;
      document.getElementById('gosterilen-sayi').textContent = j.ofset;

      KAYDIR.yukleniyor = false;
      document.getElementById('kaydir-yukleniyor').className = 'gizle';

      if (!j.dahaVar || j.yuklenen === 0) {
        kaydirBitir();
      } else {
        document.getElementById('daha-fazla-btn').style.display = '';
      }

      // "Tümü seçili" modundaysa yeni satırlar da işaretlensin
      if (window.__tumFiltreSecili) {
        govde.querySelectorAll('.satir-sec').forEach(function (c) { c.checked = true; });
        BT.secimGuncelle();
      }
    })
    .catch(function (e) {
      KAYDIR.yukleniyor = false;
      document.getElementById('kaydir-yukleniyor').className = 'gizle';
      document.getElementById('daha-fazla-btn').style.display = '';
      BT.bildir(e.message, 'hata');
    });
}

function kaydirBitir() {
  KAYDIR.bitti = true;
  var btn = document.getElementById('daha-fazla-btn');
  if (btn) btn.style.display = 'none';
  var s = document.getElementById('kaydir-sayac');
  if (s) s.innerHTML = '<b>Tüm kayıtlar gösterildi</b> (' +
    (kaydirDurum().toplam).toLocaleString('tr-TR') + ' kayıt)';
}

// Sayfa sonuna yaklaşınca kendiliğinden yükle
if (KAYDIR.alan && 'IntersectionObserver' in window) {
  var gozlemci = new IntersectionObserver(function (girisler) {
    if (girisler[0].isIntersecting) { dahaFazlaYukle(); }
  }, { rootMargin: '300px' });
  gozlemci.observe(KAYDIR.alan);
}

/** Sayfa başına kayıt adedi değiştir (çerezde saklanır) */
function adetDegistir(deger) {
  document.cookie = 'bt_sayfa_adedi=' + encodeURIComponent(deger) +
                    ';path=/;max-age=' + (60 * 60 * 24 * 365) + ';SameSite=Lax';
  var p = new URLSearchParams(window.location.search);
  p.set('adet', deger);
  window.location.search = p.toString();
}

// ---------- Filtredeki tüm kayıtları seçme ----------
window.__tumFiltreSecili = false;
window.__tumFiltreIdler  = null;

function tumFiltreyiSec(e) {
  if (e) e.preventDefault();

  var p = new URLSearchParams(window.location.search);

  BT.post('<?= site_url('takip/tum-idler') ?>?' + p.toString(), {})
    .then(function (j) {
      window.__tumFiltreIdler  = j.idler;
      window.__tumFiltreSecili = true;

      document.querySelectorAll('.satir-sec').forEach(function (c) { c.checked = true; });
      BT.secimGuncelle();

      document.getElementById('secili-sayi').textContent = j.adet;
      document.getElementById('tum-filtre-alani').className = 'gizle';
      document.getElementById('tum-secili-rozet').className = 'rozet mor';

      BT.bildir(j.mesaj, 'basari');
    })
    .catch(function (e2) { BT.bildir(e2.message, 'hata'); });
}

function secimiTemizle(e) {
  if (e) e.preventDefault();
  window.__tumFiltreSecili = false;
  window.__tumFiltreIdler  = null;
  document.querySelectorAll('.satir-sec').forEach(function (c) { c.checked = false; });
  var t = document.querySelector('[data-tumunu-sec]');
  if (t) t.checked = false;
  document.getElementById('tum-secili-rozet').className = 'rozet mor gizle';
  BT.secimGuncelle();
}

// Ekrandaki tüm satırlar seçiliyken ve sayfada daha fazla kayıt varsa
// "filtredeki hepsini seç" bağlantısını göster
(function () {
  var eskiGuncelle = BT.secimGuncelle;

  BT.secimGuncelle = function () {
    eskiGuncelle();

    var alan = document.getElementById('tum-filtre-alani');
    if (!alan) return;

    var tumSatir = document.querySelectorAll('.satir-sec').length;
    var secili   = document.querySelectorAll('.satir-sec:checked').length;
    var d        = kaydirDurum();

    if (window.__tumFiltreSecili) {
      // Kullanıcı seçimi bozduysa "tümü" modundan çık
      if (secili !== tumSatir) {
        window.__tumFiltreSecili = false;
        window.__tumFiltreIdler  = null;
        document.getElementById('tum-secili-rozet').className = 'rozet mor gizle';
      } else {
        document.getElementById('secili-sayi').textContent =
          (window.__tumFiltreIdler || []).length;
        alan.className = 'gizle';

        return;
      }
    }

    var gizle = !(secili > 0 && secili === tumSatir && d && d.toplam > tumSatir);
    alan.className = gizle ? 'gizle' : '';
  };
})();

// ---------- Toplu durum ----------
function topluDurum(durum) {
  // "Filtredeki hepsi" seçiliyse ekranda olmayan kayıtlar da dahil edilir
  var idler = window.__tumFiltreSecili && window.__tumFiltreIdler
    ? window.__tumFiltreIdler
    : BT.seciliIdler();

  if (!idler.length) { BT.bildir('Hiç kayıt seçilmedi.', 'hata'); return; }
  if (!confirm(idler.length + ' kaydın durumu değiştirilecek. Onaylıyor musunuz?')) return;

  BT.post('<?= site_url('takip/toplu-durum') ?>', { idler: idler, durum: durum })
    .then(function (j) {
      BT.bildir(j.mesaj, 'basari');

      // Onay geri alındıysa ve tahakkuk bilgisi duran kayıtlar varsa önce sor
      <?php if (! empty($tahakkukYetki)): ?>
      if (j.tahakkuk_kalanlar && j.tahakkuk_kalanlar.length) {
        window.__thTopluYenile = true;
        thSilOnayiAc(j.tahakkuk_kalanlar, '', '');
        return;
      }
      <?php endif; ?>

      setTimeout(function () { location.reload(); }, 700);
    })
    .catch(function (e) { BT.bildir(e.message, 'hata'); });
}
</script>

<script>
// Beyanname türü ÇOKLU seçim: "Tümü" ↔ tür kutuları çelişkisini yönetir,
// her değişimde formu otomatik gönderir (diğer filtrelerle aynı davranış).
(function () {
  var grup = document.getElementById('tur-coklu');
  if (!grup) return;

  var tumu   = grup.querySelector('[data-tur-tumu]');
  var kutular = grup.querySelectorAll('[data-tur-kutu]');

  function gonder() { if (grup.form) grup.form.submit(); }

  if (tumu) {
    tumu.addEventListener('change', function () {
      if (tumu.checked) {
        kutular.forEach(function (k) { k.checked = false; });
      }
      gonder();
    });
  }

  kutular.forEach(function (k) {
    k.addEventListener('change', function () {
      if (k.checked && tumu) tumu.checked = false;
      gonder();
    });
  });
})();
</script>
<?= $this->endSection() ?>
