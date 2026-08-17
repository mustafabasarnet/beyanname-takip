<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
/**
 * GERİYE DÖNÜK UYUMLULUK
 *
 * Bu görünüm yeni değişkenler bekler (dönem kaydırma, sorumlu personel,
 * sayfalama). Controller dosyası kopyalanmadıysa sayfa "Undefined variable"
 * hatası verirdi; aşağıdaki varsayılanlar bunu önler ve program eski
 * davranışıyla çalışmaya devam eder.
 *
 * Not: Tam işlevsellik için app/Controllers/Evrak.php de güncellenmelidir.
 */
// Controller güncel mi? (yeni sürüm bu değişkeni her zaman gönderir)
$eskiSurum = ! isset($secilenYil);

$secilenYil  = $secilenYil  ?? $yil;
$secilenAy   = $secilenAy   ?? $ay;
$kaydirma    = $kaydirma    ?? 0;
$personeller = $personeller ?? [];
$toplamKayit = $toplamKayit ?? count($mukellefler);
$sayfaAdedi  = $sayfaAdedi  ?? max(1, $toplamKayit);
$adetSecenek = $adetSecenek ?? [25, 50, 100, 250];
$dahaVar     = $dahaVar     ?? false;
$muafiyet    = $muafiyet    ?? [];
$muafHucre   = (int) ($muafHucre ?? 0);
$filtre      = $filtre      ?? [];
$filtre     += ['musavir_id' => null, 'q' => null, 'sorumlu_kullanici_id' => null];

// Muafiyet şeması kurulu mu? Değilse sağ tık menüsü uyarı gösterir.
$muafKurulu = db_connect()->tableExists('mukellef_evrak_muafiyet');
?>

<?php if ($eskiSurum): ?>
  <div class="uyari dikkat mb16">
    <span class="ik">⚠</span>
    <div>
      <b>Eksik güncelleme:</b> <code>app/Controllers/Evrak.php</code> dosyası
      sunucuya kopyalanmamış. Sayfa çalışıyor ancak <b>dönem kaydırma</b>,
      <b>sorumlu personel filtresi</b> ve <b>sayfalama</b> devre dışı.
      Dosyayı kopyalayıp <b>Ctrl+F5</b> yapın.
    </div>
  </div>
<?php endif; ?>

<!-- ============ FİLTRE ============ -->
<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Yıl</label>
    <select name="yil" data-oto-filtre>
      <?php foreach (yilSecenekleri() as $y): ?>
        <option value="<?= $y ?>" <?= $y === $secilenYil ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Ay <span class="kucuk-yazi" style="font-weight:400">(toplama ayı)</span></label>
    <select name="ay" data-oto-filtre>
      <?php for ($a = 1; $a <= 12; $a++): ?>
        <option value="<?= $a ?>" <?= $a === $secilenAy ? 'selected' : '' ?>><?= ayAdi($a) ?></option>
      <?php endfor; ?>
    </select>
  </div>

  <?php if (($aktifKullanici['rol'] ?? '') === 'admin'): ?>
    <div class="form-grup">
      <label>Mali Müşavir</label>
      <select name="musavir_id" data-oto-filtre>
        <option value="">Tümü</option>
        <?php foreach ($musavirler as $mid => $mad): ?>
          <option value="<?= $mid ?>" <?= secilenMusavirId($filtre['musavir_id'] ?? null) === (int) $mid ? 'selected' : '' ?>>
            <?= esc($mad) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <?php if (! empty($personeller)): ?>
    <div class="form-grup">
      <label>Sorumlu Personel</label>
      <select name="sorumlu_kullanici_id" data-oto-filtre>
        <option value="">Tümü</option>
        <?php foreach ($personeller as $pid => $pad): ?>
          <option value="<?= (int) $pid ?>"
            <?= (int) ($filtre['sorumlu_kullanici_id'] ?? 0) === (int) $pid ? 'selected' : '' ?>>
            <?= esc($pad) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="form-grup" style="min-width:170px">
    <label>Mükellef Ara <span class="kucuk-yazi" style="font-weight:400">(sunucuda)</span></label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>"
           placeholder="Ünvan, VKN, kod...">
  </div>

  <?php
  // Filtreleri koruyan sorgu dizesi (Excel / Yazdır / sayfalama için)
  $qs = http_build_query(array_filter([
      'yil'                  => $secilenYil,
      'ay'                   => $secilenAy,
      'musavir_id'           => is_array($filtre['musavir_id'] ?? null) ? null : ($filtre['musavir_id'] ?? null),
      'sorumlu_kullanici_id' => $filtre['sorumlu_kullanici_id'] ?? null,
      'q'                    => $filtre['q'] ?? null,
  ], static fn ($v) => $v !== null && $v !== ''));
  ?>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url('evrak') ?>" class="btn ikincil kucuk">Sıfırla</a>
    <a href="<?= site_url('evrak/excel?' . $qs) ?>" class="btn yesil kucuk">📊 Excel</a>
    <a href="<?= site_url('evrak/yazdir?' . $qs) ?>" target="_blank" class="btn ikincil kucuk">🖨️ Yazdır</a>
  </div>
