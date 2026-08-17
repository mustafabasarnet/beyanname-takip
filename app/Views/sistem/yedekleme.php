<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="stat-grid mb16">
  <div class="stat">
    <div class="etiket">Veritabanı Boyutu</div>
    <div class="deger"><?= esc($toplam['boyut_f']) ?></div>
  </div>
  <div class="stat mor">
    <div class="etiket">Tablo</div>
    <div class="deger"><?= (int) $toplam['tablo'] ?></div>
  </div>
  <div class="stat yesil">
    <div class="etiket">Toplam Kayıt</div>
    <div class="deger"><?= number_format(array_sum(array_column($tablolar, 'satir')), 0, ',', '.') ?></div>
  </div>
  <div class="stat sari">
    <div class="etiket">Bugün</div>
    <div class="deger" style="font-size:19px"><?= date('d.m.Y') ?></div>
    <div class="alt"><?= date('H:i') ?></div>
  </div>
</div>

<div class="kart mb16">
  <div class="kart-baslik">
    <h2>💾 Veritabanı Yedeği Al</h2>
    <a href="<?= site_url('sistem/geri-yukleme') ?>" class="btn ikincil kucuk" style="margin-left:auto">
      ♻️ Yedekten Geri Yükle
    </a>
  </div>

  <div class="kart-govde">
    <div class="uyari bilgi mb16">
      <span class="ik">ℹ</span>
      <div>
        Yedek, <b>.sql</b> dosyası olarak bilgisayarınıza iner. İçinde hem tablo yapısı
        hem de tüm verileriniz bulunur. Dosyayı güvenli bir yerde saklayın —
        <b>içinde mükellef bilgileriniz açık hâlde durur</b>.
        <div class="mt8 kucuk-yazi">
          <b>Ne sıklıkla?</b> Beyanname döneminden önce ve sonra, toplu veri girişi
          yapmadan önce ve ayda en az bir kez yedek almanız önerilir.
        </div>
      </div>
    </div>

    <form method="post" action="<?= site_url('sistem/yedek-indir') ?>" id="yedek-form">
      <?= csrf_field() ?>

      <div class="satir arali mb8">
        <b>Yedeklenecek Tablolar</b>
        <div class="btn-grup">
          <button type="button" class="btn ikincil mini" onclick="tumTablo(true)">Tümünü Seç</button>
          <button type="button" class="btn ikincil mini" onclick="tumTablo(false)">Hiçbirini</button>
        </div>
      </div>

      <div class="tablo-sarmal mb16">
        <table class="tablo">
          <thead>
            <tr>
              <th style="width:34px">
                <input type="checkbox" id="hepsi" onclick="tumTablo(this.checked)" checked>
              </th>
              <th>Tablo</th>
              <th>İçerik</th>
              <th class="sag">Kayıt</th>
              <th class="sag">Boyut</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $aciklama = [
                'mukellefler'                => 'Mükellef kartları',
                'beyanname_takip'            => 'Beyanname takip çizelgesi + tahakkuklar',
                'mukellef_beyannameleri'     => 'Mükellef ↔ beyanname türü bağlantıları',
                'beyanname_turleri'          => 'Beyanname türü tanımları',
                'evrak_takip'                => 'Evrak geldi/gelmedi kayıtları',
                'evrak_turleri'              => 'Evrak türü tanımları',
                'musavirler'                 => 'Mali müşavir kayıtları',
                'kullanicilar'               => 'Kullanıcı hesapları (şifreler şifreli)',
                'kullanici_musavirleri'      => 'Kullanıcı ↔ müşavir erişim yetkileri',
                'karsit_inceleme'            => 'Karşıt inceleme tutanakları',
                'ozel_odemeler'              => 'Özel ödeme kalemleri (Bağkur, MTV…)',
                'odeme_listeleri'            => 'Kayıtlı ödeme listeleri',
                'odeme_listesi_mukellefleri' => 'Ödeme listesi üyelikleri',
                'damga_tutarlari'            => 'Yıllık damga vergisi tutarları',
                'tatiller'                   => 'Resmi tatil tanımları',
                'mukellef_aylik_not'         => 'Aylık notlar',
                'ayarlar'                    => 'Program ayarları',
            ];
            ?>
            <?php foreach ($tablolar as $t): ?>
              <tr>
                <td>
                  <input type="checkbox" name="tablolar[]" class="tablo-sec"
                         value="<?= esc($t['ad']) ?>" checked>
                </td>
                <td><code><?= esc($t['ad']) ?></code></td>
                <td class="kucuk-yazi"><?= esc($aciklama[$t['ad']] ?? '—') ?></td>
                <td class="sag"><?= number_format($t['satir'], 0, ',', '.') ?></td>
                <td class="sag kucuk-yazi"><?= esc($t['boyut_f']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <label class="onay mb16">
        <input type="checkbox" name="sema_only" value="1">
        <b>Yalnızca tablo yapısı</b>
        <span class="kucuk-yazi">— veriler olmadan, sadece boş şema (yeni kurulum için)</span>
      </label>

      <div class="btn-grup">
        <button type="submit" class="btn yesil" id="indir-btn">⬇ Yedeği İndir (.sql)</button>
        <span class="kucuk-yazi" id="durum-metni" style="align-self:center"></span>
      </div>
    </form>
  </div>
</div>

<div class="kart">
  <div class="kart-baslik"><h2>📖 Yedeği Nasıl Geri Yüklerim?</h2></div>
  <div class="kart-govde">
    <div class="adim-grid">
      <div class="adim">
        <div class="adim-no">A</div>
        <div>
          <b>Program üzerinden</b>
          <p class="kucuk-yazi">
            <b>Sistem → Yedekleme → Yedekten Geri Yükle</b> ekranından .sql dosyasını
            yükleyin. En kolay yol.
          </p>
        </div>
      </div>
      <div class="adim">
        <div class="adim-no">B</div>
        <div>
          <b>phpMyAdmin ile</b>
          <p class="kucuk-yazi">
            Veritabanını seçin → <b>İçe Aktar</b> sekmesi → dosyayı seçin → Başlat.
          </p>
        </div>
      </div>
      <div class="adim">
        <div class="adim-no">C</div>
        <div>
          <b>Komut satırından</b>
          <p class="kucuk-yazi">
            <code>mysql -u kullanici -p veritabani &lt; yedek.sql</code>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.adim-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
.adim{display:flex;gap:12px;padding:14px;background:var(--gri-50);
      border:1px solid var(--gri-200);border-radius:var(--radius-sm)}
.adim-no{flex:0 0 28px;height:28px;border-radius:50%;background:var(--ana);color:#fff;
         display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px}
.adim p{margin:3px 0 0}
code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px}
</style>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
function tumTablo(deger) {
  document.querySelectorAll('.tablo-sec').forEach(function (c) { c.checked = deger; });
  var h = document.getElementById('hepsi');
  if (h) h.checked = deger;
  dugmeGuncelle();
}

function dugmeGuncelle() {
  var n = document.querySelectorAll('.tablo-sec:checked').length;
  document.getElementById('indir-btn').disabled = (n === 0);
  document.getElementById('durum-metni').textContent =
    n === 0 ? 'En az bir tablo seçmelisiniz.' : n + ' tablo seçildi.';
}

document.querySelectorAll('.tablo-sec').forEach(function (c) {
  c.addEventListener('change', dugmeGuncelle);
});

// İndirme başladığında geri bildirim ver (büyük veritabanı uzun sürebilir)
document.getElementById('yedek-form').addEventListener('submit', function () {
  var b = document.getElementById('indir-btn');
  b.disabled = true;
  b.textContent = '⏳ Yedek hazırlanıyor…';
  setTimeout(function () {
    b.disabled = false;
    b.textContent = '⬇ Yedeği İndir (.sql)';
    BT.bildir('Yedek indirildi. Dosyayı güvenli bir yerde saklayın.', 'basari');
  }, 2500);
});

dugmeGuncelle();
</script>
<?= $this->endSection() ?>
