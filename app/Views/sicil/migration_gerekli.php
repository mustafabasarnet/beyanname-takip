<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="uyari dikkat">
  <span class="ik">⚠</span>
  <div>
    <b>Sicil İşlemleri modülünün tabloları veritabanında yok.</b><br>
    Bu ekran, ilgili migration dosyaları çalıştırıldıktan sonra kullanılabilir:
    <code>database/migration_sicil.sql</code> ve <code>database/migration_sicil_sade.sql</code>.
  </div>
</div>

<div class="kart">
  <div class="kart-govde">
    <p class="kucuk-yazi" style="margin:0">
      Diğer modüller (mükellef, beyanname takip, makbuz, evrak, e-defter, ödeme) etkilenmez;
      bu yalnızca Sicil İşlemleri ekranı için gereken tabloları ilgilendirir.
    </p>
    <p style="margin-bottom:0"><a class="btn kucuk" href="<?= site_url('panel') ?>">Kontrol Paneline dön</a></p>
  </div>
</div>

<?= $this->endSection() ?>
