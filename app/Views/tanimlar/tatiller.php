<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="uyari bilgi"><span class="ik">ℹ</span><div>
  Beyanname son günü <b>hafta sonu veya buradaki bir tatile</b> denk gelirse,
  otomatik olarak <b>tatil bitimini izleyen ilk iş gününe</b> kaydırılır.
  Tatil eklendikten sonra mevcut kayıtların güncellenmesi için
  <a href="<?= site_url('takip/toplu-uret') ?>"><b>Toplu Dönem Üretimi</b></a> çalıştırın.
</div></div>

<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Yıl</label>
    <select name="yil" data-oto-filtre>
      <?php
      $tumYillar = array_unique(array_merge($yillar, yilSecenekleri(2, 2)));
      rsort($tumYillar);
      foreach ($tumYillar as $y): ?>
        <option value="<?= $y ?>" <?= (int) $y === (int) $yil ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="btn-grup"><button class="btn kucuk" type="button" onclick="tatilAc()">+ Yeni Tatil</button></div>
</form>

<div class="kart">
  <div class="kart-baslik"><h2>🎌 <?= $yil ?> Yılı Resmi Tatilleri (<?= count($tatiller) ?>)</h2></div>
  <div class="kart-govde sikisik">
    <?php if ($tatiller === []): ?>
      <div class="tablo-bos"><span class="ikon">📅</span><?= $yil ?> yılı için tatil tanımlanmamış.</div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead><tr><th>Tarih</th><th>Gün</th><th>Ad</th><th>Tip</th><th>Yarım Gün</th><th>Durum</th><th class="sag">İşlem</th></tr></thead>
          <tbody>
          <?php foreach ($tatiller as $t): ?>
            <tr>
              <td class="kalin"><?= trTarih($t['tarih']) ?></td>
              <td class="kucuk-yazi"><?= explode(' ', trTarihUzun($t['tarih']))[1] ?? '' ?></td>
              <td><?= esc($t['ad']) ?></td>
              <td><span class="rozet <?= $t['tip'] === 'DINI' ? 'mor' : ($t['tip'] === 'ARIFE' ? 'sari' : 'mavi') ?>">
                <?= ['RESMI'=>'Resmi','DINI'=>'Dini Bayram','ARIFE'=>'Arife','MALI_TATIL'=>'Mali Tatil','IDARI_IZIN'=>'İdari İzin'][$t['tip']] ?? $t['tip'] ?>
              </span></td>
              <td><?= $t['yarim_gun'] ? '<span class="rozet sari">Yarım</span>' : '<span class="kucuk-yazi">Tam gün</span>' ?></td>
              <td><span class="rozet <?= $t['aktif'] ? 'yesil' : 'gri' ?>"><?= $t['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
              <td class="sag">
                <button class="btn ikincil mini" onclick='tatilAc(<?= json_encode($t, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Düzenle</button>
                <a href="<?= site_url('tanimlar/tatil-sil/' . $t['id']) ?>" class="btn kirmizi mini" data-onay="Silinsin mi?">Sil</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal-arka" id="tatil-modal">
  <div class="modal">
    <form method="post" action="<?= site_url('tanimlar/tatil-kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="ta_id">
      <div class="modal-baslik"><h3 id="ta_baslik">Tatil Ekle</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button></div>
      <div class="modal-govde">
        <div class="form-grid">
          <div class="form-grup"><label>Tarih <span class="zorunlu">*</span></label>
            <input type="date" name="tarih" id="ta_tarih" class="girdi" required></div>
          <div class="form-grup"><label>Tip</label>
            <select name="tip" id="ta_tip">
              <option value="RESMI">Resmi Tatil</option><option value="DINI">Dini Bayram</option>
              <option value="ARIFE">Arife</option><option value="MALI_TATIL">Mali Tatil</option>
              <option value="IDARI_IZIN">İdari İzin</option>
            </select></div>
          <div class="form-grup tam"><label>Ad <span class="zorunlu">*</span></label>
            <input type="text" name="ad" id="ta_ad" class="girdi" required></div>
          <div class="form-grup"><label class="onay">
            <input type="checkbox" name="yarim_gun" id="ta_yarim" value="1"> Yarım gün (arife)</label></div>
          <div class="form-grup"><label>Durum</label>
            <select name="aktif" id="ta_aktif"><option value="1">Aktif</option><option value="0">Pasif</option></select></div>
        </div>
      </div>
      <div class="modal-alt">
        <button type="button" class="btn ikincil" data-modal-kapat>İptal</button>
        <button type="submit" class="btn">Kaydet</button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('script') ?>
<script>
function tatilAc(t) {
  t = t || {};
  document.getElementById('ta_baslik').textContent = t.id ? 'Tatil Düzenle' : 'Yeni Tatil';
  document.getElementById('ta_id').value    = t.id || '';
  document.getElementById('ta_tarih').value = t.tarih ? String(t.tarih).substr(0, 10) : '';
  document.getElementById('ta_ad').value    = t.ad || '';
  document.getElementById('ta_tip').value   = t.tip || 'RESMI';
  document.getElementById('ta_yarim').checked = Number(t.yarim_gun) === 1;
  document.getElementById('ta_aktif').value = t.aktif !== undefined ? t.aktif : 1;
  BT.modalAc('tatil-modal');
}
</script>
<?= $this->endSection() ?>
