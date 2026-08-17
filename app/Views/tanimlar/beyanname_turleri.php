<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="uyari bilgi"><span class="ik">ℹ</span><div>
  <b>Son tarih formülü:</b> Dönem bitiş ayı + <i>Offset Ay</i> → o ayın <i>Son Gün</i>'ü (veya ay sonu).
  Örn. KDV1 Mart dönemi: 31.03 + 1 ay → 28 Nisan. Tatil/hafta sonuna denk gelirse ilk iş gününe kaydırılır.<br>
  <b>Çelişen Kodlar:</b> Bu tür seçildiğinde pasifleşecek türlerin kodları (virgülle). Örn. YILLIK_GV için <code>KURUMLAR,KURUM_GECICI</code>.
</div></div>

<div class="kart">
  <div class="kart-baslik">
    <h2>🗂️ Beyanname Türleri</h2>
    <div class="sag"><button class="btn kucuk" onclick="turAc()">+ Yeni Tür</button></div>
  </div>
  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="tablo">
        <thead><tr><th>Kod</th><th>Ad</th><th>Periyot</th><th class="orta">Offset</th><th class="orta">Son Gün</th>
          <th>Kimler</th><th>Çelişen</th><th>Durum</th><th class="sag">İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($turler as $t): ?>
          <tr>
            <td><span class="tur-rozet" style="background:<?= esc($t['renk']) ?>"><?= esc($t['kod']) ?></span></td>
            <td><b><?= esc($t['ad']) ?></b><div class="kucuk-yazi"><?= esc($t['aciklama']) ?></div></td>
            <td><?= periyotAdi($t['periyot']) ?></td>
            <td class="orta">+<?= (int) $t['son_gun_offset_ay'] ?> ay</td>
            <td class="orta"><?= $t['son_gun_tipi'] === 'AY_SONU' ? 'Ay Sonu' : (int) $t['son_gun'] . '.' ?></td>
            <td class="kucuk-yazi"><?= ['hepsi'=>'Hepsi','gercek'=>'Şahıs','tuzel'=>'Kurum'][$t['mukellef_tipi']] ?></td>
            <td class="kucuk-yazi"><?= esc($t['celisen_kodlar'] ?: '-') ?></td>
            <td><span class="rozet <?= $t['aktif'] ? 'yesil' : 'gri' ?>"><?= $t['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
            <td class="sag" style="white-space:nowrap">
              <button class="btn ikincil mini" onclick='turAc(<?= json_encode($t, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Düzenle</button>
              <a href="<?= site_url('tanimlar/beyanname-turu-sil/' . $t['id']) ?>" class="btn kirmizi mini" data-onay="Silinsin/pasife alınsın mı?">Sil</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal-arka" id="tur-modal">
  <div class="modal genis">
    <form method="post" action="<?= site_url('tanimlar/beyanname-turu-kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="t_id">
      <div class="modal-baslik"><h3 id="t_baslik">Beyanname Türü</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button></div>
      <div class="modal-govde">
        <div class="form-grid">
          <div class="form-grup"><label>Kod <span class="zorunlu">*</span></label>
            <input type="text" name="kod" id="t_kod" class="girdi" required placeholder="KDV1_A"></div>
          <div class="form-grup"><label>Kısa Ad <span class="zorunlu">*</span></label>
            <input type="text" name="kisa_ad" id="t_kisa" class="girdi" required></div>
          <div class="form-grup tam"><label>Tam Ad <span class="zorunlu">*</span></label>
            <input type="text" name="ad" id="t_ad" class="girdi" required></div>
          <div class="form-grup"><label>Periyot</label>
            <select name="periyot" id="t_periyot">
              <option value="AYLIK">Aylık</option><option value="UC_AYLIK">Üç Aylık</option>
              <option value="ALTI_AYLIK">Altı Aylık</option><option value="YILLIK">Yıllık</option>
            </select></div>
          <div class="form-grup"><label>Offset Ay</label>
            <input type="number" name="son_gun_offset_ay" id="t_offset" class="girdi" min="0" max="12" value="1">
            <span class="yardim">Dönem bitişinden kaç ay sonra</span></div>
          <div class="form-grup"><label>Son Gün Tipi</label>
            <select name="son_gun_tipi" id="t_tip">
              <option value="GUN">Belirli Gün</option><option value="AY_SONU">Ayın Son Günü</option>
            </select></div>
          <div class="form-grup"><label>Son Gün (1-31)</label>
            <input type="number" name="son_gun" id="t_gun" class="girdi" min="1" max="31" value="28"></div>
          <div class="form-grup"><label>Uygulanacak Mükellef</label>
            <select name="mukellef_tipi" id="t_mtip">
              <option value="hepsi">Hepsi</option><option value="gercek">Sadece Şahıs</option>
              <option value="tuzel">Sadece Kurum</option>
            </select></div>
          <div class="form-grup"><label>Atlanan Dönemler</label>
            <input type="text" name="atlanan_donemler" id="t_atlanan" class="girdi" placeholder="4">
            <span class="yardim">Örn. geçici vergi 4. dönem: 4</span></div>
          <div class="form-grup tam"><label>Çelişen Kodlar</label>
            <input type="text" name="celisen_kodlar" id="t_celisen" class="girdi" placeholder="KURUMLAR,KURUM_GECICI"></div>
          <div class="form-grup tam"><label>Açıklama</label>
            <input type="text" name="aciklama" id="t_aciklama" class="girdi"></div>
          <div class="form-grup"><label>Renk</label>
            <input type="color" name="renk" id="t_renk" class="girdi" style="height:38px;padding:3px" value="#64748b"></div>
          <div class="form-grup"><label>Sıra</label>
            <input type="number" name="sira" id="t_sira" class="girdi" value="0"></div>
          <div class="form-grup"><label>Durum</label>
            <select name="aktif" id="t_aktif"><option value="1">Aktif</option><option value="0">Pasif</option></select></div>
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
function turAc(t) {
  t = t || {};
  document.getElementById('t_baslik').textContent = t.id ? 'Beyanname Türü Düzenle' : 'Yeni Beyanname Türü';
  var alanlar = { t_id:'id', t_kod:'kod', t_kisa:'kisa_ad', t_ad:'ad', t_periyot:'periyot',
    t_offset:'son_gun_offset_ay', t_tip:'son_gun_tipi', t_gun:'son_gun', t_mtip:'mukellef_tipi',
    t_atlanan:'atlanan_donemler', t_celisen:'celisen_kodlar', t_aciklama:'aciklama',
    t_renk:'renk', t_sira:'sira', t_aktif:'aktif' };
  Object.keys(alanlar).forEach(function (id) {
    var el = document.getElementById(id);
    var v = t[alanlar[id]];
    el.value = (v === null || v === undefined) ? (el.type === 'color' ? '#64748b' : '') : v;
  });
  BT.modalAc('tur-modal');
}
</script>
<?= $this->endSection() ?>
