<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<form method="post" action="<?= site_url('profil') ?>">
<?= csrf_field() ?>
<div class="kart" style="max-width:640px">
  <div class="kart-baslik"><h2>👤 Profil Bilgilerim</h2></div>
  <div class="kart-govde">
    <div class="form-grid">
      <div class="form-grup tam"><label>Ad Soyad</label>
        <input type="text" name="ad_soyad" class="girdi" value="<?= esc($kullanici['ad_soyad']) ?>" required></div>
      <div class="form-grup"><label>Kullanıcı Adı</label>
        <input type="text" class="girdi" value="<?= esc($kullanici['kullanici_adi']) ?>" disabled></div>
      <div class="form-grup"><label>Telefon</label>
        <input type="text" name="telefon" class="girdi" value="<?= esc($kullanici['telefon'] ?? '') ?>"></div>
    </div>
    <div class="bolucu"></div>
    <h3 style="font-size:14px;margin-bottom:12px">🔒 Şifre Değiştir</h3>
    <div class="form-grid">
      <div class="form-grup tam"><label>Mevcut Şifre</label>
        <input type="password" name="mevcut_sifre" class="girdi" placeholder="Şifre değiştirmiyorsanız boş bırakın"></div>
      <div class="form-grup"><label>Yeni Şifre</label>
        <input type="password" name="yeni_sifre" class="girdi" minlength="6"></div>
      <div class="form-grup"><label>Yeni Şifre (Tekrar)</label>
        <input type="password" name="yeni_sifre_tekrar" class="girdi" minlength="6"></div>
    </div>
    <div class="form-alt"><button type="submit" class="btn">💾 Kaydet</button></div>
  </div>
</div>
</form>
<?= $this->endSection() ?>
