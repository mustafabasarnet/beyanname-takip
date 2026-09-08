<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="kart">
  <div class="kart-baslik">
    <h2>🏛️ Bildirim Kurumları</h2>
    <div class="sag">
      <?php if (session('rol') === 'admin'): ?>
        <button class="btn kucuk" onclick="kurumAc()">+ Yeni Kurum</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="tablo">
        <thead><tr><th>Ad</th><th>Kod</th><th>Kısa</th><th>Açıklama</th><th>Durum</th><th class="sag">İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($kurumlar as $k): ?>
          <tr>
            <td class="kalin"><?= esc($k['ad']) ?></td>
            <td><code><?= esc($k['kod']) ?></code></td>
            <td><?= esc($k['kisa_ad'] ?? '') ?></td>
            <td class="kucuk-yazi"><?= esc($k['aciklama'] ?? '') ?></td>
            <td><span class="rozet <?= $k['aktif'] ? 'yesil' : 'gri' ?>"><?= $k['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
            <td class="sag">
              <?php if (session('rol') === 'admin'): ?>
                <button class="btn ikincil mini" onclick='kurumAc(<?= json_encode($k, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Düzenle</button>
                <a href="<?= site_url('sicil-tanim/kurum-pasif/' . (int) $k['id']) ?>" class="btn kirmizi mini"
                   data-onay="<?= $k['aktif'] ? 'Pasife alınsın mı?' : 'Aktifleştirilsin mi?' ?>">
                  <?= $k['aktif'] ? 'Pasife Al' : 'Aktifleştir' ?></a>
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
<div class="modal-arka" id="kurum-modal">
  <div class="modal">
    <form method="post" action="<?= site_url('sicil-tanim/kurum-kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="k_id">
      <div class="modal-baslik"><h3 id="k-baslik">Yeni Kurum</h3>
        <button type="button" class="modal-kapat" onclick="BT.modalKapat('kurum-modal')">✕</button></div>
      <div class="modal-govde">
        <div class="form-grid">
          <div class="form-grup"><label>Ad *</label><input type="text" name="ad" id="k_ad" class="girdi" required></div>
          <div class="form-grup"><label>Kod *</label><input type="text" name="kod" id="k_kod" class="girdi" required></div>
          <div class="form-grup"><label>Kısa Ad</label><input type="text" name="kisa_ad" id="k_kisa" class="girdi"></div>
          <div class="form-grup"><label class="onay"><input type="checkbox" name="aktif" id="k_aktif" value="1" checked>
            <span>Aktif</span></label></div>
          <div class="form-grup tam"><label>Açıklama</label>
            <textarea name="aciklama" id="k_aciklama" class="girdi" rows="2"></textarea></div>
        </div>
      </div>
      <div class="modal-alt"><button type="submit" class="btn">💾 Kaydet</button>
        <button type="button" class="btn ikincil" onclick="BT.modalKapat('kurum-modal')">İptal</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function kurumAc(k) {
  k = k || {};
  document.getElementById('k_id').value = k.id || '';
  document.getElementById('k_ad').value = k.ad || '';
  document.getElementById('k_kod').value = k.kod || '';
  document.getElementById('k_kisa').value = k.kisa_ad || '';
  document.getElementById('k_aciklama').value = k.aciklama || '';
  document.getElementById('k_aktif').checked = k.aktif !== 0;
  document.getElementById('k-baslik').textContent = k.id ? 'Düzenle: ' + (k.ad || '') : 'Yeni Kurum';
  document.getElementById('k_kod').disabled = !!k.id;
  BT.modalAc('kurum-modal');
}
</script>

<?= $this->endSection() ?>
