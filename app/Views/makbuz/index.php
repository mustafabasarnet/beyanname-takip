<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php $secMus = secilenMusavirId($filtre['musavir_id'] ?? null); ?>

<style>
/* Stiller gömülü: stil.css kopyalanmasa bile çizelge doğru görünür */
.mk-tablo{width:100%;border-collapse:collapse}
.mk-tablo th{font-size:10.5px;text-transform:uppercase;letter-spacing:.3px;color:var(--gri-500,#64748b);
  font-weight:700;padding:8px 9px;border-bottom:1px solid var(--gri-200,#e2e8f0);white-space:nowrap;text-align:left}
.mk-tablo th.sag{text-align:right}.mk-tablo th.orta{text-align:center}
.mk-tablo td{padding:7px 9px;border-bottom:1px solid var(--gri-100,#f1f5f9);font-size:13px;vertical-align:middle}
.mk-tablo td.sag{text-align:right;font-variant-numeric:tabular-nums}
.mk-tablo td.orta{text-align:center}
.mk-tablo tbody tr:hover{background:var(--gri-50,#f8fafc)}
.mk-tablo tr.mk-tamam{background:#f0fdf4}
.mk-tablo tr.mk-asim{background:#fef2f2}
.mk-tablo tr.mk-ucretsiz{opacity:.7}
.mk-cubuk{display:inline-block;width:62px;height:7px;border-radius:99px;background:var(--gri-200,#e2e8f0);
  overflow:hidden;vertical-align:middle}
.mk-cubuk i{display:block;height:100%;border-radius:99px;transition:width .25s}
.mk-yuzde{font-size:11.5px;font-weight:700;color:var(--gri-600,#475569);margin-left:6px}
.mk-ucret{cursor:pointer;border-bottom:1px dashed var(--gri-300,#cbd5e1);font-variant-numeric:tabular-nums}
.mk-ucret:hover{color:var(--ana,#2563eb);border-bottom-color:var(--ana,#2563eb)}
/* Müşavir özeti */
.mk-mus{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
.mk-mus-kart{border:1px solid var(--gri-200,#e2e8f0);border-radius:10px;padding:12px 14px;background:#fff}
.mk-mus-bas{display:flex;align-items:center;gap:7px;font-weight:700;margin-bottom:8px}
.mk-mus-nokta{width:10px;height:10px;border-radius:50%;flex:0 0 10px}
.mk-mus-satir{display:flex;justify-content:space-between;font-size:12.5px;padding:2px 0}
.mk-mus-satir b{font-variant-numeric:tabular-nums}
.mk-kaydir{padding:14px;text-align:center;background:var(--gri-50,#f8fafc);
  border-top:1px solid var(--gri-200,#e2e8f0)}
</style>

<!-- ============ FİLTRE ============ -->
<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Yıl</label>
    <select name="yil" data-oto-filtre>
      <?php foreach (yilSecenekleri() as $y): ?>
        <option value="<?= $y ?>" <?= (int) $filtre['yil'] === $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Durum</label>
    <select name="durum" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($durumlar as $dk => $dv): ?>
        <option value="<?= $dk ?>" <?= ($filtre['durum'] ?? '') === $dk ? 'selected' : '' ?>><?= esc($dv) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if (count($musavirler) > 1): ?>
    <div class="form-grup">
      <label>Mali Müşavir</label>
      <select name="musavir_id" data-oto-filtre>
        <option value="">Tümü</option>
        <?php foreach ($musavirler as $mid => $mad): ?>
          <option value="<?= $mid ?>" <?= $secMus === (int) $mid ? 'selected' : '' ?>><?= esc($mad) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="form-grup" style="min-width:170px">
    <label>Ara</label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>" placeholder="Ünvan / VKN">
  </div>

  <div class="form-grup">
    <label class="onay" style="margin-top:18px">
      <input type="checkbox" name="pasif" value="1" <?= ! empty($filtre['pasif_dahil']) ? 'checked' : '' ?> data-oto-filtre>
      Pasifler dahil
    </label>
  </div>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url('makbuz') ?>" class="btn ikincil kucuk">Sıfırla</a>
    <?php $qs = http_build_query(array_filter([
        'yil' => $filtre['yil'], 'durum' => $filtre['durum'], 'q' => $filtre['q'],
    ], static fn ($v) => $v !== null && $v !== '')); ?>
    <a href="<?= site_url('makbuz/ice-aktar?kip=ucret&yil=' . (int) $filtre['yil']) ?>" class="btn mor kucuk">
      📥 Ücret Yükle
    </a>
    <a href="<?= site_url('makbuz/ice-aktar?kip=makbuz&yil=' . (int) $filtre['yil']) ?>" class="btn mor kucuk">
      📥 Makbuz Yükle
    </a>
    <button type="button" class="btn ikincil kucuk" onclick="BT.modalAc('kopya-modal')">📋 Ücret Kopyala</button>
    <?php
      // Yazdırma bağlantısı: ekrandaki filtre çıktıya taşınır
      $yazdirQs = $qs;

      if (! empty($filtre['pasif_dahil'])) {
          $yazdirQs .= '&pasif=1';
      }

      if ($secMus > 0) {
          $yazdirQs .= '&musavir_id=' . $secMus;
      }
    ?>
    <a href="<?= site_url('makbuz/yazdir?' . $yazdirQs) ?>" target="_blank" class="btn ikincil kucuk">🖨️ Yazdır</a>
    <?php /* Menüden kaldırıldı; erişim buradan sağlanır. Sayfanın adı
             "Vergi Yükü" olduğu için buton da aynı adı taşır. */ ?>
    <a href="<?= site_url('gelir-vergisi?yil=' . (int) $filtre['yil']) ?>" class="btn kucuk"
       title="Yıllık gelir vergisi + KDV yükü hesabı">🧮 Vergi Yükü</a>
    <a href="<?= site_url('makbuz/excel?' . $qs) ?>" class="btn yesil kucuk">📊 Excel</a>
  </div>
</form>

<!-- ============ ÖZET ============ -->
<div class="stat-grid">
  <div class="stat">
    <div class="etiket">Mükellef</div>
    <div class="deger"><?= number_format((int) $ozet['mukellef'], 0, ',', '.') ?></div>
    <div class="alt"><?= (int) $ozet['ucretsiz'] ?> ücreti girilmemiş</div>
  </div>
  <div class="stat mor">
    <div class="etiket">Yıllık Sözleşme</div>
    <div class="deger" style="font-size:21px"><?= number_format($ozet['ucret'], 2, ',', '.') ?></div>
    <div class="alt">₺ hedef</div>
  </div>
  <div class="stat yesil">
    <div class="etiket">Kesilen Makbuz</div>
    <div class="deger" style="font-size:21px"><?= number_format($ozet['kesilen'], 2, ',', '.') ?></div>
    <div class="alt">₺ • <?= (int) $ozet['adet'] ?> makbuz</div>
  </div>
  <div class="stat kirmizi">
    <div class="etiket">Kalan</div>
    <div class="deger" style="font-size:21px"><?= number_format($ozet['kalan'], 2, ',', '.') ?></div>
    <div class="alt">₺ kesilecek</div>
  </div>
  <div class="stat turuncu">
    <div class="etiket">Tamamlanma</div>
    <div class="deger">%<?= (int) $ozet['oran'] ?></div>
    <div class="alt"><?= (int) $ozet['tamam'] ?> mükellef tamam</div>
  </div>
</div>

<!-- ============ MÜŞAVİR ÖZETİ ============ -->
<?php if (count($musavirOzet) > 0): ?>
  <div class="kart">
    <div class="kart-baslik">
      <h2>👥 Mali Müşavir Bazında</h2>
      <span class="sag kucuk-yazi"><?= (int) $filtre['yil'] ?> yılı</span>
    </div>
    <div class="kart-govde">
      <div class="mk-mus">
        <?php foreach ($musavirOzet as $mo): ?>
          <div class="mk-mus-kart">
            <div class="mk-mus-bas">
              <span class="mk-mus-nokta" style="background:<?= esc($mo['renk']) ?>"></span>
              <?= esc($mo['ad_soyad']) ?>
              <span class="kucuk-yazi" style="font-weight:400;margin-left:auto">
                <?= (int) $mo['mukellef'] ?> mükellef
              </span>
            </div>
            <div class="mk-mus-satir"><span>Sözleşme</span>
              <b><?= number_format($mo['ucret'], 2, ',', '.') ?> ₺</b></div>
            <div class="mk-mus-satir"><span>Kesilen</span>
              <b style="color:var(--yesil,#059669)"><?= number_format($mo['kesilen'], 2, ',', '.') ?> ₺</b></div>
            <div class="mk-mus-satir"><span>Kalan</span>
              <b style="color:<?= $mo['kalan'] > 0 ? 'var(--kirmizi,#dc2626)' : 'var(--yesil,#059669)' ?>">
                <?= number_format($mo['kalan'], 2, ',', '.') ?> ₺</b></div>
            <div class="mk-mus-satir kucuk-yazi">
              <span><?= (int) $mo['adet'] ?> makbuz</span>
              <span>stopaj <?= number_format($mo['stopaj'], 2, ',', '.') ?> ₺</span>
            </div>
            <div class="mk-cubuk" style="width:100%;margin-top:7px">
              <i style="width:<?= (int) $mo['oran'] ?>%;background:<?= (int) $mo['oran'] >= 100 ? '#059669' : '#2563eb' ?>"></i>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ============ ÇİZELGE ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🧾 Mükellef Bazında Makbuz Durumu</h2>
    <div class="sag" style="display:flex;align-items:center;gap:10px">
      <span class="kucuk-yazi">Ücreti değiştirmek için tutara tıklayın</span>
      <label class="kucuk-yazi" style="display:flex;align-items:center;gap:5px;margin:0">
        Sayfa başına
        <select id="mk-adet" class="girdi" style="padding:3px 6px;font-size:12px;width:auto">
          <?php foreach ($adetSecenek as $s): ?>
            <option value="<?= $s ?>" <?= (int) $sayfaAdedi === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </div>

  <div class="kart-govde sikisik">
    <?php if ($kayitlar === []): ?>
      <div class="tablo-bos">
        <span class="ikon">🧾</span>
        Kayıt bulunamadı.<br>
        <span class="kucuk-yazi">
          Yıllık sözleşme ücretlerini <b>📥 Ücret Yükle</b> ile Excel'den aktarabilir,
          ya da listedeki tutara tıklayarak tek tek girebilirsiniz.
        </span>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="mk-tablo" id="mk-tablo">
          <thead>
            <tr>
              <th>Mükellef</th>
              <th class="sag">Yıllık Ücret</th>
              <th class="sag">Kesilen</th>
              <th class="sag">Kalan</th>
              <th class="orta">Adet</th>
              <th>Son Makbuz</th>
              <th>İlerleme</th>
              <th>Durum</th>
            </tr>
          </thead>
          <tbody id="mk-govde">
            <?= $this->include('makbuz/_satirlar') ?>
          </tbody>
        </table>
      </div>

      <div id="mk-kaydir" class="mk-kaydir"
           data-ofset="<?= count($kayitlar) ?>"
           data-toplam="<?= (int) $toplamKayit ?>">
        <button type="button" class="btn ikincil" id="mk-daha"
                onclick="mkDahaFazla()" <?= empty($dahaVar) ? 'style="display:none"' : '' ?>>
          ↓ Daha Fazla Göster
        </button>
        <div class="kucuk-yazi" style="margin-top:7px">
          <b id="mk-gosterilen"><?= count($kayitlar) ?></b> /
          <b><?= number_format((int) $toplamKayit, 0, ',', '.') ?></b> mükellef
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ ÜCRET KOPYALAMA MODALI ============ -->
<div class="modal-arka" id="kopya-modal">
  <div class="modal">
    <form method="post" action="<?= site_url('makbuz/ucret-kopyala') ?>">
      <?= csrf_field() ?>
      <div class="modal-baslik">
        <h3>📋 Yıllık Ücretleri Kopyala</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
      </div>
      <div class="modal-govde">
        <div class="uyari bilgi" style="padding:10px 14px;font-size:13px">
          <span class="ik">ℹ</span>
          <div>
            Tarife her yıl değiştiği için önceki yılın ücretlerini yeni yıla
            zam oranıyla kopyalayabilirsiniz. <b>Hedef yılda kaydı olan
            mükelleflere dokunulmaz.</b>
          </div>
        </div>
        <div class="form-grid">
          <div class="form-grup">
            <label>Kaynak Yıl</label>
            <select name="kaynak_yil">
              <?php foreach (yilSecenekleri() as $y): ?>
                <option value="<?= $y ?>" <?= $y === (int) $filtre['yil'] - 1 ? 'selected' : '' ?>><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-grup">
            <label>Hedef Yıl</label>
            <select name="hedef_yil">
              <?php foreach (yilSecenekleri() as $y): ?>
                <option value="<?= $y ?>" <?= $y === (int) $filtre['yil'] ? 'selected' : '' ?>><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-grup tam">
            <label>Zam Oranı (%)</label>
            <input type="text" name="zam" class="girdi" value="0" inputmode="decimal"
                   placeholder="Örn: 25 → tutarlar %25 artırılır">
            <span class="yardim">Boş veya 0 bırakırsanız tutarlar aynen kopyalanır.</span>
          </div>
        </div>
      </div>
      <div class="modal-alt">
        <button type="button" class="btn ikincil" data-modal-kapat>İptal</button>
        <button type="submit" class="btn">📋 Kopyala</button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
var MK_YIL = <?= (int) $filtre['yil'] ?>;

// ---------- Yıllık ücreti satır içi düzenle ----------
document.addEventListener('click', function (e) {
  var h = e.target.closest('.mk-ucret');
  if (!h) { return; }

  var eski = h.textContent.trim().replace('— gir —', '');
  var yeni = prompt('<?= (int) $filtre['yil'] ?> yılı sözleşme ücreti (₺):', eski);
  if (yeni === null) { return; }

  BT.post('<?= site_url('makbuz/ucret') ?>', {
    mukellef_id: h.dataset.mukellef, yil: MK_YIL, tutar: yeni
  }).then(function (j) {
    BT.bildir(j.mesaj, 'basari');
    // Satırı yeniden çizmek yerine sayfayı tazelemek en güvenlisi:
    // kalan/oran/durum rozeti hep birlikte değişiyor.
    location.reload();
  }).catch(function (er) { BT.bildir(er.message, 'hata'); });
});

// ---------- Sayfa adedi ----------
(function () {
  var a = document.getElementById('mk-adet');
  if (!a) { return; }
  a.addEventListener('change', function () {
    var u = new URL(location.href);
    u.searchParams.set('adet', a.value);
    location.href = u.toString();
  });
})();

// ---------- Sonsuz kaydırma ----------
var mkYukleniyor = false;

function mkDahaFazla() {
  var alan = document.getElementById('mk-kaydir');
  if (!alan || mkYukleniyor) { return; }

  var ofset  = parseInt(alan.dataset.ofset, 10) || 0;
  var toplam = parseInt(alan.dataset.toplam, 10) || 0;
  if (ofset >= toplam) { return; }

  mkYukleniyor = true;
  var d = document.getElementById('mk-daha');
  if (d) { d.textContent = 'Yükleniyor…'; }

  var u = new URL('<?= site_url('makbuz/daha-fazla') ?>', location.origin);
  new URLSearchParams(location.search).forEach(function (v, a) { u.searchParams.set(a, v); });
  u.searchParams.set('ofset', ofset);

  fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (y) { return y.json(); })
    .then(function (v) {
      mkYukleniyor = false;
      if (d) { d.textContent = '↓ Daha Fazla Göster'; }
      if (!v.durum) { return; }

      document.getElementById('mk-govde').insertAdjacentHTML('beforeend', v.html);
      alan.dataset.ofset = v.ofset;
      document.getElementById('mk-gosterilen').textContent = v.ofset;
      if (!v.dahaVar && d) { d.style.display = 'none'; }
    })
    .catch(function () {
      mkYukleniyor = false;
      if (d) { d.textContent = '↓ Daha Fazla Göster'; }
    });
}

window.addEventListener('scroll', function () {
  var alan = document.getElementById('mk-kaydir');
  if (!alan) { return; }
  if (alan.getBoundingClientRect().top < window.innerHeight + 250) { mkDahaFazla(); }
});
</script>
<?= $this->endSection() ?>
