<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<?php
$d = $kullanici !== null;
$aksiyon = $d ? site_url('kullanicilar/guncelle/' . $kullanici['id']) : site_url('kullanicilar/kaydet');
?>
<form method="post" action="<?= $aksiyon ?>">
<?= csrf_field() ?>
<div class="kart">
  <div class="kart-baslik">
    <h2>👥 <?= $d ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı' ?></h2>
    <div class="sag"><a href="<?= site_url('kullanicilar') ?>" class="btn ikincil kucuk">← Listeye Dön</a></div>
  </div>
  <div class="kart-govde">
    <div class="form-grid">
      <div class="form-grup"><label>Ad Soyad <span class="zorunlu">*</span></label>
        <input type="text" name="ad_soyad" class="girdi" required value="<?= esc(old('ad_soyad', $kullanici['ad_soyad'] ?? '')) ?>"></div>
      <div class="form-grup"><label>Kullanıcı Adı <span class="zorunlu">*</span></label>
        <input type="text" name="kullanici_adi" class="girdi" required value="<?= esc(old('kullanici_adi', $kullanici['kullanici_adi'] ?? '')) ?>"></div>
      <div class="form-grup"><label>E-posta <span class="zorunlu">*</span></label>
        <input type="email" name="eposta" class="girdi" required value="<?= esc(old('eposta', $kullanici['eposta'] ?? '')) ?>"></div>
      <div class="form-grup"><label>Telefon</label>
        <input type="text" name="telefon" class="girdi" value="<?= esc(old('telefon', $kullanici['telefon'] ?? '')) ?>"></div>
      <div class="form-grup"><label>Rol <span class="zorunlu">*</span></label>
        <select name="rol" required>
          <?php $r = old('rol', $kullanici['rol'] ?? 'personel');
          foreach (['admin'=>'Yönetici (tüm yetkiler)','musavir'=>'Mali Müşavir (kendi mükellefleri)','personel'=>'Personel (kendi bürosu)'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $r === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-grup"><label>Birincil (Varsayılan) Mali Müşavir</label>
        <select name="musavir_id" id="birincil_musavir">
          <option value="">— Seçilmedi —</option>
          <?php foreach ($musavirler as $mid => $mad): ?>
            <option value="<?= $mid ?>" <?= (int) old('musavir_id', $kullanici['musavir_id'] ?? 0) === (int) $mid ? 'selected' : '' ?>><?= esc($mad) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="yardim">Yeni mükellef eklerken formda ön seçili gelir. Erişim yetkisi aşağıdan verilir.</span></div>
      <div class="form-grup"><label>Şifre <?= $d ? '' : '<span class="zorunlu">*</span>' ?></label>
        <input type="password" name="sifre" class="girdi" <?= $d ? '' : 'required' ?> minlength="6"
               placeholder="<?= $d ? 'Değiştirmek istemiyorsanız boş bırakın' : 'En az 6 karakter' ?>"></div>
      <div class="form-grup"><label>Durum</label>
        <select name="aktif">
          <?php $ak = (int) old('aktif', $kullanici['aktif'] ?? 1); ?>
          <option value="1" <?= $ak === 1 ? 'selected' : '' ?>>Aktif</option>
          <option value="0" <?= $ak === 0 ? 'selected' : '' ?>>Pasif</option>
        </select></div>
    </div>
    <div class="bolucu"></div>

    <h3 style="font-size:14px;margin-bottom:6px">🔑 Mali Müşavir Erişim Yetkisi</h3>
    <p class="yardim mb16">
      Bu kullanıcı, işaretlediğiniz mali müşavirlerin mükelleflerini görebilir ve
      düzenleyebilir. Birden fazla seçebilirsiniz.
      <b>Yönetici</b> rolü bu seçimden bağımsız olarak <u>tüm</u> müşavirlere erişir.
    </p>

    <div id="erisim-kutusu" class="tur-grid mb16">
      <?php
      $secili = array_map('intval', old('erisim_musavirleri', $secilenErisim ?? []) ?: []);
      foreach ($musavirler as $mid => $mad):
          $isaretli = in_array((int) $mid, $secili, true);
      ?>
        <label class="tur-kutu <?= $isaretli ? 'secili' : '' ?>">
          <div class="ust">
            <input type="checkbox" name="erisim_musavirleri[]" value="<?= $mid ?>" <?= $isaretli ? 'checked' : '' ?>>
            <b><?= esc($mad) ?></b>
          </div>
        </label>
      <?php endforeach; ?>
      <?php if ($musavirler === []): ?>
        <div class="uyari dikkat" style="grid-column:1/-1"><span class="ik">⚠</span><div>
          Henüz mali müşavir tanımlanmamış.
          <a href="<?= site_url('musavirler/yeni') ?>">Mali müşavir ekleyin</a>.
        </div></div>
      <?php endif; ?>
    </div>

    <div class="form-alt">
      <button type="submit" class="btn">💾 Kaydet</button>
      <a href="<?= site_url('kullanicilar') ?>" class="btn ikincil">İptal</a>
    </div>
  </div>
</div>
</form>
<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
(function () {
  var rolSec   = document.querySelector('[name=rol]');
  var birincil = document.getElementById('birincil_musavir');
  var kutu     = document.getElementById('erisim-kutusu');

  // Kutulara tıklanınca görsel durum
  kutu.querySelectorAll('.tur-kutu').forEach(function (k) {
    var i = k.querySelector('input');
    i.addEventListener('change', function () { k.classList.toggle('secili', i.checked); });
  });

  // Birincil müşavir seçilince erişim listesine otomatik ekle
  if (birincil) {
    birincil.addEventListener('change', function () {
      var v = birincil.value;
      if (!v) return;
      var inp = kutu.querySelector('input[value="' + v + '"]');
      if (inp && !inp.checked) {
        inp.checked = true;
        inp.closest('.tur-kutu').classList.add('secili');
      }
    });
  }

  // Yönetici rolünde uyarı göster
  function rolKontrol() {
    var admin = rolSec && rolSec.value === 'admin';
    kutu.style.opacity = admin ? '.5' : '1';
    var mevcut = document.getElementById('admin-not');
    if (admin && !mevcut) {
      var d = document.createElement('div');
      d.id = 'admin-not';
      d.className = 'uyari bilgi';
      d.innerHTML = '<span class="ik">ℹ</span><div>Yönetici rolü zaten tüm mali müşavirlere erişir; aşağıdaki seçim dikkate alınmaz.</div>';
      kutu.parentNode.insertBefore(d, kutu);
    } else if (!admin && mevcut) {
      mevcut.remove();
    }
  }
  if (rolSec) { rolSec.addEventListener('change', rolKontrol); rolKontrol(); }
})();
</script>
<?= $this->endSection() ?>
