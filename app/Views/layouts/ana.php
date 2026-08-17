<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-ad" content="<?= csrf_token() ?>">
<meta name="csrf-token" content="<?= csrf_hash() ?>">
<title><?= esc($sayfaBasligi ?? 'Beyanname Takip') ?> — Beyanname Takip</title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
<style>
/* Menü rozeti — ajanda gecikmiş/bugün sayısı (stil.css'ten bağımsız) */
.menu-rozet{display:inline-block;min-width:18px;padding:1px 6px;border-radius:99px;
  background:#dc2626;color:#fff;font-size:10.5px;font-weight:700;
  text-align:center;margin-left:auto}
</style>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📋</text></svg>">
</head>
<body>

<?php
$rol   = $aktifKullanici['rol'] ?? 'personel';
$adSoyad = $aktifKullanici['ad_soyad'] ?? '';
$basHarf = mb_strtoupper(mb_substr($adSoyad, 0, 1));
$aktifUrl = trim(uri_string(), '/');
?>

<!-- ================= YAN MENÜ ================= -->
<aside class="yan-menu">
  <div class="logo">
    <div class="logo-ikon">📋</div>
    <div class="logo-yazi">
      <b>Beyanname Takip</b>
      <span>Mükellef Yönetim Sistemi</span>
    </div>
  </div>

  <nav class="menu-liste">
    <div class="menu-baslik">Genel</div>
    <a href="<?= site_url('panel') ?>" class="<?= aktifMenu('panel') ?>">
      <span class="ikon">📊</span> Kontrol Paneli
    </a>
    <a href="<?= site_url('ajanda') ?>" class="<?= aktifMenu('ajanda') ?>">
      <span class="ikon">🗓️</span> Ajanda
      <?php if (! empty($ajandaRozet)): ?>
        <span class="menu-rozet" title="Gecikmiş + bugünkü işler"><?= (int) $ajandaRozet ?></span>
      <?php endif; ?>
    </a>

    <div class="menu-baslik">Takip</div>
    <a href="<?= site_url('takip') ?>" class="<?= aktifMenu('takip') ?>">
      <span class="ikon">📝</span> Beyanname Takip
    </a>
    <a href="<?= site_url('evrak') ?>" class="<?= aktifMenu('evrak') ?>">
      <span class="ikon">📁</span> Evrak Takip
    </a>
    <a href="<?= site_url('edefter') ?>" class="<?= aktifMenu('edefter') ?>">
      <span class="ikon">📗</span> E-Defter Takip
    </a>
    <?php if (in_array($rol, ['admin', 'musavir'], true)): ?>
      <a href="<?= site_url('odeme') ?>" class="<?= $aktifUrl === 'odeme' ? 'aktif' : '' ?>">
        <span class="ikon">💰</span> Ödeme Listesi
      </a>
      <a href="<?= site_url('odeme/listeler') ?>" class="<?= aktifMenu('odeme/liste') ?>">
        <span class="ikon">📑</span> Ödeme Listelerim
      </a>
      <a href="<?= site_url('makbuz') ?>" class="<?= aktifMenu('makbuz') ?>">
        <span class="ikon">🧾</span> Makbuz Takip
      </a>
      <a href="<?= site_url('gelir-vergisi') ?>" class="<?= aktifMenu('gelir-vergisi') ?>">
        <span class="ikon">🧮</span> Vergi Yükü
      </a>
    <?php endif; ?>
    <a href="<?= site_url('karsit') ?>" class="<?= aktifMenu('karsit') ?>">
      <span class="ikon">🔍</span> Karşıt İnceleme
    </a>

    <div class="menu-baslik">Kayıtlar</div>
    <a href="<?= site_url('mukellefler') ?>" class="<?= aktifMenu('mukellefler') ?>">
      <span class="ikon">🏢</span> Mükellefler
    </a>
    <?php if (in_array($rol, ['admin', 'musavir'], true)): ?>
      <a href="<?= site_url('musavirler') ?>" class="<?= aktifMenu('musavirler') ?>">
        <span class="ikon">👨‍💼</span> Mali Müşavirler
      </a>
    <?php endif; ?>

    <div class="menu-baslik">Raporlar</div>
    <a href="<?= site_url('raporlar/gecikmis') ?>" class="<?= aktifMenu('raporlar/gecikmis') ?>">
      <span class="ikon">⏰</span> Gecikmiş Beyannameler
    </a>
    <a href="<?= site_url('raporlar/mukellef-ozet') ?>" class="<?= aktifMenu('raporlar/mukellef-ozet') ?>">
      <span class="ikon">📈</span> Mükellef Özeti
    </a>
    <?php if ($rol === 'admin'): ?>
      <a href="<?= site_url('raporlar/musavir-performans') ?>" class="<?= aktifMenu('raporlar/musavir') ?>">
        <span class="ikon">🎯</span> Müşavir Performansı
      </a>
    <?php endif; ?>

    <?php if (in_array($rol, ['admin', 'musavir'], true)): ?>
      <div class="menu-baslik">Tanımlar</div>
      <a href="<?= site_url('tanimlar/beyanname-turleri') ?>" class="<?= aktifMenu('tanimlar/beyanname') ?>">
        <span class="ikon">🗂️</span> Beyanname Türleri
      </a>
      <a href="<?= site_url('tanimlar/damga') ?>" class="<?= aktifMenu('tanimlar/damga') ?>">
        <span class="ikon">🧾</span> Damga Vergisi Tutarları
      </a>
      <a href="<?= site_url('tanimlar/evrak-turleri') ?>" class="<?= aktifMenu('tanimlar/evrak') ?>">
        <span class="ikon">📄</span> Evrak Türleri
      </a>
      <a href="<?= site_url('tanimlar/edefter-adimlari') ?>" class="<?= aktifMenu('tanimlar/edefter') ?>">
        <span class="ikon">📗</span> E-Defter Adımları
      </a>
      <a href="<?= site_url('tanimlar/tatiller') ?>" class="<?= aktifMenu('tanimlar/tatil') ?>">
        <span class="ikon">🎌</span> Resmi Tatiller
      </a>
      <a href="<?= site_url('tanimlar/ayarlar') ?>" class="<?= aktifMenu('tanimlar/ayarlar') ?>">
        <span class="ikon">⚙️</span> Ayarlar
      </a>
    <?php endif; ?>

    <?php if ($rol === 'admin'): ?>
      <div class="menu-baslik">Sistem</div>
      <a href="<?= site_url('kullanicilar') ?>" class="<?= aktifMenu('kullanicilar') ?>">
        <span class="ikon">👥</span> Kullanıcılar
      </a>
      <a href="<?= site_url('takip/toplu-uret') ?>" class="<?= aktifMenu('takip/toplu') ?>">
        <span class="ikon">🔄</span> Toplu Dönem Üret
      </a>
      <a href="<?= site_url('sistem/yedekleme') ?>" class="<?= aktifMenu('sistem/yedekleme') ?>">
        <span class="ikon">💾</span> Yedekleme
      </a>
      <a href="<?= site_url('sistem/veri-yonetimi') ?>" class="<?= aktifMenu('sistem/veri-yonetimi') ?>">
        <span class="ikon">🧹</span> Veri Yönetimi
      </a>
      <a href="<?= site_url('sistem/cop-kutusu') ?>" class="<?= aktifMenu('sistem/cop-kutusu') ?>">
        <span class="ikon">🗑️</span> Çöp Kutusu
      </a>
    <?php endif; ?>
  </nav>

  <div class="yan-alt">
    <a href="<?= site_url('profil') ?>" class="kullanici" style="text-decoration:none">
      <div class="avatar"><?= esc($basHarf) ?></div>
      <div>
        <b><?= esc(kisalt($adSoyad, 18)) ?></b>
        <span><?= ['admin' => 'Yönetici', 'musavir' => 'Mali Müşavir', 'personel' => 'Personel'][$rol] ?? $rol ?></span>
      </div>
    </a>
    <a href="<?= site_url('cikis') ?>" style="display:flex;align-items:center;gap:9px;padding:8px 10px;color:#fca5a5;font-size:13px;font-weight:600">
      <span class="ikon">🚪</span> Çıkış Yap
    </a>
  </div>
