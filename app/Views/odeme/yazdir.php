<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>Ödeme Listesi<?= $bicim === 'ozet' ? ' — Özet' : '' ?></title>
<?php
$donem = (! empty($filtre['ay']) ? ayAdi((int) $filtre['ay']) . ' ' : '') . $filtre['yil'];
?>
<style>
/* Yazdırma stilleri gömülüdür: stil.css olmasa da çıktı düzgün olur */
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

table{width:100%;border-collapse:collapse;margin-bottom:10px}
th,td{border:1px solid #cbd5e1;padding:4px 6px;vertical-align:middle}
thead th{
  background:#1e293b;color:#fff;font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:.3px;text-align:left;
}
thead{display:table-header-group}          /* her sayfada başlık tekrarı */
tr{page-break-inside:avoid}
.sag{text-align:right}
.orta{text-align:center}
.kalin{font-weight:700}
.kucuk{font-size:9.5px;color:#64748b}
.para{font-variant-numeric:tabular-nums;white-space:nowrap}

/* Mükellef ilk satırı: gruplar görsel olarak ayrılsın */
tbody tr.grup-bas td{border-top:2px solid #94a3b8}
tbody tr:nth-child(even){background:#f8fafc}
tr.ozel-satir td{background:#fffbeb}
tr.ara-toplam td{
  background:#e2e8f0;font-weight:700;border-top:1px solid #94a3b8;
}
tfoot tr td{
  background:#0f172a;color:#fff;font-weight:700;font-size:12px;
  border-color:#0f172a;
}
.rozet{
  display:inline-block;padding:1px 5px;border-radius:3px;
  font-size:8.5px;font-weight:700;background:#e0e7ff;color:#3730a3;
}
.rozet.ozel{background:#fef3c7;color:#92400e}

.imza{margin-top:22px;display:flex;gap:40px;font-size:10.5px}
.imza div{flex:1;border-top:1px solid #94a3b8;padding-top:4px;text-align:center;color:#475569}

@page{size:A4 landscape;margin:9mm}
@media print{
  body{padding:0}
  .yazdirma-gizle{display:none!important}
}
.arac-cubugu{
  margin-bottom:10px;display:flex;gap:8px;align-items:center;
  padding:8px 10px;background:#f1f5f9;border-radius:6px;
}
.arac-cubugu a,.arac-cubugu button{
  padding:5px 11px;border-radius:5px;border:1px solid #cbd5e1;background:#fff;
  color:#0f172a;text-decoration:none;font-size:12px;cursor:pointer;font-weight:600;
}
.arac-cubugu a.etkin{background:#2563eb;border-color:#2563eb;color:#fff}
</style>
</head><body>

<?php
// Yazdırma ekranındaki biçim seçici (çıktıya girmez)
$qs = array_filter([
    'yil'    => $filtre['yil'] ?? null,
    'ay'     => $filtre['ay'] ?? null,
    'odendi' => $filtre['odendi'] ?? null,
    'q'      => $filtre['q'] ?? null,
], static fn ($v) => $v !== null && $v !== '');
$link = static fn (string $b) => site_url('odeme/yazdir?' . http_build_query($qs + ['bicim' => $b]));
?>
<div class="arac-cubugu yazdirma-gizle">
  <b style="font-size:12px">Görünüm:</b>
  <a href="<?= $link('detay') ?>" class="<?= $bicim === 'detay' ? 'etkin' : '' ?>">📋 Detaylı</a>
  <a href="<?= $link('ozet') ?>"  class="<?= $bicim === 'ozet' ? 'etkin' : '' ?>">📊 Özet (Çapraz)</a>
  <button type="button" onclick="window.print()">🖨️ Yazdır</button>
  <span class="kucuk" style="margin-left:auto">Yatay A4 — kağıt yönü otomatik ayarlanır</span>
</div>

<div class="baslik-blok">
  <div>
    <h1>Ödeme Listesi<?= $bicim === 'ozet' ? ' — Özet' : '' ?></h1>
    <div class="alt-bilgi"><b><?= esc($donem) ?></b> dönemi</div>
  </div>
  <div class="sag-bilgi">
    <?= (int) $toplam['adet'] ?> beyanname
    <?= ! empty($toplam['ozel_adet']) ? ' + ' . (int) $toplam['ozel_adet'] . ' özel kalem' : '' ?>
    • <?= count($gruplar) ?> mükellef<br>
    Yazdırma: <?= trTarihUzun(date('Y-m-d')) ?>
  </div>
</div>

<?php if ($gruplar === []): ?>
  <p>Bu dönemde ödeme kaydı bulunamadı.</p>

<?php elseif ($bicim === 'ozet'): ?>
  <!-- ============ ÇAPRAZ (PİVOT) TABLO ============ -->
  <table>
    <thead>
      <tr>
        <th style="width:22px">#</th>
        <th>Mükellef</th>
        <th style="width:82px">VKN / TCKN</th>
        <?php foreach ($sutunlar as $a => $ad): ?>
          <th class="sag"><?= esc($ad) ?></th>
        <?php endforeach; ?>
        <th class="sag" style="width:88px">TOPLAM</th>
      </tr>
    </thead>
    <tbody>
      <?php $sira = 0; foreach ($satirlar as $s): $sira++; ?>
        <tr>
          <td class="orta kucuk"><?= $sira ?></td>
          <td>
            <b><?= esc($s['mukellef']['unvan']) ?></b>
            <?php if (! empty($s['mukellef']['vergi_dairesi'])): ?>
              <div class="kucuk"><?= esc($s['mukellef']['vergi_dairesi']) ?></div>
            <?php endif; ?>
          </td>
          <td class="kucuk"><?= esc($s['mukellef']['vkn']) ?></td>
          <?php foreach ($sutunlar as $a => $ad): ?>
            <td class="sag para">
              <?= isset($s['hucre'][$a]) && $s['hucre'][$a] > 0
                  ? number_format($s['hucre'][$a], 2, ',', '.')
                  : '<span class="kucuk">—</span>' ?>
            </td>
          <?php endforeach; ?>
          <td class="sag kalin para"><?= number_format($s['toplam'], 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3">GENEL TOPLAM (<?= count($satirlar) ?> mükellef)</td>
        <?php foreach ($sutunlar as $a => $ad): ?>
          <td class="sag para"><?= number_format((float) ($sutunToplam[$a] ?? 0), 2, ',', '.') ?></td>
        <?php endforeach; ?>
        <td class="sag para"><?= number_format($toplam['genel'], 2, ',', '.') ?> ₺</td>
      </tr>
    </tfoot>
  </table>

<?php else: ?>
  <!-- ============ DETAYLI YATAY TABLO ============ -->
  <table>
    <thead>
      <tr>
        <th style="width:22px">#</th>
        <th style="width:20%">Mükellef</th>
        <th style="width:78px">VKN / TCKN</th>
        <th style="width:105px">Vergi Dairesi</th>
        <th>Beyanname / Kalem</th>
        <th style="width:92px">Dönem</th>
        <th style="width:62px">Son Tarih</th>
        <th class="sag" style="width:78px">Tahakkuk</th>
        <th class="sag" style="width:62px">Damga</th>
        <th class="sag" style="width:82px">Ödenecek</th>
      </tr>
    </thead>
    <tbody>
      <?php $sira = 0; foreach ($gruplar as $g): ?>
        <?php
        $m       = $g['mukellef'];
        $satirNo = 0;
        $adet    = count($g['satirlar']) + count($g['ozel'] ?? []);
        $sira++;
        ?>

        <?php foreach ($g['satirlar'] as $s): $satirNo++; ?>
          <tr class="<?= $satirNo === 1 ? 'grup-bas' : '' ?>">
            <?php if ($satirNo === 1): ?>
              <td class="orta kucuk" rowspan="<?= $adet ?>"><?= $sira ?></td>
              <td rowspan="<?= $adet ?>"><b><?= esc($m['unvan']) ?></b></td>
              <td class="kucuk" rowspan="<?= $adet ?>"><?= esc($m['vkn']) ?></td>
              <td class="kucuk" rowspan="<?= $adet ?>"><?= esc($m['vergi_dairesi'] ?: '—') ?></td>
            <?php endif; ?>
            <td><?= esc($s['tur_kisa']) ?></td>
            <td class="kucuk"><?= esc($s['donem_adi']) ?></td>
            <td class="kucuk"><?= trTarih($s['efektif_odeme_tarihi'] ?? $s['son_tarih']) ?></td>
            <td class="sag para"><?= number_format((float) $s['tahakkuk_tutari'], 2, ',', '.') ?></td>
            <td class="sag para">
              <?= (float) $s['hesaplanan_damga'] > 0
                  ? number_format((float) $s['hesaplanan_damga'], 2, ',', '.')
                  : '<span class="kucuk">—</span>' ?>
            </td>
            <td class="sag kalin para"><?= number_format((float) $s['odenecek'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>

        <?php foreach ($g['ozel'] ?? [] as $o): $satirNo++; ?>
          <tr class="ozel-satir <?= $satirNo === 1 ? 'grup-bas' : '' ?>">
            <?php if ($satirNo === 1): ?>
              <td class="orta kucuk" rowspan="<?= $adet ?>"><?= $sira ?></td>
              <td rowspan="<?= $adet ?>"><b><?= esc($m['unvan']) ?></b></td>
              <td class="kucuk" rowspan="<?= $adet ?>"><?= esc($m['vkn']) ?></td>
              <td class="kucuk" rowspan="<?= $adet ?>"><?= esc($m['vergi_dairesi'] ?: '—') ?></td>
            <?php endif; ?>
            <td>
              <?= esc($o['baslik']) ?>
              <span class="rozet ozel">özel</span>
              <?php if (($o['tekrar'] ?? '') === 'AYLIK'): ?>
                <span class="rozet">aylık</span>
              <?php endif; ?>
            </td>
            <td class="kucuk"><?= esc($o['donem_etiketi'] ?: '—') ?></td>
            <td class="kucuk"><?= trTarih($o['son_tarih']) ?></td>
            <td class="sag"><span class="kucuk">—</span></td>
            <td class="sag"><span class="kucuk">—</span></td>
            <td class="sag kalin para"><?= number_format((float) $o['tutar'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>

        <tr class="ara-toplam">
          <td colspan="7" class="sag">
            <?= esc(kisalt($m['unvan'], 40)) ?> — ARA TOPLAM
          </td>
          <td class="sag para"><?= number_format($g['toplam']['tahakkuk'], 2, ',', '.') ?></td>
          <td class="sag para"><?= number_format($g['toplam']['damga'], 2, ',', '.') ?></td>
          <td class="sag para"><?= number_format($g['toplam']['genel_tum'] ?? $g['toplam']['genel'], 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="7" class="sag">GENEL TOPLAM (<?= count($gruplar) ?> mükellef)</td>
        <td class="sag para"><?= number_format($toplam['tahakkuk'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($toplam['damga'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($toplam['genel'], 2, ',', '.') ?> ₺</td>
      </tr>
    </tfoot>
  </table>
<?php endif; ?>

<div class="imza">
  <div>Hazırlayan</div>
  <div>Kontrol Eden</div>
  <div>Teslim Alan</div>
</div>

<script>window.onload=function(){window.print()}</script>
</body></html>
