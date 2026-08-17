<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Giriş — Beyanname Takip</title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
</head>
<body class="giris-sayfa">

<div class="giris-kutu">
  <div class="logo">
    <div class="ik">📋</div>
    <h1>Beyanname Takip</h1>
    <div class="alt-yazi">Mükellef Beyanname ve Evrak Yönetim Sistemi</div>
  </div>

  <?php if (session()->getFlashdata('hata')): ?>
    <div class="uyari hata"><span class="ik">✕</span><div><?= esc(session()->getFlashdata('hata')) ?></div></div>
  <?php endif; ?>
  <?php if (session()->getFlashdata('basari')): ?>
    <div class="uyari basari"><span class="ik">✓</span><div><?= esc(session()->getFlashdata('basari')) ?></div></div>
  <?php endif; ?>

  <form method="post" action="<?= site_url('giris') ?>">
    <?= csrf_field() ?>

    <div class="form-grup">
      <label for="kimlik">Kullanıcı Adı veya E-posta</label>
      <input type="text" id="kimlik" name="kimlik" class="girdi" required autofocus
             value="<?= esc(old('kimlik')) ?>" placeholder="kullanici_adi">
    </div>

    <div class="form-grup">
      <label for="sifre">Şifre</label>
      <input type="password" id="sifre" name="sifre" class="girdi" required placeholder="••••••••">
    </div>

    <button type="submit" class="btn blok" style="margin-top:6px">Giriş Yap</button>
  </form>

  <div class="giris-dip">© <?= date('Y') ?> Beyanname Takip Sistemi</div>
</div>

</body>
</html>
