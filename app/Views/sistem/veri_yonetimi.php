<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="stat-grid mb16">
  <div class="stat">
    <div class="etiket">Mükellef</div>
    <div class="deger"><?= number_format($istatistik['mukellef'], 0, ',', '.') ?></div>
    <div class="alt">faal kayıt</div>
  </div>
  <div class="stat mor">
    <div class="etiket">Beyanname Kaydı</div>
    <div class="deger"><?= number_format($istatistik['beyanname'], 0, ',', '.') ?></div>
  </div>
  <div class="stat sari">
    <div class="etiket">Evrak Kaydı</div>
    <div class="deger"><?= number_format($istatistik['evrak'], 0, ',', '.') ?></div>
  </div>
  <div class="stat kirmizi">
    <div class="etiket">Çöp Kutusu</div>
    <div class="deger"><?= number_format($istatistik['cop'], 0, ',', '.') ?></div>
    <div class="alt">
      <a href="<?= site_url('sistem/cop-kutusu') ?>" style="color:inherit;text-decoration:underline">
        görüntüle →
      </a>
    </div>
  </div>
</div>

<div class="uyari dikkat mb16">
  <span class="ik">⚠</span>
  <div>
    <b>Bu ekran yalnızca yöneticiye açıktır.</b>
    Silme işlemlerinden önce <a href="<?= site_url('sistem/yedekleme') ?>"><b>yedek alın</b></a>.
    Mükellef silmeleri çöp kutusuna gider (geri alınabilir); beyanname ve evrak
    kayıtları <b>doğrudan silinir</b> — ancak bunlar Toplu Dönem Üretimi ile
    yeniden oluşturulabilir.
  </div>
</div>

