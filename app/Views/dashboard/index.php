<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<!-- ============ FİLTRE ============ -->
<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Yıl</label>
    <select name="yil" data-oto-filtre>
      <?php foreach (yilSecenekleri() as $y): ?>
        <option value="<?= $y ?>" <?= $y === $yil ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-grup">
    <label>Ay</label>
    <select name="ay" data-oto-filtre title="Beyanname durum tablosunu ve evrak kartlarını birlikte süzer">
      <?php for ($a = 1; $a <= 12; $a++): ?>
        <option value="<?= $a ?>" <?= $a === $ay ? 'selected' : '' ?>><?= ayAdi($a) ?></option>
      <?php endfor; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Tablo Ekseni</label>
    <select name="mod" data-oto-filtre title="Durum tablosu ayı neye göre saysın?">
      <option value="beyan" <?= ($dagilimMod ?? 'beyan') === 'beyan' ? 'selected' : '' ?>>
        Beyan Dönemi (son tarih)
      </option>
      <option value="donem" <?= ($dagilimMod ?? 'beyan') === 'donem' ? 'selected' : '' ?>>
        Ait Olduğu Dönem
      </option>
    </select>
  </div>
  <div class="btn-grup">
    <a href="<?= site_url('takip?gecikmis=1') ?>" class="btn kirmizi kucuk">⏰ Gecikmişler</a>
    <a href="<?= site_url('mukellefler/yeni') ?>" class="btn kucuk">+ Yeni Mükellef</a>
  </div>
</form>

<!-- ============ İSTATİSTİKLER ============ -->
<div class="stat-grid">
  <div class="stat">
    <span class="stat-ikon">🏢</span>
    <div class="etiket">Faal Mükellef</div>
    <div class="deger"><?= (int) $mukellefStat['faal'] ?></div>
    <div class="alt"><?= (int) $mukellefStat['gercek'] ?> şahıs • <?= (int) $mukellefStat['tuzel'] ?> kurum</div>
  </div>

  <div class="stat kirmizi">
    <span class="stat-ikon">⏰</span>
    <div class="etiket">Gecikmiş</div>
    <div class="deger"><?= (int) $ozet['gecikmis'] ?></div>
    <div class="alt">Süresi geçmiş beyanname</div>
  </div>

  <div class="stat turuncu">
    <span class="stat-ikon">📅</span>
    <div class="etiket">Bugün Son Gün</div>
    <div class="deger"><?= (int) $ozet['bugun'] ?></div>
    <div class="alt"><?= trTarih(date('Y-m-d')) ?></div>
  </div>

  <div class="stat sari">
    <span class="stat-ikon">📝</span>
    <div class="etiket">Hazır</div>
    <div class="deger"><?= (int) ($ozet['hazir'] ?? 0) ?></div>
    <div class="alt">Onay bekliyor</div>
  </div>

  <div class="stat yesil">
    <span class="stat-ikon">✓</span>
    <div class="etiket">Onaylandı</div>
    <div class="deger"><?= (int) ($ozet['onaylandi'] ?? 0) ?></div>
    <div class="alt"><?= $yil ?> yılı toplam <?= (int) $ozet['toplam'] ?></div>
  </div>
</div>

<!-- ============ BEYANNAME DURUM KONTROL TABLOSU ============ -->
<?php
/*
 * Beyanname türü bazında durum tablosu.
 *
 * Türler SABİT LİSTEDEN değil, seçilen ayda GERÇEKTEN VAR OLAN kayıtlardan
 * üretilir (BeyannameTakipModel::turDagilimi). Bu sayede geçici vergiler
 * yalnızca verildikleri aylarda (Şubat/Mayıs/Ağustos/Kasım) tabloda görünür,
 * Eylül seçildiğinde listeden kendiliğinden düşer.
 *
 * Sayılar tıklanabilir: panelden ayrılmadan açılır pencerede mükellef
 * listesi gösterilir (panel/tur-listesi).
 */
$dMod   = $dagilimMod ?? 'beyan';
$dTop   = ['toplam' => 0, 'onaylandi' => 0, 'hazir' => 0, 'bekliyor' => 0,
           'verilmeyecek' => 0, 'gecikmis' => 0, 'kalan' => 0];

