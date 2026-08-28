<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Giriş — Beyanname Takip</title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
<style>
/* Gömülü: stil.css kopyalanmamış bir kurulumda da satır dağılmasın */
.hatirla-satir{display:flex;align-items:center;gap:8px;margin:2px 0 4px;
  cursor:pointer;font-size:13.5px;color:#334155;user-select:none}
.hatirla-satir input[type=checkbox]{width:16px;height:16px;accent-color:#2563eb;cursor:pointer}
.hatirla-satir small{color:#94a3b8;font-size:11.5px}
</style>
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

    <?php
    /*
     * BENİ HATIRLA
     *
     * İşaretlenirse tarayıcıya güvenli bir jeton çerezi bırakılır ve
     * kullanıcı ayarlardaki süre boyunca şifre girmeden içeri alınır.
     *
     * Geriye dönük uyum: controller güncellenmemişse $hatirlaAcik
     * tanımsız olur; bu durumda kutu gösterilmez ve giriş eskisi gibi
     * çalışır (migration çalıştırılmamış kurulumda da aynısı geçerli).
     */
    $hatirlaAcik = $hatirlaAcik ?? false;
    $hatirlaGun  = (int) ($hatirlaGun ?? 90);
    ?>
    <?php if ($hatirlaAcik): ?>
      <label class="onay hatirla-satir">
        <input type="checkbox" name="hatirla" value="1"
               <?= old('hatirla') ? 'checked' : '' ?>>
        <span>Beni hatırla</span>
        <small>(<?= $hatirlaGun ?> gün boyunca şifre sorulmaz)</small>
      </label>
    <?php endif; ?>

    <button type="submit" class="btn blok" style="margin-top:6px">Giriş Yap</button>
  </form>

  <div class="giris-dip">© <?= date('Y') ?> Beyanname Takip Sistemi</div>
</div>

</body>
</html>