<!-- ============ 1) BEYANNAME KAYITLARI ============ -->
<div class="kart mb16">
  <div class="kart-baslik">
    <h2>📝 Beyanname Kayıtlarını Temizle</h2>
    <span class="kucuk-yazi" style="margin-left:auto">
      Örn: “2024 yılının tüm kayıtları”, “iptal edilen dönemler”
    </span>
  </div>

  <div class="kart-govde">
    <form method="post" action="<?= site_url('sistem/beyanname-sil') ?>" id="beyanname-form">
      <?= csrf_field() ?>

      <div class="form-grid mb16">
        <div class="form-grup">
          <label>Yıl</label>
          <select name="yil" id="f-yil" class="girdi">
            <option value="">— Tümü —</option>
            <?php foreach ($yilDagilimi as $y): ?>
              <option value="<?= (int) $y['yil'] ?>">
                <?= (int) $y['yil'] ?> (<?= number_format($y['adet'], 0, ',', '.') ?> kayıt)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grup">
          <label>Beyanname Türü</label>
          <select name="tur_id" id="f-tur" class="girdi">
            <option value="">— Tümü —</option>
            <?php foreach ($turler as $t): ?>
              <option value="<?= (int) $t['id'] ?>"><?= esc($t['kisa_ad']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grup">
          <label>Durum</label>
          <select name="durum" id="f-durum" class="girdi">
            <option value="">— Tümü —</option>
            <?php foreach ($durumlar as $k => $v): ?>
              <option value="<?= esc($k) ?>"><?= esc($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grup">
          <label>Mali Müşavir</label>
          <select name="musavir_id" id="f-musavir" class="girdi">
            <option value="">— Tümü —</option>
            <?php foreach ($musavirler as $mid => $mad): ?>
              <option value="<?= (int) $mid ?>"><?= esc($mad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="btn-grup mb16">
        <button type="button" class="btn" onclick="beyannameOnizle()">🔍 Kaç Kayıt Etkilenecek?</button>
      </div>

      <div id="onizleme-kutu" class="gizle"></div>

      <div id="silme-alani" class="gizle">
        <div class="bolucu"></div>
        <div class="form-grup mb16">
          <label>Onaylamak için <b>SİL</b> yazın <span class="zorunlu">*</span></label>
          <input type="text" name="onay" id="b-onay" class="girdi" autocomplete="off"
                 placeholder="SİL" style="max-width:180px;font-weight:700">
        </div>
        <button type="submit" class="btn kirmizi" id="b-sil-btn" disabled>
          🗑 Bu Kayıtları Sil
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ============ 2) EVRAK KAYITLARI ============ -->
<div class="kart mb16">
  <div class="kart-baslik">
    <h2>📁 Evrak Kayıtlarını Temizle</h2>
    <span class="kucuk-yazi" style="margin-left:auto">Yıl seçimi zorunludur</span>
  </div>

  <div class="kart-govde">
    <form method="post" action="<?= site_url('sistem/evrak-sil') ?>" id="evrak-form">
      <?= csrf_field() ?>

      <div class="form-grid mb16">
        <div class="form-grup">
          <label>Yıl <span class="zorunlu">*</span></label>
          <select name="evrak_yil" class="girdi" required>
            <option value="">— Seçiniz —</option>
            <?php foreach (yilSecenekleri() as $y): ?>
              <option value="<?= $y ?>"><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-grup">
          <label>Ay</label>
          <select name="evrak_ay" class="girdi">
            <option value="">— Tüm yıl —</option>
            <?php for ($a = 1; $a <= 12; $a++): ?>
              <option value="<?= $a ?>"><?= ayAdi($a) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-grup">
          <label>Onay: <b>SİL</b> yazın <span class="zorunlu">*</span></label>
          <input type="text" name="onay" id="e-onay" class="girdi" autocomplete="off"
                 placeholder="SİL" style="font-weight:700">
        </div>
      </div>

      <button type="submit" class="btn kirmizi" id="e-sil-btn" disabled>
        🗑 Evrak Kayıtlarını Sil
      </button>
    </form>
  </div>
</div>

<!-- ============ 3) MÜKELLEF TOPLU SİLME ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🏢 Mükellefleri Toplu Sil</h2>
  </div>
  <div class="kart-govde">
    <div class="uyari bilgi">
      <span class="ik">ℹ</span>
      <div>
        Mükellef silme işlemi <b>Mükellefler</b> ekranından yapılır:
        listedeki kutucuklardan istediklerinizi seçip <b>“Seçilenleri Sil”</b>
        düğmesine basın.
        <div class="mt8">
          Silinen mükellefler <b>çöp kutusuna</b> gider — beyanname ve evrak kayıtları
          korunur, istediğinizde geri yükleyebilirsiniz. Kalıcı silme yalnızca
          çöp kutusundan yapılır.
        </div>
        <div class="btn-grup mt8">
          <a href="<?= site_url('mukellefler') ?>" class="btn kucuk">🏢 Mükellef Listesi</a>
          <a href="<?= site_url('sistem/cop-kutusu') ?>" class="btn ikincil kucuk">
            🗑️ Çöp Kutusu (<?= (int) $istatistik['cop'] ?>)
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
// ---------- Beyanname önizleme ----------
function beyannameOnizle() {
  var veri = {
    yil:        document.getElementById('f-yil').value,
    tur_id:     document.getElementById('f-tur').value,
    durum:      document.getElementById('f-durum').value,
    musavir_id: document.getElementById('f-musavir').value
  };

  var kutu = document.getElementById('onizleme-kutu');
  var alan = document.getElementById('silme-alani');

  BT.post('<?= site_url('sistem/beyanname-onizle') ?>', veri)
    .then(function (j) {
      if (j.adet === 0) {
        kutu.className = 'uyari bilgi';
        kutu.innerHTML = '<span class="ik">ℹ</span><div>Bu filtreye uyan kayıt bulunamadı.</div>';
        alan.className = 'gizle';
        return;
      }

      var h = '<span class="ik">⚠</span><div>'
            + '<b>' + j.adet.toLocaleString('tr-TR') + ' beyanname kaydı silinecek.</b>';

      var d = j.durum_dagilimi || {};
      var anahtarlar = Object.keys(d);
      if (anahtarlar.length) {
        h += '<div class="mt8">Durum dağılımı: ';
        h += anahtarlar.map(function (k) {
          return '<span class="rozet gri">' + k + ': ' + d[k] + '</span>';
        }).join(' ');
        h += '</div>';
      }

      if (j.ornekler && j.ornekler.length) {
        h += '<div class="mt8 kucuk-yazi"><b>Örnek kayıtlar:</b><ul style="margin-top:4px">';
        j.ornekler.slice(0, 5).forEach(function (o) {
          h += '<li>' + o.mukellef_unvan + ' — ' + o.tur_kisa + ' — ' + o.donem_adi
             + (o.tahakkuk_tutari ? ' <b>(tahakkuk girilmiş!)</b>' : '') + '</li>';
        });
        h += '</ul></div>';
      }

      h += '</div>';
      kutu.className = 'uyari dikkat';
      kutu.innerHTML = h;
      alan.className = '';
      onayKontrol();
    })
    .catch(function (e) {
      kutu.className = 'uyari hata';
      kutu.innerHTML = '<span class="ik">✕</span><div>' + e.message + '</div>';
      alan.className = 'gizle';
    });
}

// Filtre değişince önizlemeyi geçersiz kıl (yanlış sayıya güvenilmesin)
['f-yil', 'f-tur', 'f-durum', 'f-musavir'].forEach(function (id) {
  document.getElementById(id).addEventListener('change', function () {
    document.getElementById('onizleme-kutu').className = 'gizle';
    document.getElementById('silme-alani').className = 'gizle';
  });
});

// ---------- Onay metinleri ----------
function sil_yazildi(deger) {
  return deger.toLocaleUpperCase('tr-TR').replace(/İ/g, 'I').trim() === 'SIL';
}

function onayKontrol() {
  var b = document.getElementById('b-onay');
  document.getElementById('b-sil-btn').disabled = !sil_yazildi(b.value);
}

document.getElementById('b-onay').addEventListener('input', onayKontrol);

document.getElementById('e-onay').addEventListener('input', function () {
  document.getElementById('e-sil-btn').disabled = !sil_yazildi(this.value);
});

// ---------- Son onaylar ----------
document.getElementById('beyanname-form').addEventListener('submit', function (e) {
  if (!confirm('Seçtiğiniz beyanname kayıtları silinecek.\n\nBu işlem geri alınamaz. Devam edilsin mi?')) {
    e.preventDefault();
  }
});

document.getElementById('evrak-form').addEventListener('submit', function (e) {
  if (!confirm('Seçtiğiniz evrak kayıtları silinecek.\n\nBu işlem geri alınamaz. Devam edilsin mi?')) {
    e.preventDefault();
  }
});
</script>
<?= $this->endSection() ?>
