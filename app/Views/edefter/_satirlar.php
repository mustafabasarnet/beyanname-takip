<?php
/**
 * E-Defter çizelgesi — SATIR PARÇASI
 *
 * Hem ilk yüklemede hem sonsuz kaydırmada (Edefter::dahaFazla) kullanılır;
 * böylece sonradan gelen satırlar ilk yüklenenlerle birebir aynı görünür.
 *
 * Beklenen değişkenler: $kayitlar, $adimlar, $durumlar, $yetki
 * (Parça dosyalar üst görünümün yerel değişkenlerini görmez; savunmacı yazılır.)
 */
$adimlar  = $adimlar  ?? [];
$durumlar = $durumlar ?? [];
$yetki    = $yetki    ?? false;
?>
<?php foreach ($kayitlar as $k):
    // Durum gönderilir: yüklenen/yüklenmeyecek beratlarda geri sayım yerine
    // sonuç yazılır ("✓ Verildi" / "Takip dışı").
    $kalan   = kalanGunMetni($k['son_tarih'], $k['durum']);
    $bitti   = $kalan['bitti'];
    $gecikti = ! $bitti && $kalan['gun'] < 0;
    $pasif   = $k['durum'] === 'YUKLENMEYECEK';
?>
  <tr class="<?= $gecikti ? 'gecikmis-satir' : ($kalan['gun'] === 0 && ! $bitti ? 'bugun-satir' : '') ?><?= $pasif ? ' ed-pasif' : '' ?>"
      data-id="<?= (int) $k['id'] ?>">

    <td>
      <a href="<?= site_url('mukellefler/detay/' . $k['mukellef_id']) ?>" class="kalin">
        <?= esc(kisalt($k['mukellef_unvan'], 28)) ?>
      </a>
      <div class="kucuk-yazi">
        <?= esc($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no']) ?>
        <?php if (! empty($k['sorumlu_adi'])): ?>
          • <span title="E-defter sorumlusu">👤 <?= esc(kisalt($k['sorumlu_adi'], 16)) ?></span>
        <?php endif; ?>
      </div>
    </td>

    <td>
      <span class="rozet <?= $k['donem_tipi'] === 'UC_AYLIK' ? 'mor' : 'mavi' ?>" style="font-size:10.5px">
        <?= $k['donem_tipi'] === 'UC_AYLIK' ? '3 Aylık' : 'Aylık' ?>
      </span>
      <div class="kucuk-yazi"><?= esc($k['donem_adi']) ?></div>
    </td>

    <td>
      <b><?= trTarih($k['son_tarih']) ?></b>
      <?php if (! empty($k['kaydirma_nedeni'])): ?>
        <div class="kucuk-yazi" style="color:var(--turuncu)" title="<?= esc($k['kaydirma_nedeni']) ?>">
          ↷ <?= esc(kisalt($k['kaydirma_nedeni'], 18)) ?>
        </div>
      <?php endif; ?>
    </td>

    <td>
      <?php if ($bitti): ?>
        <span class="rozet <?= $pasif ? 'gri' : 'onaylandi' ?>"><?= $pasif ? 'Takip dışı' : '✓ Yüklendi' ?></span>
      <?php else: ?>
        <span class="rozet <?= $kalan['sinif'] ?>"><?= esc($kalan['metin']) ?></span>
      <?php endif; ?>
    </td>

    <!-- ---------- KONTROL LİSTESİ ---------- -->
    <?php foreach ($adimlar as $a):
        $bu = null;

        foreach ($k['adimlar'] ?? [] as $x) {
            if ((int) $x['id'] === (int) $a['id']) { $bu = $x; break; }
        }

        $tamam = $bu !== null && $bu['tamam'];
        $ipucu = $a['ad'] . ($tamam && ! empty($bu['tarih'])
            ? ' — ' . date('d.m.Y H:i', strtotime($bu['tarih'])) : '');
    ?>
      <td class="ed-adim-h">
        <button type="button"
                class="ed-kutu<?= $tamam ? ' dolu' : '' ?>"
                data-takip="<?= (int) $k['id'] ?>"
                data-adim="<?= (int) $a['id'] ?>"
                <?= $yetki ? '' : 'disabled' ?>
                title="<?= esc($ipucu) ?>"
                aria-label="<?= esc($a['ad']) ?>"
                aria-pressed="<?= $tamam ? 'true' : 'false' ?>">
          <?= $tamam ? '✓' : '' ?>
        </button>
      </td>
    <?php endforeach; ?>

    <td class="ed-ilerleme-h">
      <div class="ed-cubuk" title="<?= (int) ($k['adim_tamam'] ?? 0) ?> / <?= (int) ($k['adim_toplam'] ?? 0) ?> adım">
        <i style="width:<?= (int) ($k['ilerleme'] ?? 0) ?>%"></i>
      </div>
      <span class="ed-yuzde">%<?= (int) ($k['ilerleme'] ?? 0) ?></span>
    </td>

    <td>
      <select class="girdi ed-durum" data-id="<?= (int) $k['id'] ?>" <?= $yetki ? '' : 'disabled' ?>
              title="Durum adımlardan otomatik hesaplanır; yalnızca 'Yüklenmeyecek' elle seçilir">
        <?php foreach ($durumlar as $dk => $dv): ?>
          <option value="<?= $dk ?>" <?= $k['durum'] === $dk ? 'selected' : '' ?>><?= esc($dv) ?></option>
        <?php endforeach; ?>
      </select>
    </td>

    <td>
      <span class="ed-not<?= empty($k['not_metni']) ? ' bos' : '' ?>"
            data-id="<?= (int) $k['id'] ?>"
            title="<?= esc($k['not_metni'] ?? 'Not eklemek için tıklayın') ?>">
        <?= $k['not_metni'] !== null && $k['not_metni'] !== ''
            ? esc(kisalt($k['not_metni'], 18)) : '+ not ekle' ?>
      </span>
    </td>
  </tr>
<?php endforeach; ?>
