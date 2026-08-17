<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<div class="stat-grid">
  <div class="stat"><div class="etiket">Toplam</div><div class="deger"><?= (int) $ozet['toplam'] ?></div></div>
  <div class="stat kirmizi"><div class="etiket">Gecikmiş</div><div class="deger"><?= (int) $ozet['gecikmis'] ?></div></div>
  <div class="stat yesil"><div class="etiket">Onaylandı</div><div class="deger"><?= (int) ($ozet['onaylandi'] ?? 0) ?></div></div>
</div>
<div class="kart"><div class="kart-baslik"><h2>📈 Raporlar</h2></div>
<div class="kart-govde">
  <div class="tur-grid">
    <a href="<?= site_url('raporlar/gecikmis') ?>" class="tur-kutu"><div class="ust"><b>⏰ Gecikmiş Beyannameler</b></div>
      <div class="aciklama" style="padding-left:0">Süresi geçmiş ve tamamlanmamış tüm kayıtlar</div></a>
    <a href="<?= site_url('raporlar/mukellef-ozet') ?>" class="tur-kutu"><div class="ust"><b>🏢 Mükellef Özeti</b></div>
      <div class="aciklama" style="padding-left:0">Mükellef bazlı tamamlanma ve gecikme durumu</div></a>
    <?php if (($aktifKullanici['rol'] ?? '') === 'admin'): ?>
    <a href="<?= site_url('raporlar/musavir-performans') ?>" class="tur-kutu"><div class="ust"><b>🎯 Müşavir Performansı</b></div>
      <div class="aciklama" style="padding-left:0">Mali müşavir bazlı iş yükü ve tamamlanma oranı</div></a>
    <?php endif; ?>
  </div>
</div></div>
<?= $this->endSection() ?>
