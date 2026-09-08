<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<!-- FİLTRE -->
<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Yıl</label>
    <select name="yil" data-oto-filtre>
      <option value="">Tümü</option>
      <?php for ($y = date('Y') + 1; $y >= date('Y') - 2; $y--): ?>
        <option value="<?= $y ?>" <?= (int) ($filtre['yil'] ?? 0) === $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </div>

  <?php
  // Değişiklik türü çoklu seçim
  $cs_ad       = 'tur';
  $cs_etiket   = 'Değişiklik Türü';
  $cs_ogeler   = $turler;
  $cs_secili   = array_filter((array) ($filtre['turu_id'] ?? []), static fn ($v) => $v !== '' && $v !== null);
  $cs_tekil    = 'tür';
  $cs_genislik = '190px';
  include APPPATH . 'Views/parcalar/_coklu_secim.php';
  ?>

  <div class="form-grup">
    <label>Durum</label>
    <select name="durum" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($durumlar as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($filtre['durum'] ?? '') === $k ? 'selected' : '' ?>><?= esc($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if (count($musavirler) > 1): ?>
    <div class="form-grup">
      <label>Mali Müşavir</label>
      <select name="musavir_id" data-oto-filtre>
        <option value="">Tümü</option>
        <?php foreach ($musavirler as $mid => $mad): ?>
          <option value="<?= $mid ?>" <?= (int) (is_array($filtre['musavir_id'] ?? null) ? ($filtre['musavir_id'][0] ?? 0) : ($filtre['musavir_id'] ?? 0)) === (int) $mid ? 'selected' : '' ?>>
            <?= esc($mad) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="form-grup" style="min-width:170px">
    <label>Ara</label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>" placeholder="Mükellef / VKN / konu">
  </div>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url('sicil/ekle') ?>" class="btn yesil kucuk">+ Sicil Değişikliği Ekle</a>
  </div>
</form>

<!-- ÖZET -->
<div class="stat-grid">
  <div class="stat"><div class="etiket">Toplam Değişiklik</div><div class="deger"><?= (int) $ozet['toplam'] ?></div></div>
  <div class="stat mavi"><div class="etiket">Açık Bildirim</div>
    <div class="deger"><?= (int) $ozet['acik_gorev'] ?></div><div class="alt">Takip bekleyen</div></div>
  <div class="stat turuncu"><div class="etiket">Bekliyor</div><div class="deger"><?= (int) $ozet['bekliyor'] ?></div></div>
  <div class="stat sari"><div class="etiket">İşlemde</div><div class="deger"><?= (int) $ozet['islemde'] ?></div></div>
  <div class="stat yesil"><div class="etiket">Tamamlandı</div><div class="deger"><?= (int) $ozet['tamam'] ?></div></div>
</div>

<!-- LİSTE -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🧾 Sicil Değişiklikleri (<?= count($kayitlar) ?>)</h2>
    <div class="sag"><a href="<?= site_url('sicil/gorevler') ?>" class="btn ikincil kucuk">📤 Bildirim Görevleri →</a></div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($kayitlar === []): ?>
      <div class="tablo-bos"><span class="ikon">📭</span>
        Kayıtlı sicil değişikliği yok.
        <div class="mt16"><a class="btn kucuk" href="<?= site_url('sicil/ekle') ?>">+ İlk Değişikliği Ekle</a></div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead>
            <tr>
              <th>Tarih</th><th>Mükellef</th><th>Tür</th><th>Özet</th>
              <th class="orta">Açık Bildirim</th><th>Durum</th><th class="sag">İşlem</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($kayitlar as $k): ?>
              <?php
              $durumRozet = match ($k['durum']) {
                  'TAMAM'    => 'yesil',
                  'ISLEMDE'  => 'sari',
                  default    => 'gri',
              };
              ?>
              <tr class="tkl" data-url="<?= site_url('sicil/detay/' . (int) $k['id']) ?>"
                  style="cursor:pointer" title="Detayı aç">
                <td class="kalin"><?= trTarih($k['degisiklik_tarihi']) ?></td>
                <td>
                  <b><?= esc(kisalt($k['mukellef_unvan'], 30)) ?></b>
                  <div class="kucuk-yazi"><?= esc($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no']) ?></div>
                </td>
                <td><span class="rozet mavi"><?= esc(kisalt($k['tur_ad'], 22)) ?></span></td>
                <td class="kucuk-yazi"><?= esc(kisalt((string) $k['yeni_deger'] ?: $k['konu'], 50)) ?></td>
                <td class="orta">
                  <?php if ((int) $k['acik_gorev'] > 0): ?>
                    <span class="rozet turuncu" title="<?= $k['en_yakin_son'] ? 'En yakın: ' . trTarih($k['en_yakin_son']) : '' ?>">
                      <?= (int) $k['acik_gorev'] ?> ⏳
                    </span>
                  <?php else: ?>
                    <span class="kucuk-yazi">—</span>
                  <?php endif; ?>
                </td>
                <td><span class="rozet <?= $durumRozet ?>"><?= esc($durumlar[$k['durum']] ?? $k['durum']) ?></span></td>
                <td class="sag">
                  <a href="<?= site_url('sicil/detay/' . (int) $k['id']) ?>" class="btn ikincil mini">Detay</a>
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
