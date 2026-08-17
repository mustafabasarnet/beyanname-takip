<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php if ($hatalar = session('geri_yukleme_hatalari')): ?>
  <div class="uyari hata mb16">
    <span class="ik">⚠</span>
    <div>
      <b>Geri yükleme sırasında <?= count($hatalar) ?> SQL hatası oluştu</b>
      <ul>
        <?php foreach (array_slice($hatalar, 0, 10) as $h): ?>
          <li><?= (int) $h['sira'] ?>. ifade — <?= esc($h['mesaj']) ?>
            <div class="kucuk-yazi"><code><?= esc($h['sql']) ?>…</code></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<div class="kart mb16">
  <div class="kart-baslik">
    <h2>♻️ Yedekten Geri Yükleme</h2>
    <a href="<?= site_url('sistem/yedekleme') ?>" class="btn ikincil kucuk" style="margin-left:auto">
      ← Yedekleme
    </a>
  </div>

  <div class="kart-govde">

    <div class="uyari hata mb16">
      <span class="ik">⛔</span>
      <div>
        <b>DİKKAT — Bu işlem geri alınamaz!</b>
        <ul class="mb0">
          <li>Yedek dosyasındaki tablolar <b>silinip yeniden oluşturulur</b>.</li>
          <li>Şu anki verilerinizin <b>üzerine yazılır</b>, mevcut kayıtlar kaybolur.</li>
          <li>İşlem bitince güvenlik için <b>oturumunuz kapatılır</b>.</li>
        </ul>
        <div class="mt8">
          <b>Önce mevcut durumun yedeğini alın:</b>
          <a href="<?= site_url('sistem/yedekleme') ?>" class="btn kucuk" style="margin-left:6px">
            💾 Şimdi Yedek Al
          </a>
        </div>
      </div>
    </div>

    <div class="bilgi-liste mb16">
      <div class="oge">
        <div class="et">Mevcut Veritabanı</div>
        <div class="dg"><?= esc($toplam['boyut_f']) ?> · <?= (int) $toplam['tablo'] ?> tablo</div>
      </div>
      <div class="oge">
        <div class="et">Azami Yükleme Boyutu</div>
        <div class="dg"><?= esc($azamiYukleme) ?></div>
      </div>
    </div>

    <form method="post" action="<?= site_url('sistem/geri-yukle') ?>"
          enctype="multipart/form-data" id="geri-form">
      <?= csrf_field() ?>

      <div class="form-grup mb16">
        <label>Yedek Dosyası (.sql) <span class="zorunlu">*</span></label>
        <input type="file" name="yedek" id="yedek" class="girdi" accept=".sql" required>
        <span class="ipucu">
          Yalnızca bu programdan alınmış <b>.sql</b> yedekleri yükleyin.
          Dosya en fazla <?= esc($azamiYukleme) ?> olabilir.
        </span>
      </div>

      <div class="form-grup mb16">
        <label>
          Onaylamak için aşağıya <b>GERİ YÜKLE</b> yazın <span class="zorunlu">*</span>
        </label>
        <input type="text" name="onay" id="onay" class="girdi" autocomplete="off"
               placeholder="GERİ YÜKLE" required
               style="max-width:280px;font-weight:700;letter-spacing:.5px">
        <span class="ipucu">Yanlışlıkla tıklamayı önlemek için istiyoruz.</span>
      </div>

      <div class="btn-grup">
        <button type="submit" class="btn kirmizi" id="geri-btn" disabled>
          ♻️ Geri Yüklemeyi Başlat
        </button>
        <a href="<?= site_url('sistem/yedekleme') ?>" class="btn ikincil">İptal</a>
      </div>
    </form>
  </div>
</div>

<div class="kart">
  <div class="kart-baslik"><h2>🛡️ Güvenlik Kontrolleri</h2></div>
  <div class="kart-govde">
    <p class="kucuk-yazi mb8">Yüklediğiniz dosya çalıştırılmadan önce denetlenir:</p>
    <ul class="kucuk-yazi">
      <li>Dosya gerçekten SQL yedeği mi (<code>CREATE TABLE</code> / <code>INSERT INTO</code> içeriyor mu)</li>
      <li><code>DROP DATABASE</code>, <code>GRANT</code>, <code>CREATE USER</code>,
          <code>INTO OUTFILE</code>, <code>LOAD_FILE</code> gibi tehlikeli ifadeler
          <b>varsa dosya reddedilir</b></li>
      <li>İfadeler tek tek çalıştırılır; 10 hatadan sonra işlem durdurulur</li>
      <li>Yalnızca <b>yönetici</b> rolü bu ekrana erişebilir</li>
    </ul>
  </div>
</div>

<style>code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px}</style>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
var onayGirdi = document.getElementById('onay');
var dosyaGirdi = document.getElementById('yedek');
var btn = document.getElementById('geri-btn');

function kontrol() {
  // Türkçe klavye farkları (İ/I) tolere edilir
  var d = onayGirdi.value.toLocaleUpperCase('tr-TR').replace(/İ/g, 'I').trim();
  btn.disabled = !(d === 'GERI YUKLE' && dosyaGirdi.files.length > 0);
}

onayGirdi.addEventListener('input', kontrol);
dosyaGirdi.addEventListener('change', kontrol);

document.getElementById('geri-form').addEventListener('submit', function (e) {
  if (!confirm('SON UYARI\n\nMevcut verilerinizin üzerine yazılacak ve bu işlem geri alınamaz.\n\nDevam edilsin mi?')) {
    e.preventDefault();
    return false;
  }
  btn.disabled = true;
  btn.textContent = '⏳ Geri yükleniyor, sayfayı kapatmayın…';
});

kontrol();
</script>
<?= $this->endSection() ?>
