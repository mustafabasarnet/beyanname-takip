<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>Beyanname Takip Çizelgesi</title>
<?php
/**
 * BEYANNAME TAKİP — YAZDIRMA ÇIKTISI (sade / kompakt)
 *
 * Amaç: çıktıyı elde tutup satır satır KONTROL etmek. Bu yüzden yalnız
 * beş sütun var ve her satırın başında elle tik atılacak boş kare durur:
 *
 *   ☐ · Ünvan · Beyanname · Dönem · Durum · Ödeme Tutarı
 *
 * Ödeme tutarı = tahakkuk + damga. Kullanıcı iki ayrı sayıyı toplamak
 * zorunda kalmasın diye tek sütunda gösterilir.
 *
 * Stil TAMAMEN GÖMÜLÜDÜR: stil.css yazdırma için fazla süslü (gölge,
 * yuvarlak köşe, renkli rozet) ve kopyalanmamış bir kurulumda çıktı
 * dağılırdı. Burada yalnız kağıda uygun kurallar var.
 *
 * Beklenen değişkenler: $kayitlar, $filtre, $durumlar
 */
$md = $filtre['tarih_modu'] ?? 'beyan';

// ---- Durum özeti (kullanıcı isteği: genel tutar toplamı YOK) ----
$sayac = [];

foreach ($kayitlar as $k) {
    $d = $k['durum'];
    $sayac[$d] = ($sayac[$d] ?? 0) + 1;
}

/**
 * Filtre özeti — çıktının hangi süzgeçle alındığı kağıtta görünmeli,
 * yoksa iki farklı çıktı karışır.
 */
$suzgec = [];

if (! empty($filtre['ay'])) {
    $suzgec[] = ayAdi((int) $filtre['ay']);
}

if (! empty($filtre['tur_id'])) {
    $suzgec[] = 'seçili beyanname türleri';
}

if (! empty($filtre['durum'])) {
    $durSec = array_map(
        static fn ($d) => $durumlar[$d] ?? $d,
        (array) $filtre['durum']
    );
    $suzgec[] = implode(' / ', $durSec);
}

if (! empty($filtre['defter_tipi'])) {
    $suzgec[] = defterTipiAdi($filtre['defter_tipi']);
}

if (! empty($filtre['gecikmis'])) {
    $suzgec[] = 'yalnız gecikmişler';
}