</aside>
<div class="karartma"></div>

<!-- ================= ANA ALAN ================= -->
<div class="ana">
  <header class="ust-bar">
    <button class="menu-ac" type="button" aria-label="Menü">☰</button>
    <h1><?= esc($sayfaBasligi ?? '') ?></h1>
    <div class="bugun">
      <span>📅</span>
      <span><?= trTarihUzun(date('Y-m-d')) ?></span>
    </div>
  </header>

  <main class="icerik">
    <?php if (session()->getFlashdata('basari')): ?>
      <div class="uyari basari"><span class="ik">✓</span><div><?= esc(session()->getFlashdata('basari')) ?></div></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('hata')): ?>
      <div class="uyari hata"><span class="ik">✕</span><div><?= esc(session()->getFlashdata('hata')) ?></div></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('bilgi')): ?>
      <div class="uyari bilgi"><span class="ik">ℹ</span><div><?= esc(session()->getFlashdata('bilgi')) ?></div></div>
    <?php endif; ?>

    <?php if ($hatalar = session()->getFlashdata('hatalar')): ?>
      <div class="uyari hata">
        <span class="ik">✕</span>
        <div>
          <b>Lütfen aşağıdaki hataları düzeltin:</b>
          <ul><?php foreach ((array) $hatalar as $h): ?><li><?= esc($h) ?></li><?php endforeach; ?></ul>
        </div>
      </div>
    <?php endif; ?>

    <?= $this->renderSection('icerik') ?>
  </main>