</form>

<!-- Dönem açıklaması: hangi ayın evrakları gösteriliyor? -->
<?php if ((int) $kaydirma > 0): ?>
  <div class="uyari bilgi mb16" style="padding:9px 14px;font-size:13px">
    <span class="ik">🗓️</span>
    <div>
      <b><?= ayAdi($secilenAy) ?> <?= $secilenYil ?></b> ayında topladığınız
      <b><?= ayAdi($ay) ?> <?= $yil ?> dönemi</b> evrakları gösteriliyor.
      <span class="kucuk-yazi">
        (Kaydırmayı <a href="<?= site_url('tanimlar/ayarlar') ?>">Ayarlar → <code>evrak_donem_kaydirma</code></a>
        ile değiştirebilirsiniz; 0 yaparsanız seçilen ay = evrak dönemi olur.)
      </span>
    </div>
  </div>
<?php endif; ?>

<!-- ============ ÖZET ============ -->
<?php
// Sayfalama nedeniyle count($mukellefler) yalnızca ilk parçayı verir;
// sayaçlar filtreye uyan TÜM mükellefler üzerinden hesaplanmalı.
//
// TAKİP DIŞI HÜCRELER TOPLAMDAN DÜŞÜLÜR: bankası olmayan mükellefin banka
// sütunu "bekleyen" sayılmaz, yüzde de gerçek beklenen evrak üzerinden çıkar.
$hamHucre    = (int) $toplamKayit * count($turler);
$toplamHucre = max(0, $hamHucre - $muafHucre);
$gelen       = (int) ($ozet['GELDI'] ?? 0);
$oran        = $toplamHucre > 0 ? round($gelen / $toplamHucre * 100) : 0;
?>
<div class="stat-grid">
  <div class="stat"><div class="etiket">Faal Mükellef</div><div class="deger"><?= number_format((int) $toplamKayit, 0, ',', '.') ?></div>
    <div class="alt"><?= ayAdi($ay) ?> <?= $yil ?> döneminde</div></div>
  <div class="stat yesil"><div class="etiket">Gelen Evrak</div><div class="deger"><?= $gelen ?></div>
    <div class="alt"><?= $toplamHucre ?> hücrenin <?= $oran ?>%'i</div></div>
  <div class="stat kirmizi"><div class="etiket">Bekleyen</div><div class="deger"><?= max(0, $toplamHucre - $gelen) ?></div>
    <div class="alt">Henüz gelmedi</div></div>
  <?php if ($muafHucre > 0): ?>
    <div class="stat"><div class="etiket">Takip Dışı</div>
      <div class="deger" id="muaf-sayac"><?= number_format($muafHucre, 0, ',', '.') ?></div>
      <div class="alt">Mükellefte bulunmayan evrak</div></div>
  <?php endif; ?>
</div>

