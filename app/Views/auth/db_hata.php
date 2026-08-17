<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Veritabanı Hatası</title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
</head>
<body class="giris-sayfa">
<div class="giris-kutu" style="max-width:560px">
  <div class="logo"><div class="ik" style="background:linear-gradient(135deg,#dc2626,#f87171)">⚠️</div>
    <h1>Veritabanı Bağlantı Hatası</h1></div>

  <div class="uyari hata"><span class="ik">✕</span><div><?= esc($mesaj ?? '') ?></div></div>

  <div class="uyari bilgi"><span class="ik">ℹ</span><div>
    <b>Kontrol Listesi</b>
    <ul>
      <li><code>.env</code> dosyasındaki veritabanı bilgilerini kontrol edin.</li>
      <li><code>database/beyanname_takip.sql</code> dosyasını içe aktardınız mı?</li>
      <li>MySQL servisi çalışıyor mu?</li>
    </ul>
  </div></div>
</div>
</body>
</html>
