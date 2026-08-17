<?php
/**
 * GELİR VERGİSİ HESABI — YAZDIRMA (tek müşavir)
 * Stiller gömülüdür.
 */
$mb = $h['makbuz'];
?>
<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>Vergi Yükü Hesabı — <?= esc($musavir['ad_soyad']) ?> (<?= (int) $yil ?>)</title>
<style>
*{box-sizing:border-box}
body{background:#fff;color:#0f172a;margin:0;padding:14px 16px;
  font-family:'Segoe UI',system-ui,-apple-system,sans-serif;font-size:11px}
.baslik-blok{display:flex;align-items:flex-end;justify-content:space-between;
  border-bottom:2px solid #0f172a;padding-bottom:7px;margin-bottom:12px;gap:12px}
h1{font-size:16px;margin:0 0 2px}
h2{font-size:12px;margin:14px 0 6px;padding-bottom:3px;border-bottom:1px solid #cbd5e1;
  text-transform:uppercase;letter-spacing:.4px;color:#334155}
.alt-bilgi{font-size:10.5px;color:#475569}
.sag-bilgi{text-align:right;font-size:10.5px;color:#475569;white-space:nowrap}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #cbd5e1;padding:5px 7px;vertical-align:middle}
thead th{background:#1e293b;color:#fff;font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:.3px;text-align:left}
thead{display:table-header-group}
tr{page-break-inside:avoid}
.sag{text-align:right}.orta{text-align:center}.kalin{font-weight:700}
.kucuk{font-size:9.5px;color:#64748b}
.para{font-variant-numeric:tabular-nums;white-space:nowrap}

/* Hesap dökümü */
.hesap td{font-size:11.5px}
.hesap tr.eksi td:first-child{padding-left:20px;color:#475569}
.hesap tr.arti td:first-child{padding-left:20px;color:#475569}
.hesap tr.kirilim td{background:#f8fafc;font-weight:700;border-top:1px dashed #94a3b8}
.hesap tr.ara td{background:#f1f5f9;font-weight:700}
.hesap tr.vurgu td{background:#e0e7ff;font-weight:700;font-size:12.5px}
.hesap tr.sonuc td{background:#0f172a;color:#fff;font-weight:700;font-size:14px;border-color:#0f172a}
.hesap tr.sonuc.iade td{background:#047857;border-color:#047857}
.hesap .not{display:block;font-size:9.5px;color:#64748b;font-weight:400}
.hesap tr.sonuc .not{color:#cbd5e1}

.iki-sutun{display:flex;gap:14px;align-items:flex-start}
.iki-sutun>div{flex:1}
tr.aktif-dilim td{background:#fef3c7;font-weight:700}

.imza{margin-top:26px;display:flex;gap:40px;font-size:10.5px}
.imza div{flex:1;border-top:1px solid #94a3b8;padding-top:4px;text-align:center;color:#475569}
.dipnot{margin-top:12px;font-size:9.5px;color:#64748b;line-height:1.5;
  border-top:1px dashed #cbd5e1;padding-top:7px}
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
  <a href="<?= site_url('gelir-vergisi/detay/' . (int) $musavir['id'] . '?yil=' . (int) $yil) ?>">← Ekrana Dön</a>
</div>

<div class="baslik-blok">
  <div>
    <h1>Vergi Yükü Hesabı — <?= (int) $yil ?></h1>
    <div class="alt-bilgi">
      <b><?= esc($musavir['ad_soyad']) ?></b>
      <?php if (! empty($musavir['buro_adi'])): ?> · <?= esc($musavir['buro_adi']) ?><?php endif; ?>
      <?php if (! empty($musavir['tc_kimlik'])): ?> · TCKN <?= esc($musavir['tc_kimlik']) ?><?php endif; ?>
      · Serbest meslek kazancı
    </div>
  </div>
  <div class="sag-bilgi"><?= date('d.m.Y H:i') ?><br><?= esc($aktifKullanici['ad_soyad'] ?? '') ?></div>
</div>

<h2>Vergi Yükü Dökümü</h2>
<table class="hesap">
  <tbody>
    <tr>
      <td>
        Serbest Meslek Hasılatı
        <span class="not">
          <?php if ($h['kip'] === 'ucret'): ?>
            <?= (int) $h['ucret_adet'] ?> mükellefin yıllık sözleşme ücreti (projeksiyon)
          <?php else: ?>
            <?= (int) $mb['adet'] ?> makbuz · brüt toplam
          <?php endif; ?>
          <?php if ($h['hasilat_elle'] !== null): ?> · elle girildi
            (makbuzlardan: <?= number_format($h['hasilat_oto'], 2, ',', '.') ?>)
          <?php endif; ?>
          <?php if ($kaynak === 'tahsil'): ?> · yalnız tahsil edilenler<?php endif; ?>
        </span>
      </td>
      <td class="sag para" style="width:24%"><?= number_format($h['hasilat'], 2, ',', '.') ?></td>
    </tr>
    <tr class="eksi">
      <td>− Mesleki Gider
        <?php if ($h['gider_aylik'] > 0): ?>
          <span class="not">
            elle <?= number_format($h['gider_elle'], 2, ',', '.') ?>
            + aylık tablo <?= number_format($h['gider_aylik'], 2, ',', '.') ?>
            (<?= (int) $h['gider_ay_sayisi'] ?> ay)
          </span>
        <?php endif; ?>
      </td>
      <td class="sag para"><?= number_format($h['gider'], 2, ',', '.') ?></td>
    </tr>
    <tr class="ara">
      <td>= Serbest Meslek Kazancı</td>
      <td class="sag para"><?= number_format($h['kazanc'], 2, ',', '.') ?></td>
    </tr>

    <?php if ($h['bagkur'] > 0): ?>
      <tr class="eksi">
        <td>− Bağ-Kur / SGK Primi</td>
        <td class="sag para"><?= number_format($h['bagkur'], 2, ',', '.') ?></td>
      </tr>
    <?php endif; ?>
    <?php if ($h['sigorta'] > 0 || $h['sigorta_talep'] > 0): ?>
      <tr class="eksi">
        <td>− Şahıs / Hayat Sigorta Primi
          <span class="not">
            <?= $h['sigorta_liste'] ? (int) $h['sigorta_adet'] . ' belge · ' : '' ?>
            en çok kârın %<?= (int) $h['sigorta_oran'] ?>'i = <?= number_format($h['sigorta_tavan'], 2, ',', '.') ?>
            <?php if ($h['sigorta_asim'] > 0): ?>
              · <?= number_format($h['sigorta_asim'], 2, ',', '.') ?> sınır aşımı indirilemedi
            <?php endif; ?>
          </span></td>
        <td class="sag para"><?= number_format($h['sigorta'], 2, ',', '.') ?></td>
      </tr>
    <?php endif; ?>
    <?php if ($h['egitim'] > 0 || $h['egitim_talep'] > 0): ?>
      <tr class="eksi">
        <td>− Eğitim ve Sağlık Harcaması
          <span class="not">
            <?= $h['egitim_liste'] ? (int) $h['egitim_adet'] . ' belge · ' : '' ?>
            en çok kârın %<?= (int) $h['egitim_oran'] ?>'u = <?= number_format($h['egitim_tavan'], 2, ',', '.') ?>
            <?php if ($h['egitim_asim'] > 0): ?>
              · <?= number_format($h['egitim_asim'], 2, ',', '.') ?> sınır aşımı indirilemedi
            <?php endif; ?>
          </span></td>
        <td class="sag para"><?= number_format($h['egitim'], 2, ',', '.') ?></td>
      </tr>
    <?php endif; ?>
    <?php if ($h['indirim_toplam'] <= 0): ?>
      <tr class="eksi">
        <td>− İndirimler <span class="not">girilmedi</span></td>
        <td class="sag para">0,00</td>
      </tr>
    <?php endif; ?>

    <tr class="vurgu">
      <td>= VERGİ MATRAHI</td>
      <td class="sag para"><?= number_format($h['matrah'], 2, ',', '.') ?></td>
    </tr>

    <tr>
      <td>
        Hesaplanan Gelir Vergisi
        <span class="not">
          <?php if (! $h['tarife_var']): ?>
            <?= (int) $yil ?> yılı tarifesi tanımlı değil!
          <?php elseif ($h['dilim_no'] > 0): ?>
            <?= (int) $h['dilim_no'] ?>. dilim · marjinal
            %<?= rtrim(rtrim(number_format($h['dilim']['oran'], 2, ',', '.'), '0'), ',') ?>
            · ortalama %<?= number_format($h['ortalama_oran'], 2, ',', '.') ?>
          <?php else: ?>Matrah oluşmadı<?php endif; ?>
        </span>
      </td>
      <td class="sag para"><?= number_format($h['vergi'], 2, ',', '.') ?></td>
    </tr>
    <?php if ($h['uyumlu_acik']): ?>
      <tr class="eksi">
        <td>− %<?= (int) $h['uyumlu_oran'] ?> Vergiye Uyumlu Mükellef İndirimi
          <span class="not">GVK mük.121 · üst sınır <?= number_format($h['uyumlu_sinir'], 0, ',', '.') ?> TL</span></td>
        <td class="sag para"><?= number_format($h['uyumlu'], 2, ',', '.') ?></td>
      </tr>
    <?php endif; ?>
    <tr class="ara">
      <td>= Ödenmesi Gereken Gelir Vergisi</td>
      <td class="sag para"><?= number_format($h['odenmesi_gereken'], 2, ',', '.') ?></td>
    </tr>

    <tr class="eksi">
      <td>− Stopaj (Tevkifat)
        <span class="not">
          <?= $h['stopaj_elle'] !== null
              ? 'elle girildi (makbuzlardan: ' . number_format($h['stopaj_oto'], 2, ',', '.') . ')'
              : 'makbuzlardan' ?>
        </span></td>
      <td class="sag para"><?= number_format($h['stopaj'], 2, ',', '.') ?></td>
    </tr>
    <?php if ($h['diger_mahsup'] > 0): ?>
      <tr class="eksi">
        <td>− Diğer Mahsuplar</td>
        <td class="sag para"><?= number_format($h['diger_mahsup'], 2, ',', '.') ?></td>
      </tr>
    <?php endif; ?>
    <tr class="arti">
      <td>+ Kalan KDV Borcu
        <span class="not">
          makbuzlarda <?= number_format($h['kdv_yukumluluk'], 2, ',', '.') ?> KDV ·
          <?= $h['kip'] === 'ucret' ? 'ücretlerden' : 'makbuzlardan' ?>
          <?= number_format($h['kdv_yukumluluk'], 2, ',', '.') ?> ·
          ödenen <?= number_format($h['kdv_odenen'], 2, ',', '.') ?>
          (<?= (int) $h['kdv_ay_sayisi'] ?> ay · ödenen
          <?= number_format($h['kdv_odenen_sutun'], 2, ',', '.') ?> +
          indirilecek <?= number_format($h['kdv_indirilecek'], 2, ',', '.') ?>)
          <?php if ($h['kdv_alacak'] > 0): ?> · fazla ödeme<?php endif; ?>
        </span></td>
      <td class="sag para"><?= number_format($h['kdv_kalan'], 2, ',', '.') ?></td>
    </tr>
    <?php /* Ara toplam satırı kaldırıldı: KDV borçtur, alacak değildir.
             Aşağıdaki iki kırılım satırı aynı sonucu daha açık anlatır. */ ?>

    <!-- Yıl içi vergi yükü kırılımı: GV tarafı + KDV -->
    <!-- İşaret kullanılmaz; lehe/aleyhe olduğu etiketle belirtilir -->
    <tr class="kirilim">
      <td><?= $h['gv_alacak'] > 0 ? 'Gelir Vergisi: Devletten Alacak' : 'Gelir Vergisi: Borç' ?>
        <span class="not"><?= number_format($h['odenmesi_gereken'], 2, ',', '.') ?> vergi
          − <?= number_format($h['stopaj'], 2, ',', '.') ?> stopaj<?php if ($h['diger_mahsup'] > 0): ?>
          − <?= number_format($h['diger_mahsup'], 2, ',', '.') ?> diğer<?php endif; ?></span></td>
      <td class="sag para"><?= number_format($h['gv_alacak'] > 0 ? $h['gv_alacak'] : $h['gv_borc'], 2, ',', '.') ?></td>
    </tr>
    <tr class="kirilim">
      <td><?= $h['kdv_alacak'] > 0 ? 'KDV: Fazla Ödeme (alacak)' : 'KDV: Ödenmemiş Borç' ?>
        <span class="not"><?= number_format($h['kdv_yukumluluk'], 2, ',', '.') ?>
          <?= $h['kip'] === 'ucret' ? 'ücret' : 'makbuz' ?> KDV'si
          − <?= number_format($h['kdv_odenen'], 2, ',', '.') ?> ödenen</span></td>
      <td class="sag para"><?= number_format($h['kdv_alacak'] > 0 ? $h['kdv_alacak'] : $h['kdv_borc'], 2, ',', '.') ?></td>
    </tr>

    <tr class="sonuc <?= $h['iade'] > 0 ? 'iade' : '' ?>">
      <td>
        <?php if (abs($h['vergi_yuku']) < 0.005): ?>
          YIL İÇİ VERGİ YÜKÜ — ALACAK/VERECEK YOK
        <?php elseif ($h['iade'] > 0): ?>
          YIL İÇİ VERGİ YÜKÜ — İADE ALACAKSINIZ
        <?php else: ?>
          YIL İÇİ VERGİ YÜKÜ — ÖDEYECEKSİNİZ
        <?php endif; ?>
      </td>
      <td class="sag para"><?= number_format($h['iade'] > 0 ? $h['iade'] : $h['odenecek'], 2, ',', '.') ?></td>
    </tr>
  </tbody>
</table>

<div class="iki-sutun">
  <div>
    <h2><?= (int) $yil ?> Tarifesi ve Dilim Dağılımı</h2>
    <table>
      <thead>
        <tr>
          <th style="width:6%">#</th>
          <th>Dilim</th>
          <th class="sag">Matrah</th>
          <th class="sag" style="width:12%">Oran</th>
          <th class="sag">Vergi</th>
        </tr>
      </thead>
      <tbody>
        <?php $kir = []; foreach ($h['kirilim'] as $x) { $kir[$x['sira']] = $x; } ?>
        <?php foreach ($dilimler as $d): ?>
          <?php $x = $kir[(int) $d['sira']] ?? null; ?>
          <tr class="<?= (int) $d['sira'] === (int) $h['dilim_no'] ? 'aktif-dilim' : '' ?>">
            <td class="orta"><?= (int) $d['sira'] ?></td>
            <td class="kucuk">
              <?php if ($d['tavan'] === null): ?>
                <?= number_format($d['taban'], 0, ',', '.') ?> üzeri
              <?php elseif ($d['taban'] <= 0): ?>
                <?= number_format($d['tavan'], 0, ',', '.') ?> TL'ye kadar
              <?php else: ?>
                <?= number_format($d['taban'], 0, ',', '.') ?>–<?= number_format($d['tavan'], 0, ',', '.') ?>
              <?php endif; ?>
            </td>
            <td class="sag para"><?= $x !== null ? number_format($x['matrah'], 2, ',', '.') : '—' ?></td>
            <td class="sag">%<?= rtrim(rtrim(number_format($d['oran'], 2, ',', '.'), '0'), ',') ?></td>
            <td class="sag para"><?= $x !== null ? number_format($x['vergi'], 2, ',', '.') : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($dilimler === []): ?>
          <tr><td colspan="5" class="orta kucuk">Bu yıl için tarife tanımlı değil.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div>
    <h2>Aylık Makbuz Dağılımı</h2>
    <table>
      <thead>
        <tr><th>Ay</th><th class="orta" style="width:16%">Adet</th>
            <th class="sag">Brüt</th><th class="sag">Stopaj</th></tr>
      </thead>
      <tbody>
        <?php foreach ($aylik as $a): ?>
          <?php if ($a['adet'] <= 0) { continue; } ?>
          <tr>
            <td><?= ayAdi($a['ay']) ?></td>
            <td class="orta"><?= (int) $a['adet'] ?></td>
            <td class="sag para"><?= number_format($a['brut'], 2, ',', '.') ?></td>
            <td class="sag para"><?= number_format($a['stopaj'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($mb['adet'] <= 0): ?>
          <tr><td colspan="4" class="orta kucuk"><?= (int) $yil ?> yılında kesilmiş makbuz yok.</td></tr>
        <?php endif; ?>
      </tbody>
      <tbody>
        <tr style="background:#f1f5f9;font-weight:700">
          <td>TOPLAM</td>
          <td class="orta"><?= (int) $mb['adet'] ?></td>
          <td class="sag para"><?= number_format($mb['brut'], 2, ',', '.') ?></td>
          <td class="sag para"><?= number_format($mb['stopaj'], 2, ',', '.') ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php if ($h['kip'] === 'ucret' && $h['ucret_adet'] > 0): ?>
  <h2>Yıllık Sözleşme Ücretleri — <?= (int) $yil ?> (hesabın kaynağı)</h2>
  <table>
    <thead>
      <tr>
        <th style="width:5%">#</th>
        <th>Mükellef</th>
        <th style="width:14%">VKN / TCKN</th>
        <th class="sag" style="width:16%">Yıllık Ücret</th>
        <th class="sag" style="width:14%">Stopaj</th>
        <th class="sag" style="width:14%">KDV</th>
      </tr>
    </thead>
    <tbody>
      <?php $ui = 0; ?>
      <?php foreach ($h['ucret']['mukellefler'] as $u): ?>
        <?php $ui++; ?>
        <tr>
          <td class="orta kucuk"><?= $ui ?></td>
          <td><?= esc($u['unvan']) ?><?= empty($u['aktif']) ? ' (pasif)' : '' ?></td>
          <td class="kucuk"><?= esc($u['vkn']) ?></td>
          <td class="sag para"><?= number_format($u['ucret'], 2, ',', '.') ?></td>
          <td class="sag para"><?= number_format($u['stopaj'], 2, ',', '.') ?></td>
          <td class="sag para"><?= number_format($u['kdv'], 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
      <tr style="background:#f1f5f9;font-weight:700">
        <td colspan="3">TOPLAM (<?= $ui ?> mükellef)</td>
        <td class="sag para"><?= number_format($h['ucret_brut'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($h['ucret_stopaj'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($h['ucret_kdv'], 2, ',', '.') ?></td>
      </tr>
    </tbody>
  </table>
<?php endif; ?>

<?php
// --- İndirim belgesi listeleri (varsa) --------------------------------
$kalemBasliklar = [
    'egitim_saglik' => ['Eğitim ve Sağlık Harcamaları', $h['egitim_oran'], $h['egitim_tavan'], $h['egitim'], $h['egitim_asim']],
    'sigorta'       => ['Şahıs / Hayat Sigorta Primleri', $h['sigorta_oran'], $h['sigorta_tavan'], $h['sigorta'], $h['sigorta_asim']],
];
$kalemTurAd = \App\Models\IndirimKalemModel::TURLER;
?>
<?php foreach ($kalemBasliklar as $kk => $kb): ?>
  <?php $satirlar = $kalemler[$kk] ?? []; ?>
  <?php if ($satirlar === []) { continue; } ?>
  <?php $kToplam = 0.0; foreach ($satirlar as $x) { $kToplam += (float) $x['tutar']; } ?>

  <h2><?= esc($kb[0]) ?> — <?= (int) $yil ?></h2>
  <table>
    <thead>
      <tr>
        <th style="width:12%">Tarih</th>
        <th style="width:15%">Tür</th>
        <th>Açıklama</th>
        <th class="sag" style="width:18%">Tutar</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($satirlar as $x): ?>
        <tr>
          <td><?= trTarih($x['tarih']) ?></td>
          <td class="kucuk"><?= esc($kalemTurAd[$kk][$x['tur']] ?? $x['tur']) ?></td>
          <td><?= esc($x['aciklama'] ?: '—') ?></td>
          <td class="sag para"><?= number_format((float) $x['tutar'], 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
      <tr style="background:#f1f5f9;font-weight:700">
        <td colspan="3">TOPLAM (<?= count($satirlar) ?> belge)</td>
        <td class="sag para"><?= number_format($kToplam, 2, ',', '.') ?></td>
      </tr>
      <tr>
        <td colspan="3" class="kucuk">
          Mevzuat üst sınırı (kârın %<?= (int) $kb[1] ?>'i):
          <?= number_format($kb[2], 2, ',', '.') ?> ₺
          <?php if ($kb[4] > 0): ?>
            · <b><?= number_format($kb[4], 2, ',', '.') ?> ₺ sınır aşımı indirilemedi</b>
          <?php endif; ?>
        </td>
        <td class="sag para kalin">
          <?= number_format($kb[3], 2, ',', '.') ?>
          <span class="not">hesaba giren</span>
        </td>
      </tr>
    </tbody>
  </table>
<?php endforeach; ?>

<?php
$kdvT = ['odenen' => 0.0, 'indirilecek' => 0.0, 'toplam' => 0.0];

foreach ($kdv as $x) {
    $kdvT['odenen']      += $x['odenen'];
    $kdvT['indirilecek'] += $x['indirilecek'];
    $kdvT['toplam']      += $x['toplam'];
}
?>
<?php if ($kdvT['toplam'] > 0): ?>
  <h2>Aylık KDV Tablosu — <?= (int) $yil ?></h2>
  <table>
    <thead>
      <tr>
        <th style="width:14%">Ay</th>
        <th class="sag" style="width:20%">Ödenen KDV</th>
        <th class="sag" style="width:20%">İndirilecek KDV</th>
        <th class="sag" style="width:18%">Ay Toplamı</th>
        <th>Açıklama</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($kdv as $x): ?>
        <?php if ($x['toplam'] <= 0 && ($x['aciklama'] ?? '') === '') { continue; } ?>
        <tr>
          <td><?= ayAdi((int) $x['ay']) ?></td>
          <td class="sag para"><?= number_format($x['odenen'], 2, ',', '.') ?></td>
          <td class="sag para"><?= number_format($x['indirilecek'], 2, ',', '.') ?></td>
          <td class="sag para kalin"><?= number_format($x['toplam'], 2, ',', '.') ?></td>
          <td class="kucuk"><?= esc($x['aciklama'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <tr style="background:#f1f5f9;font-weight:700">
        <td>TOPLAM</td>
        <td class="sag para"><?= number_format($kdvT['odenen'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($kdvT['indirilecek'], 2, ',', '.') ?></td>
        <td class="sag para"><?= number_format($kdvT['toplam'], 2, ',', '.') ?></td>
        <td class="kucuk">ay toplamı = mahsuba giren</td>
      </tr>
      <tr>
        <td colspan="3" class="kucuk">
          Makbuz KDV yükümlülüğü <?= number_format($h['kdv_yukumluluk'], 2, ',', '.') ?> ₺ ·
          ödenen (ay toplamları) <?= number_format($h['kdv_odenen'], 2, ',', '.') ?> ₺
        </td>
        <td class="sag para kalin">
          <?= number_format($h['kdv_alacak'] > 0 ? $h['kdv_alacak'] : $h['kdv_borc'], 2, ',', '.') ?>
        </td>
        <td class="kucuk"><?= $h['kdv_alacak'] > 0 ? 'fazla ödeme' : 'kalan borç' ?></td>
      </tr>
    </tbody>
  </table>
<?php endif; ?>

<?php
$agT = 0.0;
$agN = 0;

foreach ($giderler as $x) {
    $agT += $x['tutar'];

    if ($x['tutar'] > 0) {
        $agN++;
    }
}
?>
<?php if ($agT > 0): ?>
  <h2>Aylık Gider Tablosu — <?= (int) $yil ?></h2>
  <table>
    <thead>
      <tr>
        <th style="width:16%">Ay</th>
        <th class="sag" style="width:22%">Gider Tutarı</th>
        <th>Açıklama</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($giderler as $x): ?>
        <?php if ($x['tutar'] <= 0 && ($x['aciklama'] ?? '') === '') { continue; } ?>
        <tr>
          <td><?= ayAdi((int) $x['ay']) ?></td>
          <td class="sag para"><?= number_format($x['tutar'], 2, ',', '.') ?></td>
          <td class="kucuk"><?= esc($x['aciklama'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <tr style="background:#f1f5f9;font-weight:700">
        <td>TOPLAM (<?= $agN ?> ay)</td>
        <td class="sag para"><?= number_format($agT, 2, ',', '.') ?></td>
        <td class="kucuk">
          elle girilen <?= number_format($h['gider_elle'], 2, ',', '.') ?> ile birlikte
          toplam gider <?= number_format($h['gider'], 2, ',', '.') ?> ₺
        </td>
      </tr>
    </tbody>
  </table>
<?php endif; ?>

<div class="dipnot">
  <?php if ($h['kip'] === 'ucret'): ?>
    Hasılat, mükellef kartlarına girilen <b>yıllık sözleşme ücretlerinden</b> hesaplanmıştır
    (projeksiyon): stopaj %<?= (int) $h['ucret_stopaj_oran'] ?>, KDV
    %<?= (int) $h['ucret_kdv_oran'] ?>.
  <?php else: ?>
    Hasılat, Makbuz Takip modülünde kayıtlı serbest meslek makbuzlarının brüt toplamıdır.
  <?php endif; ?>
  Şahıs sigorta primi, Bağ-Kur sonrası kazancın %<?= (int) $h['sigorta_oran'] ?>'ini;
  eğitim-sağlık harcaması %<?= (int) $h['egitim_oran'] ?>'unu aşamaz (GVK md.89);
  sınırı aşan kısım indirilmemiştir.
  KDV, gelir vergisi matrahına dahil EDİLMEZ. Makbuz kesildiğinde KDV yükümlülüğü doğar;
  yıl içinde ödenen kısım (aylık tablonun <b>ödenen + indirilecek</b> toplamı)
  düşülerek <b>kalan KDV borcu</b> bulunur ve stopaj
  alacağıyla mahsuplaşır. <b>Yıl içi vergi yükü</b> = gelir vergisi dengesi + kalan KDV
  borcu; yani devlete net ödenecek / devletten alınacak tutardır.
  Vergi, <?= (int) $yil ?> yılı GVK md.103 (ücret dışı gelirler) tarifesine göre hesaplanmıştır.
  Bu döküm <b>bilgilendirme amaçlıdır</b>; resmi beyan yerine geçmez.
</div>

<div class="imza">
  <div>Hazırlayan</div>
  <div>Kontrol Eden</div>
</div>

</body></html>
