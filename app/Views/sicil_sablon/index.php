<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<!-- FİLTRE -->
<form method="get" class="filtre-bar">
  <?php $seciliAktif = $filtre['aktif'] ?? null; ?>
  <div class="form-grup">
    <label>Durum</label>
    <select name="aktif" data-oto-filtre>
      <option value="">Tümü</option>
      <option value="1" <?= $seciliAktif !== null && (int) $seciliAktif === 1 ? 'selected' : '' ?>>Aktif</option>
      <option value="0" <?= $seciliAktif !== null && (int) $seciliAktif === 0 ? 'selected' : '' ?>>Pasif</option>
    </select>
  </div>
  <div class="form-grup" style="min-width:170px">
    <label>Ara</label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>" placeholder="Şablon adı…">
  </div>
  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url('sicil-sablon/yeni') ?>" class="btn yesil kucuk">+ Yeni Şablon</a>
  </div>
</form>

<div class="kart">
  <div class="kart-baslik">
    <h2>🧩 Sicil Şablonları (<?= count($kayitlar) ?>)</h2>
    <div class="sag kucuk-yazi">
      Şablon seçilince altındaki <b>aktif</b> todo tanımları işlemlere otomatik üretilir.
    </div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($kayitlar === []): ?>
      <div class="tablo-bos"><span class="ikon">📭</span>
        Tanımlı şablon yok.
        <div class="mt16"><a class="btn kucuk" href="<?= site_url('sicil-sablon/yeni') ?>">+ İlk Şablonu Oluştur</a></div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead>
            <tr><th>Şablon</th><th class="orta">Aktif Todo</th><th class="orta">Toplam</th>
                <th class="orta">Kullanım</th><th>Durum</th><th class="sag">İşlem</th></tr>
          </thead>
          <tbody>
            <?php foreach ($kayitlar as $k): ?>
              <tr>
                <td>
                  <b><?= esc($k['ad']) ?></b>
                  <?php if (! empty($k['aciklama'])): ?>
                    <div class="kucuk-yazi"><?= esc(kisalt($k['aciklama'], 80)) ?></div>
                  <?php endif; ?>
                </td>
                <td class="orta">
                  <?php if ((int) $k['aktif_todo'] > 0): ?>
                    <span class="rozet mavi"><?= (int) $k['aktif_todo'] ?></span>
                  <?php else: ?>
                    <span class="kucuk-yazi">—</span>
                  <?php endif; ?>
                </td>
                <td class="orta kucuk-yazi"><?= (int) $k['toplam_todo'] ?></td>
                <td class="orta kucuk-yazi"><?= (int) $k['islem_sayisi'] ?> işlem</td>
                <td>
                  <?php if ((int) $k['aktif'] === 1): ?>
                    <span class="rozet yesil">Aktif</span>
                  <?php else: ?>
                    <span class="rozet gri">Pasif</span>
                  <?php endif; ?>
                </td>
                <td class="sag">
                  <a href="<?= site_url('sicil-sablon/duzenle/' . (int) $k['id']) ?>" class="btn ikincil mini">✏️ Düzenle</a>
                  <a href="<?= site_url('sicil-sablon/pasif/' . (int) $k['id']) ?>" class="btn ikincil mini"
                     <?= (int) $k['aktif'] === 1 ? 'data-onay="Şablon pasife alınsın mı? (yeni işlemde seçilemez, geçmiş korunur)"' : '' ?>>
                    <?= (int) $k['aktif'] === 1 ? 'Pasifle' : 'Aktifle' ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
