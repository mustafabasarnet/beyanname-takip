<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div id="sicil-bildirim" style="margin-bottom:10px"></div>

<div class="kart">
  <div class="kart-baslik">
    <h2>🧾 Sicil Değişikliği — <?= esc($turAd) ?></h2>
    <div class="sag">
      <?php if (session('aktif_rol') === 'admin'): ?>
        <a href="<?= site_url('sicil/sil/' . (int) $degisiklik['id']) ?>" class="btn kirmizi mini"
           data-onay="Bu değişiklik silinsin mi? (geçmiş kayıt korunmaz)">🗑 Sil</a>
      <?php endif; ?>
      <a href="<?= site_url('sicil/duzenle/' . (int) $degisiklik['id']) ?>" class="btn ikincil mini">✏️ Düzenle</a>
      <a href="<?= site_url('sicil') ?>" class="btn ikincil mini">← Listeye Dön</a>
    </div>
  </div>
  <div class="kart-govde">
    <div class="bilgi-liste mb16">
      <div class="oge"><div class="et">Mükellef</div>
        <div class="dg"><a href="<?= site_url('mukellefler/detay/' . (int) $degisiklik['mukellef_id']) ?>">
          <?= esc($mukellef['unvan'] ?? '-') ?></a>
          <span class="kucuk-yazi"> • <?= esc(($mukellef['vergi_kimlik_no'] ?? '') ?: ($mukellef['tc_kimlik_no'] ?? '')) ?></span>
        </div></div>
      <div class="oge"><div class="et">Tür</div><div class="dg"><?= esc($turAd) ?></div></div>
      <div class="oge"><div class="et">Değişiklik Tarihi</div><div class="dg kalin"><?= trTarih($degisiklik['degisiklik_tarihi']) ?></div></div>
      <div class="oge"><div class="et">Referans</div><div class="dg"><?= esc($degisiklik['referans_no'] ?: '—') ?></div></div>
    </div>

    <div class="form-grid" style="grid-template-columns:1fr 1fr">
      <div class="form-grup">
        <label>Eski Bilgi</label>
        <div class="girdi" style="background:var(--gri-100,#f1f5f9);min-height:44px;white-space:pre-wrap">
          <?= nl2br(esc($degisiklik['eski_deger'] ?: '—')) ?>
        </div>
      </div>
      <div class="form-grup">
        <label>Yeni Bilgi</label>
        <div class="girdi" style="border-color:#059669;background:#f0fdf4;min-height:44px;white-space:pre-wrap">
          <?= nl2br(esc($degisiklik['yeni_deger'] ?: '—')) ?>
        </div>
      </div>
      <?php if (! empty($degisiklik['aciklama'])): ?>
        <div class="form-grup tam"><label>Açıklama</label>
          <div class="kucuk-yazi" style="white-space:pre-wrap"><?= nl2br(esc($degisiklik['aciklama'])) ?></div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Görevler -->
<div class="kart" style="margin-top:14px">
  <div class="kart-baslik">
    <h2>📤 Bildirim Görevleri (<?= count($gorevler) ?>)</h2>
    <div class="sag"><a href="<?= site_url('sicil/gorevler') ?>" class="btn ikincil kucuk">Tüm Görevler →</a></div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($gorevler === []): ?>
      <div class="tablo-bos"><span class="ikon">📭</span>
        Bu değişiklik için bildirim görevi yok.
        <div class="mt8 kucuk-yazi">Bildirim kuralları tanımlı değilse veya tür için kural yoksa görev üretilmez.</div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead><tr><th>Kurum</th><th>Son Tarih</th><th>Kalan</th><th>Durum</th><th>Yapan</th><th class="sag">İşlem</th></tr></thead>
          <tbody>
          <?php
          $bugun = date('Y-m-d');
          foreach ($gorevler as $g):
            $gecikti = in_array($g['durum'], ['BEKLIYOR','HAZIR','GONDERILDI'], true)
                && $g['son_tarih'] !== null && $g['son_tarih'] < $bugun;
          ?>
            <tr>
              <td><b><?= esc($g['kurum_ad']) ?></b></td>
              <td class="kalin <?= $gecikti ? 'metin-kirmizi' : '' ?>">
                <?= $g['son_tarih'] ? trTarih($g['son_tarih']) : '—' ?>
                <?php if (! empty($g['kaydirma_nedeni'])): ?><span class="kucuk-yazi">↷</span><?php endif; ?>
              </td>
              <td class="kucuk-yazi">
                <?php if (in_array($g['durum'], ['TAMAM','GEREKSIZ'], true)): ?>
                  <span class="rozet <?= $g['durum'] === 'TAMAM' ? 'yesil' : 'gri' ?>">kapalı</span>
                <?php elseif ($g['son_tarih']): ?>
                  <?= kalanGunMetni($g['son_tarih'], $g['durum'])['metin'] ?>
                <?php endif; ?>
              </td>
              <td>
                <select class="girdi ed-durum" data-gorev="<?= (int) $g['id'] ?>" style="padding:4px 8px;font-size:12px">
                  <?php foreach ($durumlar as $dk => $dv): ?>
                    <option value="<?= $dk ?>" <?= $g['durum'] === $dk ? 'selected' : '' ?>><?= esc($dv) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="kucuk-yazi"><?= esc($g['yapan_adi'] ?: '—') ?></td>
              <td class="sag">
                <a href="<?= site_url('sicil/gorev/' . (int) $g['id']) ?>" class="btn ikincil mini">Detay</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Belgeler -->
