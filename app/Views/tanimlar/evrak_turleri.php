<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="uyari bilgi"><span class="ik">ℹ</span><div>
  Evrak takibinde sadece <b>Geldi / Gelmedi</b> durumu tutulur. Burada takip edilecek evrak kalemlerini tanımlayın.
</div></div>

<div class="kart">
  <div class="kart-baslik">
    <h2>📄 Evrak Türleri</h2>
    <div class="sag"><button class="btn kucuk" onclick="evrakAc()">+ Yeni Evrak Türü</button></div>
  </div>
  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="tablo">
        <thead><tr><th class="orta">Sıra</th><th>Ad</th><th>Kısa Ad</th><th>Durum</th><th class="sag">İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($turler as $t): ?>
          <tr>
            <td class="orta"><?= (int) $t['sira'] ?></td>
            <td class="kalin"><?= esc($t['ad']) ?></td>
            <td><span class="rozet mavi"><?= esc($t['kisa_ad']) ?></span></td>
            <td><span class="rozet <?= $t['aktif'] ? 'yesil' : 'gri' ?>"><?= $t['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
            <td class="sag">
              <button class="btn ikincil mini" onclick='evrakAc(<?= json_encode($t, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Düzenle</button>
              <a href="<?= site_url('tanimlar/evrak-turu-sil/' . $t['id']) ?>" class="btn kirmizi mini" data-onay="Pasife alınsın mı?">Sil</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal-arka" id="evrak-modal">
  <div class="modal">
    <form method="post" action="<?= site_url('tanimlar/evrak-turu-kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="e_id">
      <div class="modal-baslik"><h3 id="e_baslik">Evrak Türü</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button></div>
      <div class="modal-govde">
        <div class="form-grid">
          <div class="form-grup tam"><label>Ad <span class="zorunlu">*</span></label>
            <input type="text" name="ad" id="e_ad" class="girdi" required></div>
          <div class="form-grup"><label>Kısa Ad <span class="zorunlu">*</span></label>
            <input type="text" name="kisa_ad" id="e_kisa" class="girdi" required></div>
          <div class="form-grup"><label>Sıra</label>
            <input type="number" name="sira" id="e_sira" class="girdi" value="0"></div>
          <div class="form-grup"><label>Durum</label>
            <select name="aktif" id="e_aktif"><option value="1">Aktif</option><option value="0">Pasif</option></select></div>
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
function evrakAc(t) {
  t = t || {};
  document.getElementById('e_baslik').textContent = t.id ? 'Evrak Türü Düzenle' : 'Yeni Evrak Türü';
  ['id','ad','kisa_ad','sira','aktif'].forEach(function (a) {
    var idmap = { id:'e_id', ad:'e_ad', kisa_ad:'e_kisa', sira:'e_sira', aktif:'e_aktif' };
    document.getElementById(idmap[a]).value = t[a] !== undefined && t[a] !== null ? t[a] : (a === 'sira' ? 0 : '');
  });
  BT.modalAc('evrak-modal');
}
</script>
<?= $this->endSection() ?>
