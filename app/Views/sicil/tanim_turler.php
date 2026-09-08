<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="kart">
  <div class="kart-baslik">
    <h2>🧩 Değişiklik Türleri</h2>
    <div class="sag">
      <?php if (session('rol') === 'admin'): ?>
        <button class="btn kucuk" onclick="turAc()">+ Yeni Tür</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="tablo">
        <thead><tr><th class="orta">Sıra</th><th>Ad</th><th>Kod</th><th>Açıklama</th><th>Durum</th><th class="sag">İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($turler as $t): ?>
          <tr>
            <td class="orta"><?= (int) $t['sira'] ?></td>
            <td class="kalin"><?= esc($t['ad']) ?></td>
            <td><code><?= esc($t['kod']) ?></code></td>
            <td class="kucuk-yazi"><?= esc($t['aciklama'] ?? '') ?></td>
            <td><span class="rozet <?= $t['aktif'] ? 'yesil' : 'gri' ?>"><?= $t['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
            <td class="sag">
              <?php if (session('rol') === 'admin'): ?>
                <button class="btn ikincil mini" onclick='turAc(<?= json_encode($t, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Düzenle</button>
                <a href="<?= site_url('sicil-tanim/tur-pasif/' . (int) $t['id']) ?>" class="btn kirmizi mini"
                   data-onay="<?= $t['aktif'] ? 'Pasife alınsın mı? (geçmiş korunur)' : 'Aktifleştirilsin mi?' ?>">
                  <?= $t['aktif'] ? 'Pasife Al' : 'Aktifleştir' ?></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if (session('rol') === 'admin'): ?>
<div class="modal-arka" id="tur-modal">
  <div class="modal">
    <form method="post" action="<?= site_url('sicil-tanim/tur-kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="t_id">
      <div class="modal-baslik"><h3 id="t-baslik">Yeni Tür</h3>
        <button type="button" class="modal-kapat" onclick="BT.modalKapat('tur-modal')">✕</button></div>
      <div class="modal-govde">
        <div class="form-grid">
          <div class="form-grup"><label>Ad *</label><input type="text" name="ad" id="t_ad" class="girdi" required></div>
          <div class="form-grup"><label>Kod *</label><input type="text" name="kod" id="t_kod" class="girdi" required
            placeholder="ORNEK_KOD"></div>
          <div class="form-grup"><label>Sıra</label><input type="number" name="sira" id="t_sira" class="girdi" value="0"></div>
          <div class="form-grup"><label class="onay"><input type="checkbox" name="aktif" id="t_aktif" value="1" checked>
            <span>Aktif</span></label></div>
          <div class="form-grup tam"><label>Açıklama</label>
            <textarea name="aciklama" id="t_aciklama" class="girdi" rows="2"></textarea></div>
        </div>
      </div>
      <div class="modal-alt"><button type="submit" class="btn">💾 Kaydet</button>
        <button type="button" class="btn ikincil" onclick="BT.modalKapat('tur-modal')">İptal</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function turAc(t) {
  t = t || {};
  document.getElementById('t_id').value = t.id || '';
  document.getElementById('t_ad').value = t.ad || '';
  document.getElementById('t_kod').value = t.kod || '';
  document.getElementById('t_sira').value = t.sira || 0;
  document.getElementById('t_aciklama').value = t.aciklama || '';
  document.getElementById('t_aktif').checked = t.aktif !== 0;
  document.getElementById('t-baslik').textContent = t.id ? 'Düzenle: ' + (t.ad || '') : 'Yeni Tür';
  document.getElementById('t_kod').disabled = !!t.id; // kod değişmez
  BT.modalAc('tur-modal');
}
</script>

<?= $this->endSection() ?>