<!-- ============ ÇİZELGE ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>📁 <?= ayAdi($ay) ?> <?= $yil ?> Dönemi Evrak Çizelgesi</h2>
    <div class="sag kucuk-yazi" style="display:flex;align-items:center;gap:10px;margin-left:auto">
      <span class="rozet yesil">✓ Geldi</span>
      <span class="rozet kirmizi">✕ Gelmedi</span>
      <span class="rozet gri" title="Bu mükellefte böyle bir evrak yok — sayaçlara girmez">— Takip dışı</span>
      <span>Sol tık: geldi/gelmedi · Sağ tık: takip dışı</span>
      <?php if (! $eskiSurum): ?>
        <label style="display:flex;align-items:center;gap:6px;white-space:nowrap">
          Sayfa başına
          <select id="adet-sec" class="girdi" style="padding:3px 6px;font-size:12px;width:auto"
                  onchange="adetDegistir(this.value)">
            <?php foreach ($adetSecenek as $a): ?>
              <option value="<?= $a ?>" <?= (int) $sayfaAdedi === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
    </div>
  </div>

  <div class="kart-govde sikisik">
    <?php if ($mukellefler === []): ?>
      <div class="tablo-bos">
        <span class="ikon">📭</span>
        Bu dönemde faal mükellef bulunamadı.<br>
        <span class="kucuk-yazi">İşe başlama tarihi bu aydan sonra olan veya bu aydan önce terk eden mükellefler listelenmez.</span>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="matris" id="evrak-tablosu">
          <thead>
            <tr>
              <th class="sol-sabit">Mükellef</th>
              <?php foreach ($turler as $t): ?>
                <th title="<?= esc($t['ad']) ?>"><?= esc($t['kisa_ad']) ?></th>
              <?php endforeach; ?>
              <th style="min-width:70px">Tümü</th>
              <th style="min-width:170px">Aylık Not</th>
            </tr>
          </thead>
          <tbody id="evrak-govde">
          <?= $this->include('evrak/_satirlar') ?>
          </tbody>
        </table>
      </div>

      <!-- ---------- SONSUZ KAYDIRMA ---------- -->
      <?php if (! $eskiSurum): ?>
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
           data-ofset="<?= count($mukellefler) ?>"
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
          <b id="gosterilen-sayi"><?= count($mukellefler) ?></b> /
          <?= number_format((int) $toplamKayit, 0, ',', '.') ?> mükellef gösteriliyor
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php /* ---------- SAĞ TIK MENÜSÜ: TAKİP DIŞI BIRAKMA ---------- */ ?>
<style>
.evrak-menu{
  position:absolute;z-index:300;display:none;min-width:262px;
  background:#fff;border:1px solid var(--gri-200,#e2e8f0);border-radius:10px;
  box-shadow:0 10px 30px rgba(15,23,42,.18);padding:6px;font-size:13px
}
.evrak-menu.acik{display:block}
.evrak-menu .baslik{
  padding:7px 10px 8px;border-bottom:1px solid var(--gri-100,#f1f5f9);
  margin-bottom:4px;color:var(--gri-500,#64748b);font-size:11.5px;line-height:1.45
}
.evrak-menu .baslik b{color:var(--gri-800,#1e293b);font-size:12.5px}
.evrak-menu button{
  display:block;width:100%;text-align:left;background:none;border:0;
  padding:8px 10px;border-radius:7px;cursor:pointer;font:inherit;color:var(--gri-700,#334155)
}
.evrak-menu button:hover{background:var(--gri-50,#f8fafc)}
.evrak-menu button .ac{display:block;font-size:11px;color:var(--gri-500,#64748b);margin-top:1px}
.evrak-menu button.etkin{background:var(--ana-acik,#eff6ff);color:var(--ana-koyu,#1d4ed8);font-weight:600}
.evrak-menu .ayrac{height:1px;background:var(--gri-100,#f1f5f9);margin:4px 2px}
</style>

<div class="evrak-menu" id="evrak-menu">
  <div class="baslik" id="evrak-menu-baslik"></div>
  <button type="button" onclick="menuSecim('donem')" id="menu-donem"></button>
  <button type="button" onclick="menuSecim('kalici')" id="menu-kalici"></button>
  <div class="ayrac"></div>
  <button type="button" onclick="menuSecim('kapat')">Vazgeç</button>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
// YIL/AY = EVRAK DÖNEMİ (kayıtların yazıldığı ay).
// Filtredeki ay "toplama ayı"dır; kaydırma sunucuda hesaplanır.
var YIL = <?= (int) $yil ?>, AY = <?= (int) $ay ?>;

// ================= SONSUZ KAYDIRMA =================
var KAYDIR = {
  alan: document.getElementById('kaydir-alani'),
  yukleniyor: false,
  bitti: false
};

function kaydirDurum() {
  if (!KAYDIR.alan) return null;
  return {
    ofset:  parseInt(KAYDIR.alan.dataset.ofset, 10) || 0,
    toplam: parseInt(KAYDIR.alan.dataset.toplam, 10) || 0
  };
}

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

  fetch('<?= site_url('evrak/daha-fazla') ?>?' + p.toString(), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (!j.durum) { throw new Error(j.mesaj || 'Kayıtlar yüklenemedi.'); }

      var gecici = document.createElement('tbody');
      gecici.innerHTML = j.html;

      var govde = document.getElementById('evrak-govde');
      while (gecici.firstChild) { govde.appendChild(gecici.firstChild); }

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
  if (s) s.innerHTML = '<b>Tüm mükellefler gösterildi</b> (' +
    (kaydirDurum().toplam).toLocaleString('tr-TR') + ' mükellef)';
}

// Sayfa sonuna yaklaşınca kendiliğinden yükle
if (KAYDIR.alan && 'IntersectionObserver' in window) {
  var gozlemci = new IntersectionObserver(function (girisler) {
    if (girisler[0].isIntersecting) { dahaFazlaYukle(); }
  }, { rootMargin: '300px' });
  gozlemci.observe(KAYDIR.alan);
}

/** Sayfa başına mükellef adedi (çerezde saklanır) */
function adetDegistir(deger) {
  document.cookie = 'bt_evrak_adedi=' + encodeURIComponent(deger) +
                    ';path=/;max-age=' + (60 * 60 * 24 * 365) + ';SameSite=Lax';
  var p = new URLSearchParams(window.location.search);
  p.set('adet', deger);
  window.location.search = p.toString();
}

// Muafiyet şeması kurulu mu? (migration_evrak_muafiyet.sql)
var MUAF_KURULU = <?= $muafKurulu ? 'true' : 'false' ?>;

/**
 * Hücreyi verilen duruma göre yeniden çizer.
 * durum: GELDI | GELMEDI | YOK      kalici: kalıcı muafiyet var mı
 */
function hucreCiz(td, durum, kalici) {
  td.dataset.durum = durum;

  if (kalici !== undefined && kalici !== null) {
    td.dataset.kalici = kalici ? '1' : '0';
  }

  var kal = td.dataset.kalici === '1';
  td.dataset.donemsel = (durum === 'YOK' && !kal) ? '1' : '0';

  if (durum === 'GELDI') {
    td.className = 'evrak-hucre geldi';
    td.textContent = '✓';
  } else if (durum === 'YOK') {
    td.className = 'evrak-hucre yok' + (kal ? ' kalici' : '');
    td.textContent = '—';
  } else {
    td.className = 'evrak-hucre gelmedi';
    td.textContent = '✕';
  }

  var ad = td.dataset.turAd || '';
  td.title = ad + ' — ' + (
    durum === 'GELDI' ? 'Geldi'
      : durum === 'YOK' ? (kal ? 'Bu mükellefte yok (kalıcı)' : 'Bu dönem takip dışı')
        : 'Gelmedi'
  ) + ' — sağ tık: takip dışı seçenekleri';
}

/** Takip dışı sayacını canlı güncelle (kart yalnızca sayı > 0 iken vardır) */
function muafSayacDegis(fark) {
  var el = document.getElementById('muaf-sayac');
  if (!el) return;
  var s = parseInt(el.textContent.replace(/\./g, ''), 10) || 0;
  el.textContent = Math.max(0, s + fark).toLocaleString('tr-TR');
}

// ---------- Tek hücre Geldi/Gelmedi ----------
function evrakDegistir(td) {
  // Takip dışı hücre sol tıkla açılmaz; yanlışlıkla "geldi" yazılmasın diye
  // önce sağ tık menüsünden takibe alınması gerekir.
  if (td.dataset.durum === 'YOK') {
    BT.bildir('Bu hücre takip dışı. Geri almak için sağ tıklayın.', 'bilgi');
    return;
  }

  var yeni = td.dataset.durum === 'GELDI' ? 'GELMEDI' : 'GELDI';

  BT.post('<?= site_url('evrak/durum') ?>', {
    mukellef_id: td.dataset.mukellef,
    evrak_turu_id: td.dataset.tur,
    yil: YIL, ay: AY, durum: yeni
  }).then(function () {
    hucreCiz(td, yeni);
  }).catch(function (e) { BT.bildir(e.message, 'hata'); });
}

// ---------- Satırın tümünü işaretle ----------
function tumunu(mukellefId, durum) {
  BT.post('<?= site_url('evrak/tumu') ?>', {
    mukellef_id: mukellefId, yil: YIL, ay: AY, durum: durum
  }).then(function (j) {
    BT.bildir(j.mesaj, 'basari');
    document.querySelectorAll('[data-mukellef="' + mukellefId + '"].evrak-hucre').forEach(function (td) {
      // Kalıcı muaf hücreler "tümü geldi" işleminden etkilenmez
      if (td.dataset.kalici === '1') { hucreCiz(td, 'YOK'); return; }
      hucreCiz(td, durum);
    });
  }).catch(function (e) { BT.bildir(e.message, 'hata'); });
}

// ================= TAKİP DIŞI (SAĞ TIK) =================
var MENU = { td: null, el: null };

function evrakMenu(olay, td) {
  olay.preventDefault();

  if (!MUAF_KURULU) {
    BT.bildir('Bu özellik için veritabanı güncellemesi gerekli: migration_evrak_muafiyet.sql', 'hata');
    return false;
  }

  MENU.td = td;
  MENU.el = MENU.el || document.getElementById('evrak-menu');

  var kalici   = td.dataset.kalici === '1';
  var donemsel = td.dataset.donemsel === '1';

  document.getElementById('evrak-menu-baslik').innerHTML =
    '<b>' + (td.dataset.turAd || '') + '</b><br>' + (td.dataset.mukellefAd || '');

  var bD = document.getElementById('menu-donem');
  bD.innerHTML = donemsel
    ? 'Bu dönem takibe geri al<span class="ac">Yalnız <?= ayAdi($ay) ?> <?= $yil ?> etkilenir</span>'
    : 'Bu dönem takip dışı<span class="ac">Yalnız <?= ayAdi($ay) ?> <?= $yil ?> etkilenir</span>';
  bD.className = donemsel ? 'etkin' : '';

  var bK = document.getElementById('menu-kalici');
  bK.innerHTML = kalici
    ? 'Mükellefte var — takibe al<span class="ac">Tüm aylar etkilenir</span>'
    : 'Bu mükellefte hiç yok<span class="ac">Tüm aylarda takip dışı olur</span>';
  bK.className = kalici ? 'etkin' : '';

  // Menüyü imlecin yanına, ekran dışına taşmayacak şekilde yerleştir
  MENU.el.classList.add('acik');
  var g = MENU.el.offsetWidth, y = MENU.el.offsetHeight;
  var x = olay.pageX, t = olay.pageY;

  if (x + g > window.scrollX + document.documentElement.clientWidth - 8) { x -= g; }
  if (t + y > window.scrollY + document.documentElement.clientHeight - 8) { t -= y; }

  MENU.el.style.left = Math.max(8, x) + 'px';
  MENU.el.style.top  = Math.max(8, t) + 'px';

  return false;
}

function menuKapat() {
  if (MENU.el) MENU.el.classList.remove('acik');
  MENU.td = null;
}

document.addEventListener('click', function (e) {
  if (MENU.el && MENU.el.classList.contains('acik') && !MENU.el.contains(e.target)) { menuKapat(); }
});
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') menuKapat(); });
window.addEventListener('scroll', menuKapat, true);

function menuSecim(tip) {
  var td = MENU.td;
  menuKapat();

  if (!td || tip === 'kapat') return;

  var eskiYok = td.dataset.durum === 'YOK';

  if (tip === 'donem') {
    var isaretle = td.dataset.donemsel === '1' ? 0 : 1;

    BT.post('<?= site_url('evrak/donem-muaf') ?>', {
      mukellef_id: td.dataset.mukellef,
      evrak_turu_id: td.dataset.tur,
      yil: YIL, ay: AY, isaretle: isaretle
    }).then(function (j) {
      hucreCiz(td, j.yeni_durum, j.kalici);
      muafSayacDegis((j.yeni_durum === 'YOK' ? 1 : 0) - (eskiYok ? 1 : 0));
      BT.bildir(j.mesaj, 'basari');
    }).catch(function (e) { BT.bildir(e.message, 'hata'); });

    return;
  }

  // Kalıcı muafiyet — tüm ayları etkiler, onay istenir
  var kalici = td.dataset.kalici === '1';
  var soru = kalici
    ? '"' + td.dataset.turAd + '" türü ' + td.dataset.mukellefAd +
      ' için yeniden TAKİBE ALINACAK (tüm aylar). Onaylıyor musunuz?'
    : '"' + td.dataset.turAd + '" türü ' + td.dataset.mukellefAd +
      ' mükellefinde HİÇ TAKİP EDİLMEYECEK (tüm aylar).\n\n' +
      'Geçmiş aylardaki boş kayıtlar temizlenir; "geldi" işaretlenmiş kayıtlar korunur.\n\nOnaylıyor musunuz?';

  if (!window.confirm(soru)) return;

  BT.post('<?= site_url('evrak/kalici-muaf') ?>', {
    mukellef_id: td.dataset.mukellef,
    evrak_turu_id: td.dataset.tur,
    isaretle: kalici ? 0 : 1
  }).then(function (j) {
    hucreCiz(td, j.yeni_durum, j.kalici);
    muafSayacDegis((j.yeni_durum === 'YOK' ? 1 : 0) - (eskiYok ? 1 : 0));
    BT.bildir(j.mesaj, 'basari');
  }).catch(function (e) { BT.bildir(e.message, 'hata'); });
}

// ---------- Aylık not ----------
function notDuzenle(td) {
  if (td.querySelector('textarea')) return;

  var mid    = td.dataset.mukellef;
  var span   = td.querySelector('.not-metin');
  var mevcut = span.classList.contains('not-bos') ? '' : span.textContent.replace('📌 ', '').trim();

  var ta = document.createElement('textarea');
  ta.className = 'girdi';
  ta.value = mevcut;
  ta.rows = 2;
  ta.style.cssText = 'padding:4px 7px;font-size:12px;min-height:44px';

  td.innerHTML = '';
  td.appendChild(ta);
  ta.focus();

  function kaydet() {
    var yeni = ta.value.trim();
    BT.post('<?= site_url('evrak/aylik-not') ?>', { mukellef_id: mid, yil: YIL, ay: AY, not: yeni })
      .then(function () { BT.bildir('Not kaydedildi.', 'basari'); yaz(yeni); })
      .catch(function (e) { BT.bildir(e.message, 'hata'); yaz(mevcut); });
  }

  function yaz(deger) {
    td.classList.toggle('dolu', deger !== '');
    td.innerHTML = deger !== ''
      ? '<span class="not-metin">📌 ' + deger.replace(/</g, '&lt;') + '</span>'
      : '<span class="not-metin not-bos">+ not ekle</span>';
  }

  ta.addEventListener('blur', kaydet);
  ta.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ta.blur(); }
    if (e.key === 'Escape') { ta.removeEventListener('blur', kaydet); yaz(mevcut); }
  });
}
</script>
<?= $this->endSection() ?>
