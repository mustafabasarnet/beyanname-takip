<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="kart mb16">
  <div class="kart-baslik">
    <h2>🔍 Aktarma Önizlemesi</h2>
    <span class="kucuk-yazi" style="margin-left:auto">
      <b><?= esc($dosyaAdi) ?></b> → <?= esc($musavirAdi) ?>
    </span>
  </div>

  <div class="kart-govde">
    <div class="stat-grid mb16">
      <div class="stat yesil">
        <div class="etiket">Eklenecek</div>
        <div class="deger"><?= (int) $ozet['eklenecek'] ?></div>
        <div class="alt">yeni mükellef</div>
      </div>
      <div class="stat sari">
        <div class="etiket">Atlanacak</div>
        <div class="deger"><?= (int) $ozet['atlanacak'] ?></div>
        <div class="alt">zaten kayıtlı / mükerrer</div>
      </div>
      <div class="stat kirmizi">
        <div class="etiket">Hatalı</div>
        <div class="deger"><?= (int) $ozet['hatali'] ?></div>
        <div class="alt">düzeltilmeli</div>
      </div>
      <div class="stat">
        <div class="etiket">Toplam Satır</div>
        <div class="deger"><?= count($satirlar) ?></div>
      </div>
    </div>

    <?php if ((int) $ozet['eklenecek'] === 0): ?>
      <div class="uyari hata">
        <span class="ik">⚠</span>
        <div>
          <b>Eklenecek yeni mükellef yok.</b>
          Tüm satırlar ya zaten sistemde kayıtlı ya da hatalı.
          Aşağıdaki nedenleri inceleyip dosyayı düzelttikten sonra tekrar deneyin.
        </div>
      </div>
    <?php else: ?>
      <div class="uyari bilgi">
        <span class="ik">ℹ</span>
        <div>
          Aşağıdaki <b><?= (int) $ozet['eklenecek'] ?> mükellef</b> eklenecek.
          <b>Hiçbir kayıt henüz oluşturulmadı</b> — onaylayana kadar veritabanınıza dokunulmaz.
          İstemediğiniz satırların işaretini kaldırabilirsiniz.
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= site_url('mukellefler/ice-aktar/onayla') ?>" id="onay-form">
  <?= csrf_field() ?>
  <!-- Satır seçimi bu formdan geliyor: hiçbiri işaretli değilse sunucu aktarmayı reddeder -->
  <input type="hidden" name="secim" value="1">

  <div class="kart">
    <div class="kart-baslik">
      <h2>📋 Satırlar</h2>
      <div class="btn-grup" style="margin-left:auto">
        <button type="button" class="btn ikincil mini" onclick="tumunuSec(true)">Tümünü Seç</button>
        <button type="button" class="btn ikincil mini" onclick="tumunuSec(false)">Hiçbirini Seçme</button>
      </div>
    </div>

    <div class="tablo-sarmal">
      <table class="tablo">
        <thead>
          <tr>
            <th style="width:34px">
              <input type="checkbox" id="hepsi" onclick="tumunuSec(this.checked)" checked>
            </th>
            <th style="width:48px">Satır</th>
            <th style="width:90px">Durum</th>
            <th>Ünvan</th>
            <th>VKN / TCKN</th>
            <th>Tip / Defter</th>
            <th>İşe Başlama</th>
            <th>Beyannameler</th>
            <th>Açıklama</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($satirlar as $s):
            $v      = $s['veri'];
            $durum  = $s['durum'];
            $sinif  = ['eklenecek' => 'satir-ekle', 'atlanacak' => 'satir-atla', 'hatali' => 'satir-hata'][$durum] ?? '';
            $rozet  = ['eklenecek' => 'yesil', 'atlanacak' => 'sari', 'hatali' => 'kirmizi'][$durum] ?? 'gri';
            $metin  = ['eklenecek' => '✓ Eklenecek', 'atlanacak' => '⏭ Atlanacak', 'hatali' => '✕ Hatalı'][$durum] ?? $durum;
        ?>
          <tr class="<?= $sinif ?>">
            <td>
              <?php if ($durum === 'eklenecek'): ?>
                <input type="checkbox" name="satirlar[]" class="satir-onay"
                       value="<?= (int) $s['satir_no'] ?>" checked>
              <?php else: ?>
                <span class="kucuk-yazi metin-gri" title="Bu satır aktarılmayacak">—</span>
              <?php endif; ?>
            </td>

            <td class="kucuk-yazi metin-gri"><?= (int) $s['satir_no'] ?></td>

            <td><span class="rozet <?= $rozet ?>"><?= $metin ?></span></td>

            <td>
              <b><?= esc($v['unvan'] ?? '') ?></b>
              <?php if (! empty($v['kod'])): ?>
                <div class="kucuk-yazi metin-gri"><?= esc($v['kod']) ?></div>
              <?php endif; ?>
            </td>

            <td class="kucuk-yazi">
              <?= esc($v['vergi_kimlik_no'] ?? '') ?: esc($v['tc_kimlik_no'] ?? '') ?: '<span class="metin-gri">—</span>' ?>
            </td>

            <td class="kucuk-yazi">
              <?php if (! empty($v['mukellef_tipi'])): ?>
                <?= $v['mukellef_tipi'] === 'tuzel' ? 'Tüzel' : 'Gerçek' ?>
                • <?= esc(defterTipiKisa($v['defter_tipi'] ?? '')) ?>
              <?php endif; ?>
              <?php if (! empty($v['genc_girisimci'])): ?>
                <div><span class="rozet yesil" style="font-size:10px">🌱 GG <?= (int) ($v['gg_baslangic_yili'] ?? 0) ?></span></div>
              <?php endif; ?>
            </td>

            <td class="kucuk-yazi">
              <?= ! empty($v['ise_baslama_tarihi']) ? trTarih($v['ise_baslama_tarihi']) : '<span class="metin-kirmizi">—</span>' ?>
              <?php if (! empty($v['terk_tarihi'])): ?>
                <div class="metin-kirmizi">Terk: <?= trTarih($v['terk_tarihi']) ?></div>
              <?php endif; ?>
              <?php if (! empty($v['takip_baslangic'])): ?>
                <div class="metin-gri">Takip: <?= trTarih($v['takip_baslangic']) ?></div>
              <?php endif; ?>
            </td>

            <td class="kucuk-yazi">
              <?php if (! empty($s['turler'])): ?>
                <span class="rozet mavi"><?= count($s['turler']) ?> tür</span>
              <?php else: ?>
                <span class="metin-gri">—</span>
              <?php endif; ?>
            </td>

            <td class="kucuk-yazi" style="max-width:340px">
              <?php foreach ($s['neden'] as $n): ?>
                <div class="metin-kirmizi">✕ <?= esc($n) ?></div>
              <?php endforeach; ?>
              <?php foreach ($s['uyari'] as $u): ?>
                <div style="color:var(--turuncu)">⚠ <?= esc($u) ?></div>
              <?php endforeach; ?>
              <?php if ($s['neden'] === [] && $s['uyari'] === []): ?>
                <span class="metin-yesil">Sorun yok</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="kart-govde" style="border-top:1px solid var(--gri-200)">
      <label class="onay mb16">
        <input type="checkbox" name="donem_uret" value="1" checked>
        <b>Beyanname dönemlerini hemen üret</b>
        <span class="kucuk-yazi">
          — İşaretliyseniz her mükellef için takip çizelgesi satırları otomatik oluşur.
        </span>
      </label>

      <?php if ((int) $ozet['eklenecek'] > 50): ?>
        <div class="uyari dikkat mb16">
          <span class="ik">⏳</span>
          <div>
            <b><?= (int) $ozet['eklenecek'] ?> mükellef</b> aktarılacak. Dönem üretimi açıkken
            bu işlem <b>yaklaşık <?= max(1, (int) ceil((int) $ozet['eklenecek'] * 0.05)) ?> saniye</b>
            sürebilir. Sayfayı kapatmayın, tek seferde tamamlanır.
            Acele etmiyorsanız dönem üretimini kapatıp sonradan
            <b>Mükellef kartı → Dönem Üret</b> ile de yapabilirsiniz.
          </div>
        </div>
      <?php endif; ?>

      <div class="btn-grup">
        <button type="submit" class="btn yesil" id="onayla-btn"
                <?= (int) $ozet['eklenecek'] === 0 ? 'disabled' : '' ?>>
          ✓ Aktarmayı Onayla (<span id="secili-sayi"><?= (int) $ozet['eklenecek'] ?></span> mükellef)
        </button>
        <a href="<?= site_url('mukellefler/ice-aktar') ?>" class="btn ikincil">← Başka Dosya Yükle</a>
        <a href="<?= site_url('mukellefler') ?>" class="btn ikincil">İptal</a>
      </div>
    </div>
  </div>
</form>

<style>
table.tablo tbody tr.satir-atla{background:#fffbeb}
table.tablo tbody tr.satir-hata{background:#fff5f5}
table.tablo tbody tr.satir-ekle:hover{background:#f0fdf4}
</style>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
function tumunuSec(deger) {
  document.querySelectorAll('.satir-onay').forEach(function (c) { c.checked = deger; });
  var h = document.getElementById('hepsi');
  if (h) h.checked = deger;
  sayiGuncelle();
}

function sayiGuncelle() {
  var n = document.querySelectorAll('.satir-onay:checked').length;
  document.getElementById('secili-sayi').textContent = n;
  document.getElementById('onayla-btn').disabled = (n === 0);
}

document.querySelectorAll('.satir-onay').forEach(function (c) {
  c.addEventListener('change', sayiGuncelle);
});

// Çift gönderim koruması — büyük dosyalarda işlem uzun sürebilir
document.getElementById('onay-form').addEventListener('submit', function () {
  var b = document.getElementById('onayla-btn');
  b.disabled = true;
  b.textContent = '⏳ Aktarılıyor, lütfen bekleyin…';
});

sayiGuncelle();
</script>
<?= $this->endSection() ?>
