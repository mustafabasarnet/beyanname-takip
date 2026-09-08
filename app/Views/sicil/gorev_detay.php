<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div id="sicil-bildirim" style="margin-bottom:10px"></div>

<div class="kart">
  <div class="kart-baslik">
    <h2>📤 <?= esc($gorev['kurum_ad']) ?> Bildirimi</h2>
    <div class="sag">
      <a href="<?= site_url('sicil/detay/' . (int) $degisiklik['id']) ?>" class="btn ikincil mini">← Değişiklik Detayı</a>
      <a href="<?= site_url('sicil/gorevler') ?>" class="btn ikincil mini">Görevlere Dön</a>
    </div>
  </div>
  <div class="kart-govde">
    <div class="bilgi-liste mb16">
      <div class="oge"><div class="et">Mükellef</div>
        <div class="dg"><?= esc($gorev['mukellef_unvan']) ?>
          <span class="kucuk-yazi"> • <?= esc($gorev['vergi_kimlik_no'] ?: $gorev['tc_kimlik_no']) ?></span></div></div>
      <div class="oge"><div class="et">Değişiklik</div>
        <div class="dg"><?= esc($gorev['tur_ad']) ?> · <?= trTarih($gorev['degisiklik_tarihi']) ?></div></div>
      <div class="oge"><div class="et">Son Tarih</div>
        <div class="dg">
          <?php if ($gorev['son_tarih']): ?>
            <?php $kg = kalanGunMetni($gorev['son_tarih'], $gorev['durum']); ?>
            <b><?= trTarih($gorev['son_tarih']) ?></b>
            <span class="rozet <?= $kg['sinif'] ?>" style="margin-left:6px"><?= esc($kg['metin']) ?></span>
          <?php else: ?>—<?php endif; ?>
        </div></div>
      <div class="oge"><div class="et">Durum</div>
        <div class="dg"><span class="rozet <?= $gorev['durum'] === 'TAMAM' ? 'yesil' : ($gorev['durum'] === 'GEREKSIZ' ? 'gri' : 'sari') ?>">
          <?= esc($durumlar[$gorev['durum']] ?? $gorev['durum']) ?></span></div></div>
      <?php if ($gorev['tamamlanma_tarihi']): ?>
        <div class="oge"><div class="et">Tamamlanma</div>
          <div class="dg"><?= date('d.m.Y H:i', strtotime($gorev['tamamlanma_tarihi'])) ?></div></div>
      <?php endif; ?>
      <?php if ($gorev['yapan_adi']): ?>
        <div class="oge"><div class="et">Yapan</div><div class="dg"><?= esc($gorev['yapan_adi']) ?></div></div>
      <?php endif; ?>
      <?php if (! empty($gorev['kaydirma_nedeni'])): ?>
        <div class="oge"><div class="et">Kaydırma</div><div class="dg kucuk-yazi">↷ <?= esc($gorev['kaydirma_nedeni']) ?></div></div>
      <?php endif; ?>
    </div>

    <!-- Yeni / eski bilgi kısa -->
    <div class="form-grid" style="grid-template-columns:1fr 1fr;margin-bottom:14px">
      <div class="form-grup"><label>Eski</label>
        <div class="girdi" style="background:var(--gri-100);min-height:40px"><?= nl2br(esc($gorev['deg_eski'] ?: '—')) ?></div></div>
      <div class="form-grup"><label>Yeni</label>
        <div class="girdi" style="border-color:#059669;background:#f0fdf4;min-height:40px"><?= nl2br(esc($gorev['deg_yeni'] ?: '—')) ?></div></div>
    </div>

    <!-- Durum geçiş düğmeleri -->
    <div class="form-alt" style="justify-content:flex-start">
      <?php $gecerli = $gorev['durum']; ?>
      <?php if ($gecerli === 'BEKLIYOR'): ?>
        <button class="btn kucuk" onclick="durumDegistir('HAZIR')">📝 Hazırla (Hazır)</button>
      <?php elseif ($gecerli === 'HAZIR'): ?>
        <button class="btn kucuk" onclick="durumDegistir('GONDERILDI')">📤 Gönderildi</button>
        <button class="btn ikincil kucuk" onclick="durumDegistir('BEKLIYOR')">← Geri</button>
      <?php elseif ($gecerli === 'GONDERILDI'): ?>
        <button class="btn yesil kucuk" onclick="durumDegistir('TAMAM')">✅ Tamamla</button>
        <button class="btn ikincil kucuk" onclick="durumDegistir('HAZIR')">← Hazır</button>
      <?php elseif ($gecerli === 'TAMAM'): ?>
        <button class="btn ikincil kucuk" onclick="durumDegistir('GONDERILDI')">↩ Geri Aç</button>
      <?php elseif ($gecerli === 'GEREKSIZ'): ?>
        <button class="btn ikincil kucuk" onclick="durumDegistir('BEKLIYOR')">↩ Yeniden Aç</button>
      <?php endif; ?>
      <?php if (! in_array($gecerli, ['TAMAM','GEREKSIZ'], true)): ?>
        <button class="btn kirmizi kucuk" onclick="if(confirm('Gereksiz olarak işaretlensin mi?'))durumDegistir('GEREKSIZ')">🚫 Gereksiz</button>
      <?php endif; ?>
    </div>

    <!-- Not -->
    <div class="form-grid" style="grid-template-columns:1fr auto;margin-top:10px;align-items:end">
      <div class="form-grup"><label>Not</label>
        <textarea id="gorev-not" class="girdi" rows="2"><?= esc($gorev['not_metni'] ?? '') ?></textarea></div>
      <button class="btn kucuk" onclick="notKaydet()">💾 Notu Kaydet</button>
    </div>
  </div>
