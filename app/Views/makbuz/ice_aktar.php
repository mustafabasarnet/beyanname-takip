<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php $ucretMi = $kip === 'ucret'; ?>

<div class="kart">
  <div class="kart-baslik">
    <h2><?= $ucretMi ? '📥 Yıllık Ücretleri İçe Aktar' : '📥 Kesilen Makbuzları İçe Aktar' ?></h2>
    <div class="sag">
      <a href="<?= site_url('makbuz?yil=' . (int) $yil) ?>" class="btn ikincil mini">← Listeye Dön</a>
    </div>
  </div>

  <div class="kart-govde">
    <!-- Kip seçimi -->
    <div class="btn-grup" style="margin-bottom:14px">
      <a href="<?= site_url('makbuz/ice-aktar?kip=ucret&yil=' . (int) $yil) ?>"
         class="btn <?= $ucretMi ? '' : 'ikincil' ?> kucuk">💰 Yıllık Ücretler</a>
      <a href="<?= site_url('makbuz/ice-aktar?kip=makbuz&yil=' . (int) $yil) ?>"
         class="btn <?= $ucretMi ? 'ikincil' : '' ?> kucuk">🧾 Kesilen Makbuzlar</a>
    </div>

    <div class="uyari bilgi">
      <span class="ik">ℹ</span>
      <div>
        <?php if ($ucretMi): ?>
          Tarife her yıl açıklandığı için yıllık sözleşme ücretlerini toplu yükleyebilirsiniz.
          Mükellefler <b>VKN/TCKN</b> ile eşleştirilir. Aynı yıl için kayıt varsa
          <b>üzerine yazılır</b> (önizlemede uyarı görürsünüz).
        <?php else: ?>
          Ay içinde kesilen makbuzları toplu yükleyebilirsiniz. Mükellefler
          <b>VKN/TCKN</b> ile eşleştirilir. Aynı makbuz numarası daha önce
          kaydedilmişse <b>mükerrer</b> olarak işaretlenir ve aktarılmaz.
          Stopaj/KDV sütunlarını boş bırakırsanız oranlardan hesaplanır.
        <?php endif; ?>
      </div>
    </div>

    <!-- Beklenen sütunlar -->
    <div style="background:var(--gri-50,#f8fafc);border:1px solid var(--gri-200,#e2e8f0);
                border-radius:10px;padding:12px 14px;margin-bottom:14px">
      <div style="font-weight:700;margin-bottom:6px">📋 Beklenen Sütunlar</div>
      <div class="tablo-sar">
        <table class="tablo" style="font-size:12.5px">
          <thead>
            <tr><th>Sütun</th><th>Zorunlu</th><th>Açıklama</th></tr>
          </thead>
          <tbody>
            <tr><td><b>VKN/TCKN</b></td><td>Evet</td><td>Mükellef eşleştirmesi bununla yapılır</td></tr>
            <tr><td>Unvan</td><td>Hayır</td><td>VKN eşleşmezse ünvandan bulmayı dener</td></tr>
            <?php if ($ucretMi): ?>
              <tr><td><b>Yillik Ucret</b></td><td>Evet</td><td>Örn: 36000,00 veya 36.000,00</td></tr>
              <tr><td>Aciklama</td><td>Hayır</td><td>Serbest not</td></tr>
            <?php else: ?>
              <tr><td>Makbuz No</td><td>Hayır</td><td>Mükerrer kontrolünde kullanılır</td></tr>
              <tr><td><b>Tarih</b></td><td>Evet</td><td>01.03.2026 / 2026-03-01 / 01/03/2026</td></tr>
              <tr><td><b>Brut</b></td><td>Evet</td><td>Stopaj matrahı (KDV hariç)</td></tr>
              <tr><td>Stopaj</td><td>Hayır</td><td>Boşsa orandan hesaplanır</td></tr>
              <tr><td>KDV</td><td>Hayır</td><td>Boşsa orandan hesaplanır</td></tr>
              <tr><td>Aciklama</td><td>Hayır</td><td>Serbest not</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="kucuk-yazi" style="margin-top:8px">
        Sütun başlıkları büyük/küçük harf ve Türkçe karakter duyarsızdır.
        Ayraç olarak <b>;</b> <b>,</b> veya <b>sekme</b> kullanılabilir.
      </div>
    </div>

    <form method="post" action="<?= site_url('makbuz/ice-aktar/onizle') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="kip" value="<?= esc($kip) ?>">

      <div class="form-grid">
        <div class="form-grup">
          <label>Yıl <span class="zorunlu">*</span></label>
          <select name="yil">
            <?php foreach (yilSecenekleri() as $y): ?>
              <option value="<?= $y ?>" <?= (int) $yil === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
          <span class="yardim">
            <?= $ucretMi
              ? 'Ücretler bu yıla kaydedilir.'
              : 'Makbuzlar bu yılın ücretine sayılır (tarih farklı olsa da).' ?>
          </span>
        </div>

        <div class="form-grup">
          <label>Şablon</label>
          <a href="<?= site_url('makbuz/sablon?kip=' . $kip . '&yil=' . (int) $yil) ?>"
             class="btn ikincil" style="display:block;text-align:center">⬇ Örnek CSV İndir</a>
          <span class="yardim">Excel'de açıp doldurun, CSV olarak kaydedin.</span>
        </div>

        <div class="form-grup tam">
          <label>Dosya (.csv) <span class="zorunlu">*</span></label>
          <input type="file" name="dosya" class="girdi" accept=".csv,.txt" required>
          <span class="yardim">
            Excel'de <b>Farklı Kaydet → CSV (Noktalı Virgülle Ayrılmış)</b> seçin.
            En fazla <?= number_format(\App\Libraries\MakbuzIceAktar::AZAMI_SATIR, 0, ',', '.') ?> satır.
          </span>
        </div>
      </div>

      <div class="mt16">
        <button type="submit" class="btn">🔍 Önizle</button>
        <span class="kucuk-yazi" style="margin-left:8px">
          Önizlemede hangi satırların aktarılacağını seçebilirsiniz — bu adımda hiçbir şey kaydedilmez.
        </span>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
