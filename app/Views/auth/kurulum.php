<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kurulum — Beyanname Takip</title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
</head>
<body class="giris-sayfa">

<div class="giris-kutu" style="max-width:520px">
  <div class="logo">
    <div class="ik">🚀</div>
    <h1>İlk Kurulum</h1>
    <div class="alt-yazi">Yönetici hesabınızı ve mali müşavir kaydınızı oluşturun</div>
  </div>

  <?php if ($hatalar = session()->getFlashdata('hatalar')): ?>
    <div class="uyari hata"><span class="ik">✕</span><div>
      <ul><?php foreach ((array) $hatalar as $h): ?><li><?= esc($h) ?></li><?php endforeach; ?></ul>
    </div></div>
  <?php endif; ?>

  <form method="post" action="<?= site_url('kurulum/kaydet') ?>">
    <?= csrf_field() ?>

    <div class="form-grid">
      <div class="form-grup">
        <label>Ünvan</label>
        <select name="unvan" class="girdi">
          <option value="SMMM">SMMM</option>
          <option value="YMM">YMM</option>
        </select>
      </div>
      <div class="form-grup">
        <label>Ad Soyad <span class="zorunlu">*</span></label>
        <input type="text" name="ad_soyad" class="girdi" required value="<?= esc(old('ad_soyad')) ?>">
      </div>
      <div class="form-grup tam">
        <label>Büro Adı</label>
        <input type="text" name="buro_adi" class="girdi" value="<?= esc(old('buro_adi')) ?>">
      </div>
      <div class="form-grup">
        <label>Kullanıcı Adı <span class="zorunlu">*</span></label>
        <input type="text" name="kullanici_adi" class="girdi" required value="<?= esc(old('kullanici_adi')) ?>">
      </div>
      <div class="form-grup">
        <label>Telefon</label>
        <input type="text" name="telefon" class="girdi" value="<?= esc(old('telefon')) ?>">
      </div>
      <div class="form-grup tam">
        <label>E-posta <span class="zorunlu">*</span></label>
        <input type="email" name="eposta" class="girdi" required value="<?= esc(old('eposta')) ?>">
      </div>
      <div class="form-grup">
        <label>Şifre <span class="zorunlu">*</span></label>
        <input type="password" name="sifre" class="girdi" required minlength="6">
      </div>
      <div class="form-grup">
        <label>Şifre (Tekrar) <span class="zorunlu">*</span></label>
        <input type="password" name="sifre_tekrar" class="girdi" required minlength="6">
      </div>
    </div>

    <button type="submit" class="btn blok" style="margin-top:18px">Kurulumu Tamamla</button>
  </form>
</div>

</body>
</html>