<div class="kart" style="margin-top:14px">
  <div class="kart-baslik"><h2>📎 Belgeler</h2>
    <div class="sag">
      <form id="belge-form" style="display:inline-flex;gap:6px;align-items:center">
        <?= csrf_field() ?>
        <input type="hidden" name="sicil_degisikligi_id" value="<?= (int) $degisiklik['id'] ?>">
        <input type="file" name="dosya" id="belge-dosya" class="girdi" style="width:auto;padding:5px;font-size:12px">
        <button type="submit" class="btn kucuk">📤 Yükle</button>
      </form>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($belgeler === []): ?>
      <div class="kucuk-yazi" style="padding:10px">Henüz belge eklenmemiş.</div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead><tr><th>Dosya</th><th>Boyut</th><th>Yükleyen</th><th class="sag">İşlem</th></tr></thead>
          <tbody>
          <?php foreach ($belgeler as $b): ?>
            <tr>
              <td><a href="<?= site_url('sicil/belge-indir/' . (int) $b['id']) ?>"><?= esc($b['dosya_adi']) ?></a></td>
              <td class="kucuk-yazi"><?= number_format((int) $b['boyut'] / 1024, 0) ?> KB</td>
              <td class="kucuk-yazi"><?= esc($b['yukleyen_adi'] ?? '—') ?></td>
              <td class="sag">
                <button type="button" class="sil-btn belge-sil" data-id="<?= (int) $b['id'] ?>">🗑</button>
              </td>
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

  function mesaj(m, tip) {
    bildirim.innerHTML = '<div class="uyari ' + (tip === 'hata' ? 'hata' : 'basari') + '"><span class="ik">'
      + (tip === 'hata' ? '✕' : '✓') + '</span><div>' + m + '</div></div>';
    setTimeout(function () { bildirim.innerHTML = ''; }, 5000);
  }

  // Görev durumu değiştir
  document.querySelectorAll('.ed-durum').forEach(function (sel) {
    sel.addEventListener('change', function () {
      BT.post('<?= site_url('sicil/gorev-durum') ?>', {
        id: sel.dataset.gorev,
        durum: sel.value
      }).then(function (j) {
        mesaj(j.mesaj || 'Güncellendi', 'ok');
        // Satırda görünen "kapalı" rozetini yenilemek için sayfa yeter: tamamlanınca hafif yenile
        setTimeout(function () { location.reload(); }, 400);
      }).catch(function (e) { mesaj(e.message, 'hata'); });
    });
  });

  // Belge yükle
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
        mesaj('Belge eklendi.', 'ok');
        setTimeout(function () { location.reload(); }, 500);
      }).catch(function (e) { mesaj(e.message, 'hata'); });
    });
  }

  // Belge sil
  document.querySelectorAll('.belge-sil').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm('Belge silinsin mi?')) return;
      BT.post('<?= site_url('sicil/belge-sil') ?>', { id: b.dataset.id })
        .then(function (j) { mesaj(j.mesaj || 'Silindi', 'ok'); setTimeout(function () { location.reload(); }, 400); })
        .catch(function (e) { mesaj(e.message, 'hata'); });
    });
  });
})();
</script>

<?= $this->endSection() ?>
