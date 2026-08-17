<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php include APPPATH . 'Views/ajanda/_stil.php'; ?>

<?php
$d = static fn (string $alan, $vars = '') => old($alan, $kayit[$alan] ?? $vars);

$seciliGorunurluk = $d('gorunurluk', 'kisisel');
$seciliTarih      = $d('tarih', $onOnTarih);
$seciliMukellef   = (int) $d('mukellef_id', $onMukellef);

$hazirRenkler = ['#2563eb', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#7c3aed', '#db2777'];
?>

<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px">
  <a href="<?= site_url('ajanda') ?>" class="btn ikincil kucuk">← Ajandaya Dön</a>
  <h2 style="margin:0"><?= $kayit === null ? '➕ Yeni Ajanda Kaydı' : '✏️ Kaydı Düzenle' ?></h2>
</div>

<?php if (session('hatalar')): ?>
  <div class="uyari kirmizi">
    <ul style="margin:0;padding-left:18px">
      <?php foreach ((array) session('hatalar') as $h): ?>
        <li><?= esc($h) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= site_url('ajanda/kaydet') ?>" enctype="multipart/form-data" id="aj-form">
  <?= csrf_field() ?>
  <?php if ($kayit !== null): ?>
    <input type="hidden" name="id" value="<?= (int) $kayit['id'] ?>">
  <?php endif; ?>

  <!-- ============ TEMEL BİLGİ ============ -->
  <div class="kart">
    <div class="kart-baslik"><h2>Temel Bilgiler</h2></div>
    <div class="kart-govde">
      <div class="aj-form-grid">
        <div class="form-grup tam">
          <label>Başlık <span class="zorunlu">*</span></label>
          <input type="text" name="baslik" class="girdi" maxlength="200" required autofocus
                 value="<?= esc($d('baslik')) ?>"
                 placeholder="Örn: ALFA'nın sözleşmesini yenile">
        </div>

        <div class="form-grup">
          <label>Tarih <span class="zorunlu">*</span></label>
          <input type="date" name="tarih" class="girdi" required value="<?= esc($seciliTarih) ?>">
        </div>

        <div class="form-grup">
          <label>Saat</label>
          <input type="time" name="saat" class="girdi"
                 value="<?= esc(substr((string) $d('saat'), 0, 5)) ?>">
          <span class="aj-yardim">Boş bırakılırsa gün boyu sayılır.</span>
        </div>

        <div class="form-grup">
          <label>Bitiş Tarihi</label>
          <input type="date" name="bitis_tarihi" class="girdi" value="<?= esc($d('bitis_tarihi')) ?>">
          <span class="aj-yardim">Çok günlü işlerde son gün.</span>
        </div>

        <div class="form-grup tam">
          <label>Açıklama</label>
          <textarea name="aciklama" class="girdi" rows="3"
                    placeholder="Ayrıntı, not, yapılacaklar…"><?= esc($d('aciklama')) ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ GÖRÜNÜRLÜK ============ -->
  <div class="kart">
    <div class="kart-baslik">
      <h2>Kimler Görsün?</h2>
      <span class="kucuk-yazi">Kaydı kimin göreceğini belirler</span>
    </div>
    <div class="kart-govde">
      <div class="aj-form-grid">
        <div class="form-grup">
          <label>Görünürlük</label>
          <select name="gorunurluk" id="aj-gorunurluk">
            <?php foreach ($gorunurluk as $gk => $gv): ?>
              <option value="<?= $gk ?>" <?= $seciliGorunurluk === $gk ? 'selected' : '' ?>>
                <?= esc($gv) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grup" id="aj-atanan-kutu">
          <label>Görevi Yapacak Kişi</label>
          <select name="atanan_id">
            <option value="">— seçin —</option>
            <?php foreach ($kullanicilar as $uk => $uv): ?>
              <option value="<?= $uk ?>" <?= (int) $d('atanan_id') === $uk ? 'selected' : '' ?>>
                <?= esc($uv) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="aj-yardim">Kişi seçilmezse kayıt kişisel olur.</span>
        </div>

        <div class="form-grup" id="aj-musavir-kutu">
          <label>Mali Müşavir Ekibi</label>
          <select name="musavir_id">
            <option value="">— seçin —</option>
            <?php foreach ($musavirler as $mk => $mv): ?>
              <option value="<?= $mk ?>" <?= (int) $d('musavir_id') === $mk ? 'selected' : '' ?>>
                <?= esc($mv) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="aj-yardim">Bu müşavire erişimi olan herkes görür.</span>
        </div>
      </div>

      <div class="aj-kutu" style="margin-top:12px">
        <h4>Ne anlama geliyor?</h4>
        <div class="aj-yardim">
          🔒 <b>Kişisel</b> — yalnız siz görürsünüz.<br>
          👥 <b>Büro geneli</b> — tüm kullanıcılar görür (ör. "3 Mart ofis kapalı").<br>
          📌 <b>Görev</b> — atanan kişi ve siz görürsünüz; atanan da tamamlayabilir.<br>
          👨‍💼 <b>Mali müşavir ekibi</b> — o müşavire erişimi olan kullanıcılar görür.
        </div>
      </div>
    </div>
  </div>

  <!-- ============ SINIFLANDIRMA ============ -->
  <div class="kart">
    <div class="kart-baslik"><h2>Sınıflandırma</h2></div>
    <div class="kart-govde">
      <div class="aj-form-grid">
        <div class="form-grup">
          <label>Öncelik</label>
          <select name="oncelik">
            <?php foreach ($oncelikler as $ok => $ov): ?>
              <option value="<?= $ok ?>" <?= $d('oncelik', 'normal') === $ok ? 'selected' : '' ?>>
                <?= esc($ov) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grup">
          <label>Etiket</label>
          <input type="text" name="etiket" class="girdi" maxlength="60" list="aj-etiketler"
                 value="<?= esc($d('etiket')) ?>" placeholder="Toplantı, Arama, Ödeme…">
          <datalist id="aj-etiketler">
            <option value="Toplantı"><option value="Arama"><option value="Ödeme">
            <option value="Ziyaret"><option value="Sözleşme"><option value="Evrak">
          </datalist>
        </div>

        <div class="form-grup">
          <label>İlgili Mükellef</label>
          <select name="mukellef_id">
            <option value="">— yok —</option>
            <?php foreach ($mukellefler as $mk => $mv): ?>
              <option value="<?= $mk ?>" <?= $seciliMukellef === $mk ? 'selected' : '' ?>>
                <?= esc($mv) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grup tam">
          <label>Renk (takvimde görünür)</label>
          <div class="aj-renk-sec">
            <label>
              <input type="radio" name="renk" value="" <?= $d('renk') === null || $d('renk') === '' ? 'checked' : '' ?>>
              <span class="aj-renk-yuvarlak"
                    style="background:repeating-linear-gradient(45deg,#e2e8f0,#e2e8f0 4px,#fff 4px,#fff 8px)"
                    title="Önceliğe göre otomatik"></span>
            </label>
            <?php foreach ($hazirRenkler as $r): ?>
              <label>
                <input type="radio" name="renk" value="<?= $r ?>" <?= $d('renk') === $r ? 'checked' : '' ?>>
                <span class="aj-renk-yuvarlak" style="background:<?= $r ?>"></span>
              </label>
            <?php endforeach; ?>
            <span class="aj-yardim" style="margin-left:6px">
              Seçilmezse öncelik rengi kullanılır.
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ TEKRAR VE HATIRLATMA ============ -->
  <div class="kart">
    <div class="kart-baslik"><h2>Tekrar ve Hatırlatma</h2></div>
    <div class="kart-govde">
      <div class="aj-form-grid">
        <div class="form-grup">
          <label>Tekrar</label>
          <select name="tekrar" id="aj-tekrar">
            <?php foreach ($tekrarlar as $tk => $tv): ?>
              <option value="<?= $tk ?>" <?= $d('tekrar', 'yok') === $tk ? 'selected' : '' ?>>
                <?= esc($tv) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="aj-yardim">
            Tekrarlı işte "Yapıldı" denince tarih sonraki döneme ötelenir.
          </span>
        </div>

        <div class="form-grup" id="aj-tekrar-bitis-kutu">
          <label>Tekrar Bitişi</label>
          <input type="date" name="tekrar_bitis" class="girdi" value="<?= esc($d('tekrar_bitis')) ?>">
          <span class="aj-yardim">Boşsa süresiz tekrarlar.</span>
        </div>

        <div class="form-grup">
          <label>Kaç Gün Önceden Hatırlat</label>
          <input type="number" name="hatirlat_gun" class="girdi" min="0" max="365"
                 value="<?= (int) $d('hatirlat_gun', 0) ?>">
          <span class="aj-yardim">0 = yalnız o gün panelde görünür.</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ DOSYA EKLERİ ============ -->
  <div class="kart">
    <div class="kart-baslik">
      <h2>Dosya Ekleri</h2>
      <span class="kucuk-yazi">En çok <?= number_format($ekBoyut / 1024, 1, ',', '.') ?> MB / dosya</span>
    </div>
    <div class="kart-govde">
      <?php if ($ekler !== []): ?>
        <ul class="aj-ek-liste" style="margin-bottom:12px">
          <?php foreach ($ekler as $e): ?>
            <li>
              📎 <a href="<?= site_url('ajanda/ek/' . (int) $e['id']) ?>"><?= esc($e['dosya_adi']) ?></a>
              <span class="boyut"><?= number_format($e['boyut'] / 1024, 0, ',', '.') ?> KB</span>
              <a href="<?= site_url('ajanda/ek-sil/' . (int) $e['id']) ?>" class="btn kirmizi mini"
                 onclick="return confirm('Bu dosya silinsin mi?')">Sil</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="form-grup">
        <label>Yeni Dosya Ekle</label>
        <input type="file" name="ekler[]" class="girdi" multiple
               accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.csv,.docx,.doc,.txt,.zip">
        <span class="aj-yardim">
          Birden çok dosya seçebilirsiniz. İzinli türler: PDF, resim, Excel, Word, CSV, ZIP.
        </span>
      </div>
    </div>
  </div>

  <div class="form-alt">
    <button type="submit" class="btn">💾 Kaydet</button>
    <a href="<?= site_url('ajanda') ?>" class="btn ikincil">Vazgeç</a>
    <?php if ($kayit !== null): ?>
      <a href="<?= site_url('ajanda/sil/' . (int) $kayit['id']) ?>" class="btn kirmizi"
         style="margin-left:auto"
         onclick="return confirm('Bu ajanda kaydı silinsin mi?')">🗑️ Sil</a>
    <?php endif; ?>
  </div>
</form>

<script>
(function () {
  'use strict';

  var gorunurluk = document.getElementById('aj-gorunurluk');
  var atanan     = document.getElementById('aj-atanan-kutu');
  var musavir    = document.getElementById('aj-musavir-kutu');
  var tekrar     = document.getElementById('aj-tekrar');
  var tekrarBit  = document.getElementById('aj-tekrar-bitis-kutu');

  // Görünürlüğe göre ilgisiz alanları gizle — yanlış kombinasyon oluşmasın
  function gorunurlukTazele() {
    var v = gorunurluk.value;
    atanan.style.display  = v === 'gorev' ? '' : 'none';
    musavir.style.display = v === 'musavir' ? '' : 'none';
  }

  function tekrarTazele() {
    tekrarBit.style.display = tekrar.value === 'yok' ? 'none' : '';
  }

  gorunurluk.addEventListener('change', gorunurlukTazele);
  tekrar.addEventListener('change', tekrarTazele);

  gorunurlukTazele();
  tekrarTazele();
}());
</script>

<?= $this->endSection() ?>
