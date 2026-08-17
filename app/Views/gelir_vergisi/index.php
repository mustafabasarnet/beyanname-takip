<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
/**
 * GELİR VERGİSİ — mali müşavir bazında liste
 * Stiller gömülü.
 */
?>

<style>
.gvl-tablo{width:100%;border-collapse:collapse}
.gvl-tablo th{font-size:10.5px;text-transform:uppercase;letter-spacing:.3px;
  color:var(--gri-500,#64748b);font-weight:700;padding:8px 9px;
  border-bottom:1px solid var(--gri-200,#e2e8f0);white-space:nowrap;text-align:left}
.gvl-tablo th.sag{text-align:right}.gvl-tablo th.orta{text-align:center}
.gvl-tablo td{padding:8px 9px;border-bottom:1px solid var(--gri-100,#f1f5f9);font-size:13px}
.gvl-tablo td.sag{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.gvl-tablo td.orta{text-align:center}
.gvl-tablo tbody tr:hover{background:var(--gri-50,#f8fafc)}
.gvl-tablo tfoot td{background:var(--gri-50,#f8fafc);font-weight:700;
  border-top:2px solid var(--gri-300,#cbd5e1);font-variant-numeric:tabular-nums}
.gvl-nokta{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px}
.gvl-odenecek{color:#b91c1c;font-weight:700}
.gvl-iade{color:#047857;font-weight:700}
.gvl-uyari{padding:10px 12px;border-radius:8px;background:#fef3c7;color:#92400e;
  font-size:12.5px;margin-bottom:12px;border:1px solid #fde68a}
.gvl-not{font-size:11.5px;color:var(--gri-500,#64748b);margin:10px 2px 0;line-height:1.5}
</style>

<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Yıl</label>
    <select name="yil" data-oto-filtre>
      <?php foreach (yilSecenekleri() as $y): ?>
        <option value="<?= $y ?>" <?= (int) $yil === $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Göster</button>
    <a href="<?= site_url('gelir-vergisi/liste-yazdir?yil=' . (int) $yil) ?>"
       target="_blank" class="btn ikincil kucuk">🖨️ Yazdır</a>
    <a href="<?= site_url('gelir-vergisi/tarife?yil=' . (int) $yil) ?>" class="btn mor kucuk">📐 Tarife Tanımları</a>
    <a href="<?= site_url('makbuz?yil=' . (int) $yil) ?>" class="btn ikincil kucuk">🧾 Makbuz Takip</a>
  </div>
</form>

<?php if (! $tarifeVar): ?>
  <div class="gvl-uyari">
    <b><?= (int) $yil ?> yılı gelir vergisi tarifesi tanımlı değil.</b>
    Vergi hesaplanamaz —
    <a href="<?= site_url('gelir-vergisi/tarife?yil=' . (int) $yil) ?>">tarife ekranından</a>
    dilimleri girin ya da önceki yıldan kopyalayıp güncelleyin.
  </div>
<?php endif; ?>

<!-- ============ ÖZET ============ -->
<div class="stat-grid">
  <div class="stat">
    <div class="etiket">Mali Müşavir</div>
    <div class="deger"><?= (int) $ozet['musavir'] ?></div>
    <div class="alt"><?= (int) $ozet['adet'] ?> makbuz</div>
  </div>
  <div class="stat mor">
    <div class="etiket">Toplam Hasılat</div>
    <div class="deger" style="font-size:21px"><?= number_format($ozet['hasilat'], 2, ',', '.') ?></div>
    <div class="alt">₺ <?= $kaynak === 'tahsil' ? '(tahsil edilenler)' : 'brüt' ?></div>
  </div>
  <div class="stat turuncu">
    <div class="etiket">Toplam Gider</div>
    <div class="deger" style="font-size:21px"><?= number_format($ozet['gider'], 2, ',', '.') ?></div>
    <div class="alt">₺ girilen</div>
  </div>
  <div class="stat">
    <div class="etiket">Toplam Matrah</div>
    <div class="deger" style="font-size:21px"><?= number_format($ozet['matrah'], 2, ',', '.') ?></div>
    <div class="alt">₺</div>
  </div>
  <div class="stat kirmizi">
    <div class="etiket">Ödenecek Vergi</div>
    <div class="deger" style="font-size:21px"><?= number_format($ozet['odenecek'], 2, ',', '.') ?></div>
    <div class="alt">₺ · <?= number_format($ozet['iade'], 2, ',', '.') ?> ₺ iade</div>
  </div>
</div>

<!-- ============ LİSTE ============ -->
<div class="kart" style="margin-top:14px">
  <div class="kart-baslik">
    <h2><?= (int) $yil ?> Yılı Vergi Yükü Hesabı</h2>
    <span class="kucuk-yazi">Gider girmek için müşavir adına tıklayın</span>
  </div>

  <div class="tablo-sar">
    <table class="gvl-tablo">
      <thead>
        <tr>
          <th style="width:20%">Mali Müşavir</th>
          <th class="orta" style="width:8%">Kaynak</th>
          <th class="sag">Hasılat</th>
          <th class="sag">Gider</th>
          <th class="sag">Matrah</th>
          <th class="orta" style="width:7%">Dilim</th>
          <th class="sag">Hesaplanan Vergi</th>
          <th class="sag">Stopaj</th>
          <th class="sag">Kalan KDV Borcu</th>
          <th class="sag">Ödenecek / İade</th>
          <th style="width:5%"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($satirlar as $s): ?>
          <tr>
            <td>
              <span class="gvl-nokta" style="background:<?= esc($s['renk']) ?>"></span>
              <a href="<?= site_url('gelir-vergisi/detay/' . (int) $s['musavir_id'] . '?yil=' . (int) $yil) ?>"
                 class="kalin"><?= esc($s['ad_soyad']) ?></a>
              <?php if ($s['gider'] <= 0): ?>
                <span class="rozet turuncu" style="font-size:10px">gider girilmedi</span>
              <?php endif; ?>
            </td>
            <td class="orta">
              <?php if (($s['kip'] ?? 'ucret') === 'ucret'): ?>
                <span class="rozet mavi" style="font-size:10px"
                      title="Yıllık sözleşme ücretleri esas alınıyor">
                  📅 <?= (int) $s['ucret_adet'] ?> ücret
                </span>
              <?php else: ?>
                <span class="rozet gri" style="font-size:10px"
                      title="Kesilen makbuzlar esas alınıyor">
                  🧾 <?= (int) $s['makbuz']['adet'] ?> makbuz
                </span>
              <?php endif; ?>
            </td>
            <td class="sag"><?= number_format($s['hasilat'], 2, ',', '.') ?></td>
            <td class="sag"><?= number_format($s['gider'], 2, ',', '.') ?></td>
            <td class="sag kalin"><?= number_format($s['matrah'], 2, ',', '.') ?></td>
            <td class="orta">
              <?php if ($s['dilim_no'] > 0): ?>
                <span class="rozet mavi" style="font-size:10.5px">
                  %<?= rtrim(rtrim(number_format($s['dilim']['oran'], 2, ',', '.'), '0'), ',') ?>
                </span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="sag"><?= number_format($s['vergi'], 2, ',', '.') ?></td>
            <td class="sag"><?= number_format($s['stopaj'], 2, ',', '.') ?></td>
            <td class="sag"><?= number_format($s['kdv'], 2, ',', '.') ?></td>
            <td class="sag <?= $s['iade'] > 0 ? 'gvl-iade' : 'gvl-odenecek' ?>">
              <?= number_format($s['iade'] > 0 ? $s['iade'] : $s['odenecek'], 2, ',', '.') ?>
              <?php if ($s['iade'] > 0): ?><span class="kucuk-yazi">iade</span><?php endif; ?>
            </td>
            <td>
              <a href="<?= site_url('gelir-vergisi/detay/' . (int) $s['musavir_id'] . '?yil=' . (int) $yil) ?>"
                 class="btn ikincil mini">Aç</a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if ($satirlar === []): ?>
          <tr><td colspan="10" class="orta kucuk-yazi" style="padding:22px">
            Mali müşavir kaydı bulunamadı.
          </td></tr>
        <?php endif; ?>
      </tbody>

      <?php if ($satirlar !== []): ?>
        <tfoot>
          <tr>
            <td>TOPLAM</td>
            <td class="orta"></td>
            <td class="sag"><?= number_format($ozet['hasilat'], 2, ',', '.') ?></td>
            <td class="sag"><?= number_format($ozet['gider'], 2, ',', '.') ?></td>
            <td class="sag"><?= number_format($ozet['matrah'], 2, ',', '.') ?></td>
            <td></td>
            <td class="sag"><?= number_format($ozet['vergi'], 2, ',', '.') ?></td>
            <td class="sag"><?= number_format($ozet['stopaj'], 2, ',', '.') ?></td>
            <td class="sag"><?= number_format($ozet['kdv'], 2, ',', '.') ?></td>
            <td class="sag"><?= number_format($ozet['odenecek'], 2, ',', '.') ?></td>
            <td></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<p class="gvl-not">
  <b>Nasıl çalışır?</b> Her müşavir için hasılat, seçili <b>hesap kipine</b> göre gelir:
  <b>📅 yıllık sözleşme ücretleri</b> (varsayılan — yıl sonu projeksiyonu) ya da
  <b>🧾 kesilen makbuzlar</b> (bugüne kadarki gerçekleşme). Kipi müşavir sayfasından
  değiştirebilirsiniz. Stopaj da aynı kaynaktan hesaplanır.
  Siz yalnızca gideri ve varsa Bağ-Kur / geçici vergi gibi kalemleri girersiniz.
  Yıl içinde ödenen KDV, KDV tablosundan gelir ve stopajdan düşülerek net vergi yükünü verir.
  Vergi, ilgili yılın GVK md.103 tarifesine göre hesaplanır; tarife her yıl
  <a href="<?= site_url('gelir-vergisi/tarife?yil=' . (int) $yil) ?>">Tarife Tanımları</a>
  ekranından güncellenebilir. KDV hesaba dahil edilmez.
</p>

<?= $this->endSection() ?>
