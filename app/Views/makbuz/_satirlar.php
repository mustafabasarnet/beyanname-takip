<?php
/**
 * Makbuz takip çizelgesi — SATIR PARÇASI
 *
 * Hem ilk yüklemede hem sonsuz kaydırmada (Makbuz::dahaFazla) kullanılır.
 * Beklenen değişkenler: $kayitlar, $filtre
 */
$filtre = $filtre ?? [];
$yil    = (int) ($filtre['yil'] ?? date('Y'));
?>
<?php foreach ($kayitlar as $k):
    $ucret   = (float) $k['ucret'];
    $kesilen = (float) $k['kesilen'];
    $kalan   = (float) $k['kalan'];
    $oran    = (int) $k['oran'];

    if ($ucret <= 0) {
        $sinif = 'mk-ucretsiz'; $rozet = ['gri', 'Ücret girilmemiş'];
    } elseif ($kesilen > $ucret) {
        $sinif = 'mk-asim';     $rozet = ['kirmizi', 'Ücreti aşmış'];
    } elseif ($kesilen >= $ucret) {
        $sinif = 'mk-tamam';    $rozet = ['yesil', 'Tamamlandı'];
    } elseif ($kesilen > 0) {
        $sinif = '';            $rozet = ['sari', 'Devam ediyor'];
    } else {
        $sinif = '';            $rozet = ['turuncu', 'Hiç kesilmemiş'];
    }
?>
  <tr class="<?= $sinif ?>" data-mukellef="<?= (int) $k['mukellef_id'] ?>">
    <td>
      <a href="<?= site_url('makbuz/detay/' . (int) $k['mukellef_id'] . '?yil=' . $yil) ?>" class="kalin">
        <?= esc(kisalt($k['unvan'], 34)) ?>
      </a>
      <div class="kucuk-yazi">
        <?= esc($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no']) ?>
        <?php if (! empty($k['musavir_adi'])): ?>
          • <span style="color:<?= esc($k['musavir_renk'] ?: '#64748b') ?>">
              <?= esc(kisalt($k['musavir_adi'], 18)) ?>
            </span>
        <?php endif; ?>
      </div>
    </td>

    <td class="sag">
      <span class="mk-ucret" data-mukellef="<?= (int) $k['mukellef_id'] ?>"
            title="Değiştirmek için tıklayın">
        <?= $ucret > 0 ? number_format($ucret, 2, ',', '.') : '— gir —' ?>
      </span>
    </td>

    <td class="sag"><?= number_format($kesilen, 2, ',', '.') ?></td>

    <td class="sag kalin" style="color:<?= $kalan > 0 ? 'var(--kirmizi,#dc2626)' : 'var(--yesil,#059669)' ?>">
      <?= number_format($kalan, 2, ',', '.') ?>
    </td>

    <td class="orta kucuk-yazi"><?= (int) $k['adet'] ?></td>

    <td class="kucuk-yazi"><?= ! empty($k['son_makbuz']) ? trTarih($k['son_makbuz']) : '—' ?></td>

    <td style="white-space:nowrap">
      <span class="mk-cubuk" title="%<?= $oran ?> tamamlandı">
        <i style="width:<?= min(100, $oran) ?>%;background:<?= $oran >= 100 ? '#059669' : '#2563eb' ?>"></i>
      </span>
      <span class="mk-yuzde">%<?= $oran ?></span>
    </td>

    <td><span class="rozet <?= $rozet[0] ?>" style="font-size:10.5px"><?= $rozet[1] ?></span></td>
  </tr>
<?php endforeach; ?>
