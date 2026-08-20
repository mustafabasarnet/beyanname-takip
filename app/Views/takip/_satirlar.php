<?php
/**
 * Beyanname takip çizelgesi — SATIR PARÇASI
 *
 * Hem ilk sayfa yüklemesinde (takip/index) hem de sonsuz kaydırmada
 * (Takip::dahaFazla AJAX) bu dosya kullanılır. Böylece sonradan eklenen
 * satırlar ilk yüklenenlerle birebir aynı görünür.
 *
 * Beklenen değişkenler: $kayitlar, $filtre, $durumlar, $tahakkukYetki
 * ($mod verilmezse $filtre['tarih_modu'] üzerinden hesaplanır — include
 *  çağrıldığında üst görünümün yerel değişkenleri buraya taşınmaz.)
 */
$mod = $mod ?? ($filtre['tarih_modu'] ?? 'beyan');

/*
 * MUHSGK ↔ SGK eşleşme haritası.
 * Controller güncellenmemişse boş gelir; rozetler çizilmez, sayfa çalışır.
 */
$esHarita = $esHarita ?? [];
?>
          <?php foreach ($kayitlar as $k):
              /*
               * Durum bilgisi de gönderilir: Onaylandı satırlarında geri
               * sayım yerine "✓ Verildi", Verilmeyecek'te "Takip dışı"
               * yazılır. Bitmiş iş için gecikme uyarısı anlamsızdı.
               */
              $kalan   = kalanGunMetni($k['son_tarih'], $k['durum']);
              $gecikti = ! $kalan['bitti'] && $kalan['gun'] < 0;
          ?>
            <tr class="<?= $gecikti ? 'gecikmis-satir' : (! $kalan['bitti'] && $kalan['gun'] === 0 ? 'bugun-satir' : '') ?>">
              <td><input type="checkbox" class="satir-sec" value="<?= $k['id'] ?>"></td>

              <td>
                <a href="<?= site_url('mukellefler/detay/' . $k['mukellef_id']) ?>" class="kalin">
                  <?= esc(kisalt($k['mukellef_unvan'], 30)) ?>
                </a>
                <?= gencGirisimciRozet($k, (int) $k['yil'], true) ?>
                <div class="kucuk-yazi"><?= esc($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no']) ?>
                  • <?= esc(defterTipiKisa($k['defter_tipi'])) ?>
                  <?php if (! empty($k['terk_tarihi'])): ?>
                    • <span class="metin-kirmizi">Terk: <?= trTarih($k['terk_tarihi']) ?></span>
                  <?php endif; ?>
                </div>
              </td>

              <td>
                <span class="tur-rozet" style="background:<?= esc($k['tur_renk']) ?>"><?= esc($k['tur_kisa']) ?></span>
                <?php
                /*
                 * MUHSGK ile SGK aynı işlemin iki parçasıdır. Hangi satırın
                 * "ana" (tek ekrandan yönetilen) hangisinin "bağlı" olduğu
                 * rozetle belirtilir ki kullanıcı ikisini ayrı iş sanmasın.
                 */
                $esBilgi = $esHarita[(int) $k['id']] ?? null;
                ?>
                <?php if ($esBilgi !== null): ?>
                  <?php if ($esBilgi['rol'] === 'ana'): ?>
                    <span class="rozet mavi es-rozet" style="font-size:10px"
                          title="SGK prim bildirgesi bu ekrandan birlikte girilir">
                      + SGK
                    </span>
                  <?php else: ?>
                    <span class="rozet gri es-rozet" style="font-size:10px"
                          title="<?= esc($esBilgi['esler'][0]['tur_kisa'] ?? 'MUHSGK') ?> ile birlikte verilir — onayı oradan da yapabilirsiniz">
                      ⇄ <?= esc($esBilgi['esler'][0]['tur_kisa'] ?? 'MUHSGK') ?> ile bağlı
                    </span>
                  <?php endif; ?>
                <?php endif; ?>
                <?php
                /*
                 * İndirim/kısıtlama rozetleri BEYANNAME TÜRÜNÜN yanında durur,
                 * mükellef adının yanında değil: aynı mükellefin KDV satırında
                 * "Bağkur" yazması anlamsız olurdu. indirimRozetleri() türü
                 * süzer; ilgisiz beyannamelerde boş dizge döner.
                 */
                $indHtml = function_exists('indirimRozetleri')
                    ? indirimRozetleri($k, $k['tur_kodu'] ?? null)
                    : '';
                ?>
                <?php if ($indHtml !== ''): ?>
                  <div class="indirim-serit"><?= $indHtml ?></div>
                <?php endif; ?>
              </td>

              <td class="kucuk-yazi">
                <?= esc($k['donem_adi']) ?>
                <?php if ($mod === 'beyan' && (int) $k['yil'] !== (int) $filtre['yil']): ?>
                  <span class="rozet mor" style="font-size:10px" title="Bu beyanname <?= (int) $k['yil'] ?> dönemine ait, <?= $filtre['yil'] ?> yılında veriliyor">
                    <?= (int) $k['yil'] ?> dönemi
                  </span>
                <?php endif; ?>
              </td>

              <td class="kucuk-yazi"><?= trTarih($k['yasal_son_tarih']) ?></td>

              <td>
                <b><?= trTarih($k['son_tarih']) ?></b>
                <?php if (! empty($k['kaydirma_nedeni'])): ?>
                  <div class="kucuk-yazi" style="color:var(--turuncu)" title="<?= esc($k['kaydirma_nedeni']) ?>">
                    ↷ <?= esc(kisalt($k['kaydirma_nedeni'], 22)) ?>
                  </div>
                <?php endif; ?>
              </td>

              <?php /*
                Kalan hücresi JS tarafından da güncellenir: durum menüsünden
                "Onaylandı" seçildiğinde sayfa yenilenmeden rozet değişmeli.
                data-gun, geri alındığında gecikme metnini yeniden kurmak için.
              */ ?>
              <td class="kalan-hucre" data-id="<?= $k['id'] ?>" data-gun="<?= (int) $kalan['gun'] ?>">
                <span class="rozet <?= $kalan['sinif'] ?>"><?= esc($kalan['metin']) ?></span>
              </td>

              <td>
                <select class="girdi durum-sec" data-id="<?= $k['id'] ?>"
                        style="padding:4px 8px;font-size:12px;min-width:118px;font-weight:600">
                  <?php foreach ($durumlar as $dk => $dv): ?>
                    <option value="<?= $dk ?>" <?= $k['durum'] === $dk ? 'selected' : '' ?>><?= esc($dv) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>

              <?php if (! empty($tahakkukYetki)): ?>
              <?php
                // Durum "Onaylandı" değilken duran tahakkuk bilgisi ATIL sayılır:
                // veri korunur ama soluk + uyarılı gösterilir (ödeme listesine girmez).
                $atilTahakkuk = $k['tahakkuk_tutari'] !== null && $k['durum'] !== 'ONAYLANDI';
              ?>
              <td class="sag tahakkuk-hucre<?= $atilTahakkuk ? ' atil' : '' ?>" data-id="<?= $k['id'] ?>">
                <?php if ($k['tahakkuk_tutari'] !== null): ?>
                  <b class="tahakkuk-deger"><?= number_format((float) $k['tahakkuk_tutari'], 2, ',', '.') ?></b>
                  <?php if ((float) $k['damga_tutari'] > 0): ?>
                    <div class="kucuk-yazi damga-satiri">
                      +<?= number_format((float) $k['damga_tutari'], 2, ',', '.') ?> damga
                    </div>
                  <?php endif; ?>
                  <?php if ($atilTahakkuk): ?>
                    <div class="kucuk-yazi atil-not"
                         title="Durum &quot;Onaylandı&quot; olmadığı için bu tutar ödeme listesine girmez. Silmek isterseniz ₺ düğmesinden tutarı boşaltabilirsiniz.">
                      ⚠ pasif
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="kucuk-yazi metin-gri tahakkuk-deger">—</span>
                <?php endif; ?>
                <button type="button" class="btn ikincil mini" style="margin-top:3px"
                        onclick="tahakkukAc(<?= $k['id'] ?>)">₺</button>
              </td>
              <?php endif; ?>

              <td class="not-hucre <?= ! empty($k['not_metni']) ? 'dolu' : '' ?>"
                  data-id="<?= $k['id'] ?>" onclick="notDuzenle(this)" title="Not eklemek için tıklayın">
                <?php if (! empty($k['not_metni'])): ?>
                  <span class="not-metin">📌 <?= esc($k['not_metni']) ?></span>
                <?php else: ?>
                  <span class="not-metin not-bos">+ not ekle</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
