<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<?php
$d = $musavir !== null;
$aksiyon = $d ? site_url('musavirler/guncelle/' . $musavir['id']) : site_url('musavirler/kaydet');
?>
<form method="post" action="<?= $aksiyon ?>">
<?= csrf_field() ?>
<div class="kart">
  <div class="kart-baslik">
    <h2>👨‍💼 <?= $d ? 'Mali Müşavir Düzenle' : 'Yeni Mali Müşavir' ?></h2>
    <div class="sag"><a href="<?= site_url('musavirler') ?>" class="btn ikincil kucuk">← Listeye Dön</a></div>
  </div>
  <div class="kart-govde">
    <div class="form-grid">
      <div class="form-grup">
        <label>Ünvan</label>
        <select name="unvan">
          <?php $u = old('unvan', $musavir['unvan'] ?? 'SMMM'); ?>
          <option value="SMMM" <?= $u === 'SMMM' ? 'selected' : '' ?>>SMMM</option>
          <option value="YMM"  <?= $u === 'YMM'  ? 'selected' : '' ?>>YMM</option>
        </select>
      </div>
      <div class="form-grup">
        <label>Ad Soyad <span class="zorunlu">*</span></label>
        <input type="text" name="ad_soyad" class="girdi" required value="<?= esc(old('ad_soyad', $musavir['ad_soyad'] ?? '')) ?>">
      </div>
      <div class="form-grup tam">
        <label>Büro Adı</label>
        <input type="text" name="buro_adi" class="girdi" value="<?= esc(old('buro_adi', $musavir['buro_adi'] ?? '')) ?>">
      </div>
      <div class="form-grup">
        <label>TC Kimlik No</label>
        <input type="text" name="tc_kimlik" class="girdi" maxlength="11" value="<?= esc(old('tc_kimlik', $musavir['tc_kimlik'] ?? '')) ?>">
      </div>
      <div class="form-grup">
        <label>Ruhsat No</label>
        <input type="text" name="ruhsat_no" class="girdi" value="<?= esc(old('ruhsat_no', $musavir['ruhsat_no'] ?? '')) ?>">
      </div>
      <div class="form-grup">
        <label>Oda Sicil No</label>
        <input type="text" name="oda_sicil_no" class="girdi" value="<?= esc(old('oda_sicil_no', $musavir['oda_sicil_no'] ?? '')) ?>">
      </div>
      <div class="form-grup">
        <label>Telefon</label>
        <input type="text" name="telefon" class="girdi" value="<?= esc(old('telefon', $musavir['telefon'] ?? '')) ?>">
      </div>
      <div class="form-grup">
        <label>E-posta</label>
        <input type="email" name="eposta" class="girdi" value="<?= esc(old('eposta', $musavir['eposta'] ?? '')) ?>">
      </div>
      <div class="form-grup">
        <label>Liste Rengi</label>
        <input type="color" name="renk" class="girdi" style="height:38px;padding:3px"
               value="<?= esc(old('renk', $musavir['renk'] ?? '#2563eb')) ?>">
      </div>
      <div class="form-grup">
        <label>Durum</label>
        <select name="aktif">
          <?php $a = (int) old('aktif', $musavir['aktif'] ?? 1); ?>
          <option value="1" <?= $a === 1 ? 'selected' : '' ?>>Aktif</option>
          <option value="0" <?= $a === 0 ? 'selected' : '' ?>>Pasif</option>
        </select>
      </div>
      <div class="form-grup tam">
        <label>Adres</label>
        <textarea name="adres" rows="2"><?= esc(old('adres', $musavir['adres'] ?? '')) ?></textarea>
      </div>
    </div>
    <div class="form-alt">
      <button type="submit" class="btn">💾 Kaydet</button>
      <a href="<?= site_url('musavirler') ?>" class="btn ikincil">İptal</a>
    </div>
  </div>
</div>
</form>
<?= $this->endSection() ?>
