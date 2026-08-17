<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<style>
.ta-tablo td{vertical-align:middle}
.ta-ikon{font-size:18px}
.ta-pasif{opacity:.5}
.ta-kilit{font-size:11px;color:var(--gri-400,#94a3b8)}
</style>

<div class="uyari bilgi">
  <span class="ik">ℹ</span>
  <div>
    Bu adımlar <b>E-Defter Takip</b> çizelgesinde kontrol listesi olarak görünür.
    Sıra numarası küçük olan solda çıkar. <b>Hazır</b> ve <b>Onaylandı</b> adımları
    kaydın durumunu belirlediği için kaldırılamaz.
  </div>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>📗 E-Defter Takip Adımları</h2>
    <div class="sag"><a href="<?= site_url('edefter') ?>" class="btn ikincil mini">Takip Listesi</a></div>
  </div>
  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="tablo ta-tablo">
        <thead>
          <tr>
            <th style="width:60px">Sıra</th>
            <th style="width:50px">İkon</th>
            <th>Adım Adı</th>
            <th>Kod</th>
            <th>Açıklama</th>
            <th style="width:80px">Durum</th>
            <th style="width:150px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($adimlar as $a):
              $kilit = in_array($a['kod'], ['HAZIR', 'ONAY'], true); ?>
            <tr class="<?= (int) $a['aktif'] === 1 ? '' : 'ta-pasif' ?>">
              <form method="post" action="<?= site_url('tanimlar/edefter-adim-kaydet') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <td><input type="number" name="sira" class="girdi" value="<?= (int) $a['sira'] ?>" style="width:64px"></td>
                <td><input type="text" name="ikon" class="girdi ta-ikon" value="<?= esc($a['ikon']) ?>"
                           maxlength="4" style="width:52px;text-align:center"></td>
                <td><input type="text" name="ad" class="girdi" value="<?= esc($a['ad']) ?>" required></td>
                <td>
                  <code><?= esc($a['kod']) ?></code>
                  <?php if ($kilit): ?><div class="ta-kilit">🔒 iş akışı adımı</div><?php endif; ?>
                </td>
                <td><input type="text" name="aciklama" class="girdi" value="<?= esc($a['aciklama']) ?>"
                           placeholder="Kısa açıklama (ipucu)"></td>
                <td>
                  <label class="onay" style="margin:0">
                    <input type="checkbox" name="aktif" value="1" <?= (int) $a['aktif'] === 1 ? 'checked' : '' ?>>
                    Aktif
                  </label>
                </td>
                <td style="white-space:nowrap">
                  <button type="submit" class="btn mini">Kaydet</button>
                  <?php if (! $kilit && (int) $a['aktif'] === 1): ?>
                    <a href="<?= site_url('tanimlar/edefter-adim-sil/' . (int) $a['id']) ?>"
                       class="btn ikincil mini"
                       onclick="return confirm('<?= esc($a['ad'], 'js') ?> adımı pasife alınacak. İşaretlenmiş geçmiş veriler korunur. Devam?')">
                      Kaldır
                    </a>
                  <?php endif; ?>
                </td>
              </form>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="kart">
  <div class="kart-baslik"><h2>➕ Yeni Adım</h2></div>
  <div class="kart-govde">
    <form method="post" action="<?= site_url('tanimlar/edefter-adim-kaydet') ?>" class="form-grid">
      <?= csrf_field() ?>
      <div class="form-grup">
        <label>Sıra</label>
        <input type="number" name="sira" class="girdi" value="<?= (int) $sonraki ?>">
        <span class="yardim">Küçük numara solda görünür.</span>
      </div>
      <div class="form-grup">
        <label>İkon</label>
        <input type="text" name="ikon" class="girdi" maxlength="4" placeholder="📦" style="text-align:center">
      </div>
      <div class="form-grup">
        <label>Adım Adı *</label>
        <input type="text" name="ad" class="girdi" required placeholder="Örn: Kasa Kontrolü">
      </div>
      <div class="form-grup">
        <label>Kod *</label>
        <input type="text" name="kod" class="girdi" required placeholder="KASA_KONTROL"
               pattern="[A-Za-z0-9_]+" title="Yalnızca harf, rakam ve alt çizgi">
        <span class="yardim">Sonradan değiştirilemez.</span>
      </div>
      <div class="form-grup tam">
        <label>Açıklama</label>
        <input type="text" name="aciklama" class="girdi" placeholder="Kutunun üzerine gelince görünecek ipucu">
      </div>
      <input type="hidden" name="aktif" value="1">
      <div class="form-grup tam">
        <button type="submit" class="btn">Adım Ekle</button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