</div>

<div id="bildirimler"></div>

<!-- ============ AJANDA GİRİŞ UYARISI ============
     Günde bir kez, bugünkü ve gecikmiş işleri gösterir.
     Pencere kapatılınca sunucuya "okundu" yazılır; aynı gün tekrar çıkmaz. -->
<div id="aj-uyari-ort" class="aj-uyari-ort" style="display:none">
  <div class="aj-uyari">
    <div class="aj-uyari-bas">
      <span style="font-size:22px">🗓️</span>
      <h3>Bugünkü İşleriniz</h3>
    </div>
    <div class="aj-uyari-govde" id="aj-uyari-liste"></div>
    <div class="aj-uyari-alt">
      <a href="<?= site_url('ajanda') ?>" class="btn ikincil kucuk">Ajandayı Aç</a>
      <button type="button" class="btn kucuk" id="aj-uyari-kapat">Anladım</button>
    </div>
  </div>
</div>

<style>
.aj-uyari-ort{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9998;
  display:flex;align-items:center;justify-content:center;padding:20px}
.aj-uyari{background:#fff;border-radius:14px;max-width:560px;width:100%;
  max-height:80vh;overflow:auto;box-shadow:0 20px 50px rgba(0,0,0,.3)}
.aj-uyari-bas{padding:16px 20px;border-bottom:1px solid #e2e8f0;
  display:flex;align-items:center;gap:10px}
.aj-uyari-bas h3{margin:0;font-size:16px}
.aj-uyari-govde{padding:8px 20px 16px}
.aj-uyari-alt{padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;gap:10px;
  justify-content:flex-end;background:#f8fafc;border-radius:0 0 14px 14px}
.aj-uyari-is{display:flex;align-items:center;gap:10px;padding:9px 0;
  border-bottom:1px solid #f1f5f9}
.aj-uyari-is:last-child{border-bottom:0}
.aj-uyari-nokta{width:10px;height:10px;border-radius:50%;flex:0 0 10px}
.aj-uyari-is .ad{flex:1;font-size:13.5px}
.aj-uyari-is .ad small{display:block;color:#64748b;font-size:11.5px}
.aj-uyari-gec{background:#dc2626;color:#fff;padding:1px 7px;border-radius:99px;
  font-size:10.5px;font-weight:700}
</style>

<script>
(function () {
  'use strict';

  var ort = document.getElementById('aj-uyari-ort');
  if (!ort) { return; }

  var CSRF_AD  = <?= json_encode(csrf_token()) ?>;
  var CSRF_DEG = <?= json_encode(csrf_hash()) ?>;

  function kapat() {
    ort.style.display = 'none';

    var g = new URLSearchParams();
    g.append(CSRF_AD, CSRF_DEG);

    fetch('<?= site_url('ajanda/uyari-okundu') ?>', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: g
    }).catch(function () { /* sessiz geç */ });
  }

  document.getElementById('aj-uyari-kapat').addEventListener('click', kapat);
  ort.addEventListener('click', function (e) { if (e.target === ort) { kapat(); } });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ort.style.display !== 'none') { kapat(); }
  });

  fetch('<?= site_url('ajanda/giris-uyarisi') ?>', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(function (r) { return r.json(); })
    .then(function (v) {
      if (!v.durum || !v.goster || !v.isler || !v.isler.length) { return; }

      var kutu = document.getElementById('aj-uyari-liste');
      kutu.innerHTML = '';

      v.isler.forEach(function (i) {
        var satir = document.createElement('div');
        satir.className = 'aj-uyari-is';

        var nokta = document.createElement('span');
        nokta.className = 'aj-uyari-nokta';
        nokta.style.background = i.renk;

        var ad = document.createElement('div');
        ad.className = 'ad';

        var bag = document.createElement('a');
        bag.href = '<?= site_url('ajanda/detay/') ?>' + i.id;
        bag.textContent = i.baslik;
        ad.appendChild(bag);

        var alt = document.createElement('small');
        alt.textContent = i.tarih + (i.saat ? ' · ' + i.saat : '')
                        + (i.mukellef ? ' · ' + i.mukellef : '');
        ad.appendChild(alt);

        satir.appendChild(nokta);
        satir.appendChild(ad);

        if (i.gecikmis) {
          var rz = document.createElement('span');
          rz.className = 'aj-uyari-gec';
          rz.textContent = 'gecikmiş';
          satir.appendChild(rz);
        }

        kutu.appendChild(satir);
      });

      ort.style.display = 'flex';
    })
    .catch(function () { /* ajanda kurulu değilse sessiz geç */ });
}());
</script>

<script src="<?= base_url('assets/js/uygulama.js') ?>"></script>
<?= $this->renderSection('script') ?>
</body>
</html>
