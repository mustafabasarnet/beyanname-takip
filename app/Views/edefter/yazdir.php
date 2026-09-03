<?php
/**
 * E-DEFTER BERAT TAKİBİ — YAZDIRMA (kompakt kontrol çıktısı)
 *
 * Ekrandaki filtreyle tam liste dökülür; kontrol için kompakt tasarım:
 * adım sütunları ✓/— ile, satırlar kısaltılmış, A4 yatay.
 *
 * Stiller GÖMÜLÜ: stil.css kopyalanmasa da çıktı düzgün olur.
 */
$eMod    = $filtre['tarih_modu'] ?? 'berat';
$aySec   = $filtre['ay'] ?? null;
$durumA  = $durumlar[$filtre['durum'] ?? ''] ?? null;
$dTipA   = $donemTipleri[$filtre['donem_tipi'] ?? ''] ?? null;
$adimSay = count($adimlar);
?>
<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>E-Defter Takip <?= (int) ($filtre['yil'] ?? date('Y')) ?> — Yazdır</title>
<style>
*{box-sizing:border-box}
body{
  background:#fff;color:#0f172a;margin:0;padding:10px 12px;
  font-family:'Segoe UI',system-ui,-apple-system,sans-serif;font-size:9.5px;
}
.baslik-blok{
  display:flex;align-items:flex-end;justify-content:space-between;
  border-bottom:2px solid #0f172a;padding-bottom:6px;margin-bottom:8px;gap:12px;
}
h1{font-size:15px;margin:0 0 2px}
.alt-bilgi{font-size:9.5px;color:#475569}
.sag-bilgi{text-align:right;font-size:9.5px;color:#475569;white-space:nowrap}

/* Özet şeridi */
.ozet-serit{display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap}
.ozet-kutu{flex:1;min-width:80px;border:1px solid #cbd5e1;border-radius:4px;padding:4px 7px;background:#f8fafc}
.ozet-kutu .et{font-size:8px;text-transform:uppercase;letter-spacing:.3px;color:#64748b;font-weight:700}
.ozet-kutu .dg{font-size:12.5px;font-weight:700;font-variant-numeric:tabular-nums;margin-top:1px}
.ozet-kutu.kirmizi .dg{color:#b91c1c}
.ozet-kutu.mavi .dg{color:#1d4ed8}
.ozet-kutu.yesil .dg{color:#047857}
.ozet-kutu.turuncu .dg{color:#c2410c}
.ozet-kutu.mor .dg{color:#6d28d9}

table{width:100%;border-collapse:collapse;margin-bottom:6px}
th,td{border:1px solid #cbd5e1;padding:3px 5px;vertical-align:middle;font-size:9px}
thead th{
  background:#1e293b;color:#fff;font-size:8.5px;font-weight:700;
  text-transform:uppercase;letter-spacing:.2px;text-align:left;white-space:nowrap;
}
thead{display:table-header-group}         /* her sayfada başlık tekrarı */
tr{page-break-inside:avoid}
.sag{text-align:right}
.orta{text-align:center}
.kalin{font-weight:700}
.kucuk{font-size:8px;color:#64748b}
.monospace{font-variant-numeric:tabular-nums;white-space:nowrap}
tbody tr:nth-child(even){background:#f8fafc}
tr.ed-gecikmis td{background:#fef2f2}
tr.ed-pasif{opacity:.55}

/* Adım başlığı: ikon + kısa ad, iki satır */
thead th.ed-adim{text-align:center;width:20px;padding:2px 1px}
.ed-adim-basi{display:flex;flex-direction:column;align-items:center;line-height:1.15}
.ed-adim-basi .ik{font-size:11px}
.ed-adim-basi .ad{font-size:6.6px;max-width:46px;white-space:normal;text-align:center;overflow:hidden}

/* Adım hücresi: ✓ / — */
td.ed-adim-h{text-align:center;width:20px;padding:2px 1px;font-weight:800;font-size:9.5px}
td.ed-adim-h.evet{color:#047857}
td.ed-adim-h.hayir{color:#cbd5e1}

/* Durum rozeti */
.durum{display:inline-block;padding:1px 5px;border-radius:3px;font-size:8px;font-weight:700;white-space:nowrap}
.durum.yesil{background:#d1fae5;color:#065f46}
.durum.kirmizi{background:#fee2e2;color:#991b1b}
.durum.sari{background:#fef3c7;color:#92400e}
.durum.turuncu{background:#ffedd5;color:#9a3412}
.durum.gri{background:#e2e8f0;color:#475569}
.durum.mavi{background:#dbeafe;color:#1e40af}
.durum.mor{background:#ede9fe;color:#5b21b6}

/* İlerleme çubuğu — yazıcıda da görünsün */
.cubuk{display:inline-block;width:36px;height:5px;border:1px solid #94a3b8;border-radius:99px;overflow:hidden;vertical-align:middle;background:#fff}
.cubuk i{display:block;height:100%;background:#475569}
.cubuk i.tam{background:#047857}
.ilerleme{font-size:8px;font-weight:700;margin-left:4px}

/* Yazdırma dışı araç çubuğu */
.arac-cubugu{
  margin-bottom:8px;display:flex;gap:8px;align-items:center;
  padding:7px 10px;background:#f1f5f9;border-radius:6px;
}
.arac-cubugu a,.arac-cubugu button{
  font:inherit;padding:4px 10px;border-radius:5px;border:1px solid #cbd5e1;
  background:#fff;color:#0f172a;text-decoration:none;cursor:pointer;font-size:10px;
}
.arac-cubugu button{background:#2563eb;color:#fff;border-color:#2563eb;font-weight:600}
.arac-cubugu .gercek{font-size:9.5px;color:#475569;margin-left:auto}

@page{size:A4 landscape;margin:8mm}
@media print{
  body{padding:0}
  .yazdirma-gizle{display:none!important}
}
</style>
</head>
<body>

<div class="arac-cubugu yazdirma-gizle">
  <button onclick="window.print()">🖨️ Yazdır</button>
  <a href="<?= site_url('edefter') ?>">← Ekrana Dön</a>
  <span class="gercek">Gerçek renkler kâğıtta görünmeyebilir — kontrol için yeterlidir.</span>
</div>

<div class="baslik-blok">
  <div>
    <h1>📗 E-Defter Berat Takibi — Kontrol Listesi</h1>
    <div class="alt-bilgi">
      <?php if ($eMod === 'donem'): ?>
        Dönem ekseni
      <?php else: ?>
        Berat tarihi (yükleme) ekseni
      <?php endif; ?>
      · <?= (int) ($filtre['yil'] ?? date('Y')) ?>
      <?php if ($aySec !== null): ?>· <?= ayAdi($aySec) ?><?php endif; ?>
      <?php if ($dTipA !== null): ?> · <?= esc($dTipA) ?><?php endif; ?>
      <?php if ($durumA !== null): ?> · Durum: <?= esc($durumA) ?><?php endif; ?>
      <?php if (! empty($filtre['sorumlu_id'])): ?> · Sorumlu seçili<?php endif; ?>
      <?php if (! empty($filtre['q'])): ?> · Arama: "<?= esc($filtre['q']) ?>"<?php endif; ?>
      <?php if (! empty($filtre['gecikmis'])): ?> · Sadece gecikmişler<?php endif; ?>
    </div>
  </div>
  <div class="sag-bilgi">
    <?= date('d.m.Y H:i') ?><br>
    <?= esc($aktifKullanici['ad_soyad'] ?? '') ?>
  </div>
</div>

<!-- ============ ÖZET ŞERİDİ ============ -->
<div class="ozet-serit">
  <div class="ozet-kutu"><div class="et">Toplam</div>
    <div class="dg"><?= (int) $ozet['toplam'] ?></div></div>
  <div class="ozet-kutu kirmizi"><div class="et">Gecikmiş</div>
    <div class="dg"><?= (int) $ozet['gecikmis'] ?></div></div>
  <div class="ozet-kutu turuncu"><div class="et">Devam Ediyor</div>
    <div class="dg"><?= (int) ($ozet['devam'] ?? 0) ?></div></div>
  <div class="ozet-kutu mavi"><div class="et">Hazır</div>
    <div class="dg"><?= (int) ($ozet['hazir'] ?? 0) ?></div></div>
  <div class="ozet-kutu yesil"><div class="et">Onaylandı</div>
    <div class="dg"><?= (int) ($ozet['onaylandi'] ?? 0) ?></div></div>
  <div class="ozet-kutu"><div class="et">Yüklenmeyecek</div>
    <div class="dg"><?= (int) ($ozet['yuklenmeyecek'] ?? 0) ?></div></div>
  <div class="ozet-kutu mor"><div class="et">Tamamlanma</div>
    <div class="dg">%<?= (int) $ozet['oran'] ?></div></div>
</div>

<table>
  <thead>
    <tr>
      <th style="width:15%">Mükellef</th>
      <th style="width:10%">Dönem</th>
      <th class="orta" style="width:8%">Son Tarih</th>
      <th style="width:8%">Durum</th>
      <?php foreach ($adimlar as $a): ?>
        <th class="ed-adim" title="<?= esc($a['ad']) ?>">
          <div class="ed-adim-basi">
            <span class="ik"><?= $a['ikon'] ?: '•' ?></span>
            <span class="ad"><?= esc(kisalt($a['ad'], 14)) ?></span>
          </div>
        </th>
      <?php endforeach; ?>
      <th class="orta" style="width:7%">İlerleme</th>
      <th style="width:9%">Not</th>
    </tr>
  </thead>
  <tbody>
  <?php if ($kayitlar === []): ?>
    <tr><td colspan="<?= 5 + $adimSay ?>" class="orta kucuk" style="padding:14px">
      Bu filtreyle eşleşen kayıt bulunamadı.</td></tr>
  <?php endif; ?>

  <?php foreach ($kayitlar as $k):
      $kalan   = kalanGunMetni($k['son_tarih'], $k['durum']);
      $gecikti = ! $kalan['bitti'] && $kalan['gun'] < 0;
      $pasif   = $k['durum'] === 'YUKLENMEYECEK';
      $durumRoz = ($kalan['bitti'])
          ? ($pasif ? 'gri' : 'yesil') : $kalan['sinif'];
      $durumMet = ($kalan['bitti'])
          ? ($pasif ? 'Takip dışı' : '✓ Yüklendi') : $kalan['metin'];
  ?>
    <tr class="<?= $gecikti ? 'ed-gecikmis' : '' ?><?= $pasif ? ' ed-pasif' : '' ?>">
      <td>
        <span class="kalin"><?= esc(kisalt($k['mukellef_unvan'], 34)) ?></span>
        <div class="kucuk"><?= esc($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no']) ?>
          <?php if (! empty($k['sorumlu_adi'])): ?>
            · <?= esc(kisalt($k['sorumlu_adi'], 12)) ?>
          <?php endif; ?>
        </div>
      </td>
      <td>
        <?php if ($k['donem_tipi'] === 'UC_AYLIK'): ?>
          <span class="durum mor">3 Aylık</span>
        <?php else: ?>
          <span class="durum mavi">Aylık</span>
        <?php endif; ?>
        <div class="kucuk"><?= esc($k['donem_adi']) ?></div>
      </td>
      <td class="orta monospace kalin"><?= trTarih($k['son_tarih']) ?>
        <?php if (! empty($k['kaydirma_nedeni'])): ?>
          <div class="kucuk">↷ <?= esc(kisalt($k['kaydirma_nedeni'], 16)) ?></div>
        <?php endif; ?>
      </td>
      <td><span class="durum <?= $durumRoz ?>"><?= esc($durumMet) ?></span></td>

      <?php foreach ($adimlar as $a):
          $tamam = false;

          foreach ($k['adimlar'] ?? [] as $x) {
              if ((int) $x['id'] === (int) $a['id']) { $tamam = ! empty($x['tamam']); break; }
          }
      ?>
        <td class="ed-adim-h <?= $tamam ? 'evet' : 'hayir' ?>"><?= $tamam ? '✓' : '—' ?></td>
      <?php endforeach; ?>

      <td class="orta">
        <span class="cubuk"><i class="<?= (int) ($k['ilerleme'] ?? 0) >= 100 ? 'tam' : '' ?>"
              style="width:<?= min(100, (int) ($k['ilerleme'] ?? 0)) ?>%"></i></span>
        <span class="ilerleme">%<?= (int) ($k['ilerleme'] ?? 0) ?></span>
      </td>
      <td class="kucuk"><?= $k['not_metni'] !== null && $k['not_metni'] !== ''
          ? esc(kisalt($k['not_metni'], 24)) : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<p class="kucuk" style="margin:4px 0 0">
  Toplam <?= count($kayitlar) ?> kayıt · <?= (int) ($ozet['toplam'] ?? count($kayitlar)) ?> kayıt filtrelenmiş listede.
  <span style="color:#64748b">Eksik adım tespiti için satırları işaretlere göre kontrol edin.</span>
</p>

</body>
</html>
