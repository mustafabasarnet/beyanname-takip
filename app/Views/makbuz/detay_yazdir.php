<?php
/**
 * TEK MÜKELLEFİN MAKBUZ DÖKÜMÜ — YAZDIRMA
 * Stiller gömülüdür.
 */
$vkn  = $mukellef['vergi_kimlik_no'] ?: $mukellef['tc_kimlik_no'];
$oran = $ucret > 0 ? min(100, (int) round($kesilen / $ucret * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>Makbuz Dökümü — <?= esc($mukellef['unvan']) ?> (<?= (int) $yil ?>)</title>
<style>
*{box-sizing:border-box}
body{background:#fff;color:#0f172a;margin:0;padding:14px 16px;
  font-family:'Segoe UI',system-ui,-apple-system,sans-serif;font-size:11px}
.baslik-blok{display:flex;align-items:flex-end;justify-content:space-between;
  border-bottom:2px solid #0f172a;padding-bottom:7px;margin-bottom:10px;gap:12px}
h1{font-size:16px;margin:0 0 2px}
.alt-bilgi{font-size:10.5px;color:#475569}
.sag-bilgi{text-align:right;font-size:10.5px;color:#475569;white-space:nowrap}
.ozet-serit{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap}
.ozet-kutu{flex:1;min-width:110px;border:1px solid #cbd5e1;border-radius:5px;padding:5px 8px;background:#f8fafc}
.ozet-kutu .et{font-size:9px;text-transform:uppercase;letter-spacing:.3px;color:#64748b;font-weight:700}
.ozet-kutu .dg{font-size:14px;font-weight:700;font-variant-numeric:tabular-nums;margin-top:1px}
.ozet-kutu.mor .dg{color:#6d28d9}.ozet-kutu.yesil .dg{color:#047857}.ozet-kutu.kirmizi .dg{color:#b91c1c}
table{width:100%;border-collapse:collapse;margin-bottom:10px}
th,td{border:1px solid #cbd5e1;padding:4px 6px;vertical-align:middle}
thead th{background:#1e293b;color:#fff;font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:.3px;text-align:left}
thead{display:table-header-group}
tr{page-break-inside:avoid}
.sag{text-align:right}.orta{text-align:center}.kalin{font-weight:700}
.kucuk{font-size:9.5px;color:#64748b}
.para{font-variant-numeric:tabular-nums;white-space:nowrap}
tbody tr:nth-child(even){background:#f8fafc}
tr.tahsil td{background:#f0fdf4}
tfoot tr td{background:#0f172a;color:#fff;font-weight:700;font-size:12px;border-color:#0f172a}
.rozet{display:inline-block;padding:1px 5px;border-radius:3px;font-size:8.5px;font-weight:700}
.rozet.yesil{background:#d1fae5;color:#065f46}
.rozet.gri{background:#e2e8f0;color:#475569}
.imza{margin-top:22px;display:flex;gap:40px;font-size:10.5px}
.imza div{flex:1;border-top:1px solid #94a3b8;padding-top:4px;text-align:center;color:#475569}
.arac-cubugu{margin-bottom:10px;display:flex;gap:8px;padding:8px 10px;background:#f1f5f9;border-radius:6px}
.arac-cubugu a,.arac-cubugu button{font:inherit;padding:5px 11px;border-radius:5px;border:1px solid #cbd5e1;
  background:#fff;color:#0f172a;text-decoration:none;cursor:pointer;font-size:11px}
.arac-cubugu button{background:#2563eb;color:#fff;border-color:#2563eb;font-weight:600}
@page{size:A4 portrait;margin:11mm}
@media print{body{padding:0}.yazdirma-gizle{display:none!important}}
</style>
</head>
<body>

<div class="arac-cubugu yazdirma-gizle">
  <button onclick="window.print()">🖨️ Yazdır</button>
  <a href="<?= site_url('makbuz/detay/' . (int) $mukellef['id'] . '?yil=' . (int) $yil) ?>">← Ekrana Dön</a>
</div>

<div class="baslik-blok">
  <div>
    <h1><?= esc($mukellef['unvan']) ?></h1>
    <div class="alt-bilgi"><?= esc($vkn) ?> · <?= (int) $yil ?> yılı serbest meslek makbuzu dökümü</div>
  </div>
  <div class="sag-bilgi"><?= date('d.m.Y H:i') ?><br><?= esc($aktifKullanici['ad_soyad'] ?? '') ?></div>
</div>

<div class="ozet-serit">
  <div class="ozet-kutu mor">
    <div class="et">Yıllık Sözleşme</div>
    <div class="dg"><?= number_format($ucret, 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu yesil">
    <div class="et">Kesilen (Brüt)</div>
    <div class="dg"><?= number_format($kesilen, 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu kirmizi">
    <div class="et">Kalan</div>
    <div class="dg"><?= number_format($kalan, 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu">
    <div class="et">Makbuz Adedi</div>
    <div class="dg"><?= count($makbuzlar) ?></div>
  </div>
  <div class="ozet-kutu">
    <div class="et">Tamamlanma</div>
    <div class="dg">%<?= $oran ?></div>
  </div>
</div>

<?php $t = ['brut' => 0.0, 'stopaj' => 0.0, 'kdv' => 0.0, 'net' => 0.0]; ?>
<table>
  <thead>
    <tr>
      <th class="orta" style="width:4%">#</th>
      <th style="width:10%">Tarih</th>
      <th style="width:11%">Makbuz No</th>
      <th style="width:16%">Kesen Müşavir</th>
      <th class="sag">Brüt</th>
      <th class="sag">Stopaj</th>
      <th class="sag">KDV</th>
      <th class="sag">Net</th>
      <th class="orta" style="width:10%">Tahsilat</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = 0; ?>
    <?php foreach ($makbuzlar as $m): ?>
      <?php
        $i++;
        $t['brut']   += (float) $m['brut'];
        $t['stopaj'] += (float) $m['stopaj'];
        $t['kdv']    += (float) $m['kdv'];
        $t['net']    += (float) $m['net'];
      ?>
      <tr class="<?= ! empty($m['tahsil_edildi']) ? 'tahsil' : '' ?>">
        <td class="orta kucuk"><?= $i ?></td>
        <td><?= trTarih($m['tarih']) ?></td>
        <td><?= esc($m['makbuz_no'] ?: '—') ?></td>
        <td class="kucuk"><?= esc($m['musavir_adi'] ?? '—') ?></td>
        <td class="sag para"><?= number_format((float) $m['brut'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format((float) $m['stopaj'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format((float) $m['kdv'], 2, ',', '.') ?></td>
        <td class="sag para kalin"><?= number_format((float) $m['net'], 2, ',', '.') ?></td>
        <td class="orta">
          <?php if (! empty($m['tahsil_edildi'])): ?>
            <span class="rozet yesil">Tahsil<?= ! empty($m['tahsil_tarihi']) ? ' ' . trTarih($m['tahsil_tarihi']) : '' ?></span>
          <?php else: ?>
            <span class="rozet gri">Bekliyor</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($makbuzlar === []): ?>
      <tr><td colspan="9" class="orta kucuk"><?= (int) $yil ?> yılında bu mükellefe kesilmiş makbuz yok.</td></tr>
    <?php endif; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4">TOPLAM (<?= $i ?> makbuz)</td>
      <td class="sag para"><?= number_format($t['brut'], 2, ',', '.') ?></td>
      <td class="sag para"><?= number_format($t['stopaj'], 2, ',', '.') ?></td>
      <td class="sag para"><?= number_format($t['kdv'], 2, ',', '.') ?></td>
      <td class="sag para"><?= number_format($t['net'], 2, ',', '.') ?></td>
      <td></td>
    </tr>
  </tfoot>
</table>

<div class="imza">
  <div>Düzenleyen</div>
  <div>Mükellef</div>
</div>

</body></html>
