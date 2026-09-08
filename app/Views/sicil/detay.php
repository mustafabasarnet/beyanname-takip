<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
$deg     = $degisiklik;              // detay satırı (+ todolar)
$todolar = $deg['todolar'] ?? [];
$toplam  = count($todolar);
$tamam   = 0;
$acik    = 0;

foreach ($todolar as $t) {
    if ($t['durum'] === 'TAMAM') { $tamam++; }
    elseif (in_array($t['durum'], ['BEKLIYOR', 'HAZIR', 'GONDERILDI'], true)) { $acik++; }
}
$kalan = $toplam - $tamam - $acik;   // takip dışı (gereksiz)
$oran  = $toplam > 0 ? (int) round($tamam / $toplam * 100) : 0;
$degDurumRozet = match ($deg['durum']) {
    'TAMAM'   => 'yesil',
    'ISLEMDE' => 'sari',
    default   => 'gri',
};
$rol = $aktifKullanici['rol'] ?? 'personel';
?>

<style>
.todo-satir{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-bottom:1px solid var(--gri-100,#f1f5f9);
  transition:background .2s}
.todo-satir:last-child{border-bottom:none}
.todo-satir.arka-kirmizi{background:var(--kirmizi-acik,#fee2e2)}
.todo-satir.arka-turuncu{background:var(--turuncu-acik,#ffedd5)}
.todo-satir.arka-sari{background:var(--sari-acik,#fef9c3)}
.todo-satir.arka-yesil{background:var(--yesil-acik,#d1fae5)}
.todo-satir.arka-gri{background:var(--gri-100,#f1f5f9)}
.todo-kutu{margin-top:2px;flex:0 0 auto}
.todo-kutu input{width:20px;height:20px;accent-color:#059669;cursor:pointer}
.todo-ad{font-size:14px;font-weight:600;color:var(--gri-900)}
.todo-ad.yapildi{color:#047857;text-decoration:line-through}
.todo-ayrinti{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:3px;font-size:12px;color:var(--gri-500)}
.todo-islem{margin-left:auto;display:flex;gap:6px;align-items:center;flex:0 0 auto}
</style>

<div id="sicil-bildirim" style="margin-bottom:10px"></div>

<!-- ÖZET -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🧾 <?= esc($deg['tur_ad']) ?></h2>
    <div class="sag">
      <?php if ($rol === 'admin'): ?>
        <a href="<?= site_url('sicil/sil/' . (int) $deg['id']) ?>" class="btn kirmizi mini"
           data-onay="Bu işlem ve todo geçmişi listeden kaldırılsın mı?">🗑 Sil</a>
      <?php endif; ?>
      <a href="<?= site_url('sicil/duzenle/' . (int) $deg['id']) ?>" class="btn ikincil mini">✏️ Düzenle</a>
      <a href="<?= site_url('sicil') ?>" class="btn ikincil mini">← Listeye Dön</a>
    </div>
  </div>
  <div class="kart-govde">
    <div class="bilgi-liste mb16">
      <div class="oge"><div class="et">Mükellef</div>
        <div class="dg">
          <a href="<?= site_url('mukellefler/detay/' . (int) $deg['mukellef_id']) ?>"><?= esc($deg['mukellef_unvan']) ?></a>
          <span class="kucuk-yazi"> • <?= esc(($deg['vergi_kimlik_no'] ?: $deg['tc_kimlik_no']) ?: '') ?></span>
        </div></div>
      <div class="oge"><div class="et">Şablon</div>
        <div class="dg"><span class="rozet mavi"><?= esc($deg['tur_ad']) ?></span></div></div>
      <div class="oge"><div class="et">İşlem Tarihi</div><div class="dg kalin"><?= trTarih($deg['degisiklik_tarihi']) ?></div></div>
      <div class="oge"><div class="et">Durum</div>
        <div class="dg"><span class="rozet <?= $degDurumRozet ?>" id="deg-durum-rozet"><?= esc(\App\Models\SicilDegisiklikModel::DURUMLAR[$deg['durum']] ?? $deg['durum']) ?></span></div></div>
      <div class="oge"><div class="et">Ekleyen</div><div class="dg"><?= esc($deg['kaydeden_adi'] ?: '—') ?></div></div>
    </div>

    <?php if (! empty($deg['aciklama'])): ?>
      <div class="uyari bilgi" style="margin-bottom:12px"><span class="ik">📌</span>
        <div style="white-space:pre-wrap"><?= nl2br(esc($deg['aciklama'])) ?></div></div>
    <?php endif; ?>

    <!-- İlerleme -->
    <div>
      <div class="satir arali" style="justify-content:space-between">
        <b id="ilerleme-metin"><?= $toplam > 0 ? $tamam . '/' . $toplam . ' tamamlandı' : 'Todo tanımlı değil' ?></b>
        <span class="kucuk-yazi" id="ilerleme-alt">
          <?php if ($toplam > 0): ?><?= $acik ?> açık<?php if ($kalan > 0): ?> · <?= $kalan ?> takip dışı<?php endif; ?><?php endif; ?>
        </span>
      </div>
      <div class="progress">
        <div class="dolu" id="ilerleme-bar" style="width:<?= $oran ?>%"></div>
      </div>
    </div>
  </div>
</div>

<!-- TODO LİSTESİ -->
<div class="kart" style="margin-top:14px">
  <div class="kart-baslik">
    <h2>📋 Todo Listesi (<?= $toplam ?>)</h2>
    <div class="sag kucuk-yazi">
      <span class="rozet yesil">☑ Yapıldı</span>
      <span class="rozet sari">⚠ Yaklaşıyor</span>
      <span class="rozet kirmizi">✖ Geçti</span>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($todolar === []): ?>
      <div class="tablo-bos"><span class="ikon">📭</span>
        Bu işlem için todo üretilmedi.
        <div class="mt8 kucuk-yazi">Şablonun aktif todo tanımı yok veya kayıt eski sürümle açılmış.</div>
      </div>
    <?php else: ?>
      <?php foreach ($todolar as $t): ?>
        <?php
        $etik = todoKalanEtiketi($t['son_tarih'], (string) $t['durum']);
        $tamMi = $t['durum'] === 'TAMAM';
        $gereksizMi = $t['durum'] === 'GEREKSIZ';
        ?>
        <div class="todo-satir arka-<?= $etik['arka'] ?>" data-id="<?= (int) $t['id'] ?>" id="todo-<?= (int) $t['id'] ?>">
          <div class="todo-kutu">
            <input type="checkbox" class="todo-cb" data-id="<?= (int) $t['id'] ?>"
                   <?= $tamMi ? 'checked' : '' ?> title="<?= $tamMi ? 'Geri aç' : 'Tamamla' ?>">
          </div>
          <div style="flex:1;min-width:0">
            <div class="todo-ad <?= $tamMi ? 'yapildi' : '' ?>" data-rol="ad"><?= esc($t['ad']) ?></div>
            <div class="todo-ayrinti">
              <span>Son Tarih: <b><?= $t['son_tarih'] ? trTarih($t['son_tarih']) : '—' ?></b></span>
              <?php if (! empty($t['kaydirma_nedeni']) && ! $tamMi): ?><span class="kucuk-yazi" title="Tatil kaydırması">↷</span><?php endif; ?>
              <span class="rozet <?= $etik['sinif'] ?: 'gri' ?>" data-rol="etik"><?= esc($etik['metin']) ?></span>
              <?php if ($tamMi): ?>
                <span class="kucuk-yazi" data-rol="yapan">
                  ✓ <?= esc($t['yapan_adi'] ?: '—') ?><?= $t['tamamlanma_tarihi'] ? ' · ' . date('d.m.Y H:i', strtotime($t['tamamlanma_tarihi'])) : '' ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="todo-islem">
            <?php if ($tamMi || $gereksizMi): ?>
              <button type="button" class="btn ikincil mini" data-rol="ac"
                      data-islem="BEKLIYOR" title="Todo'yu geri aç">↩ Geri al</button>
            <?php else: ?>
              <button type="button" class="btn ikincil mini" data-rol="ac"
                      data-islem="GEREKSIZ" title="Takip dışı bırak (geçmiş korunur)">⊘ Takip dışı</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var toplam = <?= $toplam ?>;
  var kapi = document.getElementById('ilerleme-bar');

  function rozetSinif(d) {
    return d === 'TAMAM' ? 'yesil' : (d === 'ISLEMDE' ? 'sari' : 'gri');
  }

  function satirGuncelle(satir, j) {
    var etik = j.etik || { arka: '', sinif: '', metin: '' };
    var tamMi = j.yeni_durum === 'TAMAM';
    var gereksizMi = j.yeni_durum === 'GEREKSIZ';

    // zemin
    satir.className = 'todo-satir arka-' + (etik.arka || '');
    // checkbox
    var cb = satir.querySelector('.todo-cb');
    cb.checked = tamMi;
    // ad (tamamda çizili)
    var ad = satir.querySelector('[data-rol="ad"]');
    ad.classList.toggle('yapildi', tamMi);
    // etiket
    var et = satir.querySelector('[data-rol="etik"]');
    et.className = 'rozet ' + (etik.sinif || 'gri');
    et.textContent = etik.metin;
    // yapan
    var yapan = satir.querySelector('[data-rol="yapan"]');
    if (tamMi) {
      if (!yapan) {
        yapan = document.createElement('span');
        yapan.className = 'kucuk-yazi';
        yapan.setAttribute('data-rol', 'yapan');
        satir.querySelector('.todo-ayrinti').appendChild(yapan);
      }
      yapan.textContent = '✓ ' + (j.yapan_adi || '—') + (j.tamamlanma_tarihi ? ' · ' + j.tamamlanma_tarihi : '');
    } else if (yapan) {
      yapan.remove();
    }
    // işlem butonu
    var btnKap = satir.querySelector('.todo-islem');
    btnKap.innerHTML = '';
    if (tamMi || gereksizMi) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'btn ikincil mini'; b.setAttribute('data-rol', 'ac');
      b.setAttribute('data-islem', 'BEKLIYOR'); b.title = "Todo'yu geri aç";
      b.textContent = '↩ Geri al';
      b.addEventListener('click', function () { istekGonder(satir, 'BEKLIYOR'); });
      btnKap.appendChild(b);
    } else {
      var b2 = document.createElement('button');
      b2.type = 'button'; b2.className = 'btn ikincil mini'; b2.setAttribute('data-rol', 'ac');
      b2.setAttribute('data-islem', 'GEREKSIZ'); b2.title = 'Takip dışı bırak';
      b2.textContent = '⊘ Takip dışı';
      b2.addEventListener('click', function () { istekGonder(satir, 'GEREKSIZ'); });
      btnKap.appendChild(b2);
    }
    // işlem ilerlemesi + üst durum
    var tm = parseInt(j.tamam || 0, 10);
    var tp = parseInt(j.toplam || toplam, 10);
    document.getElementById('ilerleme-metin').textContent = tp > 0 ? tm + '/' + tp + ' tamamlandı' : 'Todo tanımlı değil';
    if (kapi) { kapi.style.width = tp > 0 ? Math.round(tm / tp * 100) + '%' : '0%'; }
    var rozet = document.getElementById('deg-durum-rozet');
    rozet.className = 'rozet ' + rozetSinif(j.deg_durum);
    rozet.textContent = j.deg_durum_metin || j.deg_durum;
    BT.bildir(j.mesaj || 'Durum güncellendi.', j.yeni_durum === 'TAMAM' ? 'basari' : 'bilgi');
  }

  function istekGonder(satir, durum) {
    var id = satir.getAttribute('data-id');
    var btnler = satir.querySelectorAll('button'); btnler.forEach(function (b) { b.disabled = true; });
    BT.post('<?= site_url('sicil/todo-durum') ?>', { id: id, durum: durum })
      .then(function (j) {
        if (!j.durum) throw new Error(j.mesaj || 'Güncellenemedi.');
        satirGuncelle(satir, j);
      })
      .catch(function (e) {
        btnler.forEach(function (b) { b.disabled = false; });
        BT.bildir(e.message, 'hata');
      });
  }

  // Checkbox değişimi
  document.querySelectorAll('.todo-cb').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var satir = document.getElementById('todo-' + cb.getAttribute('data-id'));
      istekGonder(satir, cb.checked ? 'TAMAM' : 'BEKLIYOR');
    });
  });

  // "Geri al / Takip dışı" butonları
  document.querySelectorAll('[data-rol="ac"]').forEach(function (b) {
    b.addEventListener('click', function () {
      var satir = b.closest('.todo-satir');
      istekGonder(satir, b.getAttribute('data-islem'));
    });
  });
})();
</script>

<?= $this->endSection() ?>
