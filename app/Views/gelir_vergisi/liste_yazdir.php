<?php
/**
 * GELİR VERGİSİ — TÜM MÜŞAVİRLER LİSTESİ YAZDIRMA
 * Stiller gömülüdür.
 */
?>
<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>Vergi Yükü Özeti — <?= (int) $yil ?></title>
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
.ozet-kutu{flex:1;min-width:105px;border:1px solid #cbd5e1;border-radius:5px;padding:5px 8px;background:#f8fafc}
.ozet-kutu .et{font-size:9px;text-transform:uppercase;letter-spacing:.3px;color:#64748b;font-weight:700}
.ozet-kutu .dg{font-size:13.5px;font-weight:700;font-variant-numeric:tabular-nums;margin-top:1px}
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
tfoot tr td{background:#0f172a;color:#fff;font-weight:700;font-size:12px;border-color:#0f172a}
.odenecek{color:#b91c1c;font-weight:700}
.iade{color:#047857;font-weight:700}
tfoot .odenecek,tfoot .iade{color:#fff}
.imza{margin-top:22px;display:flex;gap:40px;font-size:10.5px}
.imza div{flex:1;border-top:1px solid #94a3b8;padding-top:4px;text-align:center;color:#475569}
.dipnot{margin-top:10px;font-size:9.5px;color:#64748b;line-height:1.5;
  border-top:1px dashed #cbd5e1;padding-top:6px}
.arac-cubugu{margin-bottom:10px;display:flex;gap:8px;padding:8px 10px;background:#f1f5f9;border-radius:6px}
.arac-cubugu a,.arac-cubugu button{font:inherit;padding:5px 11px;border-radius:5px;border:1px solid #cbd5e1;
  background:#fff;color:#0f172a;text-decoration:none;cursor:pointer;font-size:11px}
.arac-cubugu button{background:#2563eb;color:#fff;border-color:#2563eb;font-weight:600}
@page{size:A4 landscape;margin:9mm}
@media print{body{padding:0}.yazdirma-gizle{display:none!important}}
</style>
</head>
<body>

<div class="arac-cubugu yazdirma-gizle">
  <button onclick="window.print()">🖨️ Yazdır</button>
  <a href="<?= site_url('gelir-vergisi?yil=' . (int) $yil) ?>">← Ekrana Dön</a>
</div>

<div class="baslik-blok">
  <div>
    <h1>Mali Müşavir Vergi Yükü Özeti</h1>
    <div class="alt-bilgi">
      <?= (int) $yil ?> yılı · serbest meslek kazancı
      <?= $kaynak === 'tahsil' ? '· yalnız tahsil edilen makbuzlar' : '· kesilen tüm makbuzlar (brüt)' ?>
    </div>
  </div>
  <div class="sag-bilgi"><?= date('d.m.Y H:i') ?><br><?= esc($aktifKullanici['ad_soyad'] ?? '') ?></div>
</div>

<div class="ozet-serit">
  <div class="ozet-kutu">
    <div class="et">Müşavir</div><div class="dg"><?= (int) $ozet['musavir'] ?></div>
  </div>
  <div class="ozet-kutu">
    <div class="et">Makbuz</div><div class="dg"><?= (int) $ozet['adet'] ?></div>
  </div>
  <div class="ozet-kutu mor">
    <div class="et">Hasılat</div><div class="dg"><?= number_format($ozet['hasilat'], 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu">
    <div class="et">Gider</div><div class="dg"><?= number_format($ozet['gider'], 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu">
    <div class="et">Matrah</div><div class="dg"><?= number_format($ozet['matrah'], 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu">
    <div class="et">Hesaplanan Vergi</div><div class="dg"><?= number_format($ozet['vergi'], 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu">
    <div class="et">Kalan KDV Borcu</div><div class="dg"><?= number_format($ozet['kdv'], 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu kirmizi">
    <div class="et">Ödenecek</div><div class="dg"><?= number_format($ozet['odenecek'], 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu yesil">
    <div class="et">İade</div><div class="dg"><?= number_format($ozet['iade'], 2, ',', '.') ?></div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th class="orta" style="width:4%">#</th>
      <th style="width:18%">Mali Müşavir</th>
      <th class="orta" style="width:6%">Makbuz</th>
      <th class="sag">Hasılat</th>
      <th class="sag">Gider</th>
      <th class="sag">Kazanç</th>
      <th class="sag">İndirim</th>
      <th class="sag">Matrah</th>
      <th class="orta" style="width:6%">Dilim</th>
      <th class="sag">Hesaplanan Vergi</th>
      <th class="sag">Stopaj</th>
      <th class="sag">Kalan KDV Borcu</th>
      <th class="sag">Ödenecek / İade</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = 0; $tIndirim = 0.0; $tKazanc = 0.0; $tKdv = 0.0; ?>
    <?php foreach ($satirlar as $s): ?>
      <?php
        $i++;
        $tKazanc  += $s['kazanc'];
        $tIndirim += $s['indirim_toplam'];
        $tKdv     += $s['kdv'];
      ?>
      <tr>
        <td class="orta kucuk"><?= $i ?></td>
        <td class="kalin"><?= esc($s['ad_soyad']) ?></td>
        <td class="orta"><?= (int) $s['makbuz']['adet'] ?></td>
        <td class="sag para"><?= number_format($s['hasilat'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($s['gider'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($s['kazanc'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($s['indirim_toplam'], 2, ',', '.') ?></td>
        <td class="sag para kalin"><?= number_format($s['matrah'], 2, ',', '.') ?></td>
        <td class="orta">
          <?= $s['dilim_no'] > 0
              ? '%' . rtrim(rtrim(number_format($s['dilim']['oran'], 2, ',', '.'), '0'), ',')
              : '—' ?>
        </td>
        <td class="sag para"><?= number_format($s['vergi'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($s['stopaj'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($s['kdv'], 2, ',', '.') ?></td>
        <td class="sag para <?= $s['iade'] > 0 ? 'iade' : 'odenecek' ?>">
          <?= number_format($s['iade'] > 0 ? $s['iade'] : $s['odenecek'], 2, ',', '.') ?>
          <?= $s['iade'] > 0 ? ' (iade)' : '' ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($satirlar === []): ?>
      <tr><td colspan="13" class="orta kucuk">Mali müşavir kaydı bulunamadı.</td></tr>
    <?php endif; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">TOPLAM (<?= $i ?> müşavir)</td>
      <td class="orta"><?= (int) $ozet['adet'] ?></td>
      <td class="sag para"><?= number_format($ozet['hasilat'], 2, ',', '.') ?></td>
      <td class="sag para"><?= number_format($ozet['gider'], 2, ',', '.') ?></td>
      <td class="sag para"><?= number_format($tKazanc, 2, ',', '.') ?></td>
      <td class="sag para"><?= number_format($tIndirim, 2, ',', '.') ?></td>
      <td class="sag para"><?= number_format($ozet['matrah'], 2, ',', '.') ?></td>
      <td></td>
      <td class="sag para"><?= number_format($ozet['vergi'], 2, ',', '.') ?></td>
      <td class="sag para"><?= number_format($ozet['stopaj'], 2, ',', '.') ?></td>
      <td class="sag para"><?= number_format($tKdv, 2, ',', '.') ?></td>
      <td class="sag para"><?= number_format($ozet['odenecek'], 2, ',', '.') ?></td>
    </tr>
  </tfoot>
</table>

<div class="dipnot">
  Hasılat, Makbuz Takip'te kayıtlı makbuzların brüt toplamıdır; stopaj da makbuzlardan gelir.
  KDV gelir vergisi matrahına dahil edilmez; yıl içi vergi yükü olarak stopajdan düşülür. Vergi, <?= (int) $yil ?> yılı GVK md.103
  (ücret dışı gelirler) tarifesine göre hesaplanmıştır. Bilgilendirme amaçlıdır; resmi beyan yerine geçmez.
</div>

<div class="imza">
  <div>Hazırlayan</div>
  <div>Kontrol Eden</div>
</div>

</body></html>
