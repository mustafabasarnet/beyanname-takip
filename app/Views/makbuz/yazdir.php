<?php
/**
 * MAKBUZ TAKİP — YAZDIRMA
 *
 * bicim=liste : mükellef bazında ücret/kesilen/kalan dökümü
 * bicim=ozet  : mali müşavir bazında özet
 *
 * Stiller GÖMÜLÜ: stil.css kopyalanmasa da çıktı düzgün olur.
 */
$yil    = (int) ($filtre['yil'] ?? date('Y'));
$durumA = $durumlar[$filtre['durum'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>Makbuz Takip <?= $yil ?><?= $bicim === 'ozet' ? ' — Müşavir Özeti' : '' ?></title>
<style>
*{box-sizing:border-box}
body{
  background:#fff;color:#0f172a;margin:0;padding:14px 16px;
  font-family:'Segoe UI',system-ui,-apple-system,sans-serif;font-size:11px;
}
.baslik-blok{
  display:flex;align-items:flex-end;justify-content:space-between;
  border-bottom:2px solid #0f172a;padding-bottom:7px;margin-bottom:10px;gap:12px;
}
h1{font-size:16px;margin:0 0 2px}
.alt-bilgi{font-size:10.5px;color:#475569}
.sag-bilgi{text-align:right;font-size:10.5px;color:#475569;white-space:nowrap}

/* Özet şeridi */
.ozet-serit{
  display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;
}
.ozet-kutu{
  flex:1;min-width:110px;border:1px solid #cbd5e1;border-radius:5px;
  padding:5px 8px;background:#f8fafc;
}
.ozet-kutu .et{font-size:9px;text-transform:uppercase;letter-spacing:.3px;color:#64748b;font-weight:700}
.ozet-kutu .dg{font-size:14px;font-weight:700;font-variant-numeric:tabular-nums;margin-top:1px}
.ozet-kutu.mor .dg{color:#6d28d9}
.ozet-kutu.yesil .dg{color:#047857}
.ozet-kutu.kirmizi .dg{color:#b91c1c}

table{width:100%;border-collapse:collapse;margin-bottom:10px}
th,td{border:1px solid #cbd5e1;padding:4px 6px;vertical-align:middle}
thead th{
  background:#1e293b;color:#fff;font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:.3px;text-align:left;
}
thead{display:table-header-group}         /* her sayfada başlık tekrarı */
tr{page-break-inside:avoid}
.sag{text-align:right}
.orta{text-align:center}
.kalin{font-weight:700}
.kucuk{font-size:9.5px;color:#64748b}
.para{font-variant-numeric:tabular-nums;white-space:nowrap}
tbody tr:nth-child(even){background:#f8fafc}
tr.satir-tamam td{background:#f0fdf4}
tr.satir-asim td{background:#fef2f2}
tr.satir-ucretsiz td{color:#64748b}
tfoot tr td{
  background:#0f172a;color:#fff;font-weight:700;font-size:12px;
  border-color:#0f172a;
}
.rozet{
  display:inline-block;padding:1px 5px;border-radius:3px;
  font-size:8.5px;font-weight:700;background:#e0e7ff;color:#3730a3;white-space:nowrap;
}
.rozet.yesil{background:#d1fae5;color:#065f46}
.rozet.kirmizi{background:#fee2e2;color:#991b1b}
.rozet.sari{background:#fef3c7;color:#92400e}
.rozet.turuncu{background:#ffedd5;color:#9a3412}
.rozet.gri{background:#e2e8f0;color:#475569}

/* İlerleme çubuğu — yazıcıda da görünsün diye kenarlıklı */
.cubuk{
  display:inline-block;width:46px;height:6px;border:1px solid #94a3b8;
  border-radius:99px;overflow:hidden;vertical-align:middle;background:#fff;
}
.cubuk i{display:block;height:100%;background:#475569}
.cubuk i.tam{background:#047857}

.imza{margin-top:22px;display:flex;gap:40px;font-size:10.5px}
.imza div{flex:1;border-top:1px solid #94a3b8;padding-top:4px;text-align:center;color:#475569}

.arac-cubugu{
  margin-bottom:10px;display:flex;gap:8px;align-items:center;
  padding:8px 10px;background:#f1f5f9;border-radius:6px;
}
.arac-cubugu a,.arac-cubugu button{
  font:inherit;padding:5px 11px;border-radius:5px;border:1px solid #cbd5e1;
  background:#fff;color:#0f172a;text-decoration:none;cursor:pointer;font-size:11px;
}
.arac-cubugu button{background:#2563eb;color:#fff;border-color:#2563eb;font-weight:600}
.arac-cubugu a.secili{background:#0f172a;color:#fff;border-color:#0f172a;font-weight:600}

@page{size:A4 <?= $bicim === 'ozet' ? 'portrait' : 'landscape' ?>;margin:9mm}
@media print{
  body{padding:0}
  .yazdirma-gizle{display:none!important}
}
</style>
</head>
<body>

<?php
// Yazdırma dışı araç çubuğu: biçim değiştirme + yazdır düğmesi
$qs = static function (array $ek) use ($filtre, $bicim) {
    $p = array_filter([
        'yil'    => $filtre['yil'] ?? null,
        'durum'  => $filtre['durum'] ?? null,
        'q'      => $filtre['q'] ?? null,
        'pasif'  => $filtre['pasif_dahil'] ?? null,
        'bicim'  => $bicim,
    ], static fn ($v) => $v !== null && $v !== '');

    // Müşavir filtresi tek seçimse URL'de korunur
    $m = $filtre['musavir_id'] ?? null;

    if (is_array($m) && count($m) === 1) {
        $p['musavir_id'] = (int) $m[0];
    } elseif (! is_array($m) && $m) {
        $p['musavir_id'] = (int) $m;
    }

    return site_url('makbuz/yazdir') . '?' . http_build_query(array_merge($p, $ek));
};
?>
<div class="arac-cubugu yazdirma-gizle">
  <button onclick="window.print()">🖨️ Yazdır</button>
  <a href="<?= $qs(['bicim' => 'liste']) ?>" class="<?= $bicim === 'liste' ? 'secili' : '' ?>">📋 Mükellef Listesi</a>
  <a href="<?= $qs(['bicim' => 'ozet']) ?>" class="<?= $bicim === 'ozet' ? 'secili' : '' ?>">👨‍💼 Müşavir Özeti</a>
  <a href="<?= site_url('makbuz?yil=' . $yil) ?>">← Ekrana Dön</a>
</div>

<div class="baslik-blok">
  <div>
    <h1>Serbest Meslek Makbuzu Takibi<?= $bicim === 'ozet' ? ' — Mali Müşavir Özeti' : '' ?></h1>
    <div class="alt-bilgi">
      <?= $yil ?> yılı
      <?php if ($durumA !== null): ?> · Durum: <?= esc($durumA) ?><?php endif; ?>
      <?php if (! empty($filtre['q'])): ?> · Arama: "<?= esc($filtre['q']) ?>"<?php endif; ?>
      <?php if (! empty($filtre['pasif_dahil'])): ?> · Pasifler dahil<?php endif; ?>
    </div>
  </div>
  <div class="sag-bilgi">
    <?= date('d.m.Y H:i') ?><br>
    <?= esc($aktifKullanici['ad_soyad'] ?? '') ?>
  </div>
</div>

<!-- ============ ÖZET ŞERİDİ ============ -->
<div class="ozet-serit">
  <div class="ozet-kutu">
    <div class="et">Mükellef</div>
    <div class="dg"><?= number_format((int) $ozet['mukellef'], 0, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu mor">
    <div class="et">Yıllık Sözleşme</div>
    <div class="dg"><?= number_format($ozet['ucret'], 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu yesil">
    <div class="et">Kesilen Makbuz</div>
    <div class="dg"><?= number_format($ozet['kesilen'], 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu kirmizi">
    <div class="et">Kalan</div>
    <div class="dg"><?= number_format($ozet['kalan'], 2, ',', '.') ?></div>
  </div>
  <div class="ozet-kutu">
    <div class="et">Tamamlanma</div>
    <div class="dg">%<?= (int) $ozet['oran'] ?></div>
  </div>
  <div class="ozet-kutu">
    <div class="et">Makbuz Adedi</div>
    <div class="dg"><?= number_format((int) $ozet['adet'], 0, ',', '.') ?></div>
  </div>
</div>

<?php if ($bicim === 'ozet'): ?>
  <!-- ================= MALİ MÜŞAVİR ÖZETİ ================= -->
  <?php
    $t = ['mukellef' => 0, 'ucret' => 0.0, 'kesilen' => 0.0, 'kalan' => 0.0,
          'adet' => 0, 'stopaj' => 0.0, 'kdv' => 0.0];
  ?>
  <table>
    <thead>
      <tr>
        <th style="width:22%">Mali Müşavir</th>
        <th class="orta" style="width:7%">Mükellef</th>
        <th class="sag">Yıllık Sözleşme</th>
        <th class="sag">Kesilen</th>
        <th class="sag">Kalan</th>
        <th class="orta" style="width:7%">Makbuz</th>
        <th class="sag">Stopaj</th>
        <th class="sag">KDV</th>
        <th class="orta" style="width:10%">Oran</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($musavirOzet as $m): ?>
        <?php
          $t['mukellef'] += $m['mukellef'];
          $t['ucret']    += $m['ucret'];
          $t['kesilen']  += $m['kesilen'];
          $t['kalan']    += $m['kalan'];
          $t['adet']     += $m['adet'];
          $t['stopaj']   += $m['stopaj'];
          $t['kdv']      += $m['kdv'];
        ?>
        <tr>
          <td class="kalin"><?= esc($m['ad_soyad']) ?></td>
          <td class="orta"><?= (int) $m['mukellef'] ?></td>
          <td class="sag para"><?= number_format($m['ucret'], 2, ',', '.') ?></td>
          <td class="sag para"><?= number_format($m['kesilen'], 2, ',', '.') ?></td>
          <td class="sag para kalin"><?= number_format($m['kalan'], 2, ',', '.') ?></td>
          <td class="orta"><?= (int) $m['adet'] ?></td>
          <td class="sag para"><?= number_format($m['stopaj'], 2, ',', '.') ?></td>
          <td class="sag para"><?= number_format($m['kdv'], 2, ',', '.') ?></td>
          <td class="orta">
            <span class="cubuk"><i class="<?= $m['oran'] >= 100 ? 'tam' : '' ?>"
                  style="width:<?= min(100, (int) $m['oran']) ?>%"></i></span>
            <span class="kalin">%<?= (int) $m['oran'] ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($musavirOzet === []): ?>
        <tr><td colspan="9" class="orta kucuk">Kayıt bulunamadı.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td>TOPLAM</td>
        <td class="orta"><?= $t['mukellef'] ?></td>
        <td class="sag para"><?= number_format($t['ucret'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($t['kesilen'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($t['kalan'], 2, ',', '.') ?></td>
        <td class="orta"><?= $t['adet'] ?></td>
        <td class="sag para"><?= number_format($t['stopaj'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($t['kdv'], 2, ',', '.') ?></td>
        <td class="orta">%<?= $t['ucret'] > 0 ? (int) round($t['kesilen'] / $t['ucret'] * 100) : 0 ?></td>
      </tr>
    </tfoot>
  </table>

<?php else: ?>
  <!-- ================= MÜKELLEF LİSTESİ ================= -->
  <?php $t = ['ucret' => 0.0, 'kesilen' => 0.0, 'kalan' => 0.0, 'adet' => 0]; ?>
  <table>
    <thead>
      <tr>
        <th style="width:4%" class="orta">#</th>
        <th style="width:26%">Mükellef</th>
        <th style="width:12%">VKN / TCKN</th>
        <th style="width:14%">Mali Müşavir</th>
        <th class="sag">Yıllık Ücret</th>
        <th class="sag">Kesilen</th>
        <th class="sag">Kalan</th>
        <th class="orta" style="width:5%">Adet</th>
        <th style="width:8%">Son Makbuz</th>
        <th class="orta" style="width:9%">Oran</th>
        <th style="width:10%">Durum</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 0; ?>
      <?php foreach ($kayitlar as $k): ?>
        <?php
          $i++;
          $ucret   = (float) $k['ucret'];
          $kesilen = (float) $k['kesilen'];
          $kalan   = (float) $k['kalan'];

          if ($ucret <= 0) {
              $sinif = 'satir-ucretsiz'; $rozet = ['gri', 'Ücret yok'];
          } elseif ($kesilen > $ucret) {
              $sinif = 'satir-asim';     $rozet = ['kirmizi', 'Aşmış'];
          } elseif ($kesilen >= $ucret) {
              $sinif = 'satir-tamam';    $rozet = ['yesil', 'Tamam'];
          } elseif ($kesilen > 0) {
              $sinif = '';               $rozet = ['sari', 'Devam'];
          } else {
              $sinif = '';               $rozet = ['turuncu', 'Kesilmemiş'];
          }

          $t['ucret']   += $ucret;
          $t['kesilen'] += $kesilen;
          $t['kalan']   += $kalan;
          $t['adet']    += (int) $k['adet'];
        ?>
        <tr class="<?= $sinif ?>">
          <td class="orta kucuk"><?= $i ?></td>
          <td class="kalin"><?= esc($k['unvan']) ?></td>
          <td class="kucuk"><?= esc($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no']) ?></td>
          <td class="kucuk"><?= esc($k['musavir_adi'] ?? '—') ?></td>
          <td class="sag para"><?= $ucret > 0 ? number_format($ucret, 2, ',', '.') : '—' ?></td>
          <td class="sag para"><?= number_format($kesilen, 2, ',', '.') ?></td>
          <td class="sag para kalin"><?= number_format($kalan, 2, ',', '.') ?></td>
          <td class="orta"><?= (int) $k['adet'] ?></td>
          <td class="kucuk"><?= ! empty($k['son_makbuz']) ? trTarih($k['son_makbuz']) : '—' ?></td>
          <td class="orta">
            <span class="cubuk"><i class="<?= $k['oran'] >= 100 ? 'tam' : '' ?>"
                  style="width:<?= min(100, (int) $k['oran']) ?>%"></i></span>
            <span class="kalin">%<?= (int) $k['oran'] ?></span>
          </td>
          <td><span class="rozet <?= $rozet[0] ?>"><?= $rozet[1] ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($kayitlar === []): ?>
        <tr><td colspan="11" class="orta kucuk">Filtreye uyan kayıt bulunamadı.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4">TOPLAM (<?= $i ?> mükellef)</td>
        <td class="sag para"><?= number_format($t['ucret'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($t['kesilen'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($t['kalan'], 2, ',', '.') ?></td>
        <td class="orta"><?= $t['adet'] ?></td>
        <td></td>
        <td class="orta">%<?= $t['ucret'] > 0 ? (int) round($t['kesilen'] / $t['ucret'] * 100) : 0 ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
<?php endif; ?>

<div class="imza">
  <div>Hazırlayan</div>
  <div>Kontrol Eden</div>
  <div>Onaylayan</div>
</div>

</body></html>