if (! empty($filtre['q'])) {
    $suzgec[] = 'arama: ' . $filtre['q'];
}
?>
<style>
/* ---- Kağıda uygun sade stil (gömülü: stil.css'e bağımlı değil) ---- */
*{box-sizing:border-box}
body{
  background:#fff;margin:0;padding:14px 16px;color:#111;
  font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;font-size:11.5px
}
h1{font-size:15px;margin:0 0 3px;letter-spacing:.2px}
.ust{
  display:flex;justify-content:space-between;align-items:flex-end;
  gap:14px;border-bottom:1.5px solid #111;padding-bottom:7px;margin-bottom:9px
}
.bilgi{font-size:10.5px;color:#444;line-height:1.55}
.bilgi b{color:#111}
.sayac{font-size:10.5px;text-align:right;white-space:nowrap;line-height:1.55}
.sayac span{display:inline-block;margin-left:9px}
.sayac b{font-size:12px}

table{width:100%;border-collapse:collapse;table-layout:fixed}
th{
  font-size:9.5px;text-transform:uppercase;letter-spacing:.4px;text-align:left;
  padding:5px 6px;border-bottom:1.2px solid #111;color:#000;font-weight:700
}
td{padding:4px 6px;border-bottom:1px solid #d8d8d8;vertical-align:top;line-height:1.35}
/* Zebra: uzun listede satır kaymasını önler */
tbody tr:nth-child(even) td{background:#f6f6f6}

.k-kutu{width:20px;text-align:center}
.k-unvan{width:auto}
/* Tür adları ("MUHSGK (3Ay)") tek satıra sığmalı; iki satıra kırılınca
   satır yükseklikleri bozuluyor ve liste okunaksızlaşıyordu. */
.k-tur{width:96px}
.k-donem{width:150px}
.k-durum{width:88px}
.k-tutar{width:92px;text-align:right}
.k-tur,.k-donem,.k-durum{white-space:nowrap}

td.sag{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
/* Elle işaretlenecek kare */
.kutu{display:inline-block;width:10px;height:10px;border:1.1px solid #333}
.vkn{color:#666;font-size:9.5px}
/* Gecikmiş satır: kağıtta renk yerine kalın sol çizgi ile ayrılır */
tr.gec td:first-child{border-left:2.5px solid #000}
.bos{color:#aaa}

.dip{margin-top:9px;font-size:9.5px;color:#666;display:flex;
  justify-content:space-between;gap:12px;border-top:1px solid #ccc;padding-top:6px}

@media print{
  body{padding:0}
  /* Geliştirme ortamında CodeIgniter hata ayıklama çubuğu enjekte edilir
     ve çıktının köşesinde görünür. Üretimde zaten çıkmaz ama çıktı her
     ortamda temiz olmalı. */
  #debug-icon,#debug-bar,.debug-bar,.kint-rich,.kint-folder{display:none!important}
  thead{display:table-header-group}   /* başlık her sayfada tekrar etsin */
  tr{page-break-inside:avoid}
  tbody tr:nth-child(even) td{background:#f6f6f6;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  @page{margin:1cm;size:portrait}
}
</style>
</head><body>

<div class="ust">
  <div>
    <h1>Beyanname Takip Çizelgesi</h1>
    <div class="bilgi">
      <b><?= esc($filtre['yil']) ?></b> <?= $md === 'donem' ? 'dönemi' : 'beyan yılı' ?>
      <?php if ($suzgec !== []): ?>
        • <?= esc(implode(' • ', $suzgec)) ?>
      <?php endif; ?>
      <br>
      <?= trTarihUzun(date('Y-m-d')) ?> tarihinde yazdırıldı
      • <b><?= count($kayitlar) ?></b> kayıt
    </div>
  </div>

  <?php if ($sayac !== []): ?>
    <div class="sayac">
      <?php foreach ($durumlar as $dk => $dv): ?>
        <?php if (empty($sayac[$dk])) { continue; } ?>
        <span><b><?= (int) $sayac[$dk] ?></b> <?= esc($dv) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<table>
  <thead>
    <tr>
      <th class="k-kutu">✓</th>
      <th class="k-unvan">Mükellef</th>
      <th class="k-tur">Beyanname</th>
      <th class="k-donem">Dönem</th>
      <th class="k-durum">Durum</th>
      <th class="k-tutar">Ödeme Tutarı</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($kayitlar as $k): ?>
    <?php
    /*
     * Ödeme tutarı = tahakkuk + damga.
     * Tahakkuk girilmemişse toplam yazmak yanıltıcı olur ("0,00" ödenecek
     * bir tutar sanılabilir); bu yüzden tire konur.
     */
    $tahakkuk = $k['tahakkuk_tutari'];
    $damga    = (float) ($k['damga_tutari'] ?? 0);
    $odeme    = $tahakkuk === null ? null : (float) $tahakkuk + $damga;

    // Gecikmiş: yalnızca işi bitmemiş kayıtlar için (bkz. kalanGunMetni)
    $kalan   = kalanGunMetni($k['son_tarih'], $k['durum']);
    $gecikti = ! $kalan['bitti'] && $kalan['gun'] < 0;
    ?>
    <tr class="<?= $gecikti ? 'gec' : '' ?>">
      <td class="k-kutu"><span class="kutu"></span></td>
      <td>
        <?= esc($k['mukellef_unvan']) ?>
        <div class="vkn"><?= esc($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no']) ?></div>
      </td>
      <td><?= esc($k['tur_kisa']) ?></td>
      <td><?= esc($k['donem_adi']) ?></td>
      <td><?= esc($durumlar[$k['durum']] ?? $k['durum']) ?></td>
      <td class="sag">
        <?php if ($odeme === null): ?>
          <span class="bos">—</span>
        <?php else: ?>
          <?= number_format($odeme, 2, ',', '.') ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>

  <?php if ($kayitlar === []): ?>
    <tr><td colspan="6" style="text-align:center;padding:20px;color:#888">
      Bu filtreye uyan kayıt bulunamadı.
    </td></tr>
  <?php endif; ?>
  </tbody>
</table>

<div class="dip">
  <span>Ödeme tutarı, tahakkuk ve damga vergisi toplamıdır.
        Tahakkuku girilmemiş kayıtlarda tire (—) görünür.</span>
  <span>Kalın sol çizgi: süresi geçmiş, hâlâ verilmemiş beyanname.</span>
</div>

<script>window.onload=function(){window.print()}</script>
</body></html>
