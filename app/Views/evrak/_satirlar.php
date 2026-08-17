<?php
/**
 * Evrak takip çizelgesi — SATIR PARÇASI
 *
 * Hem ilk sayfa yüklemesinde (evrak/index) hem de sonsuz kaydırmada
 * (Evrak::dahaFazla AJAX) kullanılır; böylece sonradan eklenen satırlar
 * ilk yüklenenlerle birebir aynı görünür.
 *
 * Beklenen değişkenler: $mukellefler, $turler, $matris, $notlar, $muafiyet
 *
 * HÜCRE DURUMLARI
 *   GELDI   → yeşil ✓
 *   GELMEDI → kırmızı ✕
 *   YOK     → taralı gri "—" (mükellefte bu evrak türü yok)
 *
 * Etkin durum EvrakTakipModel::etkinDurum() ile bulunur: o aya ait kayıt
 * kalıcı muafiyeti EZER; kayıt yoksa kalıcı ayar geçerlidir.
 */
// Controller eski sürümse (muafiyet göndermiyorsa) program çalışmaya devam etsin
$muafiyet = $muafiyet ?? [];
?>
          <?php foreach ($mukellefler as $m): $mid = (int) $m['id']; ?>
            <tr>
              <td class="sol-sabit">
                <a href="<?= site_url('mukellefler/detay/' . $mid) ?>"><?= esc(kisalt($m['unvan'], 32)) ?></a>
                <div class="kucuk-yazi" style="font-weight:400">
                  <?= esc($m['vergi_kimlik_no'] ?: $m['tc_kimlik_no']) ?>
                  <?php if (! empty($m['terk_tarihi'])): ?>
                    • <span class="metin-kirmizi">Terk: <?= trTarih($m['terk_tarihi']) ?></span>
                  <?php endif; ?>
                </div>
              </td>

              <?php foreach ($turler as $t):
                  $tid    = (int) $t['id'];
                  $hucre  = $matris[$mid][$tid] ?? null;
                  $kalici = isset($muafiyet[$mid][$tid]);
                  $durum  = \App\Models\EvrakTakipModel::etkinDurum($hucre, $kalici);
                  $yok    = $durum === 'YOK';
                  // Dönemsel istisna: o ay kaydı YOK ama kalıcı ayar değil
                  $donemsel = $yok && ! $kalici;

                  $sinif = match ($durum) {
                      'GELDI' => 'geldi',
                      'YOK'   => 'yok' . ($kalici ? ' kalici' : ''),
                      default => 'gelmedi',
                  };

                  $isaret = match ($durum) {
                      'GELDI' => '✓',
                      'YOK'   => '—',
                      default => '✕',
                  };

                  $ipucu = esc($t['ad']) . ' — ' . match ($durum) {
                      'GELDI' => 'Geldi',
                      'YOK'   => $kalici
                          ? ('Bu mükellefte yok (kalıcı)' . ($muafiyet[$mid][$tid] !== '' ? ' · ' . esc($muafiyet[$mid][$tid]) : ''))
                          : 'Bu dönem takip dışı',
                      default => 'Gelmedi',
                  };
              ?>
                <td class="evrak-hucre <?= $sinif ?>"
                    data-mukellef="<?= $mid ?>" data-tur="<?= $tid ?>"
                    data-durum="<?= $durum ?>"
                    data-kalici="<?= $kalici ? 1 : 0 ?>"
                    data-donemsel="<?= $donemsel ? 1 : 0 ?>"
                    data-tur-ad="<?= esc($t['ad'], 'attr') ?>"
                    data-mukellef-ad="<?= esc(kisalt($m['unvan'], 40), 'attr') ?>"
                    onclick="evrakDegistir(this)"
                    oncontextmenu="return evrakMenu(event, this)"
                    title="<?= $ipucu ?> — sağ tık: takip dışı seçenekleri">
                  <?= $isaret ?>
                </td>
              <?php endforeach; ?>

              <td>
                <button class="btn yesil mini" onclick="tumunu(<?= $mid ?>,'GELDI')" title="Tümü geldi">✓✓</button>
                <button class="btn ikincil mini" onclick="tumunu(<?= $mid ?>,'GELMEDI')" title="Tümünü temizle">✕</button>
              </td>

              <td class="not-hucre <?= ! empty($notlar[$mid]) ? 'dolu' : '' ?>"
                  data-mukellef="<?= $mid ?>" onclick="notDuzenle(this)">
                <?php if (! empty($notlar[$mid])): ?>
                  <span class="not-metin">📌 <?= esc($notlar[$mid]) ?></span>
                <?php else: ?>
                  <span class="not-metin not-bos">+ not ekle</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
