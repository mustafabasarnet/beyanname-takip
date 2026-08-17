<?php
/**
 * AJANDA — YAZDIRMA
 * Stiller gömülüdür: stil.css kopyalanmasa da çıktı düzgün olur.
 */
$bugun = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>Ajanda Listesi</title>
<style>
*{box-sizing:border-box}
body{background:#fff;color:#0f172a;margin:0;padding:14px 16px;
  font-family:'Segoe UI',system-ui,-apple-system,sans-serif;font-size:11px}
.baslik-blok{display:flex;align-items:flex-end;justify-content:space-between;
  border-bottom:2px solid #0f172a;padding-bottom:7px;margin-bottom:10px;gap:12px}
h1{font-size:16px;margin:0 0 2px}
.alt-bilgi{font-size:10.5px;color:#475569}
.sag-bilgi{text-align:right;font-size:10.5px;color:#475569;white-space:nowrap}
table{width:100%;border-collapse:collapse;margin-bottom:10px}
th,td{border:1px solid #cbd5e1;padding:4px 6px;vertical-align:top}
thead th{background:#1e293b;color:#fff;font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:.3px;text-align:left}
thead{display:table-header-group}
tr{page-break-inside:avoid}
.sag{text-align:right}.orta{text-align:center}.kalin{font-weight:700}
.kucuk{font-size:9.5px;color:#64748b}
tbody tr:nth-child(even){background:#f8fafc}
tr.gecikmis td{background:#fef2f2}
tr.bugun td{background:#eff6ff}
tr.kapali td{color:#94a3b8}
tr.kapali .is{text-decoration:line-through}
.rozet{display:inline-block;padding:1px 5px;border-radius:3px;
  font-size:8.5px;font-weight:700;white-space:nowrap}
.rozet.acil{background:#fee2e2;color:#991b1b}
.rozet.yuksek{background:#ffedd5;color:#9a3412}
.rozet.normal{background:#dbeafe;color:#1e40af}
.rozet.dusuk{background:#f1f5f9;color:#475569}
.rozet.gec{background:#dc2626;color:#fff}
.ozet{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap}
.ozet div{flex:1;min-width:100px;border:1px solid #cbd5e1;border-radius:5px;
  padding:5px 8px;background:#f8fafc}
.ozet .et{font-size:9px;text-transform:uppercase;letter-spacing:.3px;
  color:#64748b;font-weight:700}
.ozet .dg{font-size:14px;font-weight:700;margin-top:1px}
.imza{margin-top:22px;display:flex;gap:40px;font-size:10.5px}
.imza div{flex:1;border-top:1px solid #94a3b8;padding-top:4px;text-align:center;color:#475569}
.arac-cubugu{margin-bottom:10px;display:flex;gap:8px;padding:8px 10px;background:#f1f5f9;border-radius:6px}
.arac-cubugu a,.arac-cubugu button{font:inherit;padding:5px 11px;border-radius:5px;
  border:1px solid #cbd5e1;background:#fff;color:#0f172a;text-decoration:none;
  cursor:pointer;font-size:11px}
.arac-cubugu button{background:#2563eb;color:#fff;border-color:#2563eb;font-weight:600}
@page{size:A4 portrait;margin:11mm}
@media print{body{padding:0}.yazdirma-gizle{display:none!important}}
</style>
</head>
<body>

<div class="arac-cubugu yazdirma-gizle">
  <button onclick="window.print()">🖨️ Yazdır</button>
  <a href="<?= site_url('ajanda') ?>">← Ekrana Dön</a>
</div>

<?php
$sayac = ['gecikmis' => 0, 'bugun' => 0, 'bekleyen' => 0, 'yapildi' => 0];

foreach ($kayitlar as $k) {
    if ($k['durum'] === 'YAPILDI') {
        $sayac['yapildi']++;
    } elseif ($k['durum'] === 'BEKLIYOR') {
        $sayac['bekleyen']++;

        if ($k['gecikmis']) {
            $sayac['gecikmis']++;
        } elseif ($k['bugun']) {
            $sayac['bugun']++;
        }
    }
}
?>

<div class="baslik-blok">
  <div>
    <h1>Ajanda Listesi</h1>
    <div class="alt-bilgi">
      <?php if (! empty($filtre['bas']) || ! empty($filtre['bit'])): ?>
        <?= ! empty($filtre['bas']) ? trTarih($filtre['bas']) : '…' ?>
        – <?= ! empty($filtre['bit']) ? trTarih($filtre['bit']) : '…' ?>
      <?php else: ?>
        Tüm tarihler
      <?php endif; ?>
      <?php if (! empty($filtre['durum'])): ?>
        · Durum: <?= esc($durumlar[$filtre['durum']] ?? $filtre['durum']) ?>
      <?php endif; ?>
      <?php if (! empty($filtre['oncelik'])): ?>
        · Öncelik: <?= esc($oncelikler[$filtre['oncelik']] ?? $filtre['oncelik']) ?>
      <?php endif; ?>
      <?php if (! empty($filtre['etiket'])): ?> · Etiket: <?= esc($filtre['etiket']) ?><?php endif; ?>
      <?php if (! empty($filtre['q'])): ?> · Arama: "<?= esc($filtre['q']) ?>"<?php endif; ?>
    </div>
  </div>
  <div class="sag-bilgi">
    <?= date('d.m.Y H:i') ?><br><?= esc($aktifKullanici['ad_soyad'] ?? '') ?>
  </div>
</div>

<div class="ozet">
  <div><div class="et">Toplam</div><div class="dg"><?= count($kayitlar) ?></div></div>
  <div><div class="et">Bekleyen</div><div class="dg"><?= $sayac['bekleyen'] ?></div></div>
  <div><div class="et">Gecikmiş</div><div class="dg" style="color:#b91c1c"><?= $sayac['gecikmis'] ?></div></div>
  <div><div class="et">Bugün</div><div class="dg" style="color:#c2410c"><?= $sayac['bugun'] ?></div></div>
  <div><div class="et">Tamamlanan</div><div class="dg" style="color:#047857"><?= $sayac['yapildi'] ?></div></div>
</div>

<table>
  <thead>
    <tr>
      <th class="orta" style="width:4%">#</th>
      <th style="width:11%">Tarih</th>
      <th>İş</th>
      <th style="width:13%">İlgili / Atanan</th>
      <th style="width:9%">Öncelik</th>
      <th style="width:9%">Durum</th>
      <th class="orta" style="width:5%">✓</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = 0; ?>
    <?php foreach ($kayitlar as $k): ?>
      <?php
        $i++;
        $sinif = $k['durum'] !== 'BEKLIYOR' ? 'kapali'
               : ($k['gecikmis'] ? 'gecikmis' : ($k['bugun'] ? 'bugun' : ''));
      ?>
      <tr class="<?= $sinif ?>">
        <td class="orta kucuk"><?= $i ?></td>
        <td>
          <b><?= trTarih($k['tarih']) ?></b>
          <?php if (! empty($k['saat'])): ?>
            <div class="kucuk"><?= substr($k['saat'], 0, 5) ?></div>
          <?php endif; ?>
          <?php if ($k['gecikmis']): ?>
            <span class="rozet gec"><?= abs($k['kalan_gun']) ?> gün geçti</span>
          <?php endif; ?>
        </td>
        <td>
          <span class="is kalin"><?= esc($k['baslik']) ?></span>
          <?php if (! empty($k['etiket'])): ?>
            <span class="kucuk">[<?= esc($k['etiket']) ?>]</span>
          <?php endif; ?>
          <?php if ($k['tekrar'] !== 'yok'): ?><span class="kucuk">🔁</span><?php endif; ?>
          <?php if (! empty($k['aciklama'])): ?>
            <div class="kucuk"><?= esc(kisalt($k['aciklama'], 110)) ?></div>
          <?php endif; ?>
        </td>
        <td class="kucuk">
          <?php if (! empty($k['mukellef_unvan'])): ?>
            <?= esc(kisalt($k['mukellef_unvan'], 26)) ?><br>
          <?php endif; ?>
          <?php if (! empty($k['atanan_adi'])): ?>
            👤 <?= esc($k['atanan_adi']) ?>
          <?php endif; ?>
        </td>
        <td>
          <span class="rozet <?= esc($k['oncelik']) ?>">
            <?= esc($oncelikler[$k['oncelik']] ?? $k['oncelik']) ?>
          </span>
        </td>
        <td class="kucuk"><?= esc($durumlar[$k['durum']] ?? $k['durum']) ?></td>
        <td class="orta"><?= $k['durum'] === 'BEKLIYOR' ? '☐' : '☑' ?></td>
      </tr>
    <?php endforeach; ?>

    <?php if ($kayitlar === []): ?>
      <tr><td colspan="7" class="orta kucuk" style="padding:20px">
        Filtreye uyan ajanda kaydı bulunamadı.
      </td></tr>
    <?php endif; ?>
  </tbody>
</table>

<div class="imza">
  <div>Hazırlayan</div>
  <div>Kontrol Eden</div>
</div>

</body></html>