</div>

<!-- Görev belgeleri -->
<div class="kart" style="margin-top:14px">
  <div class="kart-baslik"><h2>📎 Görev Belgeleri</h2>
    <div class="sag">
      <form id="belge-form" style="display:inline-flex;gap:6px;align-items:center">
        <?= csrf_field() ?>
        <input type="hidden" name="gorev_id" value="<?= (int) $gorev['id'] ?>">
        <input type="file" name="dosya" id="belge-dosya" class="girdi" style="width:auto;padding:5px;font-size:12px">
        <button type="submit" class="btn kucuk">📤 Yükle</button>
      </form>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($belgeler === []): ?>
      <div class="kucuk-yazi" style="padding:10px">Bu göreve henüz belge eklenmemiş.</div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead><tr><th>Dosya</th><th>Boyut</th><th class="sag">İşlem</th></tr></thead>
          <tbody>
          <?php foreach ($belgeler as $b): ?>
            <tr>
              <td><a href="<?= site_url('sicil/belge-indir/' . (int) $b['id']) ?>"><?= esc($b['dosya_adi']) ?></a></td>
              <td class="kucuk-yazi"><?= number_format((int) $b['boyut'] / 1024, 0) ?> KB</td>
              <td class="sag"><button class="sil-btn belge-sil" data-id="<?= (int) $b['id'] ?>">🗑</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var bildirim = document.getElementById('sicil-bildirim');
  window.durumDegistir = function (durum) {
    BT.post('<?= site_url('sicil/gorev-durum') ?>', { id: <?= (int) $gorev['id'] ?>, durum: durum })
      .then(function (j) {
        bildirim.innerHTML = '<div class="uyari basari"><span class="ik">✓</span><div>' + (j.mesaj || '') + '</div></div>';
        setTimeout(function () { location.reload(); }, 500);
      }).catch(function (e) {
        bildirim.innerHTML = '<div class="uyari hata"><span class="ik">✕</span><div>' + e.message + '</div></div>';
      });
  };
  window.notKaydet = function () {
    BT.post('<?= site_url('sicil/gorev-not') ?>', {
      id: <?= (int) $gorev['id'] ?>,
      not: document.getElementById('gorev-not').value
    }).then(function (j) {
      bildirim.innerHTML = '<div class="uyari basari"><span class="ik">✓</span><div>' + (j.mesaj || '') + '</div></div>';
      setTimeout(function () { bildirim.innerHTML = ''; }, 3000);
    }).catch(function (e) {
      bildirim.innerHTML = '<div class="uyari hata"><span class="ik">✕</span><div>' + e.message + '</div></div>';
    });
  };

  var belgeForm = document.getElementById('belge-form');
  if (belgeForm) {
    belgeForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(belgeForm);
      fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
      fetch('<?= site_url('sicil/belge-yukle') ?>', {
        method: 'POST', body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.durum) throw new Error(j.mesaj || 'Yüklenemedi.');
        location.reload();
      }).catch(function (e) {
        bildirim.innerHTML = '<div class="uyari hata"><span class="ik">✕</span><div>' + e.message + '</div></div>';
      });
    });
  }
  document.querySelectorAll('.belge-sil').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm('Belge silinsin mi?')) return;
      BT.post('<?= site_url('sicil/belge-sil') ?>', { id: b.dataset.id })
        .then(function () { location.reload(); }).catch(function (e) { alert(e.message); });
    });
  });
})();
</script>

<?= $this->endSection() ?>
