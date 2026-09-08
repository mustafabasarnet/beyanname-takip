<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
$rol    = $aktifKullanici['rol'] ?? 'personel';
$seciliAralik = (string) ($filtre['aralik'] ?? '');
?>

<!-- FİLTRE -->
<form method="get" class="filtre-bar">
  <input type="hidden" name="aralik" value="<?= esc($seciliAralik) ?>">

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
  // Şablon çoklu seçim
  $cs_ad       = 'tur';
  $cs_etiket   = 'Şablon';
  $cs_ogeler   = $turler;
  $cs_secili   = array_filter((array) ($filtre['turu_id'] ?? []), static fn ($v) => $v !== '' && $v !== null);
  $cs_tekil    = 'şablon';
  $cs_genislik = '200px';
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
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>" placeholder="Mükellef / VKN / açıklama">
  </div>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url('sicil/ekle') ?>" class="btn yesil kucuk">+ Yeni İşlem</a>
  </div>
</form>

<!-- ZAMAN HIZLI FİLTRESİ -->
<div class="filtre-bar" style="margin-top:8px;padding:8px 12px">
  <span class="kucuk-yazi" style="align-self:center">Todo zamanı:</span>
  <?php
  $aralikEtiketleri = [
      ''             => ['Tümü', ''],
      'gecikti'      => ['⏰ Geciken', 'kirmizi'],
      'bugun'        => ['Bugün Son Gün', 'turuncu'],
      'ic7'          => ['≤ 7 Gün', 'sari'],
      'ic15'         => ['≤ 15 Gün', ''],
      'tamamlanan'   => ['✓ Tamamlanan', 'yesil'],
  ];
  foreach ($aralikEtiketleri as $aKod => [$aAd, $aSinif]):
      $aktifA = $seciliAralik === $aKod;
      $urlA = $aKod === '' ? site_url('sicil') : site_url('sicil?aralik=' . $aKod);
  ?>
    <a class="btn kucuk <?= $aSinif . ($aktifA ? ' ' : ' ikincil') ?>" style="text-decoration:none"
       href="<?= $urlA ?>"><?= $aAd ?></a>
  <?php endforeach; ?>
</div>

<!-- ÖZET KARTLAR -->
<div class="stat-grid" style="margin-top:18px">
  <div class="stat">
    <div class="etiket">Toplam İşlem</div>
    <div class="deger"><?= (int) $ozet['toplam'] ?></div>
    <div class="alt"><?= (int) $ozet['islemde'] ?> devam ediyor</div>
  </div>
  <div class="stat mavi">
    <div class="etiket">Açık Todo</div>
    <div class="deger"><?= (int) $ozet['acik_todo'] ?></div>
    <div class="alt">tamamlanmayı bekleyen</div>
  </div>
  <a class="stat kirmizi" style="text-decoration:none;color:inherit" href="<?= site_url('sicil?aralik=gecikti') ?>">
    <div class="etiket">Süresi Geçti</div><div class="deger"><?= (int) $sayac['gecikti'] ?></div></a>
  <a class="stat turuncu" style="text-decoration:none;color:inherit" href="<?= site_url('sicil?aralik=bugun') ?>">
    <div class="etiket">Bugün Son Gün</div><div class="deger"><?= (int) $sayac['bugun'] ?></div></a>
  <a class="stat sari" style="text-decoration:none;color:inherit" href="<?= site_url('sicil?aralik=ic7') ?>">
    <div class="etiket">≤ 7 Gün</div><div class="deger"><?= (int) $sayac['ic7'] ?></div></a>
  <a class="stat yesil" style="text-decoration:none;color:inherit" href="<?= site_url('sicil?aralik=tamamlanan') ?>">
    <div class="etiket">Tamamlanan Todo</div><div class="deger"><?= (int) $sayac['tamamlanan'] ?></div></a>
</div>

<!-- LİSTE -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🧾 Sicil İşlemleri (<?= count($kayitlar) ?>)</h2>
    <div class="sag">
      <?php if (in_array($rol, ['admin', 'musavir'], true)): ?>
        <a href="<?= site_url('sicil-sablon') ?>" class="btn ikincil kucuk">🧩 Şablonlar</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($kayitlar === []): ?>
      <div class="tablo-bos"><span class="ikon">📭</span>
        Kayıtlı sicil işlemi yok.
        <div class="mt16">
          <a class="btn kucuk" href="<?= site_url('sicil/ekle') ?>">+ İlk İşlemi Ekle</a>
          <?php if (in_array($rol, ['admin', 'musavir'], true)): ?>
            <a class="btn ikincil kucuk" href="<?= site_url('sicil-sablon') ?>">Şablon tanımla</a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead>
            <tr>
              <th>Tarih</th><th>Mükellef</th><th>Şablon</th>
              <th style="min-width:130px">İlerleme</th><th>En Yakın Son Tarih</th>
              <th>Durum</th><th class="sag">İşlem</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $bugun = date('Y-m-d');
            foreach ($kayitlar as $k):
              $toplam = (int) $k['toplam_todo'];
              $tamam  = (int) $k['tamam_todo'];
              $acik   = (int) $k['acik_todo'];
              $enYakin = $k['en_yakin_son'];
              $gecikti = $acik > 0 && $enYakin !== null && $enYakin < $bugun;
              $bugunMu = $acik > 0 && $enYakin === $bugun;
              $durumRozet = match ($k['durum']) {
                  'TAMAM'   => 'yesil',
                  'ISLEMDE' => 'sari',
                  default   => 'gri',
              };
            ?>
              <tr class="tkl" data-url="<?= site_url('sicil/detay/' . (int) $k['id']) ?>"
                  style="cursor:pointer" title="Detayı aç">
                <td class="kalin"><?= trTarih($k['degisiklik_tarihi']) ?></td>
                <td>
                  <b><?= esc(kisalt($k['mukellef_unvan'], 28)) ?></b>
                  <div class="kucuk-yazi"><?= esc(($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no']) ?: '') ?></div>
                </td>
                <td><span class="rozet mavi"><?= esc(kisalt($k['tur_ad'], 22)) ?></span></td>
                <td>
                  <?php if ($toplam > 0): ?>
                    <div style="display:flex;align-items:center;gap:8px">
                      <div class="progress" style="flex:1;min-width:60px">
                        <div class="dolu" style="width:<?= (int) round($tamam / $toplam * 100) ?>%"></div>
                      </div>
                      <span class="kucuk-yazi kalin"><?= $tamam ?>/<?= $toplam ?></span>
                    </div>
                  <?php else: ?>
                    <span class="kucuk-yazi">—</span>
                  <?php endif; ?>
                </td>
                <td class="kucuk-yazi">
                  <?php if ($acik > 0 && $enYakin): ?>
                    <?php if ($gecikti): ?>
                      <span class="rozet kirmizi" title="En az bir todo'nun süresi geçti">⚠ Gecikti</span>
                    <?php elseif ($bugunMu): ?>
                      <span class="rozet turuncu">Bugün</span>
                    <?php else: ?>
                      <?= trTarih($enYakin) ?>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="metin-gri">—</span>
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
