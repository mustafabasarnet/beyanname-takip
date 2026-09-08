<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="kart-baslik" style="margin:0 0 12px">
  <h2>📤 Bildirim Görevleri</h2>
  <span class="kucuk-yazi">Süresi dolan/yaklaşan bildirimleri tek ekrandan yönetin.</span>
</div>

<!-- Sayaç kartları (aynı zamanda filtre) -->
<?php
$aktifAralik = $filtre['aralik'] ?? '';
$kartlar = [
  ''          => ['Toplam',      $sayac['toplam'],     ''],
  'gecikti'   => ['⏰ Süresi Geçti', $sayac['gecikti'], 'kirmizi'],
  'bugun'     => ['🔴 Bugün Son Gün', $sayac['bugun'], 'turuncu'],
  'ic3'       => ['≤ 3 Gün',      $sayac['ic3'],       'sari'],
  'ic7'       => ['≤ 7 Gün',      $sayac['ic7'],       'mavi'],
  'ic15'      => ['≤ 15 Gün',     $sayac['ic15'],      'gri'],
  'bekleyen'  => ['Bekleyen',     $sayac['bekleyen'],  'mavi'],
  'tamamlanan'=> ['✓ Tamamlanan', $sayac['tamamlanan'],'yesil'],
];
?>
<div class="stat-grid">
  <?php foreach ($kartlar as $kod => $veri): ?>
    <?php
    $url = site_url('sicil/gorevler' . ($kod === '' ? '' : '?aralik=' . $kod));
    $secili = ($aktifAralik === $kod);
    ?>
    <a href="<?= $url ?>" class="stat <?= $veri[2] ?> <?= $secili ? 'secili' : '' ?>"
       style="text-decoration:none;color:inherit;border:<?= $secili ? '2px solid var(--ana)' : '1px solid transparent' ?>">
      <div class="etiket"><?= $veri[0] ?></div>
      <div class="deger"><?= number_format((int) $veri[1], 0, ',', '.') ?></div>
    </a>
  <?php endforeach; ?>
</div>

<!-- Filtre -->
<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Durum</label>
    <select name="durum" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($durumlar as $k => $v): ?>
        <option value="<?= $k ?>" <?= ! is_array($filtre['durum'] ?? null) && ($filtre['durum'] ?? '') === $k ? 'selected' : '' ?>>
          <?= esc($v) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-grup">
    <label>Kurum</label>
    <select name="kurum" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($kurumlar as $kid => $kad): ?>
        <option value="<?= $kid ?>" <?= ! is_array($filtre['kurum_id'] ?? null) && (int) ($filtre['kurum_id'] ?? 0) === (int) $kid ? 'selected' : '' ?>>
          <?= esc($kad) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-grup" style="min-width:180px">
    <label>Ara</label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>" placeholder="Mükellef / VKN / kurum">
  </div>
  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url('sicil/gorevler') ?>" class="btn ikincil kucuk">Temizle</a>
    <a href="<?= site_url('sicil') ?>" class="btn ikincil kucuk">← Sicil Değişiklikleri</a>
  </div>
</form>

<!-- Liste -->
<div class="kart">
  <div class="kart-baslik"><h2>Görevler (<?= count($kayitlar) ?>)</h2></div>
  <div class="kart-govde sikisik">
    <?php if ($kayitlar === []): ?>
      <div class="tablo-bos"><span class="ikon">🎉</span>Bu filtreyle görev yok.</div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead>
            <tr>
              <th>Kurum</th><th>Mükellef</th><th>Değişiklik</th><th>Son Tarih</th>
              <th>Durum</th><th>Yapan</th><th class="sag">İşlem</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $bugun = date('Y-m-d');
          foreach ($kayitlar as $g):
            $gecikti = in_array($g['durum'], ['BEKLIYOR','HAZIR','GONDERILDI'], true)
                && $g['son_tarih'] && $g['son_tarih'] < $bugun;
          ?>
            <tr>
              <td><b><?= esc($g['kurum_ad']) ?></b></td>
              <td>
                <a href="<?= site_url('mukellefler/detay/' . (int) ($g['mukellef_id'] ?? 0)) ?>" class="kalin">
                  <?= esc(kisalt($g['mukellef_unvan'], 26)) ?></a>
                <div class="kucuk-yazi"><?= esc($g['vergi_kimlik_no'] ?: $g['tc_kimlik_no']) ?></div>
              </td>
              <td class="kucuk-yazi">
                <span class="rozet mavi"><?= esc(kisalt($g['tur_ad'], 18)) ?></span>
                <div><?= trTarih($g['degisiklik_tarihi']) ?></div>
              </td>
              <td class="<?= $gecikti ? 'metin-kirmizi' : '' ?>" style="white-space:nowrap">
                <b><?= $g['son_tarih'] ? trTarih($g['son_tarih']) : '—' ?></b>
                <?php if ($g['son_tarih']): ?>
                  <div class="kucuk-yazi">
                    <?php if (in_array($g['durum'], ['TAMAM','GEREKSIZ'], true)): ?>
                      <span class="rozet <?= $g['durum'] === 'TAMAM' ? 'yesil' : 'gri' ?>">
                        <?= $g['durum'] === 'TAMAM' ? '✓ Tamam' : 'İptal' ?></span>
                    <?php else: ?>
                      <?php $kg = kalanGunMetni($g['son_tarih'], $g['durum']); ?>
                      <span class="rozet <?= $kg['sinif'] ?>"><?= esc($kg['metin']) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <select class="girdi ed-durum" data-gorev="<?= (int) $g['id'] ?>" style="padding:4px 8px;font-size:12px;max-width:130px">
                  <?php foreach ($durumlar as $dk => $dv): ?>
                    <option value="<?= $dk ?>" <?= $g['durum'] === $dk ? 'selected' : '' ?>><?= esc($dv) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="kucuk-yazi"><?= esc($g['yapan_adi'] ?: '—') ?></td>
              <td class="sag">
                <a href="<?= site_url('sicil/gorev/' . (int) $g['id']) ?>" class="btn ikincil mini">Detay</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var bildirimEl = document.createElement('div');
  bildirimEl.id = 'sicil-ust-bildirim';
  bildirimEl.style.marginBottom = '10px';
  var ilk = document.querySelector('.kart-baslik');
  if (ilk) ilk.after(bildirimEl);
  function mesaj(m, tip) {
    bildirimEl.innerHTML = '<div class="uyari ' + (tip === 'hata' ? 'hata' : 'basari') + '"><span class="ik">'
      + (tip === 'hata' ? '✕' : '✓') + '</span><div>' + m + '</div></div>';
    setTimeout(function () { bildirimEl.innerHTML = ''; }, 4000);
  }
  document.querySelectorAll('.ed-durum').forEach(function (sel) {
    sel.addEventListener('change', function () {
      BT.post('<?= site_url('sicil/gorev-durum') ?>', { id: sel.dataset.gorev, durum: sel.value })
        .then(function (j) { mesaj(j.mesaj || 'Güncellendi.', 'ok'); setTimeout(function () { location.reload(); }, 400); })
        .catch(function (e) { mesaj(e.message, 'hata'); });
    });
  });
})();
</script>

<?= $this->endSection() ?>
