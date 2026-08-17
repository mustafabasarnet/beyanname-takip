<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
/**
 * GELİR VERGİSİ HESABI — tek mali müşavir
 *
 * Sol sütun: gider ve mahsup girişleri (yazdıkça canlı hesap)
 * Sağ sütun: adım adım hesap dökümü
 *
 * Stiller gömülü: stil.css kopyalanmasa da ekran doğru görünür.
 */
$k  = $h['kayit'];
$mb = $h['makbuz'];

/** Para biçimi (boş = boş kutu) */
$p = static fn ($v) => $v === null || $v === '' ? '' : number_format((float) $v, 2, ',', '.');
?>

<style>
.gv-duzen{display:grid;grid-template-columns:minmax(300px,360px) 1fr;gap:16px;align-items:start}
@media(max-width:980px){.gv-duzen{grid-template-columns:1fr}}

.gv-kart{background:#fff;border:1px solid var(--gri-200,#e2e8f0);border-radius:10px;overflow:hidden}
.gv-kart-bas{padding:10px 14px;border-bottom:1px solid var(--gri-200,#e2e8f0);
  font-weight:700;font-size:13.5px;background:var(--gri-50,#f8fafc);display:flex;
  align-items:center;justify-content:space-between;gap:8px}
.gv-kart-govde{padding:12px 14px}

/* Giriş alanları */
.gv-alan{margin-bottom:11px}
.gv-alan label{display:block;font-size:11.5px;font-weight:600;color:var(--gri-600,#475569);margin-bottom:3px}
.gv-alan .ipucu{font-size:10.5px;color:var(--gri-500,#64748b);font-weight:400;margin-top:2px;line-height:1.35}
.gv-para{width:100%;padding:7px 9px;border:1px solid var(--gri-300,#cbd5e1);border-radius:6px;
  font-size:14px;text-align:right;font-variant-numeric:tabular-nums;font-family:inherit}
.gv-para:focus{outline:none;border-color:var(--ana,#2563eb);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.gv-para.buyuk{font-size:17px;font-weight:700;padding:9px 11px}
.gv-ayrac{border:0;border-top:1px dashed var(--gri-200,#e2e8f0);margin:13px 0}
.gv-baslik-kucuk{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;
  color:var(--gri-500,#64748b);font-weight:700;margin-bottom:8px}

/* Hesap dökümü tablosu */
.gv-hesap{width:100%;border-collapse:collapse}
.gv-hesap td{padding:7px 10px;border-bottom:1px solid var(--gri-100,#f1f5f9);font-size:13.5px}
.gv-hesap td.sag{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.gv-hesap tr.gv-eksi td:first-child{padding-left:22px;color:var(--gri-600,#475569)}
.gv-hesap tr.gv-eksi td.sag{color:#b91c1c}
.gv-hesap tr.gv-arti td:first-child{padding-left:22px;color:var(--gri-600,#475569)}
.gv-hesap tr.gv-kirilim td{background:#f8fafc;font-weight:600;font-size:13px;
  border-top:1px dashed var(--gri-300,#cbd5e1)}
.gv-hesap tr.gv-kirilim td.sag.yesil{color:#047857}
.gv-hesap tr.gv-arti td.sag{color:#0369a1}

/* Sınırlı indirim rozeti ve aşım uyarısı */
.gv-limit{display:inline-block;margin-left:5px;padding:1px 6px;border-radius:99px;
  background:#e0e7ff;color:#3730a3;font-size:10px;font-weight:700}
.gv-liste-not{margin-top:4px;padding:5px 8px;border-radius:6px;background:#eff6ff;
  color:#1e40af;font-size:11px;line-height:1.35;border:1px solid #bfdbfe}
.gv-para[readonly]{background:var(--gri-100,#f1f5f9);color:var(--gri-600,#475569);cursor:not-allowed}
.gv-asim{margin-top:4px;padding:5px 8px;border-radius:6px;background:#fef3c7;
  color:#92400e;font-size:11px;line-height:1.35;border:1px solid #fde68a}

/* Hesap kipi seçici */
.kip-secici{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:14px;
  padding:10px 12px;background:#fff;border:1px solid var(--gri-200,#e2e8f0);border-radius:10px}
.kip-etiket{font-size:11.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.3px;color:var(--gri-500,#64748b)}
.kip-dugme{font:inherit;text-align:left;padding:7px 13px;border-radius:8px;cursor:pointer;
  border:1px solid var(--gri-300,#cbd5e1);background:#fff;color:var(--gri-700,#334155);
  font-size:13px;font-weight:600;line-height:1.25;transition:.15s}
.kip-dugme:hover{border-color:var(--ana,#2563eb);background:var(--gri-50,#f8fafc)}
.kip-dugme.aktif{background:#0f172a;color:#fff;border-color:#0f172a}
.kip-dugme small{display:block;font-size:10.5px;font-weight:400;opacity:.75;margin-top:1px}
.kip-not{font-size:11.5px;color:var(--gri-500,#64748b);flex:1;min-width:200px;line-height:1.4}

/* Yıllık ücret dökümü — katlanır kart + sayfalama */
.uc-bas{cursor:pointer;user-select:none}
.uc-bas:hover{background:var(--gri-100,#f1f5f9)}
.uc-ok{display:inline-block;transition:transform .18s;font-size:11px;
  color:var(--gri-500,#64748b);margin-right:2px}
.uc-bas[aria-expanded="true"] .uc-ok{transform:rotate(90deg)}
.uc-ac-yazi{margin-left:8px;padding:1px 8px;border-radius:99px;
  background:var(--ana-acik,#dbeafe);color:var(--ana-koyu,#1e40af);
  font-size:10.5px;font-weight:700}
.uc-arac{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
.uc-sayac{font-size:11.5px;color:var(--gri-500,#64748b)}
.uc-gezinme{margin-left:auto;display:flex;gap:7px;align-items:center}
.uc-gezinme button:disabled{opacity:.4;cursor:not-allowed}

/* Yıllık ücret dökümü */
.ucret-tablo{width:100%;border-collapse:collapse}
.ucret-tablo th{font-size:10px;text-transform:uppercase;letter-spacing:.3px;
  color:var(--gri-500,#64748b);font-weight:700;padding:6px 8px;text-align:left;
  border-bottom:1px solid var(--gri-200,#e2e8f0);white-space:nowrap}
.ucret-tablo th.sag{text-align:right}
.ucret-tablo td{padding:6px 8px;border-bottom:1px solid var(--gri-100,#f1f5f9);font-size:13px}
.ucret-tablo td.sag{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.ucret-tablo tbody tr:hover{background:var(--gri-50,#f8fafc)}
.ucret-tablo tr.pasif td{opacity:.6}
.ucret-tablo tfoot td{background:var(--gri-50,#f8fafc);font-weight:700;font-size:13.5px;
  border-top:2px solid var(--gri-300,#cbd5e1);font-variant-numeric:tabular-nums;padding:9px 8px}

/* İndirim kalemi listeleri (eğitim-sağlık / sigorta) */
.kalem-ozet{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.kalem-ozet>div{flex:1;min-width:150px;border:1px solid var(--gri-200,#e2e8f0);
  border-radius:8px;padding:8px 11px;background:var(--gri-50,#f8fafc)}
.kalem-ozet .et{font-size:10px;text-transform:uppercase;letter-spacing:.3px;
  color:var(--gri-500,#64748b);font-weight:700}
.kalem-ozet .dg{font-size:16px;font-weight:800;font-variant-numeric:tabular-nums;margin-top:2px}
.kalem-ozet .yesil .dg{color:#047857}
.kalem-ozet .kirmizi .dg{color:#b91c1c}
.kalem-ozet .yesil{background:#f0fdf4;border-color:#bbf7d0}
.kalem-ozet .kirmizi{background:#fef2f2;border-color:#fecaca}

.kalem-tablo{width:100%;border-collapse:collapse}
.kalem-tablo th{font-size:10px;text-transform:uppercase;letter-spacing:.3px;
  color:var(--gri-500,#64748b);font-weight:700;padding:6px 8px;text-align:left;
  border-bottom:1px solid var(--gri-200,#e2e8f0);white-space:nowrap}
.kalem-tablo th.sag{text-align:right}
.kalem-tablo td{padding:6px 8px;border-bottom:1px solid var(--gri-100,#f1f5f9);font-size:13px}
.kalem-tablo td.sag{text-align:right}
.kalem-tablo td.tarih{white-space:nowrap;font-variant-numeric:tabular-nums;
  color:var(--gri-700,#334155);font-weight:600}
.kalem-tablo td.tutar{font-variant-numeric:tabular-nums;font-weight:700;white-space:nowrap}
.kalem-tablo td.aciklama{color:var(--gri-600,#475569)}
.kalem-tablo td.islem{white-space:nowrap;text-align:right}
.kalem-tablo tbody tr:hover{background:var(--gri-50,#f8fafc)}
.kalem-tablo tr.duzenleniyor td{background:#fef3c7}
.kalem-tablo tr.bos td{text-align:center;color:var(--gri-500,#64748b);
  padding:18px 8px;font-size:12.5px;line-height:1.5}
.kalem-tablo tfoot td{background:var(--gri-50,#f8fafc);font-weight:700;font-size:13.5px;
  border-top:2px solid var(--gri-300,#cbd5e1);font-variant-numeric:tabular-nums;padding:9px 8px}

.kalem-rozet{display:inline-block;padding:2px 8px;border-radius:99px;
  font-size:10.5px;font-weight:700;white-space:nowrap}
.kalem-rozet.t-egitim{background:#dbeafe;color:#1e40af}
.kalem-rozet.t-saglik{background:#dcfce7;color:#166534}
.kalem-rozet.t-hayat{background:#f3e8ff;color:#6b21a8}
.kalem-rozet.t-sahis{background:#fce7f3;color:#9d174d}
.kalem-rozet.t-diger{background:#e2e8f0;color:#475569}

.kalem-form{display:flex;gap:9px;align-items:flex-end;flex-wrap:wrap;
  margin-top:12px;padding:11px 12px;background:var(--gri-50,#f8fafc);
  border:1px solid var(--gri-200,#e2e8f0);border-radius:9px}
.kalem-form.duzenleme{background:#fffbeb;border-color:#fde68a}
.kalem-form .alan{display:flex;flex-direction:column;gap:4px}
.kalem-form .alan.genis{flex:1;min-width:180px}
.kalem-form .alan.dugme{flex-direction:row;gap:6px}
.kalem-form label{font-size:11px;font-weight:700;color:var(--gri-600,#475569);
  text-transform:uppercase;letter-spacing:.3px}
.kalem-form .girdi,.kalem-form select{padding:7px 9px;font-size:13px}
.kalem-form .girdi.para{text-align:right;font-variant-numeric:tabular-nums;max-width:140px}

.kalem-kopya{margin-top:10px;font-size:12.5px}
.kalem-kopya summary{cursor:pointer;color:var(--ana,#2563eb);font-weight:600}
.kalem-kopya form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:9px}

/* Aylık KDV tablosu */
.kdv-tablo{width:100%;border-collapse:collapse}
.kdv-tablo th{font-size:10px;text-transform:uppercase;letter-spacing:.3px;
  color:var(--gri-500,#64748b);font-weight:700;padding:6px 7px;text-align:left;
  border-bottom:1px solid var(--gri-200,#e2e8f0);white-space:nowrap}
.kdv-tablo th.sag{text-align:right}
.kdv-tablo td{padding:3px 5px;border-bottom:1px solid var(--gri-100,#f1f5f9)}
.kdv-tablo td.ay{font-size:12.5px;font-weight:600;color:var(--gri-700,#334155);white-space:nowrap}
.kdv-tablo td.sag{text-align:right;font-variant-numeric:tabular-nums;font-size:12.5px}
.kdv-tablo tbody tr:hover{background:var(--gri-50,#f8fafc)}
.kdv-tablo tfoot td{background:var(--gri-50,#f8fafc);font-weight:700;font-size:13px;
  border-top:2px solid var(--gri-300,#cbd5e1);font-variant-numeric:tabular-nums;padding:8px 7px}
.kdv-girdi{width:100%;padding:5px 7px;border:1px solid var(--gri-300,#cbd5e1);
  border-radius:5px;font-size:12.5px;text-align:right;font-family:inherit;
  font-variant-numeric:tabular-nums;background:#fff}
.kdv-girdi:focus{outline:none;border-color:var(--ana,#2563eb);
  box-shadow:0 0 0 2px rgba(37,99,235,.12)}
.kdv-girdi.dolu{background:#f0fdf4;border-color:#86efac}
.kdv-ozet{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px}
.kdv-ozet div{flex:1;min-width:120px;border:1px solid var(--gri-200,#e2e8f0);
  border-radius:8px;padding:8px 10px;background:var(--gri-50,#f8fafc)}
.kdv-ozet .et{font-size:10px;text-transform:uppercase;letter-spacing:.3px;
  color:var(--gri-500,#64748b);font-weight:700}
.kdv-ozet .dg{font-size:16px;font-weight:800;font-variant-numeric:tabular-nums;margin-top:2px}
.kdv-ozet .mavi .dg{color:#0369a1}
.kdv-ozet .yesil{background:#f0fdf4;border-color:#bbf7d0}
.kdv-ozet .yesil .dg{color:#047857}
.gv-hesap tr.gv-ara td{background:var(--gri-50,#f8fafc);font-weight:700;
  border-top:1px solid var(--gri-300,#cbd5e1)}
.gv-hesap tr.gv-vurgu td{background:#eff6ff;font-weight:700;font-size:14.5px}
.gv-hesap tr.gv-sonuc td{background:#0f172a;color:#fff;font-weight:700;font-size:16px}
.gv-hesap tr.gv-sonuc.iade td{background:#047857}
.gv-hesap tr.gv-sonuc.notr td{background:#475569}
.gv-hesap .aciklama{font-size:10.5px;color:var(--gri-500,#64748b);font-weight:400;display:block}

/* Dilim şeridi */
.gv-dilimler{width:100%;border-collapse:collapse;font-size:12px;margin-top:4px}
.gv-dilimler th{font-size:10px;text-transform:uppercase;letter-spacing:.3px;
  color:var(--gri-500,#64748b);text-align:left;padding:5px 8px;border-bottom:1px solid var(--gri-200,#e2e8f0)}
.gv-dilimler th.sag{text-align:right}
.gv-dilimler td{padding:5px 8px;border-bottom:1px solid var(--gri-100,#f1f5f9)}
.gv-dilimler td.sag{text-align:right;font-variant-numeric:tabular-nums}
.gv-dilimler tr.aktif td{background:#fef3c7;font-weight:700}
.gv-dilimler tr.dolu td{background:#f0fdf4}

.gv-uyari{padding:10px 12px;border-radius:8px;background:#fef3c7;color:#92400e;
  font-size:12.5px;margin-bottom:12px;border:1px solid #fde68a}
.gv-bilgi{padding:9px 12px;border-radius:8px;background:#eff6ff;color:#1e40af;
  font-size:12px;margin-bottom:12px;border:1px solid #bfdbfe;line-height:1.45}

.gv-ust{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.gv-onay{display:flex;align-items:flex-start;gap:7px;font-size:12.5px;
  padding:9px 10px;background:var(--gri-50,#f8fafc);border-radius:7px;
  border:1px solid var(--gri-200,#e2e8f0);cursor:pointer}
.gv-onay input{margin-top:2px;flex:0 0 auto}

/* Aylık şerit */
.gv-aylik{display:grid;grid-template-columns:repeat(12,1fr);gap:3px;margin-top:6px}
.gv-ay{text-align:center;font-size:9.5px;color:var(--gri-500,#64748b)}
.gv-ay .sutun{height:44px;display:flex;align-items:flex-end;margin-bottom:3px}
.gv-ay .sutun i{display:block;width:100%;background:#2563eb;border-radius:3px 3px 0 0;min-height:2px}
.gv-ay.bos .sutun i{background:var(--gri-200,#e2e8f0)}
.gv-ay b{display:block;font-size:9.5px;color:var(--gri-700,#334155);font-weight:600}

.gv-rozet{display:inline-block;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:700}
.gv-rozet.mavi{background:#dbeafe;color:#1e40af}
.gv-rozet.yesil{background:#d1fae5;color:#065f46}
</style>

<div class="gv-ust">
  <a href="<?= site_url('gelir-vergisi?yil=' . (int) $yil) ?>" class="btn ikincil kucuk">← Listeye Dön</a>
  <h2 style="margin:0"><?= esc($musavir['ad_soyad']) ?></h2>
  <span class="gv-rozet mavi"><?= (int) $yil ?> Gelir Vergisi</span>

  <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
    <form method="get" style="display:flex;gap:6px;align-items:center;margin:0">
      <label class="kucuk-yazi" style="margin:0">Yıl</label>
      <select name="yil" data-oto-filtre style="padding:5px 8px">
        <?php foreach (yilSecenekleri() as $y): ?>
          <option value="<?= $y ?>" <?= (int) $yil === $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <a href="<?= site_url('gelir-vergisi/yazdir/' . (int) $musavir['id'] . '?yil=' . (int) $yil) ?>"
       target="_blank" class="btn ikincil kucuk">🖨️ Yazdır</a>
    <a href="<?= site_url('gelir-vergisi/tarife?yil=' . (int) $yil) ?>" class="btn ikincil kucuk">📐 Tarife</a>
  </div>
</div>

<!-- ============ HESAP KİPİ SEÇİCİ ============ -->
<form method="post" action="<?= site_url('gelir-vergisi/kip') ?>" class="kip-secici">
  <?= csrf_field() ?>
  <input type="hidden" name="musavir_id" value="<?= (int) $musavir['id'] ?>">
  <input type="hidden" name="yil" value="<?= (int) $yil ?>">

  <span class="kip-etiket">Hesap kaynağı:</span>

  <button type="submit" name="kip" value="ucret"
          class="kip-dugme <?= $h['kip'] === 'ucret' ? 'aktif' : '' ?>">
    📅 Yıllık Ücret Projeksiyonu
    <small><?= (int) $h['ucret_adet'] ?> mükellef ·
      <?= number_format($h['ucret_brut'], 2, ',', '.') ?> ₺</small>
  </button>

  <button type="submit" name="kip" value="makbuz"
          class="kip-dugme <?= $h['kip'] === 'makbuz' ? 'aktif' : '' ?>">
    🧾 Kesilen Makbuzlar
    <small><?= (int) $h['makbuz']['adet'] ?> makbuz ·
      <?= number_format($h['makbuz']['brut'], 2, ',', '.') ?> ₺</small>
  </button>

  <span class="kip-not">
    <?php if ($h['kip'] === 'ucret'): ?>
      Yıllık sözleşme ücretleri makbuza dönüşmüş kabul ediliyor —
      <b>yıl sonu vergi yükünüzü</b> şimdiden görüyorsunuz.
    <?php else: ?>
      Yalnızca fiilen kesilmiş makbuzlar sayılıyor — <b>bugüne kadarki</b> durum.
    <?php endif; ?>
  </span>
</form>

<?php if (! $h['tarife_var']): ?>
  <div class="gv-uyari">
    <b><?= (int) $yil ?> yılı için gelir vergisi tarifesi tanımlı değil.</b>
    Vergi hesaplanamaz. <a href="<?= site_url('gelir-vergisi/tarife?yil=' . (int) $yil) ?>">Tarife ekranından</a>
    dilimleri girin veya önceki yıldan kopyalayın.
  </div>
<?php endif; ?>

<form method="post" action="<?= site_url('gelir-vergisi/kaydet') ?>" id="gv-form">
  <?= csrf_field() ?>
  <input type="hidden" name="musavir_id" value="<?= (int) $musavir['id'] ?>">
  <input type="hidden" name="yil" value="<?= (int) $yil ?>">
  <input type="hidden" name="hesap_kipi" value="<?= esc($h['kip']) ?>">

  <div class="gv-duzen">

    <!-- ============ SOL: GİRİŞLER ============ -->
    <div>
      <div class="gv-kart">
        <div class="gv-kart-bas">✏️ Gider ve Mahsup Girişi</div>
        <div class="gv-kart-govde">

          <div class="gv-bilgi">
            <?php if ($h['kip'] === 'ucret'): ?>
              <b>Hasılat yıllık sözleşme ücretlerinden geliyor:</b>
              <?= (int) $yil ?> yılında <?= (int) $h['ucret_adet'] ?> mükellef,
              toplam <b><?= number_format($h['hasilat_oto'], 2, ',', '.') ?> ₺</b>.
              <br>Ücretler makbuza dönüşmüş kabul edilir; stopaj
              %<?= (int) $h['ucret_stopaj_oran'] ?>, KDV
              %<?= (int) $h['ucret_kdv_oran'] ?> olarak hesaplanır.
            <?php else: ?>
              <b>Hasılat kesilen makbuzlardan geliyor:</b>
              <?= (int) $yil ?> yılında <?= (int) $mb['adet'] ?> makbuz,
              brüt <b><?= number_format($h['hasilat_oto'], 2, ',', '.') ?> ₺</b>.
              <?php if ($kaynak === 'tahsil'): ?>
                <br>Yalnızca <b>tahsil edilen</b> makbuzlar sayılıyor (Ayarlar'dan değişir).
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <div class="gv-alan">
            <label for="gv-gider">Toplam Mesleki Gider (₺)</label>
            <input type="text" class="gv-para buyuk" id="gv-gider" name="gider"
                   value="<?= $p($k['gider']) ?>" inputmode="decimal" placeholder="0,00" autofocus>
            <div class="ipucu">Kira, personel, amortisman, aidat, kırtasiye… toplamı.</div>
          </div>

          <hr class="gv-ayrac">
          <div class="gv-baslik-kucuk">Matrahtan İndirimler</div>

          <div class="gv-alan">
            <label for="gv-bagkur">Ödenen Bağ-Kur / SGK Primi (₺)</label>
            <input type="text" class="gv-para" id="gv-bagkur" name="bagkur"
                   value="<?= $p($k['bagkur']) ?>" inputmode="decimal" placeholder="0,00">
            <div class="ipucu">Sınırsız indirilir.</div>
          </div>

          <div class="gv-alan">
            <label for="gv-sigorta">
              Şahıs / Hayat Sigorta Primi (₺)
              <span class="gv-limit">en çok %<?= (int) $h['sigorta_oran'] ?></span>
            </label>
            <input type="text" class="gv-para" id="gv-sigorta" name="sigorta_primi"
                   value="<?= $h['sigorta_liste'] ? number_format($h['sigorta_talep'], 2, ',', '.') : $p($k['sigorta_primi'] ?? 0) ?>"
                   inputmode="decimal" placeholder="0,00"
                   <?= $h['sigorta_liste'] ? 'readonly' : '' ?>>
            <?php if ($h['sigorta_liste']): ?>
              <div class="gv-liste-not">
                🔒 Aşağıdaki <b><?= (int) $h['sigorta_adet'] ?> belgelik liste</b>den geliyor.
                <a href="#sigorta">Listeye git</a>
              </div>
            <?php endif; ?>
            <div class="ipucu">
              Bağ-Kur sonrası kazancın %<?= (int) $h['sigorta_oran'] ?>'ini aşamaz (GVK 89/1).
              Üst sınır: <b id="gv-sigorta-tavan"><?= number_format($h['sigorta_tavan'], 2, ',', '.') ?></b> ₺
            </div>
            <div class="gv-asim" id="gv-sigorta-asim"
                 style="<?= $h['sigorta_asim'] > 0 ? '' : 'display:none' ?>">
              ⚠ <b id="gv-sigorta-asim-tutar"><?= number_format($h['sigorta_asim'], 2, ',', '.') ?></b> ₺
              sınırı aştığı için indirilemedi.
            </div>
          </div>

          <div class="gv-alan">
            <label for="gv-egitim">
              Eğitim ve Sağlık Harcaması (₺)
              <span class="gv-limit">en çok %<?= (int) $h['egitim_oran'] ?></span>
            </label>
            <input type="text" class="gv-para" id="gv-egitim" name="egitim_saglik"
                   value="<?= $h['egitim_liste'] ? number_format($h['egitim_talep'], 2, ',', '.') : $p($k['egitim_saglik'] ?? 0) ?>"
                   inputmode="decimal" placeholder="0,00"
                   <?= $h['egitim_liste'] ? 'readonly' : '' ?>>
            <?php if ($h['egitim_liste']): ?>
              <div class="gv-liste-not">
                🔒 Aşağıdaki <b><?= (int) $h['egitim_adet'] ?> belgelik liste</b>den geliyor.
                <a href="#egitim_saglik">Listeye git</a>
              </div>
            <?php endif; ?>
            <div class="ipucu">
              Bağ-Kur sonrası kazancın %<?= (int) $h['egitim_oran'] ?>'unu aşamaz (GVK 89/2).
              Üst sınır: <b id="gv-egitim-tavan"><?= number_format($h['egitim_tavan'], 2, ',', '.') ?></b> ₺
            </div>
            <div class="gv-asim" id="gv-egitim-asim"
                 style="<?= $h['egitim_asim'] > 0 ? '' : 'display:none' ?>">
              ⚠ <b id="gv-egitim-asim-tutar"><?= number_format($h['egitim_asim'], 2, ',', '.') ?></b> ₺
              sınırı aştığı için indirilemedi.
            </div>
          </div>

          <hr class="gv-ayrac">
          <div class="gv-baslik-kucuk">Vergiden Mahsuplar</div>

          <div class="gv-alan">
            <label for="gv-stopaj">Stopaj / Tevkifat (₺)</label>
            <input type="text" class="gv-para" id="gv-stopaj" name="stopaj_elle"
                   value="<?= $p($k['stopaj_elle']) ?>" inputmode="decimal"
                   placeholder="<?= number_format($h['stopaj_oto'], 2, ',', '.') ?> (<?= $h['kip'] === 'ucret' ? 'ücretlerden' : 'makbuzlardan' ?>)">
            <div class="ipucu">
              Boş bırakırsanız
              <?= $h['kip'] === 'ucret' ? 'yıllık ücretlerden' : 'makbuzlardan' ?> gelen
              <b><?= number_format($h['stopaj_oto'], 2, ',', '.') ?> ₺</b> kullanılır.
            </div>
          </div>

          <div class="gv-alan">
            <label for="gv-diger-m">Diğer Mahsuplar (₺)</label>
            <input type="text" class="gv-para" id="gv-diger-m" name="diger_mahsup"
                   value="<?= $p($k['diger_mahsup']) ?>" inputmode="decimal" placeholder="0,00">
          </div>

          <label class="gv-onay">
            <input type="checkbox" name="uyumlu_indirim" value="1" id="gv-uyumlu"
                   <?= ! empty($k['uyumlu_indirim']) ? 'checked' : '' ?>>
            <span>
              <b>%<?= rtrim(rtrim(number_format($h['uyumlu_oran'], 2, ',', '.'), '0'), ',') ?>
                 vergiye uyumlu mükellef indirimi</b> (GVK mük.121)
              <span class="ipucu" style="display:block">
                Hesaplanan verginin %<?= (int) $h['uyumlu_oran'] ?>'i,
                en çok <?= number_format($h['uyumlu_sinir'], 0, ',', '.') ?> ₺ indirilir.
              </span>
            </span>
          </label>

          <hr class="gv-ayrac">

          <div class="gv-alan">
            <label for="gv-hasilat">Hasılatı Elle Gir (₺) — isteğe bağlı</label>
            <input type="text" class="gv-para" id="gv-hasilat" name="hasilat_elle"
                   value="<?= $p($k['hasilat_elle']) ?>" inputmode="decimal"
                   placeholder="<?= number_format($h['hasilat_oto'], 2, ',', '.') ?> (<?= $h['kip'] === 'ucret' ? 'ücretlerden' : 'makbuzlardan' ?>)">
            <div class="ipucu">
              Sisteme girilmemiş tutarlarınız varsa toplam hasılatı buradan yazabilirsiniz.
              Boşsa <?= $h['kip'] === 'ucret' ? 'yıllık ücret' : 'makbuz' ?> toplamı kullanılır.
            </div>
          </div>

          <div class="gv-alan">
            <label for="gv-aciklama">Açıklama</label>
            <input type="text" class="girdi" id="gv-aciklama" name="aciklama"
                   value="<?= esc($k['aciklama'] ?? '') ?>" maxlength="250"
                   placeholder="Not (isteğe bağlı)">
          </div>

          <button type="submit" class="btn" style="width:100%">💾 Kaydet</button>
        </div>
      </div>
    </div>

    <!-- ============ SAĞ: HESAP DÖKÜMÜ ============ -->
    <div>
      <div class="gv-kart">
        <div class="gv-kart-bas">
          <span>🧮 <?= (int) $yil ?> Yılı Vergi Yükü Hesabı</span>
          <span class="kucuk-yazi" id="gv-durum" style="font-weight:400"></span>
        </div>
        <div class="gv-kart-govde" style="padding:0">
          <table class="gv-hesap">
            <tbody>
              <tr>
                <td>
                  Serbest Meslek Hasılatı
                  <span class="aciklama" id="gv-hasilat-not">
                    <?php if ($h['hasilat_elle'] !== null): ?>
                      elle girildi
                    <?php elseif ($h['kip'] === 'ucret'): ?>
                      <?= (int) $h['ucret_adet'] ?> mükellefin yıllık sözleşme ücreti
                    <?php else: ?>
                      <?= (int) $mb['adet'] ?> makbuz · brüt toplam
                    <?php endif; ?>
                  </span>
                </td>
                <td class="sag" id="c-hasilat"><?= number_format($h['hasilat'], 2, ',', '.') ?></td>
              </tr>
              <tr class="gv-eksi">
                <td>
                  − Mesleki Gider
                  <?php if ($h['gider_aylik'] > 0): ?>
                    <span class="aciklama" id="gv-gider-not">
                      elle <?= number_format($h['gider_elle'], 2, ',', '.') ?>
                      + aylık tablo <?= number_format($h['gider_aylik'], 2, ',', '.') ?>
                      (<?= (int) $h['gider_ay_sayisi'] ?> ay)
                    </span>
                  <?php endif; ?>
                </td>
                <td class="sag" id="c-gider"><?= number_format($h['gider'], 2, ',', '.') ?></td>
              </tr>
              <tr class="gv-ara">
                <td>= Serbest Meslek Kazancı</td>
                <td class="sag" id="c-kazanc"><?= number_format($h['kazanc'], 2, ',', '.') ?></td>
              </tr>

              <tr class="gv-eksi">
                <td>− Bağ-Kur / SGK Primi</td>
                <td class="sag" id="c-bagkur"><?= number_format($h['bagkur'], 2, ',', '.') ?></td>
              </tr>
              <tr class="gv-eksi">
                <td>
                  − Şahıs / Hayat Sigorta Primi
                  <span class="aciklama" id="gv-sigorta-not">
                    <?= $h['sigorta_liste'] ? (int) $h['sigorta_adet'] . ' belge · ' : '' ?>
                    en çok (kazanç−Bağkur)×%<?= (int) $h['sigorta_oran'] ?> =
                    <?= number_format($h['sigorta_tavan'], 2, ',', '.') ?> ₺
                  </span>
                </td>
                <td class="sag" id="c-sigorta"><?= number_format($h['sigorta'], 2, ',', '.') ?></td>
              </tr>
              <tr class="gv-eksi">
                <td>
                  − Eğitim ve Sağlık Harcaması
                  <span class="aciklama" id="gv-egitim-not">
                    <?= $h['egitim_liste'] ? (int) $h['egitim_adet'] . ' belge · ' : '' ?>
                    en çok (kazanç−Bağkur)×%<?= (int) $h['egitim_oran'] ?> =
                    <?= number_format($h['egitim_tavan'], 2, ',', '.') ?> ₺
                  </span>
                </td>
                <td class="sag" id="c-egitim"><?= number_format($h['egitim'], 2, ',', '.') ?></td>
              </tr>
              <tr class="gv-ara">
                <td>= İndirimler Toplamı</td>
                <td class="sag" id="c-indirim_toplam"><?= number_format($h['indirim_toplam'], 2, ',', '.') ?></td>
              </tr>
              <tr class="gv-vurgu">
                <td>= VERGİ MATRAHI</td>
                <td class="sag" id="c-matrah"><?= number_format($h['matrah'], 2, ',', '.') ?></td>
              </tr>

              <tr>
                <td>
                  Hesaplanan Gelir Vergisi
                  <span class="aciklama" id="gv-dilim-not">
                    <?= $h['dilim_no'] > 0
                        ? $h['dilim_no'] . '. dilim · %' . rtrim(rtrim(number_format($h['dilim']['oran'], 2, ',', '.'), '0'), ',')
                          . ' · ortalama %' . number_format($h['ortalama_oran'], 2, ',', '.')
                        : 'Matrah yok' ?>
                  </span>
                </td>
                <td class="sag" id="c-vergi"><?= number_format($h['vergi'], 2, ',', '.') ?></td>
              </tr>
              <tr class="gv-eksi">
                <td>− %<?= (int) $h['uyumlu_oran'] ?> Uyumlu Mükellef İndirimi</td>
                <td class="sag" id="c-uyumlu"><?= number_format($h['uyumlu'], 2, ',', '.') ?></td>
              </tr>
              <tr class="gv-ara">
                <td>= Ödenmesi Gereken Vergi</td>
                <td class="sag" id="c-odenmesi_gereken"><?= number_format($h['odenmesi_gereken'], 2, ',', '.') ?></td>
              </tr>

              <tr class="gv-eksi">
                <td>− Stopaj (Tevkifat)
                  <span class="aciklama">
                    <?php if ($h['stopaj_elle'] !== null): ?>
                      elle girildi
                    <?php elseif ($h['kip'] === 'ucret'): ?>
                      ücretlerin %<?= (int) $h['ucret_stopaj_oran'] ?>'si · iade doğurur
                    <?php else: ?>
                      makbuzlardan · iade doğurur
                    <?php endif; ?>
                  </span>
                </td>
                <td class="sag" id="c-stopaj"><?= number_format($h['stopaj'], 2, ',', '.') ?></td>
              </tr>
              <tr class="gv-eksi">
                <td>− Diğer Mahsuplar</td>
                <td class="sag" id="c-diger_mahsup"><?= number_format($h['diger_mahsup'], 2, ',', '.') ?></td>
              </tr>
              <tr class="gv-arti">
                <td>
                  + Kalan KDV Borcu
                  <span class="aciklama" id="gv-kdv-not">
                    <?= $h['kip'] === 'ucret' ? 'ücretlerden' : 'makbuzlardan' ?>
                    <?= number_format($h['kdv_yukumluluk'], 2, ',', '.') ?> KDV ·
                    ödenen <?= number_format($h['kdv_odenen'], 2, ',', '.') ?>
                    (<?= (int) $h['kdv_ay_sayisi'] ?> ay · ödenen
                    <?= number_format($h['kdv_odenen_sutun'], 2, ',', '.') ?> +
                    indirilecek <?= number_format($h['kdv_indirilecek'], 2, ',', '.') ?>)
                    <?php if ($h['kdv_alacak'] > 0): ?> · fazla ödeme<?php endif; ?>
                  </span>
                </td>
                <td class="sag" id="c-kdv"><?= number_format($h['kdv_kalan'], 2, ',', '.') ?></td>
              </tr>
              <!--
                Ara toplam satırı KALDIRILDI (21. güncelleme).
                Matematiksel olarak doğruydu ama kavramsal olarak yanıltıcıydı:
                KDV bir BORÇTUR, "mahsup" (alacak) değildir; stopaj alacağından
                düşülmüş hâlde tek rakama sıkıştırılınca okunmuyordu.
                Aşağıdaki iki kırılım satırı aynı sonucu daha açık anlatıyor.

                Değer JS için gizli tutulur (AJAX canlı hesap bu id'yi günceller).
              -->
              <tr style="display:none">
                <td>ara toplam (gizli — AJAX kullanır)</td>
                <td class="sag" id="c-mahsup_toplam"><?= number_format($h['mahsup_toplam'], 2, ',', '.') ?></td>
              </tr>

              <!--
                YIL İÇİ VERGİ YÜKÜ KIRILIMI
                Gelir vergisi tarafı tek başına iade doğurabilir; KDV borcu
                eklenince tablo değişir. İkisi ayrı gösterilir ki
                "136.047 alacağım vardı, 256.139 KDV borcuyla 120.092 ödeyeceğim"
                zinciri okunabilsin.
              -->
              <!--
                İŞARET KURALI: eksi işareti kafa karıştırdığı için kaldırıldı.
                Lehe olan tutarlar YEŞİL ve "alacak" etiketiyle, aleyhe olanlar
                normal renkte "borç" etiketiyle gösterilir.
              -->
              <tr class="gv-kirilim">
                <td id="gv-denge-etiket">
                  <?= $h['gv_alacak'] > 0 ? 'Gelir Vergisi: Devletten Alacak' : 'Gelir Vergisi: Borç' ?>
                  <span class="aciklama">
                    <?= number_format($h['odenmesi_gereken'], 2, ',', '.') ?> vergi
                    − <?= number_format($h['stopaj'], 2, ',', '.') ?> stopaj
                    <?php if ($h['diger_mahsup'] > 0): ?>
                      − <?= number_format($h['diger_mahsup'], 2, ',', '.') ?> diğer
                    <?php endif; ?>
                  </span>
                </td>
                <td class="sag <?= $h['gv_alacak'] > 0 ? 'yesil' : '' ?>" id="c-gv-denge">
                  <?= number_format($h['gv_alacak'] > 0 ? $h['gv_alacak'] : $h['gv_borc'], 2, ',', '.') ?>
                </td>
              </tr>
              <tr class="gv-kirilim">
                <td id="gv-kdvk-etiket">
                  <?= $h['kdv_alacak'] > 0 ? 'KDV: Fazla Ödeme (alacak)' : 'KDV: Ödenmemiş Borç' ?>
                  <span class="aciklama">
                    <?= number_format($h['kdv_yukumluluk'], 2, ',', '.') ?>
                    <?= $h['kip'] === 'ucret' ? 'ücret' : 'makbuz' ?> KDV'si
                    − <?= number_format($h['kdv_odenen'], 2, ',', '.') ?> ödenen
                  </span>
                </td>
                <td class="sag <?= $h['kdv_alacak'] > 0 ? 'yesil' : '' ?>" id="c-kdv-yuk">
                  <?= number_format($h['kdv_alacak'] > 0 ? $h['kdv_alacak'] : $h['kdv_borc'], 2, ',', '.') ?>
                </td>
              </tr>

              <tr class="gv-sonuc <?= $h['iade'] > 0 ? 'iade' : '' ?><?= abs($h['vergi_yuku']) < 0.005 ? ' notr' : '' ?>"
                  id="gv-sonuc-satir">
                <td id="gv-sonuc-etiket">
                  <?php if (abs($h['vergi_yuku']) < 0.005): ?>
                    YIL İÇİ VERGİ YÜKÜ — ALACAK/VERECEK YOK
                  <?php elseif ($h['iade'] > 0): ?>
                    YIL İÇİ VERGİ YÜKÜ — İADE ALACAKSINIZ
                  <?php else: ?>
                    YIL İÇİ VERGİ YÜKÜ — ÖDEYECEKSİNİZ
                  <?php endif; ?>
                </td>
                <td class="sag" id="c-sonuc-tutar">
                  <?= number_format($h['iade'] > 0 ? $h['iade'] : $h['odenecek'], 2, ',', '.') ?>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tarife dilimleri -->
      <div class="gv-kart" style="margin-top:14px">
        <div class="gv-kart-bas">
          <span>📐 <?= (int) $yil ?> Gelir Vergisi Tarifesi (ücret dışı gelirler)</span>
          <a href="<?= site_url('gelir-vergisi/tarife?yil=' . (int) $yil) ?>" class="kucuk-yazi">Düzenle</a>
        </div>
        <div class="gv-kart-govde" style="padding:6px 8px">
          <?php if ($dilimler === []): ?>
            <p class="kucuk-yazi" style="margin:8px">Bu yıl için dilim tanımlı değil.</p>
          <?php else: ?>
            <table class="gv-dilimler">
              <thead>
                <tr>
                  <th style="width:6%">#</th>
                  <th>Gelir Dilimi</th>
                  <th class="sag" style="width:16%">Bu Dilimdeki Matrah</th>
                  <th class="sag" style="width:12%">Oran</th>
                  <th class="sag" style="width:16%">Vergi</th>
                </tr>
              </thead>
              <tbody>
                <?php $kir = []; foreach ($h['kirilim'] as $x) { $kir[$x['sira']] = $x; } ?>
                <?php foreach ($dilimler as $d): ?>
                  <?php $x = $kir[(int) $d['sira']] ?? null; ?>
                  <tr class="<?= (int) $d['sira'] === (int) $h['dilim_no'] ? 'aktif' : ($x !== null ? 'dolu' : '') ?>">
                    <td><?= (int) $d['sira'] ?></td>
                    <td>
                      <?php if ($d['tavan'] === null): ?>
                        <?= number_format($d['taban'], 0, ',', '.') ?> ₺'den fazlası
                      <?php elseif ($d['taban'] <= 0): ?>
                        <?= number_format($d['tavan'], 0, ',', '.') ?> ₺'ye kadar
                      <?php else: ?>
                        <?= number_format($d['taban'], 0, ',', '.') ?> –
                        <?= number_format($d['tavan'], 0, ',', '.') ?> ₺
                      <?php endif; ?>
                    </td>
                    <td class="sag"><?= $x !== null ? number_format($x['matrah'], 2, ',', '.') : '—' ?></td>
                    <td class="sag">%<?= rtrim(rtrim(number_format($d['oran'], 2, ',', '.'), '0'), ',') ?></td>
                    <td class="sag"><?= $x !== null ? number_format($x['vergi'], 2, ',', '.') : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- Aylık makbuz dağılımı -->
      <div class="gv-kart" style="margin-top:14px">
        <div class="gv-kart-bas">
          <span>📅 Aylık Makbuz Dağılımı (brüt)</span>
          <a href="<?= site_url('makbuz?yil=' . (int) $yil . '&musavir_id=' . (int) $musavir['id']) ?>"
             class="kucuk-yazi">Makbuz Takip →</a>
        </div>
        <div class="gv-kart-govde">
          <?php $enBuyuk = max(array_column($aylik, 'brut')) ?: 1; ?>
          <div class="gv-aylik">
            <?php foreach ($aylik as $a): ?>
              <div class="gv-ay <?= $a['brut'] <= 0 ? 'bos' : '' ?>" title="<?= ayAdi($a['ay']) ?>: <?= number_format($a['brut'], 2, ',', '.') ?> ₺ (<?= $a['adet'] ?> makbuz)">
                <div class="sutun"><i style="height:<?= max(2, (int) round($a['brut'] / $enBuyuk * 44)) ?>px"></i></div>
                <b><?= $a['brut'] > 0 ? number_format($a['brut'] / 1000, 0, ',', '.') . 'B' : '—' ?></b>
                <?= ayKisa($a['ay']) ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<!-- ============ İNDİRİM KALEMLERİ (belge listeleri) ============ -->
<?php
// Parça dosya normal include ile çağrılır; $this->include() üst görünümün
// yerel değişkenlerini TAŞIMAZ, bu yüzden değişkenler burada hazırlanır.
$kalemGorunum = [
    [
        'kalem'    => 'egitim_saglik',
        'baslik'   => 'Eğitim ve Sağlık Harcamaları',
        'ikon'     => '🎓',
        'satirlar' => $kalemler['egitim_saglik'],
        'turler'   => \App\Models\IndirimKalemModel::TURLER['egitim_saglik'],
        'tavan'    => $h['egitim_tavan'],
        'oran'     => $h['egitim_oran'],
        'inen'     => $h['egitim'],
        'asim'     => $h['egitim_asim'],
    ],
    [
        'kalem'    => 'sigorta',
        'baslik'   => 'Şahıs / Hayat Sigorta Primleri',
        'ikon'     => '🛡️',
        'satirlar' => $kalemler['sigorta'],
        'turler'   => \App\Models\IndirimKalemModel::TURLER['sigorta'],
        'tavan'    => $h['sigorta_tavan'],
        'oran'     => $h['sigorta_oran'],
        'inen'     => $h['sigorta'],
        'asim'     => $h['sigorta_asim'],
    ],
];

foreach ($kalemGorunum as $g) {
    extract($g);
    include APPPATH . 'Views/gelir_vergisi/_kalem_liste.php';
}
?>

<!-- ============ AYLIK KDV TABLOSU (ayrı form) ============ -->
<?php
$kdvT = ['odenen' => 0.0, 'indirilecek' => 0.0, 'toplam' => 0.0];

foreach ($kdv as $x) {
    $kdvT['odenen']      += $x['odenen'];
    $kdvT['indirilecek'] += $x['indirilecek'];
    $kdvT['toplam']      += $x['toplam'];
}
?>
<form method="post" action="<?= site_url('gelir-vergisi/kdv-kaydet') ?>" id="kdv-form"
      class="gv-kart" style="margin-top:16px" id="kdv">
  <?= csrf_field() ?>
  <input type="hidden" name="musavir_id" value="<?= (int) $musavir['id'] ?>">
  <input type="hidden" name="yil" value="<?= (int) $yil ?>">

  <div class="gv-kart-bas">
    <span>🧾 <?= (int) $yil ?> Aylık KDV Tablosu</span>
    <span class="kucuk-yazi" style="font-weight:400">Rakamları elle girin</span>
  </div>

  <div class="gv-kart-govde">
    <div class="gv-bilgi" style="margin-bottom:12px">
      <?php if ($h['kip'] === 'ucret'): ?>
        Yıllık sözleşme ücretlerinizden <b>KDV yükümlülüğü doğar</b>
        (<?= number_format($h['ucret_brut'], 2, ',', '.') ?> ₺ ücret ×
        %<?= (int) $h['ucret_kdv_oran'] ?>); buraya yıl içinde
      <?php else: ?>
        Makbuz kestiğinizde <b>KDV yükümlülüğü doğar</b>; buraya yıl içinde
      <?php endif; ?>
      ödediğiniz KDV'yi girin. Her ayın <b>Ödenen + İndirilecek</b> toplamı
      (Ay Toplamı sütunu) ödeme sayılır; yıllık toplamla makbuz yükümlülüğü
      arasındaki fark <b>kalan KDV borcu</b> olarak gelir vergisi hesabına
      girer ve stopaj alacağınızla mahsuplaşır.
      KDV, gelir vergisi <b>matrahına girmez</b>.
    </div>

    <div class="kdv-ozet">
      <div>
        <div class="et"><?= $h['kip'] === 'ucret' ? 'Ücret' : 'Makbuz' ?> KDV Yükümlülüğü</div>
        <div class="dg"><?= number_format($h['kdv_yukumluluk'], 2, ',', '.') ?></div>
      </div>
      <div>
        <div class="et">Ödenen KDV (ay toplamları)</div>
        <div class="dg" id="kdv-t-odenen"><?= number_format($kdvT['toplam'], 2, ',', '.') ?></div>
      </div>
      <div class="<?= $h['kdv_alacak'] > 0 ? 'yesil' : 'mavi' ?>">
        <div class="et"><?= $h['kdv_alacak'] > 0 ? 'Fazla Ödeme (alacak)' : 'Kalan KDV Borcu' ?></div>
        <div class="dg" id="kdv-t-kalan">
          <?= number_format($h['kdv_alacak'] > 0 ? $h['kdv_alacak'] : $h['kdv_borc'], 2, ',', '.') ?>
        </div>
      </div>
      <div>
        <div class="et">Kırılım</div>
        <div class="dg" style="font-size:12.5px;line-height:1.5">
          ödenen <b id="kdv-t-sadece-odenen"><?= number_format($kdvT['odenen'], 2, ',', '.') ?></b><br>
          indirilecek <b id="kdv-t-indirilecek"><?= number_format($kdvT['indirilecek'], 2, ',', '.') ?></b>
        </div>
      </div>
    </div>

    <div class="tablo-sar">
      <table class="kdv-tablo">
        <thead>
          <tr>
            <th style="width:11%">Ay</th>
            <th class="sag" style="width:22%">Ödenen KDV (₺)</th>
            <th class="sag" style="width:22%">İndirilecek KDV (₺)</th>
            <th class="sag" style="width:15%">Ay Toplamı</th>
            <th>Açıklama</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($kdv as $x): ?>
            <?php $ay = (int) $x['ay']; ?>
            <tr>
              <td class="ay"><?= ayAdi($ay) ?></td>
              <td>
                <input type="text" class="kdv-girdi kdv-sayi <?= $x['odenen'] > 0 ? 'dolu' : '' ?>"
                       name="kdv[<?= $ay ?>][odenen]" data-ay="<?= $ay ?>" data-tur="odenen"
                       value="<?= $x['odenen'] > 0 ? number_format($x['odenen'], 2, ',', '.') : '' ?>"
                       inputmode="decimal" placeholder="0,00">
              </td>
              <td>
                <input type="text" class="kdv-girdi kdv-sayi <?= $x['indirilecek'] > 0 ? 'dolu' : '' ?>"
                       name="kdv[<?= $ay ?>][indirilecek]" data-ay="<?= $ay ?>" data-tur="indirilecek"
                       value="<?= $x['indirilecek'] > 0 ? number_format($x['indirilecek'], 2, ',', '.') : '' ?>"
                       inputmode="decimal" placeholder="0,00">
              </td>
              <td class="sag kalin" data-ay-toplam="<?= $ay ?>">
                <?= number_format($x['toplam'], 2, ',', '.') ?>
              </td>
              <td>
                <input type="text" class="kdv-girdi" style="text-align:left"
                       name="kdv[<?= $ay ?>][aciklama]" maxlength="200"
                       value="<?= esc($x['aciklama'] ?? '') ?>" placeholder="—">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td>TOPLAM</td>
            <td class="sag" id="kdv-f-odenen"><?= number_format($kdvT['odenen'], 2, ',', '.') ?></td>
            <td class="sag" id="kdv-f-indirilecek"><?= number_format($kdvT['indirilecek'], 2, ',', '.') ?></td>
            <td class="sag" id="kdv-f-toplam" title="ödenen + indirilecek — mahsuba giren toplam">
              <?= number_format($kdvT['toplam'], 2, ',', '.') ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <button type="submit" class="btn" style="margin-top:12px">💾 KDV Tablosunu Kaydet</button>
    <span class="kucuk-yazi" style="margin-left:10px">
      Kaydettikten sonra yukarıdaki hesap kalan KDV borcunu dikkate alarak güncellenir.
    </span>
  </div>
</form>

<!-- ============ AYLIK GİDER TABLOSU ============ -->
<?php
$agToplam = 0.0;
$agAdet   = 0;

foreach ($giderler as $x) {
    $agToplam += $x['tutar'];

    if ($x['tutar'] > 0) {
        $agAdet++;
    }
}
?>
<form method="post" action="<?= site_url('gelir-vergisi/gider-kaydet') ?>" id="agider-form"
      class="gv-kart" style="margin-top:16px" id="aylik-gider">
  <?= csrf_field() ?>
  <input type="hidden" name="musavir_id" value="<?= (int) $musavir['id'] ?>">
  <input type="hidden" name="yil" value="<?= (int) $yil ?>">

  <div class="gv-kart-bas">
    <span>📒 <?= (int) $yil ?> Aylık Gider Tablosu</span>
    <span class="kucuk-yazi" style="font-weight:400">
      <?= $agAdet ?> ay girildi · toplam <b><?= number_format($agToplam, 2, ',', '.') ?> ₺</b>
    </span>
  </div>

  <div class="gv-kart-govde">
    <div class="gv-bilgi" style="margin-bottom:12px">
      Mesleki giderinizi ay ay girin (kira, personel, aidat, kırtasiye…).
      Bu tablonun toplamı, yukarıdaki <b>Toplam Mesleki Gider</b> kutusuna
      <b>eklenir</b> — kutuyu değiştirmez. Böylece bir kısmını toplu, bir kısmını
      ay ay tutabilirsiniz.
    </div>

    <div class="kdv-ozet">
      <div>
        <div class="et">Elle Girilen Gider</div>
        <div class="dg"><?= number_format($h['gider_elle'], 2, ',', '.') ?></div>
      </div>
      <div>
        <div class="et">Aylık Tablo Toplamı</div>
        <div class="dg" id="ag-t-toplam"><?= number_format($agToplam, 2, ',', '.') ?></div>
      </div>
      <div class="mavi">
        <div class="et">Hesaba Giren Toplam Gider</div>
        <div class="dg" id="ag-t-genel"><?= number_format($h['gider'], 2, ',', '.') ?></div>
      </div>
    </div>

    <div class="tablo-sar">
      <table class="kdv-tablo">
        <thead>
          <tr>
            <th style="width:12%">Ay</th>
            <th class="sag" style="width:24%">Gider Tutarı (₺)</th>
            <th>Açıklama</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($giderler as $x): ?>
            <?php $ay = (int) $x['ay']; ?>
            <tr>
              <td class="ay"><?= ayAdi($ay) ?></td>
              <td>
                <input type="text" class="kdv-girdi ag-sayi <?= $x['tutar'] > 0 ? 'dolu' : '' ?>"
                       name="agider[<?= $ay ?>][tutar]" data-ay="<?= $ay ?>"
                       value="<?= $x['tutar'] > 0 ? number_format($x['tutar'], 2, ',', '.') : '' ?>"
                       inputmode="decimal" placeholder="0,00">
              </td>
              <td>
                <input type="text" class="kdv-girdi" style="text-align:left"
                       name="agider[<?= $ay ?>][aciklama]" maxlength="200"
                       value="<?= esc($x['aciklama'] ?? '') ?>"
                       placeholder="Kira, personel, aidat…">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td>TOPLAM</td>
            <td class="sag" id="ag-f-toplam"><?= number_format($agToplam, 2, ',', '.') ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <button type="submit" class="btn" style="margin-top:12px">💾 Gider Tablosunu Kaydet</button>
    <span class="kucuk-yazi" style="margin-left:10px">
      Kaydettikten sonra yukarıdaki hesap yeni gideri dikkate alarak güncellenir.
    </span>
  </div>
</form>

<!-- ============ YILLIK ÜCRET DÖKÜMÜ (projeksiyon kaynağı) ============ -->
<?php if ($h['ucret_adet'] > 0): ?>
  <?php
  // Sayfalama: mükellef sayısı arttıkça sayfa uzamasın diye 25'erli.
  // Tüm satırlar HTML'e basılır, gezinme JS ile yapılır — böylece
  // arama ve yazdırma tüm veriyi görür (sunucuya ek istek yok).
  $ucSayfaAdet = 25;
  $ucSatirlar  = $h['ucret']['mukellefler'];
  $ucToplamSayfa = max(1, (int) ceil(count($ucSatirlar) / $ucSayfaAdet));
  ?>
  <div class="gv-kart uc-kart" id="ucret-dokum" style="margin-top:16px">
    <div class="gv-kart-bas uc-bas" role="button" tabindex="0"
         aria-expanded="false" aria-controls="uc-govde">
      <span>
        <span class="uc-ok">▸</span>
        📅 <?= (int) $yil ?> Yıllık Sözleşme Ücretleri
        <?= $h['kip'] === 'ucret' ? '— hesabın kaynağı' : '(bu kip kullanılmıyor)' ?>
      </span>
      <span class="kucuk-yazi" style="font-weight:400">
        <?= (int) $h['ucret_adet'] ?> mükellef ·
        <b><?= number_format($h['ucret_brut'], 2, ',', '.') ?> ₺</b> ·
        stopaj <?= number_format($h['ucret_stopaj'], 2, ',', '.') ?> ·
        KDV <?= number_format($h['ucret_kdv'], 2, ',', '.') ?>
        <span class="uc-ac-yazi">göster</span>
      </span>
    </div>

    <div class="gv-kart-govde" id="uc-govde" style="display:none">
      <div class="gv-bilgi" style="margin-bottom:12px">
        Mükellef kartlarına girdiğiniz yıllık ücretler <b>makbuza dönüşmüş</b> kabul edilir.
        Her ücretten <b>%<?= (int) $h['ucret_stopaj_oran'] ?> stopaj</b> ve
        <b>%<?= (int) $h['ucret_kdv_oran'] ?> KDV</b> doğar.
        <?php if ($h['kip'] !== 'ucret'): ?>
          <br>Şu an <b>kesilen makbuzlar</b> kipi seçili; bu tablo yalnız bilgi amaçlı gösteriliyor.
        <?php endif; ?>
      </div>

      <div class="kdv-ozet">
        <div>
          <div class="et">Yıllık Ücret Toplamı</div>
          <div class="dg"><?= number_format($h['ucret_brut'], 2, ',', '.') ?></div>
        </div>
        <div>
          <div class="et">Doğan Stopaj (%<?= (int) $h['ucret_stopaj_oran'] ?>)</div>
          <div class="dg" style="color:#047857"><?= number_format($h['ucret_stopaj'], 2, ',', '.') ?></div>
        </div>
        <div class="mavi">
          <div class="et">Doğan KDV (%<?= (int) $h['ucret_kdv_oran'] ?>)</div>
          <div class="dg"><?= number_format($h['ucret_kdv'], 2, ',', '.') ?></div>
        </div>
        <div>
          <div class="et">Mükellef Sayısı</div>
          <div class="dg"><?= (int) $h['ucret_adet'] ?></div>
        </div>
      </div>

      <?php if (count($ucSatirlar) > $ucSayfaAdet): ?>
        <div class="uc-arac">
          <input type="search" id="uc-ara" class="girdi" placeholder="🔍 Mükellef ara…"
                 autocomplete="off" style="max-width:240px">
          <span class="uc-sayac" id="uc-sayac"></span>
          <span class="uc-gezinme">
            <button type="button" class="btn ikincil mini" id="uc-onceki">‹ Önceki</button>
            <span id="uc-sayfa-bilgi" class="kucuk-yazi"></span>
            <button type="button" class="btn ikincil mini" id="uc-sonraki">Sonraki ›</button>
          </span>
        </div>
      <?php endif; ?>

      <div class="tablo-sar">
        <table class="ucret-tablo" id="uc-tablo" data-sayfa-adet="<?= $ucSayfaAdet ?>">
          <thead>
            <tr>
              <th style="width:5%">#</th>
              <th>Mükellef</th>
              <th style="width:13%">VKN / TCKN</th>
              <th class="sag" style="width:15%">Yıllık Ücret</th>
              <th class="sag" style="width:14%">Stopaj</th>
              <th class="sag" style="width:14%">KDV</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 0; ?>
            <?php foreach ($ucSatirlar as $u): ?>
              <?php $i++; ?>
              <tr class="<?= empty($u['aktif']) ? 'pasif' : '' ?>"
                  data-uc-satir="<?= $i ?>"
                  data-ad="<?= esc(mb_strtolower($u['unvan'] . ' ' . $u['vkn'], 'UTF-8')) ?>">
                <td class="kucuk-yazi"><?= $i ?></td>
                <td>
                  <?= esc(kisalt($u['unvan'], 40)) ?>
                  <?php if (empty($u['aktif'])): ?>
                    <span class="rozet gri" style="font-size:10px">pasif</span>
                  <?php endif; ?>
                </td>
                <td class="kucuk-yazi"><?= esc($u['vkn']) ?></td>
                <td class="sag"><?= number_format($u['ucret'], 2, ',', '.') ?></td>
                <td class="sag"><?= number_format($u['stopaj'], 2, ',', '.') ?></td>
                <td class="sag"><?= number_format($u['kdv'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
            <tr id="uc-bos" style="display:none">
              <td colspan="6" class="orta kucuk-yazi" style="padding:18px;text-align:center">
                Aramanıza uyan mükellef bulunamadı.
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3">TOPLAM (<?= $i ?> mükellef)</td>
              <td class="sag"><?= number_format($h['ucret_brut'], 2, ',', '.') ?></td>
              <td class="sag"><?= number_format($h['ucret_stopaj'], 2, ',', '.') ?></td>
              <td class="sag"><?= number_format($h['ucret_kdv'], 2, ',', '.') ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
(function () {
  'use strict';

  var form   = document.getElementById('gv-form');
  var durum  = document.getElementById('gv-durum');
  var zaman  = null;

  // Canlı hesap: kullanıcı yazdıkça sunucudan güncel rakamı iste.
  // Hesap sunucuda yapılır ki ekran ile kayıt/yazdırma HER ZAMAN aynı sonucu versin.
  function hesapla() {
    var veri = new FormData(form);
    durum.textContent = 'hesaplanıyor…';

    fetch('<?= site_url('gelir-vergisi/hesapla') ?>', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: veri
    })
      .then(function (r) { return r.json(); })
      .then(function (c) {
        if (!c.durum) { durum.textContent = c.mesaj || 'hata'; return; }

        var b = c.bicimli;
        ['hasilat','gider','kazanc','bagkur','sigorta','egitim','indirim_toplam',
         'matrah','vergi','uyumlu','odenmesi_gereken','stopaj','diger_mahsup',
         'kdv','mahsup_toplam'].forEach(function (a) {
          var el = document.getElementById('c-' + a);
          if (el) { el.textContent = b[a]; }
        });

        // Sınırlı indirimlerin tavanı kazanç değiştikçe kayar; ekranda güncelle
        ['sigorta','egitim'].forEach(function (a) {
          var tav = document.getElementById('gv-' + a + '-tavan');
          if (tav) { tav.textContent = b[a + '_tavan']; }

          var not = document.getElementById('gv-' + a + '-not');
          if (not) {
            not.textContent = 'en çok kârın %' +
              (a === 'sigorta' ? <?= (int) $h['sigorta_oran'] ?> : <?= (int) $h['egitim_oran'] ?>) +
              "'" + (a === 'sigorta' ? 'i' : 'u') + ' = ' + b[a + '_tavan'] + ' ₺';
          }

          // Sınır aşıldıysa uyarı göster
          var asim  = c.hesap[a + '_asim'] > 0;
          var kutu  = document.getElementById('gv-' + a + '-asim');
          if (kutu) {
            kutu.style.display = asim ? '' : 'none';
            var tut = document.getElementById('gv-' + a + '-asim-tutar');
            if (tut) { tut.textContent = b[a + '_asim']; }
          }
        });

        var iade   = c.hesap.iade > 0;
        var satir  = document.getElementById('gv-sonuc-satir');
        satir.classList.toggle('iade', iade);
        var notr = Math.abs(c.hesap.vergi_yuku) < 0.005;
        satir.classList.toggle('notr', notr);
        document.getElementById('gv-sonuc-etiket').textContent =
          notr ? 'YIL İÇİ VERGİ YÜKÜ — ALACAK/VERECEK YOK'
               : (iade ? 'YIL İÇİ VERGİ YÜKÜ — İADE ALACAKSINIZ'
                       : 'YIL İÇİ VERGİ YÜKÜ — ÖDEYECEKSİNİZ');
        document.getElementById('c-sonuc-tutar').textContent = iade ? b.iade : b.odenecek;

        // Vergi yükü kırılımı — EKSİ İŞARETİ YOK, lehe olan yeşil gösterilir
        var alacak = c.hesap.gv_alacak > 0;
        var dEl = document.getElementById('c-gv-denge');
        if (dEl) {
          dEl.textContent = alacak ? b.gv_alacak : b.gv_borc;
          dEl.classList.toggle('yesil', alacak);
          var dEt = document.getElementById('gv-denge-etiket');
          if (dEt) {
            dEt.childNodes[0].nodeValue =
              alacak ? 'Gelir Vergisi: Devletten Alacak ' : 'Gelir Vergisi: Borç ';
          }
        }

        // Kalan KDV borcu (− ise fazla ödeme = alacak)
        var kdvAlacak = c.hesap.kdv_alacak > 0;
        var kEl = document.getElementById('c-kdv-yuk');
        if (kEl) {
          kEl.textContent = kdvAlacak ? b.kdv_alacak : b.kdv_borc;
          kEl.classList.toggle('yesil', kdvAlacak);
          var kEt = document.getElementById('gv-kdvk-etiket');
          if (kEt) {
            kEt.childNodes[0].nodeValue =
              kdvAlacak ? 'KDV: Fazla Ödeme (alacak) ' : 'KDV: Ödenmemiş Borç ';
          }
        }

        // Gider kırılım notu (elle + aylık tablo)
        var gNot = document.getElementById('gv-gider-not');
        if (gNot && c.hesap.gider_aylik > 0) {
          gNot.textContent = 'elle ' + b.gider_elle + ' + aylık tablo ' + b.gider_aylik +
                             ' (' + c.hesap.gider_ay_sayisi + ' ay)';
        }

        var not = document.getElementById('gv-dilim-not');
        if (!c.tarife_var) {
          not.textContent = 'Bu yıl için tarife tanımlı değil!';
        } else if (c.dilim_no > 0) {
          not.textContent = c.dilim_no + '. dilim · %' + c.dilim_oran +
                            ' · ortalama %' + c.ort_oran;
        } else {
          not.textContent = 'Matrah yok';
        }

        durum.textContent = 'güncel (kaydedilmedi)';
      })
      .catch(function () { durum.textContent = 'bağlantı hatası'; });
  }

  // Yazma bitiminden 400 ms sonra tek istek — her tuşta istek atılmaz
  form.querySelectorAll('input[type="text"], input[type="checkbox"]').forEach(function (el) {
    el.addEventListener(el.type === 'checkbox' ? 'change' : 'input', function () {
      clearTimeout(zaman);
      zaman = setTimeout(hesapla, 400);
    });
  });

  // Enter'a basınca form gönderilmesin, hesap yenilensin
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
      e.preventDefault();
      clearTimeout(zaman);
      hesapla();
    }
  });

  // ----------------------------------------------------------------
  //  İNDİRİM KALEMLERİ — satır düzenleme
  //  "Düzenle" tıklanınca alttaki ekleme formu o satırı yükler.
  // ----------------------------------------------------------------
  document.querySelectorAll('.kalem-duzenle').forEach(function (d) {
    d.addEventListener('click', function () {
      var hedef = d.dataset.hedef;
      var f = document.getElementById('form-' + hedef);
      if (!f) { return; }

      f.querySelector('[data-alan="id"]').value       = d.dataset.id;
      f.querySelector('[data-alan="tarih"]').value    = d.dataset.tarih;
      f.querySelector('[data-alan="tur"]').value      = d.dataset.tur;
      f.querySelector('[data-alan="aciklama"]').value = d.dataset.aciklama;
      f.querySelector('[data-alan="tutar"]').value    = d.dataset.tutar;
      f.querySelector('[data-alan="gonder"]').textContent = '💾 Güncelle';
      f.classList.add('duzenleme');
      f.querySelector('.kalem-iptal').style.display = '';

      // Düzenlenen satırı vurgula
      document.querySelectorAll('.kalem-tablo tr').forEach(function (tr) {
        tr.classList.remove('duzenleniyor');
      });
      var satir = document.querySelector('[data-kalem-satir="' + d.dataset.id + '"]');
      if (satir) { satir.classList.add('duzenleniyor'); }

      f.querySelector('[data-alan="aciklama"]').focus();
      f.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });

  // Vazgeç → form yeni kayıt kipine döner
  document.querySelectorAll('.kalem-iptal').forEach(function (b) {
    b.addEventListener('click', function () {
      var f = b.closest('.kalem-form');
      f.reset();
      f.querySelector('[data-alan="id"]').value = '';
      f.querySelector('[data-alan="gonder"]').textContent = '+ Ekle';
      f.classList.remove('duzenleme');
      b.style.display = 'none';
      document.querySelectorAll('.kalem-tablo tr').forEach(function (tr) {
        tr.classList.remove('duzenleniyor');
      });
    });
  });

  // ----------------------------------------------------------------
  //  KDV TABLOSU — canlı toplam (yalnız görsel; kaydedince hesaba girer)
  // ----------------------------------------------------------------
  var kdvForm = document.getElementById('kdv-form');
  if (!kdvForm) { return; }

  // Türkçe biçimli metni sayıya çevirir (sunucudaki trParaCoz ile aynı kural)
  function paraOku(m) {
    var s = String(m == null ? '' : m)
      .replace(/[₺\s\u00a0]/g, '').replace(/TL/gi, '');
    if (!s) { return 0; }

    var eksi = s.charAt(0) === '-';
    s = s.replace(/[^0-9.,]/g, '');
    if (!s) { return 0; }

    var sonN = s.lastIndexOf('.'), sonV = s.lastIndexOf(',');
    var kon = -1;

    if (sonN >= 0 && sonV >= 0) {
      kon = Math.max(sonN, sonV);
    } else if (sonN >= 0 || sonV >= 0) {
      var k = sonN >= 0 ? sonN : sonV;
      var im = sonN >= 0 ? '.' : ',';
      var adet = s.split(im).length - 1;
      var hane = s.length - k - 1;
      // Tek ayırıcı + 1-2 hane → ondalık; değilse binlik
      kon = (adet === 1 && hane >= 1 && hane <= 2) ? k : -1;
    }

    var sayi;
    if (kon >= 0) {
      sayi = parseFloat(
        (s.slice(0, kon).replace(/[^0-9]/g, '') || '0') + '.' +
        (s.slice(kon + 1).replace(/[^0-9]/g, '') || '0'));
    } else {
      sayi = parseFloat(s.replace(/[^0-9]/g, '') || '0');
    }

    if (isNaN(sayi)) { return 0; }
    return eksi ? -sayi : sayi;
  }

  function bicim(n) {
    return n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function kdvTopla() {
    var tOd = 0, tIn = 0;

    for (var ay = 1; ay <= 12; ay++) {
      var od = kdvForm.querySelector('[name="kdv[' + ay + '][odenen]"]');
      var ind = kdvForm.querySelector('[name="kdv[' + ay + '][indirilecek]"]');
      if (!od || !ind) { continue; }

      var a = paraOku(od.value), b2 = paraOku(ind.value);
      tOd += a; tIn += b2;

      od.classList.toggle('dolu', a > 0);
      ind.classList.toggle('dolu', b2 > 0);

      var hucre = kdvForm.querySelector('[data-ay-toplam="' + ay + '"]');
      if (hucre) { hucre.textContent = bicim(a + b2); }
    }

    [['kdv-t-odenen', tOd + tIn], ['kdv-f-odenen', tOd],
     ['kdv-t-sadece-odenen', tOd],
     ['kdv-t-indirilecek', tIn], ['kdv-f-indirilecek', tIn],
     ['kdv-t-toplam', tOd + tIn], ['kdv-f-toplam', tOd + tIn]].forEach(function (x) {
      var el = document.getElementById(x[0]);
      if (el) { el.textContent = bicim(x[1]); }
    });

    // Kalan KDV borcu = makbuz yükümlülüğü − fiilen ödenen
    var kalanEl = document.getElementById('kdv-t-kalan');
    if (kalanEl) {
      // Ödeme = ay toplamı (ödenen + indirilecek)
      var kalan = <?= (float) $h['kdv_yukumluluk'] ?> - (tOd + tIn);
      kalanEl.textContent = bicim(Math.abs(kalan));
      var kutu = kalanEl.closest('div');
      var et   = kutu ? kutu.querySelector('.et') : null;
      if (kutu) {
        kutu.classList.toggle('yesil', kalan < 0);
        kutu.classList.toggle('mavi', kalan >= 0);
      }
      if (et) { et.textContent = kalan < 0 ? 'Fazla Ödeme (alacak)' : 'Kalan KDV Borcu'; }
    }
  }

  kdvForm.querySelectorAll('.kdv-sayi').forEach(function (el) {
    el.addEventListener('input', kdvTopla);
  });

  // ----------------------------------------------------------------
  //  AYLIK GİDER TABLOSU — canlı toplam
  //  Elle girilen gider + tablo toplamı = hesaba giren toplam gider
  // ----------------------------------------------------------------
  var agForm = document.getElementById('agider-form');

  if (agForm) {
    var giderKutusu = document.getElementById('gv-gider');

    function agTopla() {
      var t = 0;

      agForm.querySelectorAll('.ag-sayi').forEach(function (el) {
        var v = paraOku(el.value);
        t += v;
        el.classList.toggle('dolu', v > 0);
      });

      [['ag-t-toplam', t], ['ag-f-toplam', t]].forEach(function (x) {
        var el = document.getElementById(x[0]);
        if (el) { el.textContent = bicim(x[1]); }
      });

      // Genel toplam = elle girilen + tablo
      var elle = giderKutusu ? paraOku(giderKutusu.value) : 0;
      var gen  = document.getElementById('ag-t-genel');
      if (gen) { gen.textContent = bicim(elle + t); }
    }

    agForm.querySelectorAll('.ag-sayi').forEach(function (el) {
      el.addEventListener('input', agTopla);
    });

    // Üstteki gider kutusu değişince genel toplam da güncellensin
    if (giderKutusu) { giderKutusu.addEventListener('input', agTopla); }

    agForm.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
        e.preventDefault();
        agForm.submit();
      }
    });
  }

  // ----------------------------------------------------------------
  //  YILLIK ÜCRET DÖKÜMÜ — katlanır kart + sayfalama + arama
  //
  //  Tüm satırlar HTML'de basılıdır; gezinme yalnızca görünürlüğü
  //  değiştirir. Böylece yazdırma ve tarayıcı içi arama tüm veriyi görür,
  //  sunucuya ek istek gitmez.
  // ----------------------------------------------------------------
  var ucKart = document.getElementById('ucret-dokum');

  if (ucKart) {
    var ucBas    = ucKart.querySelector('.uc-bas');
    var ucGovde  = document.getElementById('uc-govde');
    var ucTablo  = document.getElementById('uc-tablo');
    var ucSatir  = [].slice.call(ucTablo.querySelectorAll('tr[data-uc-satir]'));
    var ucAdet   = parseInt(ucTablo.dataset.sayfaAdet, 10) || 25;
    var ucAra    = document.getElementById('uc-ara');
    var ucOnceki = document.getElementById('uc-onceki');
    var ucSonraki = document.getElementById('uc-sonraki');
    var ucBilgi  = document.getElementById('uc-sayfa-bilgi');
    var ucSayac  = document.getElementById('uc-sayac');
    var ucBos    = document.getElementById('uc-bos');
    var ucSayfa  = 1;

    function ucSuzulmus() {
      var q = (ucAra ? ucAra.value : '').trim().toLocaleLowerCase('tr');
      if (!q) { return ucSatir; }
      return ucSatir.filter(function (tr) {
        return (tr.dataset.ad || '').indexOf(q) !== -1;
      });
    }

    function ucCiz() {
      var liste = ucSuzulmus();
      var toplamSayfa = Math.max(1, Math.ceil(liste.length / ucAdet));
      if (ucSayfa > toplamSayfa) { ucSayfa = toplamSayfa; }

      var bas = (ucSayfa - 1) * ucAdet;
      var son = bas + ucAdet;

      ucSatir.forEach(function (tr) { tr.style.display = 'none'; });
      liste.slice(bas, son).forEach(function (tr) { tr.style.display = ''; });

      if (ucBos) { ucBos.style.display = liste.length ? 'none' : ''; }

      if (ucBilgi) {
        ucBilgi.textContent = 'Sayfa ' + ucSayfa + ' / ' + toplamSayfa;
      }
      if (ucSayac) {
        ucSayac.textContent = liste.length === ucSatir.length
          ? ucSatir.length + ' mükellef'
          : liste.length + ' / ' + ucSatir.length + ' mükellef';
      }
      if (ucOnceki)  { ucOnceki.disabled  = ucSayfa <= 1; }
      if (ucSonraki) { ucSonraki.disabled = ucSayfa >= toplamSayfa; }
    }

    function ucAcKapa(ac) {
      ucGovde.style.display = ac ? '' : 'none';
      ucBas.setAttribute('aria-expanded', ac ? 'true' : 'false');
      var yazi = ucBas.querySelector('.uc-ac-yazi');
      if (yazi) { yazi.textContent = ac ? 'gizle' : 'göster'; }
      if (ac) { ucCiz(); }
    }

    ucBas.addEventListener('click', function () {
      ucAcKapa(ucBas.getAttribute('aria-expanded') !== 'true');
    });
    ucBas.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        ucAcKapa(ucBas.getAttribute('aria-expanded') !== 'true');
      }
    });

    if (ucOnceki) {
      ucOnceki.addEventListener('click', function () {
        if (ucSayfa > 1) { ucSayfa--; ucCiz(); }
      });
    }
    if (ucSonraki) {
      ucSonraki.addEventListener('click', function () {
        ucSayfa++; ucCiz();
      });
    }
    if (ucAra) {
      ucAra.addEventListener('input', function () { ucSayfa = 1; ucCiz(); });
    }

    // Adres çubuğunda #ucret-dokum varsa doğrudan açık gelsin
    if (location.hash === '#ucret-dokum') {
      ucAcKapa(true);
      ucKart.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  // KDV tablosunda Enter → satır sonu değil, kaydet
  kdvForm.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
      e.preventDefault();
      kdvForm.submit();
    }
  });
}());
</script>

<?= $this->endSection() ?>
