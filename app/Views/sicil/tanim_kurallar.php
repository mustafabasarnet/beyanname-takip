<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="uyari bilgi"><span class="ik">ℹ</span><div>
  Bir değişiklik türü kaydedildiğinde buradaki <b>aktif kurallara</b> göre bildirim görevleri otomatik oluşur.
  Süreler yalnızca burada tutulur — kodda sabit yoktur.
</div></div>

<div class="kart">
  <div class="kart-baslik">
    <h2>⚙️ Bildirim Kuralları</h2>
    <div class="sag">
      <?php if (session('rol') === 'admin'): ?>
        <button class="btn kucuk" onclick="kuralAc()">+ Yeni Kural</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="tablo">
        <thead><tr><th>Değişiklik Türü</th><th>Kurum</th><th>Süre</th><th>Öncelik</th><th>Durum</th><th class="sag">İşlem</th></tr></thead>
        <tbody>
        <?php
        $tipEtiket = [
          'GUN' => 'gün', 'IS_GUNU' => 'iş günü', 'TAKVIM_GUNU' => 'takvim günü',
          'AY' => 'ay', 'BELIRLI_TARIH' => 'belirli tarih',
        ];
        foreach ($kurallar as $kr):
          if ($kr['sure_tipi'] === 'BELIRLI_TARIH') {
              $sureMetin = 'Belirli: ' . trTarih($kr['belirli_tarih']);
          } elseif ($kr['sure_tipi'] === 'AY') {
              $sureMetin = $kr['sure_deger'] . ' ay';
          } else {
              $sureMetin = $kr['sure_deger'] . ' ' . ($tipEtiket[$kr['sure_tipi']] ?? $kr['sure_tipi']);
          }
        ?>
          <tr>
            <td class="kalin"><?= esc($kr['tur_ad']) ?></td>
            <td><span class="rozet mavi"><?= esc($kr['kurum_ad']) ?></span></td>
            <td class="kucuk-yazi"><?= esc($sureMetin) ?></td>
            <td class="orta"><?= (int) $kr['oncelik'] ?></td>
            <td><span class="rozet <?= $kr['aktif'] ? 'yesil' : 'gri' ?>"><?= $kr['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
            <td class="sag">
              <?php if (session('rol') === 'admin'): ?>
                <button class="btn ikincil mini" onclick='kuralAc(<?= json_encode($kr, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Düzenle</button>
                <a href="<?= site_url('sicil-tanim/kural-pasif/' . (int) $kr['id']) ?>" class="btn kirmizi mini"
                   data-onay="<?= $kr['aktif'] ? 'Pasife alınsın mı? (geçmiş görevler korunur)' : 'Aktifleştirilsin mi?' ?>">
                  <?= $kr['aktif'] ? 'Pasife Al' : 'Aktifleştir' ?></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($kurallar === []): ?>
          <tr><td colspan="6" class="orta kucuk-yazi" style="padding:14px">Henüz kural tanımlı değil.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if (session('rol') === 'admin'): ?>
<div class="modal-arka" id="kural-modal">
  <div class="modal">
    <form method="post" action="<?= site_url('sicil-tanim/kural-kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="kr_id">
      <div class="modal-baslik"><h3 id="kr-baslik">Yeni Kural</h3>
        <button type="button" class="modal-kapat" onclick="BT.modalKapat('kural-modal')">✕</button></div>
      <div class="modal-govde">
        <div class="form-grid">
          <div class="form-grup"><label>Değişiklik Türü *</label>
            <select name="degisiklik_turu_id" id="kr_tur" class="girdi" required>
              <option value="">— Seçin —</option>
              <?php foreach ($turler as $tid => $tad): ?>
                <option value="<?= $tid ?>"><?= esc($tad) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="form-grup"><label>Kurum *</label>
            <select name="kurum_id" id="kr_kurum" class="girdi" required>
              <option value="">— Seçin —</option>
              <?php foreach ($kurumlar as $kid => $kad): ?>
                <option value="<?= $kid ?>"><?= esc($kad) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="form-grup"><label>Süre Tipi *</label>
            <select name="sure_tipi" id="kr_tip" class="girdi">
              <option value="GUN">Gün</option>
              <option value="IS_GUNU">İş Günü</option>
              <option value="TAKVIM_GUNU">Takvim Günü</option>
              <option value="AY">Ay</option>
              <option value="BELIRLI_TARIH">Belirli Tarih</option>
            </select></div>
          <div class="form-grup" id="kr-deger-kutu"><label id="kr-deger-label">Değer *</label>
            <input type="number" name="sure_deger" id="kr_deger" class="girdi" min="1"></div>
          <div class="form-grup" id="kr-tarih-kutu" style="display:none"><label>Belirli Tarih *</label>
            <input type="date" name="belirli_tarih" id="kr_tarih" class="girdi"></div>
          <div class="form-grup"><label>Öncelik</label>
            <input type="number" name="oncelik" id="kr_oncelik" class="girdi" value="0"></div>
          <div class="form-grup"><label class="onay"><input type="checkbox" name="aktif" id="kr_aktif" value="1" checked>
            <span>Aktif</span></label></div>
          <div class="form-grup tam"><label>Açıklama</label>
            <textarea name="aciklama" id="kr_aciklama" class="girdi" rows="2"></textarea></div>
        </div>
      </div>
      <div class="modal-alt"><button type="submit" class="btn">💾 Kaydet</button>
        <button type="button" class="btn ikincil" onclick="BT.modalKapat('kural-modal')">İptal</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function kuralTipDegis() {
  var tip = document.getElementById('kr_tip').value;
  var tarihKutu = document.getElementById('kr-tarih-kutu');
  var degerKutu = document.getElementById('kr-deger-kutu');
  if (tip === 'BELIRLI_TARIH') {
    tarihKutu.style.display = '';
    degerKutu.style.display = 'none';
  } else {
    tarihKutu.style.display = 'none';
    degerKutu.style.display = '';
    var etiket = { 'GUN': 'Gün', 'IS_GUNU': 'İş Günü', 'TAKVIM_GUNU': 'Takvim Günü', 'AY': 'Ay' }[tip] || 'Değer';
    document.getElementById('kr-deger-label').textContent = etiket + ' *';
  }
}
document.getElementById('kr_tip').addEventListener('change', kuralTipDegis);

function kuralAc(kr) {
  kr = kr || {};
  document.getElementById('kr_id').value = kr.id || '';
  document.getElementById('kr_tur').value = kr.degisiklik_turu_id || '';
  document.getElementById('kr_kurum').value = kr.kurum_id || '';
  document.getElementById('kr_tip').value = kr.sure_tipi || 'GUN';
  document.getElementById('kr_deger').value = kr.sure_deger || '';
  document.getElementById('kr_tarih').value = kr.belirli_tarih || '';
  document.getElementById('kr_oncelik').value = kr.oncelik || 0;
  document.getElementById('kr_aciklama').value = kr.aciklama || '';
  document.getElementById('kr_aktif').checked = kr.aktif !== 0;
  document.getElementById('kr-baslik').textContent = kr.id ? 'Kural Düzenle' : 'Yeni Kural';
  kuralTipDegis();
  BT.modalAc('kural-modal');
}
</script>

<?= $this->endSection() ?>