foreach ($turDagilim as $t) {
    foreach (array_keys($dTop) as $anahtar) {
        $dTop[$anahtar] += (int) $t[$anahtar];
    }
}

$dTakipli   = $dTop['toplam'] - $dTop['verilmeyecek'];
$dGenelOran = $dTakipli > 0 ? (int) round($dTop['onaylandi'] / $dTakipli * 100) : 100;
?>

<style>
/* Stil gömülü: stil.css kopyalanmasa bile tablo doğru görünsün */
.bdk-kart{margin-bottom:18px}
.bdk-baslik{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.bdk-lejant{display:flex;gap:12px;flex-wrap:wrap;font-size:11.5px;color:var(--gri-500,#64748b)}
.bdk-lejant i{width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:4px}
.bdk-tablo{width:100%;border-collapse:collapse}
.bdk-tablo th{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--gri-500,#64748b);
  font-weight:700;text-align:right;padding:9px 10px;border-bottom:1px solid var(--gri-200,#e2e8f0);white-space:nowrap}
.bdk-tablo th:first-child{text-align:left}
.bdk-tablo td{padding:9px 10px;border-bottom:1px solid var(--gri-100,#f1f5f9);text-align:right;font-size:13.5px}
.bdk-tablo td:first-child{text-align:left}
.bdk-tablo tbody tr:hover{background:var(--gri-50,#f8fafc)}
.bdk-tablo tfoot td{font-weight:800;border-top:2px solid var(--gri-200,#e2e8f0);border-bottom:0;background:var(--gri-50,#f8fafc)}
.bdk-tur{display:inline-block;padding:2px 9px;border-radius:6px;color:#fff;font-size:11.5px;font-weight:700}
.bdk-say{background:none;border:0;font:inherit;font-weight:700;cursor:pointer;padding:2px 7px;
  border-radius:6px;color:inherit;min-width:34px}
.bdk-say:hover{background:var(--ana-acik,#dbeafe);color:var(--ana-koyu,#1d4ed8)}
.bdk-say.bos{color:var(--gri-300,#cbd5e1);cursor:default;font-weight:600}
.bdk-say.bos:hover{background:none;color:var(--gri-300,#cbd5e1)}
.bdk-yesil{color:#059669}.bdk-sari{color:#ca8a04}.bdk-gri{color:var(--gri-500,#64748b)}
.bdk-kirmizi{color:#dc2626}
.bdk-cubuk{display:inline-block;width:110px;height:7px;border-radius:99px;background:var(--gri-200,#e2e8f0);
  overflow:hidden;vertical-align:middle;margin-right:8px}
.bdk-cubuk i{display:block;height:100%;background:#059669;border-radius:99px}
.bdk-oran{font-size:12px;font-weight:700;color:var(--gri-600,#475569)}
.bdk-bos{padding:30px;text-align:center;color:var(--gri-400,#94a3b8)}
/* Açılır pencere */
.bdk-ortu{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:900;display:none}
.bdk-ortu.acik{display:block}
.bdk-pencere{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:901;
  background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:min(860px,94vw);
  max-height:86vh;display:none;flex-direction:column}
.bdk-pencere.acik{display:flex}
.bdk-p-bas{display:flex;align-items:center;justify-content:space-between;gap:10px;
  padding:14px 18px;border-bottom:1px solid var(--gri-200,#e2e8f0)}
.bdk-p-bas h3{margin:0;font-size:15.5px}
.bdk-p-govde{overflow:auto;padding:0 4px 4px}
.bdk-p-kapat{background:none;border:0;font-size:22px;cursor:pointer;color:var(--gri-400,#94a3b8);line-height:1}
.bdk-p-kapat:hover{color:var(--kirmizi,#dc2626)}
@media(max-width:760px){
  .bdk-tablo th:nth-child(6),.bdk-tablo td:nth-child(6){display:none}
  .bdk-cubuk{width:60px}
}
</style>

<div class="kart bdk-kart">
  <div class="kart-baslik">
    <div class="bdk-baslik">
      <h2>📊 Beyanname Durum Kontrol</h2>
      <span class="rozet mavi"><?= $dMod === 'donem' ? 'Dönem' : 'Beyan' ?>: <?= ayAdi($ay) ?> <?= $yil ?></span>
    </div>
    <div class="sag bdk-lejant">
      <span><i style="background:#059669"></i>Onaylandı</span>
      <span><i style="background:#ca8a04"></i>Hazır</span>
      <span><i style="background:#cbd5e1"></i>Bekliyor</span>
      <span><i style="background:#dc2626"></i>Gecikmiş</span>
    </div>
  </div>

  <div class="kart-govde sikisik">
    <?php if ($turDagilim === []): ?>
      <div class="bdk-bos">
        <div style="font-size:34px;opacity:.5">📭</div>
        <b><?= ayAdi($ay) ?> <?= $yil ?></b> için beyanname bulunmuyor.
        <div class="kucuk-yazi" style="margin-top:6px">
          Bu ayda son günü dolan beyanname yok. Başka bir ay seçmeyi deneyin.
        </div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="bdk-tablo">
          <thead>
            <tr>
              <th>Beyanname Türü</th>
              <th>Toplam</th>
              <th>Onaylandı</th>
              <th>Hazır</th>
              <th>Bekliyor</th>
              <th>Gecikmiş</th>
              <th>Kalan</th>
              <th style="text-align:left;padding-left:18px">Durum</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($turDagilim as $t): ?>
              <tr>
                <td>
                  <span class="bdk-tur" style="background:<?= esc($t['tur_renk']) ?>"><?= esc($t['tur_kisa']) ?></span>
                  <?php if ($t['verilmeyecek'] > 0): ?>
                    <span class="kucuk-yazi" title="Verilmeyecek olarak işaretlenen kayıtlar oran hesabına girmez">
                      (<?= (int) $t['verilmeyecek'] ?> verilmeyecek)
                    </span>
                  <?php endif; ?>
                </td>
                <?php
                // Tıklanabilir sayı hücresi. Sıfırsa düğme değil düz yazı olur.
                $hucre = static function (int $deger, string $durum, string $sinif, array $t) use ($ay, $yil, $dMod) {
                    if ($deger === 0) {
                        return '<span class="bdk-say bos">0</span>';
                    }

                    return '<button type="button" class="bdk-say ' . $sinif . '"'
                        . ' data-tur="' . (int) $t['tur_id'] . '"'
                        . ' data-tur-ad="' . esc($t['tur_kisa'], 'attr') . '"'
                        . ' data-durum="' . $durum . '"'
                        . ' title="Listeyi görmek için tıklayın">'
                        . number_format($deger, 0, ',', '.') . '</button>';
                };
                ?>
                <td><?= $hucre((int) $t['toplam'], '', '', $t) ?></td>
                <td><?= $hucre((int) $t['onaylandi'], 'ONAYLANDI', 'bdk-yesil', $t) ?></td>
                <td><?= $hucre((int) $t['hazir'], 'HAZIR', 'bdk-sari', $t) ?></td>
                <td><?= $hucre((int) $t['bekliyor'], 'BEKLIYOR', 'bdk-gri', $t) ?></td>
                <td><?= $hucre((int) $t['gecikmis'], 'GECIKMIS', 'bdk-kirmizi', $t) ?></td>
                <td><?= $hucre((int) $t['kalan'], 'KALAN', '', $t) ?></td>
                <td style="text-align:left;padding-left:18px;white-space:nowrap">
                  <span class="bdk-cubuk"><i style="width:<?= (int) $t['oran'] ?>%"></i></span>
                  <span class="bdk-oran">%<?= (int) $t['oran'] ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td>TOPLAM</td>
              <td><?= number_format($dTop['toplam'], 0, ',', '.') ?></td>
              <td class="bdk-yesil"><?= number_format($dTop['onaylandi'], 0, ',', '.') ?></td>
              <td class="bdk-sari"><?= number_format($dTop['hazir'], 0, ',', '.') ?></td>
              <td class="bdk-gri"><?= number_format($dTop['bekliyor'], 0, ',', '.') ?></td>
              <td class="bdk-kirmizi"><?= number_format($dTop['gecikmis'], 0, ',', '.') ?></td>
              <td><?= number_format($dTop['kalan'], 0, ',', '.') ?></td>
              <td style="text-align:left;padding-left:18px;white-space:nowrap">
                <span class="bdk-cubuk"><i style="width:<?= $dGenelOran ?>%"></i></span>
                <span class="bdk-oran">%<?= $dGenelOran ?></span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
/*
 * E-DEFTER BERAT KARTI
 *
 * Yalnızca seçilen ayda yüklenecek berat VARSA gösterilir (kullanıcı tercihi).
 * Böylece berat dönemi olmayan aylarda panel sade kalır.
 */
$edOzet = $edefterOzet ?? null;
?>
<?php if ($edOzet !== null && (int) $edOzet['toplam'] > 0): ?>
  <style>
  .edk-kart{margin-bottom:18px}
  .edk-bas{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
  .edk-ikon{width:30px;height:30px;border-radius:8px;background:#fef3c7;display:inline-flex;
    align-items:center;justify-content:center;font-size:16px}
  .edk-rakamlar{display:flex;gap:38px;flex-wrap:wrap;padding:4px 2px 14px}
  .edk-sayi{display:block;font-size:27px;font-weight:800;line-height:1.15;letter-spacing:-1px}
  .edk-etiket{font-size:11px;text-transform:uppercase;letter-spacing:.4px;
    color:var(--gri-500,#64748b);font-weight:600}
  .edk-bag{text-decoration:none;color:inherit;display:block}
  .edk-bag:hover .edk-sayi{color:var(--ana,#2563eb)}
  .edk-cubuk{height:9px;border-radius:99px;background:var(--gri-200,#e2e8f0);overflow:hidden}
  .edk-cubuk i{display:block;height:100%;background:#059669;border-radius:99px}
  .edk-alt{display:flex;justify-content:space-between;align-items:center;gap:10px;
    margin-top:9px;font-size:12.5px;color:var(--gri-500,#64748b);flex-wrap:wrap}
  </style>

  <div class="kart edk-kart">
    <div class="kart-baslik">
      <div class="edk-bas">
        <span class="edk-ikon">📗</span>
        <h2 style="margin:0">E-Defter</h2>
        <?php if (! empty($edefterDonem)): ?>
          <span class="rozet gri" title="Bu ay yüklenecek beratların dönemi"><?= esc($edefterDonem) ?></span>
        <?php endif; ?>
      </div>
      <div class="sag">
        <a href="<?= site_url('edefter?yil=' . (int) $yil . '&ay=' . (int) $ay) ?>" class="btn ikincil mini">
          Takip Listesi
        </a>
      </div>
    </div>

    <div class="kart-govde">
      <?php
      // Kart sayıları tıklanabilir: E-Defter Takip ekranını süzülü açar
      $edBag = static fn (string $ek = '') => site_url('edefter?yil=' . (int) $yil . '&ay=' . (int) $ay . $ek);
      ?>
      <div class="edk-rakamlar">
        <a href="<?= $edBag() ?>" class="edk-bag">
          <span class="edk-sayi"><?= number_format((int) $edOzet['toplam'], 0, ',', '.') ?></span>
          <span class="edk-etiket">Toplam</span>
        </a>
        <a href="<?= $edBag('&durum=ONAYLANDI') ?>" class="edk-bag">
          <span class="edk-sayi" style="color:#059669"><?= (int) $edOzet['onaylandi'] ?></span>
          <span class="edk-etiket">Yüklenen</span>
        </a>
        <a href="<?= $edBag('&durum=HAZIR') ?>" class="edk-bag">
          <span class="edk-sayi" style="color:#ca8a04"><?= (int) $edOzet['hazir'] ?></span>
          <span class="edk-etiket">Hazır</span>
        </a>
        <?php if ((int) $edOzet['gecikmis'] > 0): ?>
          <a href="<?= $edBag('&gecikmis=1') ?>" class="edk-bag">
            <span class="edk-sayi" style="color:#dc2626"><?= (int) $edOzet['gecikmis'] ?></span>
            <span class="edk-etiket">Gecikmiş</span>
          </a>
        <?php endif; ?>
        <a href="<?= $edBag() ?>" class="edk-bag">
          <span class="edk-sayi"><?= (int) $edOzet['kalan'] ?></span>
          <span class="edk-etiket">Kalan</span>
        </a>
      </div>

      <div class="edk-cubuk"><i style="width:<?= (int) $edOzet['oran'] ?>%"></i></div>

      <div class="edk-alt">
        <span>Berat: Yevmiye + Kebir</span>
        <b>%<?= (int) $edOzet['oran'] ?></b>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Açılır liste penceresi -->
<div class="bdk-ortu" id="bdk-ortu" onclick="bdkKapat()"></div>
<div class="bdk-pencere" id="bdk-pencere" role="dialog" aria-modal="true">
  <div class="bdk-p-bas">
    <h3 id="bdk-p-baslik">Liste</h3>
    <div style="display:flex;align-items:center;gap:10px">
      <a href="#" id="bdk-p-takip" class="btn ikincil mini">Takip ekranında aç</a>
      <button type="button" class="bdk-p-kapat" onclick="bdkKapat()" aria-label="Kapat">&times;</button>
    </div>
  </div>
  <div class="bdk-p-govde" id="bdk-p-govde">
    <div class="bdk-bos">Yükleniyor…</div>
  </div>
</div>

<script>
(function () {
  var YIL = <?= (int) $yil ?>, AY = <?= (int) $ay ?>, MOD = <?= json_encode($dMod) ?>;

  var DURUM_AD = {
    '': 'Tümü', 'ONAYLANDI': 'Onaylandı', 'HAZIR': 'Hazır',
    'BEKLIYOR': 'Bekliyor', 'GECIKMIS': 'Gecikmiş', 'KALAN': 'Kalan (Bekliyor + Hazır)'
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  window.bdkKapat = function () {
    document.getElementById('bdk-ortu').classList.remove('acik');
    document.getElementById('bdk-pencere').classList.remove('acik');
  };

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { window.bdkKapat(); }
  });

  document.querySelectorAll('.bdk-say[data-tur]').forEach(function (dugme) {
    dugme.addEventListener('click', function () {
      var turId  = dugme.dataset.tur,
          turAd  = dugme.dataset.turAd,
          durum  = dugme.dataset.durum || '';

      document.getElementById('bdk-p-baslik').textContent =
        turAd + ' — ' + (DURUM_AD[durum] || durum);
      document.getElementById('bdk-p-govde').innerHTML =
        '<div class="bdk-bos">Yükleniyor…</div>';
      document.getElementById('bdk-ortu').classList.add('acik');
      document.getElementById('bdk-pencere').classList.add('acik');

      // "Takip ekranında aç" bağlantısı aynı süzgeci taşır.
      // KALAN ve GECIKMIS gerçek birer durum değil; Takip ekranında
      // karşılıkları farklı parametrelerdir.
      var takipQs = 'yil=' + YIL + '&ay=' + AY + '&mod=' + MOD + '&tur_id=' + turId;
      if (durum === 'GECIKMIS')      { takipQs += '&gecikmis=1'; }
      else if (durum && durum !== 'KALAN') { takipQs += '&durum=' + durum; }
      document.getElementById('bdk-p-takip').href =
        <?= json_encode(site_url('takip')) ?> + '?' + takipQs;

      var url = <?= json_encode(site_url('panel/tur-listesi')) ?>
        + '?yil=' + YIL + '&ay=' + AY + '&mod=' + MOD
        + '&tur_id=' + turId + '&durum=' + encodeURIComponent(durum);

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (y) { return y.json(); })
        .then(function (v) {
          if (!v.durum) {
            document.getElementById('bdk-p-govde').innerHTML =
              '<div class="bdk-bos">' + esc(v.mesaj || 'Liste alınamadı.') + '</div>';
            return;
          }
          if (!v.kayitlar.length) {
            document.getElementById('bdk-p-govde').innerHTML =
              '<div class="bdk-bos">Bu koşullara uyan kayıt yok.</div>';
            return;
          }

          var h = '<table class="tablo"><thead><tr>'
                + '<th>Mükellef</th><th>Dönem</th><th>Son Tarih</th><th>Durum</th>'
                + '</tr></thead><tbody>';

          v.kayitlar.forEach(function (k) {
            h += '<tr>'
              + '<td><a href="' + <?= json_encode(site_url('takip')) ?>
                  + '?q=' + encodeURIComponent(k.mukellef) + '" class="kalin">'
                  + esc(k.mukellef) + '</a>'
              + '<div class="kucuk-yazi">' + esc(k.kimlik || '') + '</div></td>'
              + '<td class="kucuk-yazi">' + esc(k.donem) + '</td>'
              + '<td' + (k.gecikmis ? ' class="metin-kirmizi"' : '') + '><b>'
                  + esc(k.son_tarih) + '</b>' + (k.gecikmis ? ' ⏰' : '') + '</td>'
              + '<td><span class="rozet ' + k.durum.toLowerCase() + '">'
                  + esc(k.durum_ad) + '</span></td>'
              + '</tr>';
          });

          h += '</tbody></table>';
          if (v.adet >= 500) {
            h += '<div class="kucuk-yazi" style="padding:10px 14px">'
               + 'İlk 500 kayıt gösteriliyor. Tamamı için “Takip ekranında aç”.</div>';
          }
          document.getElementById('bdk-p-govde').innerHTML = h;
        })
        .catch(function () {
          document.getElementById('bdk-p-govde').innerHTML =
            '<div class="bdk-bos">Bağlantı hatası. Sayfayı yenileyip tekrar deneyin.</div>';
        });
    });
  });
})();
</script>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:18px">

  <!-- ============ GECİKMİŞLER ============ -->
  <div class="kart">
    <div class="kart-baslik">
      <h2>⏰ Gecikmiş Beyannameler</h2>
      <div class="sag"><a href="<?= site_url('raporlar/gecikmis') ?>" class="btn ikincil mini">Tümü</a></div>
    </div>
    <div class="kart-govde sikisik">
      <?php if ($gecikmisler === []): ?>
        <div class="tablo-bos"><span class="ikon">🎉</span>Gecikmiş beyanname yok. Harika!</div>
      <?php else: ?>
        <div class="tablo-sar">
          <table class="tablo">
            <thead><tr><th>Mükellef</th><th>Beyanname</th><th>Dönem</th><th>Son Tarih</th><th>Durum</th></tr></thead>
            <tbody>
            <?php foreach ($gecikmisler as $g): $k = kalanGunMetni($g['son_tarih']); ?>
              <tr class="gecikmis-satir">
                <td><a href="<?= site_url('mukellefler/detay/' . $g['mukellef_id']) ?>"><?= esc(kisalt($g['mukellef_unvan'], 26)) ?></a></td>
                <td><span class="tur-rozet" style="background:<?= esc($g['tur_renk']) ?>"><?= esc($g['tur_kisa']) ?></span></td>
                <td class="kucuk-yazi"><?= esc($g['donem_adi']) ?></td>
                <td><?= trTarih($g['son_tarih']) ?></td>
                <td><span class="rozet kirmizi"><?= esc($k['metin']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ============ YAKLAŞANLAR ============ -->
  <div class="kart">
    <div class="kart-baslik">
      <h2>📌 Yaklaşan Son Tarihler (7 Gün)</h2>
      <div class="sag"><a href="<?= site_url('takip') ?>" class="btn ikincil mini">Çizelge</a></div>
    </div>
    <div class="kart-govde sikisik">
      <?php if ($yaklasanlar === []): ?>
        <div class="tablo-bos"><span class="ikon">✅</span>Önümüzdeki 7 gün içinde son tarihi olan beyanname yok.</div>
      <?php else: ?>
        <div class="tablo-sar">
          <table class="tablo">
            <thead><tr><th>Mükellef</th><th>Beyanname</th><th>Dönem</th><th>Son Tarih</th><th>Kalan</th></tr></thead>
            <tbody>
            <?php foreach ($yaklasanlar as $y): $k = kalanGunMetni($y['son_tarih']); ?>
              <tr class="<?= $k['gun'] === 0 ? 'bugun-satir' : '' ?>">
                <td><a href="<?= site_url('mukellefler/detay/' . $y['mukellef_id']) ?>"><?= esc(kisalt($y['mukellef_unvan'], 26)) ?></a></td>
                <td><span class="tur-rozet" style="background:<?= esc($y['tur_renk']) ?>"><?= esc($y['tur_kisa']) ?></span></td>
                <td class="kucuk-yazi"><?= esc($y['donem_adi']) ?></td>
                <td><?= trTarih($y['son_tarih']) ?></td>
                <td><span class="rozet <?= $k['sinif'] ?>"><?= esc($k['metin']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ============ AJANDA ============ -->
<?php if (! empty($ajanda['liste'])): ?>
<div class="kart">
  <div class="kart-baslik">
    <h2>🗓️ Ajanda — Yaklaşan İşler</h2>
    <div class="sag">
      <?php if ((int) $ajanda['sayaclar']['gecikmis'] > 0): ?>
        <span class="rozet kirmizi"><?= (int) $ajanda['sayaclar']['gecikmis'] ?> gecikmiş</span>
      <?php endif; ?>
      <?php if ((int) $ajanda['sayaclar']['bugun'] > 0): ?>
        <span class="rozet turuncu"><?= (int) $ajanda['sayaclar']['bugun'] ?> bugün</span>
      <?php endif; ?>
      <a href="<?= site_url('ajanda') ?>" class="btn ikincil mini">Tümü</a>
      <a href="<?= site_url('ajanda/yeni') ?>" class="btn mini">+ Yeni</a>
    </div>
  </div>

  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="tablo">
        <thead>
          <tr>
            <th style="width:13%">Tarih</th>
            <th>İş</th>
            <th style="width:16%">İlgili</th>
            <th style="width:10%">Öncelik</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ajanda['liste'] as $ai): ?>
            <tr>
              <td style="white-space:nowrap">
                <b><?= trTarih($ai['tarih']) ?></b>
                <?php if ($ai['gecikmis']): ?>
                  <br><span class="rozet kirmizi" style="font-size:10px">
                    <?= abs($ai['kalan_gun']) ?> gün geçti</span>
                <?php elseif ($ai['bugun']): ?>
                  <br><span class="rozet turuncu" style="font-size:10px">BUGÜN</span>
                <?php elseif ($ai['kalan_gun'] > 0): ?>
                  <br><span class="kucuk-yazi"><?= $ai['kalan_gun'] ?> gün kaldı</span>
                <?php endif; ?>
              </td>
              <td>
                <span style="display:inline-block;width:4px;height:14px;border-radius:99px;
                             vertical-align:middle;margin-right:7px;
                             background:<?= esc($ai['renk_efektif']) ?>"></span>
                <a href="<?= site_url('ajanda/detay/' . (int) $ai['id']) ?>" class="kalin">
                  <?= esc(kisalt($ai['baslik'], 46)) ?>
                </a>
                <?php if (! empty($ai['saat'])): ?>
                  <span class="kucuk-yazi">🕐 <?= substr($ai['saat'], 0, 5) ?></span>
                <?php endif; ?>
                <?php if ($ai['tekrar'] !== 'yok'): ?>
                  <span class="kucuk-yazi" title="Tekrarlı">🔁</span>
                <?php endif; ?>
              </td>
              <td class="kucuk-yazi">
                <?php if (! empty($ai['mukellef_unvan'])): ?>
                  🏢 <?= esc(kisalt($ai['mukellef_unvan'], 20)) ?>
                <?php elseif (! empty($ai['atanan_adi'])): ?>
                  👤 <?= esc($ai['atanan_adi']) ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php
                $rz = ['dusuk' => '', 'normal' => 'mavi', 'yuksek' => 'turuncu', 'acil' => 'kirmizi'];
                ?>
                <span class="rozet <?= $rz[$ai['oncelik']] ?? '' ?>" style="font-size:10.5px">
                  <?= esc(ucfirst($ai['oncelik'])) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ============ KARŞIT İNCELEME UYARISI ============ -->
<?php if (! empty($karsitYaklasan) || (int) ($karsitOzet['gecikmis'] ?? 0) > 0): ?>
<div class="kart">
  <div class="kart-baslik">
    <h2>🔍 Karşıt İnceleme — Cevap Bekleyenler</h2>
    <div class="sag">
      <?php if ((int) ($karsitOzet['gecikmis'] ?? 0) > 0): ?>
        <span class="rozet kirmizi"><?= (int) $karsitOzet['gecikmis'] ?> süresi geçti</span>
      <?php endif; ?>
      <a href="<?= site_url('karsit') ?>" class="btn ikincil mini">Tümü</a>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="tablo">
        <thead><tr><th>Mükellef</th><th>YMM</th><th>Geliş</th><th>Son Cevap</th><th>Kalan</th></tr></thead>
        <tbody>
        <?php foreach ($karsitYaklasan as $ki): $kl = kalanGunMetni($ki['son_cevap_tarihi']); ?>
          <tr class="<?= $kl['gun'] < 0 ? 'gecikmis-satir' : '' ?>">
            <td><a href="<?= site_url('karsit') ?>"><?= esc(kisalt($ki['mukellef_unvan'], 26)) ?></a></td>
            <td class="kucuk-yazi"><?= esc(kisalt($ki['ymm_adi'], 22)) ?></td>
            <td class="kucuk-yazi"><?= trTarih($ki['gelis_tarihi']) ?></td>
            <td class="kucuk-yazi"><?= trTarih($ki['son_cevap_tarihi']) ?></td>
            <td><span class="rozet <?= $kl['sinif'] ?>"><?= esc($kl['metin']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ============ AYLIK GRAFİK ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>📊 <?= $yil ?> Yılı Aylık Beyanname Dağılımı</h2>
    <div class="sag kucuk-yazi">
      <span class="rozet bekliyor">Bekliyor</span>
      <span class="rozet hazir">Hazır</span>
      <span class="rozet onaylandi">Onaylandı</span>
    </div>
  </div>
  <div class="kart-govde">
    <div style="display:flex;align-items:flex-end;gap:8px;height:190px;padding-top:10px">
      <?php
      $enYuksek = 1;
      foreach ($grafik as $g) {
          $enYuksek = max($enYuksek, array_sum($g));
      }
      ?>
      <?php foreach ($grafik as $ayNo => $g): $toplam = array_sum($g); ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:5px;height:100%">
          <div style="flex:1;width:100%;display:flex;flex-direction:column;justify-content:flex-end;gap:2px"
               title="<?= ayAdi($ayNo) ?>: <?= $toplam ?> beyanname">
            <?php
            $renkler = [
                'ONAYLANDI' => '#059669', 'HAZIR' => '#ca8a04', 'BEKLIYOR' => '#cbd5e1',
            ];
            foreach ($renkler as $d => $renk):
                $adet = (int) ($g[$d] ?? 0);
                if ($adet === 0) { continue; }
                $yuk = max(3, round($adet / $enYuksek * 150));
            ?>
              <div style="height:<?= $yuk ?>px;background:<?= $renk ?>;border-radius:3px" title="<?= $d ?>: <?= $adet ?>"></div>
            <?php endforeach; ?>
          </div>
          <div class="kucuk-yazi" style="font-size:10.5px;font-weight:600"><?= ayKisa($ayNo) ?></div>
          <div class="kucuk-yazi" style="font-size:10px"><?= $toplam ?: '' ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ============ EVRAK ÖZETİ ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>📁 <?= ayAdi($ay) ?> <?= $yil ?> Evrak Durumu</h2>
    <div class="sag"><a href="<?= site_url('evrak?yil=' . $yil . '&ay=' . $ay) ?>" class="btn ikincil mini">Evrak Çizelgesi</a></div>
  </div>
  <div class="kart-govde">
    <?php
    $toplamEvrak = ($evrakOzet['GELDI'] ?? 0) + ($evrakOzet['GELMEDI'] ?? 0);
    $oran = $toplamEvrak > 0 ? round(($evrakOzet['GELDI'] ?? 0) / $toplamEvrak * 100) : 0;
    ?>
    <div class="satir arali mb8">
      <div><b><?= (int) ($evrakOzet['GELDI'] ?? 0) ?></b> evrak geldi
        <span class="metin-gri">/ <?= (int) ($evrakOzet['GELMEDI'] ?? 0) ?> gelmedi</span></div>
      <b class="<?= $oran >= 70 ? 'metin-yesil' : 'metin-kirmizi' ?>">%<?= $oran ?></b>
    </div>
    <div class="progress"><div class="dolu" style="width:<?= $oran ?>%"></div></div>

    <?php if ($evrakGelmeyen !== []): ?>
      <div class="bolucu"></div>
      <div class="kucuk-yazi mb8"><b>Hiç evrak getirmeyen mükellefler:</b></div>
      <div class="satir">
        <?php foreach ($evrakGelmeyen as $m): ?>
          <a href="<?= site_url('evrak?yil=' . $yil . '&ay=' . $ay) ?>" class="rozet kirmizi"><?= esc(kisalt($m['unvan'], 24)) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
