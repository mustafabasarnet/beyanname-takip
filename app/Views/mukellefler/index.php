<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<form method="get" class="filtre-bar">
  <?php if (! empty($filtre['harf'])): ?>
    <!-- Seçili harf, diğer filtreler değişince de korunur -->
    <input type="hidden" name="harf" value="<?= esc($filtre['harf']) ?>">
  <?php endif; ?>
  <div class="form-grup" style="min-width:200px">
    <label>Ara</label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>" placeholder="Ünvan, VKN, TCKN, kod">
  </div>
  <div class="form-grup">
    <label>Durum</label>
    <select name="durum" data-oto-filtre>
      <?php foreach (['aktif'=>'Faal Olanlar','terk'=>'Terk Edenler','pasif'=>'Pasif','hepsi'=>'Tümü'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= ($filtre['durum'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-grup">
    <label>Tip</label>
    <select name="tip" data-oto-filtre>
      <option value="">Tümü</option>
      <option value="gercek" <?= ($filtre['tip'] ?? '') === 'gercek' ? 'selected' : '' ?>>Gerçek Kişi</option>
      <option value="tuzel"  <?= ($filtre['tip'] ?? '') === 'tuzel'  ? 'selected' : '' ?>>Tüzel Kişi</option>
    </select>
  </div>
  <?php if (count($musavirler) > 1): ?>
    <div class="form-grup">
      <label>Mali Müşavir</label>
      <select name="musavir_id" data-oto-filtre>
        <option value="">Tümü</option>
        <?php foreach ($musavirler as $mid => $mad): ?>
          <option value="<?= $mid ?>" <?= (int) ($secilenMusavir ?? 0) === (int) $mid ? 'selected' : '' ?>><?= esc($mad) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <div class="form-grup">
    <label class="onay" style="margin-top:18px">
      <input type="checkbox" name="gg" value="1" <?= ! empty($filtre['genc_girisimci']) ? 'checked' : '' ?> data-oto-filtre>
      🌱 Genç Girişimci
    </label>
  </div>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url('mukellefler/yeni') ?>" class="btn yesil kucuk">+ Yeni Mükellef</a>
    <?php if (! empty($maliYetki)): ?>
      <a href="<?= site_url('mukellefler/ice-aktar') ?>" class="btn mor kucuk"
         title="Excel/CSV dosyasından toplu mükellef aktarın">📥 Excel’den Aktar</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($hatalar = session('aktarma_hatalari')): ?>
  <div class="uyari hata mb16">
    <span class="ik">⚠</span>
    <div>
      <b>Aktarılamayan satırlar (<?= count($hatalar) ?>)</b>
      <ul>
        <?php foreach (array_slice($hatalar, 0, 20) as $h): ?>
          <li><?= (int) $h['satir'] ?>. satır — <?= esc($h['unvan']) ?>: <?= esc($h['mesaj']) ?></li>
        <?php endforeach; ?>
        <?php if (count($hatalar) > 20): ?>
          <li>… ve <?= count($hatalar) - 20 ?> satır daha</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<!-- ============ ALFABE ŞERİDİ ============ -->
<?php
// Harf bağlantıları mevcut filtreleri korur
$temelSorgu = array_filter([
    'q'           => $filtre['q'] ?? null,
    'durum'       => ($filtre['durum'] ?? 'aktif') !== 'aktif' ? $filtre['durum'] : null,
    'tip'         => $filtre['tip'] ?? null,
    'gg'          => ! empty($filtre['genc_girisimci']) ? 1 : null,
    'musavir_id'  => $secilenMusavir ?: null,
], static fn ($v) => $v !== null && $v !== '');

$harfLink = static function (?string $harf) use ($temelSorgu) {
    $s = $temelSorgu;

    if ($harf !== null) {
        $s['harf'] = $harf;
    }

    return site_url('mukellefler') . ($s !== [] ? '?' . http_build_query($s) : '');
};
?>
<!--
  Stiller bilerek bu dosyaya gömülüdür: stil.css sunucuya kopyalanmasa bile
  şerit her zaman doğru görünsün. Renkler ana temanın değişkenlerinden gelir,
  değişken tanımlı değilse yedek değerler devreye girer.
-->
<style>
.alfabe-kart{
  background:#fff;
  border:1px solid var(--gri-200, #e2e8f0);
  border-radius:var(--radius, 12px);
  box-shadow:var(--golge, 0 1px 3px rgba(15,23,42,.08));
  padding:12px 14px;
  margin-bottom:18px;
}
.alfabe-ust{
  display:flex;align-items:center;gap:8px;
  margin-bottom:10px;flex-wrap:wrap;
}
.alfabe-baslik{
  font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;
  color:var(--gri-500, #64748b);display:flex;align-items:center;gap:6px;
}
.alfabe-temizle{
  margin-left:auto;font-size:12px;font-weight:600;text-decoration:none;
  color:var(--kirmizi, #dc2626);padding:3px 10px;border-radius:99px;
  background:var(--kirmizi-acik, #fee2e2);transition:.15s;
}
.alfabe-temizle:hover{filter:brightness(.95)}

.alfabe{
  display:flex;flex-wrap:wrap;gap:5px;align-items:stretch;
}
.alfabe a{
  position:relative;
  display:inline-flex;flex-direction:column;align-items:center;justify-content:center;
  min-width:34px;padding:5px 8px 4px;
  border-radius:8px;
  font-size:13.5px;font-weight:700;line-height:1.15;
  text-decoration:none;
  color:var(--gri-700, #334155);
  background:var(--gri-50, #f8fafc);
  border:1px solid var(--gri-200, #e2e8f0);
  transition:transform .12s, background .12s, border-color .12s, box-shadow .12s;
}
.alfabe a .adet{
  font-size:9.5px;font-weight:600;margin-top:1px;
  color:var(--gri-500, #64748b);letter-spacing:.2px;
}
.alfabe a:hover{
  background:var(--ana-acik, #dbeafe);
  border-color:var(--ana, #2563eb);
  color:var(--ana-koyu, #1d4ed8);
  transform:translateY(-1px);
  box-shadow:var(--golge-md, 0 4px 12px rgba(15,23,42,.08));
}
.alfabe a:hover .adet{color:var(--ana-koyu, #1d4ed8)}

.alfabe a.aktif{
  background:var(--ana, #2563eb);
  border-color:var(--ana, #2563eb);
  color:#fff;
  box-shadow:0 2px 8px rgba(37,99,235,.35);
}
.alfabe a.aktif .adet{color:rgba(255,255,255,.85)}

.alfabe a.tumu{
  min-width:auto;padding:5px 14px 4px;
  background:#fff;border-color:var(--gri-300, #cbd5e1);
}
.alfabe a.tumu.aktif{
  background:var(--ana, #2563eb);border-color:var(--ana, #2563eb);color:#fff;
}

.alfabe a.bos{
  opacity:.4;pointer-events:none;
  background:#fff;border-style:dashed;
  color:var(--gri-400, #94a3b8);
}

.alfabe .ayrac{
  width:1px;align-self:stretch;margin:2px 5px;
  background:var(--gri-200, #e2e8f0);
}

@media (max-width:760px){
  .alfabe a{min-width:30px;padding:4px 6px 3px;font-size:12.5px}
  .alfabe a .adet{font-size:9px}
}
</style>

<div class="alfabe-kart">
  <div class="alfabe-ust">
    <span class="alfabe-baslik">🔤 Ünvana Göre Filtrele</span>

    <?php if ($seciliHarf !== null): ?>
      <span class="kucuk-yazi" style="color:var(--gri-500,#64748b)">
        <b>"<?= esc($seciliHarf) ?>"</b> ile başlayan
        <b><?= number_format((int) $toplamKayit, 0, ',', '.') ?></b> mükellef
      </span>
      <a href="<?= $harfLink(null) ?>" class="alfabe-temizle">✕ Filtreyi kaldır</a>
    <?php endif; ?>
  </div>

  <div class="alfabe">
    <a href="<?= $harfLink(null) ?>"
       class="tumu <?= $seciliHarf === null ? 'aktif' : '' ?>"
       title="Tüm mükellefler">
      Tümü<span class="adet"><?= number_format((int) $harfsizToplam, 0, ',', '.') ?></span>
    </a>
    <span class="ayrac"></span>

    <?php foreach ($alfabe as $h): ?>
      <?php $adet = (int) ($harfDagilimi[$h] ?? 0); ?>
      <a href="<?= $adet > 0 ? $harfLink($h) : '#' ?>"
         class="<?= $seciliHarf === $h ? 'aktif' : ($adet === 0 ? 'bos' : '') ?>"
         title="<?= $h === '#' ? 'Sayı/sembol ile başlayanlar' : esc($h) . ' ile başlayanlar' ?>: <?= $adet ?> mükellef">
        <?= esc($h) ?>
        <span class="adet"><?= $adet > 0 ? $adet : '–' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="stat-grid">
  <div class="stat"><div class="etiket">Toplam</div><div class="deger"><?= (int) $istatistik['toplam'] ?></div></div>
  <div class="stat yesil"><div class="etiket">Faal</div><div class="deger"><?= (int) $istatistik['faal'] ?></div></div>
  <div class="stat kirmizi"><div class="etiket">Terk</div><div class="deger"><?= (int) $istatistik['terk'] ?></div></div>
  <div class="stat mor"><div class="etiket">Kurum</div><div class="deger"><?= (int) $istatistik['tuzel'] ?></div>
    <div class="alt"><?= (int) $istatistik['gercek'] ?> şahıs</div></div>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>🏢 Mükellef Listesi (<?= count($mukellefler) ?>)</h2>
    <?php if (! empty($yoneticiMi) && $mukellefler !== []): ?>
      <div class="btn-grup" style="margin-left:auto">
        <span class="kucuk-yazi" id="secim-metni" style="align-self:center"></span>
        <button type="button" class="btn kirmizi kucuk gizle" id="toplu-sil-btn"
                onclick="topluSil()">🗑 Seçilenleri Sil</button>
      </div>
    <?php endif; ?>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($mukellefler === []): ?>
      <div class="tablo-bos"><span class="ikon">📭</span>Mükellef bulunamadı.
        <div class="mt16"><a href="<?= site_url('mukellefler/yeni') ?>" class="btn kucuk">+ İlk Mükellefi Ekle</a></div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead><tr>
            <?php if (! empty($yoneticiMi)): ?>
              <th style="width:34px"><input type="checkbox" id="hepsi" onclick="tumSec(this.checked)"></th>
            <?php endif; ?>
            <th>Kod</th><th>Ünvan</th><th>VKN / TCKN</th><th>Tip</th>
            <th>İşe Başlama</th><th>Durum</th><th>Mali Müşavir</th><th>Sorumlu</th><th class="sag">İşlem</th>
          </tr></thead>
          <tbody>
          <?php foreach ($mukellefler as $m):
            $durum = ['metin'=>'Faal','sinif'=>'yesil'];
            if ((int) $m['aktif'] === 0) { $durum = ['metin'=>'Pasif','sinif'=>'gri']; }
            elseif (! empty($m['terk_tarihi'])) {
              $durum = $m['terk_tarihi'] < date('Y-m-d')
                ? ['metin'=>'Terk '.trTarih($m['terk_tarihi']),'sinif'=>'kirmizi']
                : ['metin'=>'Terk Edecek','sinif'=>'turuncu'];
            }
          ?>
            <tr>
              <?php if (! empty($yoneticiMi)): ?>
                <td><input type="checkbox" class="muk-sec" value="<?= (int) $m['id'] ?>"
                           data-unvan="<?= esc($m['unvan'], 'attr') ?>"></td>
              <?php endif; ?>
              <td class="kucuk-yazi"><?= esc($m['kod'] ?: '-') ?></td>
              <td>
                <a href="<?= site_url('mukellefler/detay/' . $m['id']) ?>" class="kalin"><?= esc($m['unvan']) ?></a>
                <?= gencGirisimciRozet($m, null, true) ?>
                <?php if (! empty($m['faaliyet_konusu'])): ?>
                  <div class="kucuk-yazi"><?= esc(kisalt($m['faaliyet_konusu'], 45)) ?></div>
                <?php endif; ?>
              </td>
              <td class="kucuk-yazi"><?= esc(vknTckn($m)) ?></td>
              <td><span class="rozet <?= $m['mukellef_tipi'] === 'tuzel' ? 'mor' : 'mavi' ?>">
                <?= $m['mukellef_tipi'] === 'tuzel' ? 'Kurum' : 'Şahıs' ?></span></td>
              <td class="kucuk-yazi"><?= trTarih($m['ise_baslama_tarihi']) ?></td>
              <td><span class="rozet <?= $durum['sinif'] ?>"><?= esc($durum['metin']) ?></span></td>
              <td class="kucuk-yazi">
                <span class="rozet gri" style="background:<?= esc($m['musavir_renk'] ?? '#e2e8f0') ?>22;color:<?= esc($m['musavir_renk'] ?? '#475569') ?>">
                  <?= esc(kisalt($m['musavir_adi'] ?? '-', 18)) ?></span>
              </td>
              <td class="kucuk-yazi"><?= esc(kisalt($m['sorumlu_adi'] ?? '-', 16)) ?></td>
              <td class="sag" style="white-space:nowrap">
                <a href="<?= site_url('mukellefler/detay/' . $m['id']) ?>" class="btn ikincil mini">Detay</a>
                <a href="<?= site_url('mukellefler/duzenle/' . $m['id']) ?>" class="btn ikincil mini">Düzenle</a>
                <a href="<?= site_url('mukellefler/sil/' . $m['id']) ?>" class="btn kirmizi mini"
                   data-onay="'<?= esc($m['unvan'], 'js') ?>' silinecek. Emin misiniz?">Sil</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>

<?php if (! empty($yoneticiMi)): ?>
<?= $this->section('script') ?>
<script>
// ---------- Mükellef toplu silme (yalnızca yönetici) ----------
function tumSec(deger) {
  document.querySelectorAll('.muk-sec').forEach(function (c) { c.checked = deger; });
  secimGuncelle();
}

function secimGuncelle() {
  var n = document.querySelectorAll('.muk-sec:checked').length;
  var btn = document.getElementById('toplu-sil-btn');
  var metin = document.getElementById('secim-metni');

  btn.className = 'btn kirmizi kucuk' + (n === 0 ? ' gizle' : '');
  metin.textContent = n > 0 ? n + ' mükellef seçildi' : '';

  var h = document.getElementById('hepsi');
  var t = document.querySelectorAll('.muk-sec').length;
  if (h) { h.checked = (n > 0 && n === t); h.indeterminate = (n > 0 && n < t); }
}

document.querySelectorAll('.muk-sec').forEach(function (c) {
  c.addEventListener('change', secimGuncelle);
});

function topluSil() {
  var secili = Array.prototype.slice.call(document.querySelectorAll('.muk-sec:checked'));
  if (!secili.length) { BT.bildir('Hiç mükellef seçilmedi.', 'hata'); return; }

  var adlar = secili.slice(0, 5).map(function (c) { return '• ' + c.dataset.unvan; }).join('\n');
  if (secili.length > 5) { adlar += '\n• … ve ' + (secili.length - 5) + ' mükellef daha'; }

  if (!confirm(secili.length + ' mükellef çöp kutusuna taşınacak:\n\n' + adlar
             + '\n\nBeyanname ve evrak kayıtları korunur; Çöp Kutusu’ndan geri alabilirsiniz.\n\nDevam edilsin mi?')) {
    return;
  }

  var btn = document.getElementById('toplu-sil-btn');
  btn.disabled = true;
  btn.textContent = '⏳ Siliniyor…';

  BT.post('<?= site_url('sistem/mukellef-toplu-sil') ?>', {
    idler: secili.map(function (c) { return c.value; })
  })
    .then(function (j) {
      BT.bildir(j.mesaj, 'basari');
      setTimeout(function () { location.reload(); }, 900);
    })
    .catch(function (e) {
      BT.bildir(e.message, 'hata');
      btn.disabled = false;
      btn.textContent = '🗑 Seçilenleri Sil';
    });
}

secimGuncelle();
</script>
<?= $this->endSection() ?>
<?php endif; ?>
