<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
// $yetki controller'dan gelir (bkz. Edefter::index). Savunmacı varsayılan:
$yetki   = $yetki ?? false;
$secMus  = secilenMusavirId($filtre['musavir_id'] ?? null);
$adimSay = count($adimlar);
?>

<style>
/* Stiller görünüme gömüldü: stil.css kopyalanmasa bile çizelge doğru görünür */
.ed-tablo{width:100%;border-collapse:collapse}
.ed-tablo th{font-size:10.5px;text-transform:uppercase;letter-spacing:.3px;color:var(--gri-500,#64748b);
  font-weight:700;padding:8px 8px;border-bottom:1px solid var(--gri-200,#e2e8f0);white-space:nowrap;text-align:left}
.ed-tablo td{padding:8px;border-bottom:1px solid var(--gri-100,#f1f5f9);font-size:13px;vertical-align:middle}
.ed-tablo tbody tr:hover{background:var(--gri-50,#f8fafc)}
.ed-tablo tr.gecikmis-satir{background:#fef2f2}
.ed-tablo tr.bugun-satir{background:#fffbeb}
.ed-tablo tr.ed-pasif{opacity:.55}
.ed-adim-h{text-align:center;width:44px}
th.ed-adim-h{text-align:center}
.ed-adim-basi{display:flex;flex-direction:column;align-items:center;gap:2px;line-height:1.1}
.ed-adim-basi .ik{font-size:14px}
.ed-adim-basi .ad{font-size:9.5px;max-width:56px;white-space:normal;text-align:center}
/* Kontrol kutusu */
.ed-kutu{width:24px;height:24px;border-radius:6px;border:2px solid var(--gri-300,#cbd5e1);
  background:#fff;cursor:pointer;font-size:13px;font-weight:800;color:#fff;line-height:1;
  display:inline-flex;align-items:center;justify-content:center;transition:all .12s}
.ed-kutu:hover:not(:disabled){border-color:var(--ana,#2563eb);transform:scale(1.1)}
.ed-kutu.dolu{background:#059669;border-color:#059669}
.ed-kutu:disabled{cursor:not-allowed;opacity:.5}
.ed-kutu.bekle{opacity:.4}
/* İlerleme */
.ed-ilerleme-h{white-space:nowrap;width:120px}
.ed-cubuk{display:inline-block;width:70px;height:7px;border-radius:99px;background:var(--gri-200,#e2e8f0);
  overflow:hidden;vertical-align:middle}
.ed-cubuk i{display:block;height:100%;background:#059669;border-radius:99px;transition:width .25s}
.ed-yuzde{font-size:11.5px;font-weight:700;color:var(--gri-600,#475569);margin-left:6px}
.ed-durum{padding:3px 6px;font-size:12px;max-width:130px}
.ed-not{cursor:pointer;font-size:12px;color:var(--gri-600,#475569)}
.ed-not.bos{color:var(--gri-300,#cbd5e1)}
.ed-not:hover{color:var(--ana,#2563eb);text-decoration:underline}
.ed-toplu{background:none;border:0;cursor:pointer;font-size:11px;color:var(--ana,#2563eb);font-weight:600}
.ed-kaydir{padding:16px 20px;text-align:center;border-top:1px solid var(--gri-100,#f1f5f9);
  background:var(--gri-50,#f8fafc)}
</style>

<!-- ============ FİLTRE ============ -->
<?php $eMod = $filtre['tarih_modu'] ?? 'berat'; ?>
<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Görünüm</label>
    <select name="mod" data-oto-filtre title="Yıl ve Ay filtresinin neye göre çalışacağı">
      <option value="berat" <?= $eMod === 'berat' ? 'selected' : '' ?>>Berat Tarihi (yükleme)</option>
      <option value="donem" <?= $eMod === 'donem' ? 'selected' : '' ?>>Ait Olduğu Dönem</option>
    </select>
  </div>

  <div class="form-grup">
    <label><?= $eMod === 'donem' ? 'Dönem Yılı' : 'Berat Yılı' ?></label>
    <select name="yil" data-oto-filtre>
      <?php foreach (yilSecenekleri() as $y): ?>
        <option value="<?= $y ?>" <?= (int) $filtre['yil'] === $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-grup">
    <label><?= $eMod === 'donem' ? 'Dönem Ayı' : 'Berat Ayı (son tarih)' ?></label>
    <select name="ay" data-oto-filtre
            title="<?= $eMod === 'donem' ? 'Defterin ait olduğu ay' : 'Beratın yükleneceği ay' ?>">
      <option value="0" <?= $filtre['ay'] === null ? 'selected' : '' ?>>Tüm Aylar</option>
      <?php for ($a = 1; $a <= 12; $a++): ?>
        <option value="<?= $a ?>" <?= (int) $filtre['ay'] === $a ? 'selected' : '' ?>><?= ayAdi($a) ?></option>
      <?php endfor; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Dönem Tipi</label>
    <select name="donem_tipi" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($donemTipleri as $tk => $tv): ?>
        <option value="<?= $tk ?>" <?= ($filtre['donem_tipi'] ?? '') === $tk ? 'selected' : '' ?>><?= esc($tv) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Durum</label>
    <select name="durum" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($durumlar as $dk => $dv): ?>
        <option value="<?= $dk ?>" <?= ($filtre['durum'] ?? '') === $dk ? 'selected' : '' ?>><?= esc($dv) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if ($personeller !== []): ?>
    <div class="form-grup">
      <label>Sorumlu Personel</label>
      <select name="sorumlu_id" data-oto-filtre>
        <option value="">Tümü</option>
        <?php foreach ($personeller as $pid => $pad): ?>
          <option value="<?= $pid ?>" <?= (int) ($filtre['sorumlu_id'] ?? 0) === (int) $pid ? 'selected' : '' ?>>
            <?= esc($pad) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <?php if (count($musavirler) > 1): ?>
    <div class="form-grup">
      <label>Mali Müşavir</label>
      <select name="musavir_id" data-oto-filtre>
        <option value="">Tümü</option>
        <?php foreach ($musavirler as $mid => $mad): ?>
          <option value="<?= $mid ?>" <?= $secMus === (int) $mid ? 'selected' : '' ?>><?= esc($mad) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="form-grup" style="min-width:170px">
    <label>Ara</label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>" placeholder="Mükellef / VKN">
  </div>

  <div class="form-grup">
    <label class="onay" style="margin-top:18px">
      <input type="checkbox" name="gecikmis" value="1" <?= ! empty($filtre['gecikmis']) ? 'checked' : '' ?> data-oto-filtre>
      Sadece gecikmişler
    </label>
  </div>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url('edefter') ?>" class="btn ikincil kucuk">Sıfırla</a>
    <?php
    // Dönem üretimi DÖNEM YILI üzerinden çalışır. Berat görünümünde
    // kullanıcı 2027'yi seçmiş olsa da üretilecek dönemler 2026'ya ait
    // olabilir; bu yüzden hangi yılın üretileceği açıkça yazılır.
    $uretYil = $eMod === 'donem' ? (int) $filtre['yil'] : (int) $filtre['yil'];
    ?>
    <a href="<?= site_url('edefter/toplu-uret?yil=' . $uretYil) ?>"
       class="btn ikincil kucuk"
       title="<?= $uretYil ?> dönem yılına ait e-defter kayıtlarını oluşturur/günceller"
       onclick="return confirm('<?= $uretYil ?> DÖNEM yılı için e-defter kayıtları oluşturulacak/güncellenecek. Devam edilsin mi?')">
      🔄 <?= $uretYil ?> Dönemlerini Üret
    </a>
  </div>
</form>

<?php if (! empty($filtre['ay'])): ?>
  <div class="uyari bilgi" style="padding:9px 14px;font-size:13px">
    <span class="ik">ℹ</span>
    <div>
      <?php if ($eMod === 'berat'): ?>
        <b><?= ayAdi((int) $filtre['ay']) ?> <?= (int) $filtre['yil'] ?></b> içinde
        <b>beratı yüklenecek</b> defterler listeleniyor.
        Bu nedenle önceki yıla ait dönemler de görünebilir
        (örn. Mayıs <?= (int) $filtre['yil'] ?> → <?= (int) $filtre['yil'] - 1 ?> Aralık dönemi).
      <?php else: ?>
        <b><?= (int) $filtre['yil'] ?></b> yılının
        <b><?= ayAdi((int) $filtre['ay']) ?></b> ayında <b>biten</b> dönemler listeleniyor;
        beratları izleyen yıla düşebilir
        (örn. <?= (int) $filtre['yil'] ?> Aralık → 14.05.<?= (int) $filtre['yil'] + 1 ?>).
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ============ ÖZET ============ -->
<div class="stat-grid">
  <div class="stat">
    <div class="etiket">Toplam</div>
    <div class="deger"><?= number_format((int) $ozet['toplam'], 0, ',', '.') ?></div>
    <div class="alt">Filtreye uyan dönem</div>
  </div>
  <div class="stat kirmizi">
    <div class="etiket">Gecikmiş</div>
    <div class="deger"><?= (int) $ozet['gecikmis'] ?></div>
    <div class="alt">Süresi geçti</div>
  </div>
  <div class="stat turuncu">
    <div class="etiket">Devam Ediyor</div>
    <div class="deger"><?= (int) ($ozet['devam'] ?? 0) ?></div>
    <div class="alt">İşlem sürüyor</div>
  </div>
  <div class="stat sari">
    <div class="etiket">Hazır</div>
    <div class="deger"><?= (int) ($ozet['hazir'] ?? 0) ?></div>
    <div class="alt">Berat bekliyor</div>
  </div>
  <div class="stat yesil">
    <div class="etiket">Yüklendi</div>
    <div class="deger"><?= (int) ($ozet['onaylandi'] ?? 0) ?></div>
    <div class="alt">%<?= (int) $ozet['oran'] ?> tamamlandı</div>
  </div>
</div>

<!-- ============ ÇİZELGE ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>📗 E-Defter Berat Takibi</h2>
    <div class="sag" style="display:flex;align-items:center;gap:10px">
      <span class="kucuk-yazi">Adımı işaretlemek için kutuya tıklayın</span>
      <label class="kucuk-yazi" style="display:flex;align-items:center;gap:5px;margin:0">
        Sayfa başına
        <select id="ed-adet" class="girdi" style="padding:3px 6px;font-size:12px;width:auto">
          <?php foreach ($adetSecenek as $s): ?>
            <option value="<?= $s ?>" <?= (int) $sayfaAdedi === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </div>

  <div class="kart-govde sikisik">
    <?php if ($kayitlar === []): ?>
      <div class="tablo-bos">
        <span class="ikon">📗</span>
        E-defter dönemi bulunamadı.<br>
        <span class="kucuk-yazi">
          Mükellef kartından <b>E-Defter Dönemi</b> (Aylık / Üç Aylık) seçin,
          ardından <b>🔄 Dönem Üret</b> düğmesine basın.
        </span>
        <div class="mt16">
          <a href="<?= site_url('edefter/toplu-uret?yil=' . (int) $filtre['yil']) ?>" class="btn kucuk">🔄 Dönem Üret</a>
        </div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="ed-tablo" id="ed-tablo">
          <thead>
            <tr>
              <th>Mükellef</th>
              <th>Dönem</th>
              <th>Son Tarih</th>
              <th>Kalan</th>
              <?php foreach ($adimlar as $a): ?>
                <th class="ed-adim-h" title="<?= esc($a['aciklama'] ?? $a['ad']) ?>">
                  <div class="ed-adim-basi">
                    <span class="ik"><?= $a['ikon'] ?: '•' ?></span>
                    <span class="ad"><?= esc($a['ad']) ?></span>
                  </div>
                </th>
              <?php endforeach; ?>
              <th class="ed-ilerleme-h">İlerleme</th>
              <th>Durum</th>
              <th>Not</th>
            </tr>
          </thead>
          <tbody id="ed-govde">
            <?= $this->include('edefter/_satirlar') ?>
          </tbody>
        </table>
      </div>

      <!-- Sonsuz kaydırma -->
      <div id="ed-kaydir" class="ed-kaydir"
           data-ofset="<?= count($kayitlar) ?>"
           data-toplam="<?= (int) $toplamKayit ?>"
           data-adet="<?= (int) $sayfaAdedi ?>">
        <button type="button" class="btn ikincil" id="ed-daha"
                onclick="edDahaFazla()" <?= empty($dahaVar) ? 'style="display:none"' : '' ?>>
          ↓ Daha Fazla Göster
        </button>
        <div class="kucuk-yazi" style="margin-top:8px">
          <b id="ed-gosterilen"><?= count($kayitlar) ?></b> /
          <b><?= number_format((int) $toplamKayit, 0, ',', '.') ?></b> kayıt
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var CSRF_AD  = <?= json_encode(csrf_token()) ?>;
  var CSRF_DEG = <?= json_encode(csrf_hash()) ?>;

  function govde(u, veri) {
    veri[CSRF_AD] = CSRF_DEG;
    return fetch(u, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams(veri)
    }).then(function (y) { return y.json(); }).then(function (v) {
      // CSRF tek kullanımlık olabilir; yeni değer dönerse saklanır
      if (v.csrf) { CSRF_DEG = v.csrf; }
      return v;
    });
  }

  // Satırın ilerleme çubuğunu ve durumunu tazeler
  function satirTazele(tr, v) {
    var cubuk = tr.querySelector('.ed-cubuk i');
    var yuzde = tr.querySelector('.ed-yuzde');
    var durum = tr.querySelector('.ed-durum');
    if (cubuk) { cubuk.style.width = v.ilerleme + '%'; }
    if (yuzde) { yuzde.textContent = '%' + v.ilerleme; }
    // v.durum yanıtın başarı bayrağıdır; kaydın durumu kayit_durum'dadır.
    if (durum && v.kayit_durum) { durum.value = v.kayit_durum; }

    var bar = tr.querySelector('.ed-cubuk');
    if (bar) { bar.title = v.adim_tamam + ' / ' + v.adim_toplam + ' adım'; }
  }

  // ---- Adım kutusu ----
  document.addEventListener('click', function (e) {
    var kutu = e.target.closest('.ed-kutu');
    if (!kutu || kutu.disabled) { return; }

    var dolu = kutu.classList.contains('dolu');
    kutu.classList.add('bekle');

    govde(<?= json_encode(site_url('edefter/adim')) ?>, {
      takip_id: kutu.dataset.takip,
      adim_id : kutu.dataset.adim,
      tamam   : dolu ? '0' : '1'
    }).then(function (v) {
      kutu.classList.remove('bekle');
      if (!v.durum) { alert(v.mesaj || 'İşlem başarısız.'); return; }

      kutu.classList.toggle('dolu', !dolu);
      kutu.textContent = dolu ? '' : '✓';
      kutu.setAttribute('aria-pressed', dolu ? 'false' : 'true');
      satirTazele(kutu.closest('tr'), v);
    }).catch(function () {
      kutu.classList.remove('bekle');
      alert('Bağlantı hatası.');
    });
  });

  // ---- Durum kutusu ----
  document.addEventListener('change', function (e) {
    var sec = e.target.closest('.ed-durum');
    if (!sec) { return; }

    govde(<?= json_encode(site_url('edefter/durum')) ?>, {
      id: sec.dataset.id, durum: sec.value
    }).then(function (v) {
      if (!v.durum) { alert(v.mesaj || 'Durum değiştirilemedi.'); return; }
      var tr = sec.closest('tr');
      tr.classList.toggle('ed-pasif', v.kayit_durum === 'YUKLENMEYECEK');
      if (v.ilerleme !== undefined) { satirTazele(tr, v); }
    });
  });

  // ---- Not ----
  document.addEventListener('click', function (e) {
    var not = e.target.closest('.ed-not');
    if (!not) { return; }

    var eski = not.classList.contains('bos') ? '' : (not.title || '');
    var yeni = prompt('Not (en fazla 300 karakter):', eski);
    if (yeni === null) { return; }

    govde(<?= json_encode(site_url('edefter/not')) ?>, {
      id: not.dataset.id, not: yeni
    }).then(function (v) {
      if (!v.durum) { alert(v.mesaj || 'Not kaydedilemedi.'); return; }
      var m = (v.not || '').trim();
      not.textContent = m === '' ? '+ not ekle' : (m.length > 18 ? m.slice(0, 18) + '…' : m);
      not.title = m === '' ? 'Not eklemek için tıklayın' : m;
      not.classList.toggle('bos', m === '');
    });
  });

  // ---- Sayfa adedi ----
  var adet = document.getElementById('ed-adet');
  if (adet) {
    adet.addEventListener('change', function () {
      var u = new URL(location.href);
      u.searchParams.set('adet', adet.value);
      location.href = u.toString();
    });
  }

  // ---- Sonsuz kaydırma ----
  var yukleniyor = false;

  window.edDahaFazla = function () {
    var alan = document.getElementById('ed-kaydir');
    if (!alan || yukleniyor) { return; }

    var ofset  = parseInt(alan.dataset.ofset, 10) || 0;
    var toplam = parseInt(alan.dataset.toplam, 10) || 0;
    if (ofset >= toplam) { return; }

    yukleniyor = true;
    var dugme = document.getElementById('ed-daha');
    if (dugme) { dugme.textContent = 'Yükleniyor…'; }

    var u = new URL(<?= json_encode(site_url('edefter/daha-fazla')) ?>, location.origin);
    new URLSearchParams(location.search).forEach(function (d, a) { u.searchParams.set(a, d); });
    u.searchParams.set('ofset', ofset);

    fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (y) { return y.json(); })
      .then(function (v) {
        yukleniyor = false;
        if (dugme) { dugme.textContent = '↓ Daha Fazla Göster'; }
        if (!v.durum) { return; }

        document.getElementById('ed-govde').insertAdjacentHTML('beforeend', v.html);
        alan.dataset.ofset = v.ofset;
        document.getElementById('ed-gosterilen').textContent = v.ofset;
        if (!v.dahaVar && dugme) { dugme.style.display = 'none'; }
      })
      .catch(function () {
        yukleniyor = false;
        if (dugme) { dugme.textContent = '↓ Daha Fazla Göster'; }
      });
  };

  window.addEventListener('scroll', function () {
    var alan = document.getElementById('ed-kaydir');
    if (!alan) { return; }
    var kutu = alan.getBoundingClientRect();
    if (kutu.top < window.innerHeight + 250) { window.edDahaFazla(); }
  });
})();
</script>

<?= $this->endSection() ?>
