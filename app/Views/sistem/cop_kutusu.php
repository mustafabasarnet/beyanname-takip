<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="kart">
  <div class="kart-baslik">
    <h2>🗑️ Çöp Kutusu (<?= count($kayitlar) ?>)</h2>
    <a href="<?= site_url('sistem/veri-yonetimi') ?>" class="btn ikincil kucuk" style="margin-left:auto">
      ← Veri Yönetimi
    </a>
  </div>

  <div class="kart-govde">
    <div class="uyari bilgi mb16">
      <span class="ik">ℹ</span>
      <div>
        Buradaki mükellefler <b>silinmiş görünür</b> ama verileri duruyor.
        <b>Geri Yükle</b> ile eski hâline döndürebilirsiniz.
        <b>Kalıcı Sil</b> derseniz mükellef ve <b>tüm beyanname/evrak kayıtları</b>
        veritabanından tamamen kaldırılır — bu işlem geri alınamaz.
      </div>
    </div>

    <?php if ($kayitlar === []): ?>
      <div class="tablo-bos">
        <span class="ikon">🗑️</span>
        Çöp kutusu boş.
        <div class="kucuk-yazi mt8">
          Mükellef listesinden sildiğiniz kayıtlar burada birikir.
        </div>
      </div>
    <?php else: ?>

      <form method="post" id="cop-form">
        <?= csrf_field() ?>

        <div class="satir arali mb8">
          <div class="btn-grup">
            <button type="button" class="btn ikincil mini" onclick="tumSec(true)">Tümünü Seç</button>
            <button type="button" class="btn ikincil mini" onclick="tumSec(false)">Hiçbirini</button>
          </div>
          <span class="kucuk-yazi" id="secim-metni">0 kayıt seçildi</span>
        </div>

        <div class="tablo-sarmal mb16">
          <table class="tablo">
            <thead>
              <tr>
                <th style="width:34px">
                  <input type="checkbox" id="hepsi" onclick="tumSec(this.checked)">
                </th>
                <th>Mükellef</th>
                <th>VKN / TCKN</th>
                <th>Mali Müşavir</th>
                <th class="sag">Beyanname</th>
                <th class="sag">Evrak</th>
                <th>Silinme Tarihi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($kayitlar as $k): ?>
                <tr>
                  <td>
                    <input type="checkbox" name="idler[]" class="cop-sec" value="<?= (int) $k['id'] ?>">
                  </td>
                  <td>
                    <b><?= esc($k['unvan']) ?></b>
                    <div class="kucuk-yazi metin-gri">
                      <?= esc($k['kod'] ?: '—') ?> ·
                      <?= $k['mukellef_tipi'] === 'tuzel' ? 'Tüzel' : 'Gerçek' ?> ·
                      <?= esc(defterTipiKisa($k['defter_tipi'])) ?>
                    </div>
                  </td>
                  <td class="kucuk-yazi">
                    <?= esc($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no'] ?: '—') ?>
                  </td>
                  <td class="kucuk-yazi"><?= esc($k['musavir_adi'] ?: '—') ?></td>
                  <td class="sag">
                    <?php if ((int) $k['beyanname'] > 0): ?>
                      <span class="rozet mavi"><?= (int) $k['beyanname'] ?></span>
                    <?php else: ?>
                      <span class="metin-gri">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="sag">
                    <?php if ((int) $k['evrak'] > 0): ?>
                      <span class="rozet mor"><?= (int) $k['evrak'] ?></span>
                    <?php else: ?>
                      <span class="metin-gri">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="kucuk-yazi">
                    <?= $k['deleted_at'] ? date('d.m.Y H:i', strtotime($k['deleted_at'])) : '—' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="bolucu"></div>

        <div class="satir" style="gap:14px;flex-wrap:wrap;align-items:flex-end">
          <button type="submit" class="btn yesil" id="geri-btn"
                  formaction="<?= site_url('sistem/cop-geri-yukle') ?>" disabled>
            ♻️ Seçilenleri Geri Yükle
          </button>

          <div class="form-grup mb0" style="max-width:170px">
            <label class="kucuk-yazi">Kalıcı silmek için <b>SİL</b> yazın</label>
            <input type="text" name="onay" id="onay" class="girdi" autocomplete="off"
                   placeholder="SİL" style="font-weight:700">
          </div>

          <button type="submit" class="btn kirmizi" id="kalici-btn"
                  formaction="<?= site_url('sistem/cop-kalici-sil') ?>" disabled>
            🗑 Seçilenleri Kalıcı Sil
          </button>

          <button type="submit" class="btn kirmizi" id="bosalt-btn"
                  formaction="<?= site_url('sistem/cop-kalici-sil') ?>"
                  name="tumu" value="1" disabled style="margin-left:auto">
            💥 Çöp Kutusunu Tamamen Boşalt
          </button>
        </div>
      </form>

    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
function tumSec(deger) {
  document.querySelectorAll('.cop-sec').forEach(function (c) { c.checked = deger; });
  var h = document.getElementById('hepsi');
  if (h) h.checked = deger;
  guncelle();
}

function silYazildi() {
  var v = document.getElementById('onay').value;
  return v.toLocaleUpperCase('tr-TR').replace(/İ/g, 'I').trim() === 'SIL';
}

function guncelle() {
  var n = document.querySelectorAll('.cop-sec:checked').length;
  var onay = silYazildi();

  document.getElementById('secim-metni').textContent = n + ' kayıt seçildi';
  document.getElementById('geri-btn').disabled   = (n === 0);
  document.getElementById('kalici-btn').disabled = (n === 0 || !onay);
  document.getElementById('bosalt-btn').disabled = !onay;
}

document.querySelectorAll('.cop-sec').forEach(function (c) {
  c.addEventListener('change', guncelle);
});
document.getElementById('onay').addEventListener('input', guncelle);

// Kalıcı silmelerde ikinci onay
document.getElementById('kalici-btn').addEventListener('click', function (e) {
  var n = document.querySelectorAll('.cop-sec:checked').length;
  if (!confirm(n + ' mükellef ve TÜM beyanname/evrak kayıtları kalıcı olarak silinecek.\n\n'
             + 'Bu işlem GERİ ALINAMAZ. Devam edilsin mi?')) {
    e.preventDefault();
  }
});

document.getElementById('bosalt-btn').addEventListener('click', function (e) {
  if (!confirm('Çöp kutusundaki TÜM mükellefler ve bağlı kayıtları kalıcı olarak silinecek.\n\n'
             + 'Bu işlem GERİ ALINAMAZ. Devam edilsin mi?')) {
    e.preventDefault();
  }
});

guncelle();
</script>
<?= $this->endSection() ?>
