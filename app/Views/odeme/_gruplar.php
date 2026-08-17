<?php
/**
 * Ödeme listesi — MÜKELLEF GRUBU PARÇASI (kompakt / katlanabilir)
 *
 * Hem ilk yüklemede hem sonsuz kaydırmada (Odeme::dahaFazla) kullanılır;
 * böylece sonradan gelen gruplar ilk yüklenenlerle birebir aynı görünür.
 *
 * Tasarım: her mükellef TEK SATIR başlık (ünvan + kalem sayısı + toplam +
 * ödeme durumu). Detay tablosu tıklanınca açılır. Beyannameler onaylandıkça
 * sayfanın uzamasını bu yapı engeller.
 *
 * Beklenen değişkenler: $gruplar, $filtre, $qs
 * (Parça dosyalar üst görünümün yerel değişkenlerini görmez; savunmacı yazılır.)
 */
$qs     = $qs     ?? '';
$filtre = $filtre ?? [];
?>
<?php foreach ($gruplar as $g):
    $mid      = (int) $g['mukellef']['id'];
    // Toplamlar MODELDE hesaplanır; görünüm yeniden toplamaz.
    //   'genel'     → yalnızca beyannameler
    //   'ozel'      → beyanname dışı kalemler
    //   'genel_tum' → ikisinin toplamı
    // (Eskiden burada $g['toplam']['genel'] + $ozelTop yapılıyordu; model
    //  özel kalemi zaten 'genel'e eklediği için tutar MÜKERRER çıkıyordu.)
    $ozelTop   = (float) ($g['toplam']['ozel'] ?? 0);
    $ozelAdet  = (int) ($g['toplam']['ozel_adet'] ?? count($g['ozel'] ?? []));
    $genelTop  = (float) ($g['toplam']['genel_tum'] ?? $g['toplam']['genel']);
    $kalemAdet = (int) $g['toplam']['adet'] + $ozelAdet;

    // Ödeme durumu: hepsi ödendiyse grup "tamam" sayılır
    $odenenAdet = 0;

    foreach ($g['satirlar'] as $s) {
        if ((int) $s['odendi'] === 1) { $odenenAdet++; }
    }

    foreach ($g['ozel'] ?? [] as $o) {
        if ((int) $o['odendi'] === 1) { $odenenAdet++; }
    }

    $tamam = $kalemAdet > 0 && $odenenAdet === $kalemAdet;
    $kismi = $odenenAdet > 0 && ! $tamam;
?>
  <div class="od-grup<?= $tamam ? ' od-tamam' : '' ?>" data-grup="<?= $mid ?>">
    <!-- ---------- KATLANABİLİR BAŞLIK ---------- -->
    <button type="button" class="od-bas" aria-expanded="false" data-hedef="od-g<?= $mid ?>">
      <span class="od-ok" aria-hidden="true">▸</span>

      <span class="od-ad">
        <?= esc(kisalt($g['mukellef']['unvan'], 42)) ?>
        <?php if ($tamam): ?>
          <span class="od-rozet tamam" title="Tüm kalemler ödendi">✓</span>
        <?php elseif ($kismi): ?>
          <span class="od-rozet kismi" title="<?= $odenenAdet ?>/<?= $kalemAdet ?> kalem ödendi">
            <?= $odenenAdet ?>/<?= $kalemAdet ?>
          </span>
        <?php endif; ?>
        <span class="od-vkn"><?= esc($g['mukellef']['vkn']) ?></span>
      </span>

      <span class="od-adet"><?= $kalemAdet ?> kalem</span>

      <?php if ($ozelAdet > 0): ?>
        <span class="od-rozet ozel" title="<?= $ozelAdet ?> özel ödeme kalemi">+<?= $ozelAdet ?></span>
      <?php endif; ?>

      <span class="od-tutar"><?= number_format($genelTop, 2, ',', '.') ?> ₺</span>
    </button>

    <!-- ---------- DETAY (varsayılan kapalı) ---------- -->
    <div class="od-govde" id="od-g<?= $mid ?>" hidden>
      <div class="od-ust">
        <span class="kucuk-yazi">
          <?= esc($g['mukellef']['vkn']) ?>
          <?= $g['mukellef']['vergi_dairesi'] ? ' • ' . esc($g['mukellef']['vergi_dairesi']) : '' ?>
        </span>
        <a href="<?= site_url('odeme/bildirim/' . $mid . '?' . $qs) ?>"
           target="_blank" class="btn ikincil mini">🖨️ Bildirim</a>
      </div>

      <div class="tablo-sar">
        <table class="tablo od-tablo">
          <thead>
            <tr>
              <th>Beyanname</th>
              <th>Dönem</th>
              <th>Son Tarih</th>
              <th class="sag">Tahakkuk</th>
              <th class="sag">Damga</th>
              <th class="sag">Ödenecek</th>
              <th class="orta" style="width:70px">Ödendi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($g['satirlar'] as $s): ?>
            <tr data-satir="<?= $s['id'] ?>" class="<?= (int) $s['odendi'] === 1 ? 'od-odendi' : '' ?>">
              <td><span class="tur-rozet" style="background:<?= esc($s['tur_renk']) ?>"><?= esc($s['tur_kisa']) ?></span></td>
              <td class="kucuk-yazi"><?= esc($s['donem_adi']) ?></td>
              <td class="kucuk-yazi">
                <?= trTarih($s['efektif_odeme_tarihi'] ?? $s['son_tarih']) ?>
                <?php if (! empty($s['odeme_son_tarih']) && $s['odeme_son_tarih'] !== $s['son_tarih']): ?>
                  <span class="kucuk-yazi" style="color:var(--ana)"
                        title="Beyan/onay son günü <?= trTarih($s['son_tarih']) ?>">
                    (beyan <?= trTarih($s['son_tarih']) ?>)
                  </span>
                <?php endif; ?>
              </td>
              <td class="sag"><?= number_format((float) $s['tahakkuk_tutari'], 2, ',', '.') ?></td>
              <td class="sag" style="color:var(--turuncu)">
                <?= (float) $s['hesaplanan_damga'] > 0 ? number_format((float) $s['hesaplanan_damga'], 2, ',', '.') : '—' ?>
              </td>
              <td class="sag kalin"><?= number_format((float) $s['odenecek'], 2, ',', '.') ?></td>
              <td class="orta">
                <input type="checkbox" class="odendi-kutu" data-id="<?= $s['id'] ?>"
                       <?= (int) $s['odendi'] === 1 ? 'checked' : '' ?>
                       title="<?= $s['odeme_tarihi'] ? 'Ödeme: ' . trTarih($s['odeme_tarihi']) : 'Ödendi olarak işaretle' ?>">
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="od-ara">
              <td colspan="3" class="sag">BEYANNAME ARA TOPLAMI</td>
              <td class="sag"><?= number_format($g['toplam']['tahakkuk'], 2, ',', '.') ?></td>
              <td class="sag" style="color:var(--turuncu)"><?= number_format($g['toplam']['damga'], 2, ',', '.') ?></td>
              <td class="sag" style="color:var(--yesil)"><?= number_format($g['toplam']['genel'], 2, ',', '.') ?> ₺</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <?php if (! empty($g['ozel'])): ?>
        <div class="tablo-sar od-ozel">
          <table class="tablo od-tablo">
            <thead>
              <tr><th colspan="6" class="od-ozel-bas">➕ Diğer Ödemeler (beyanname dışı)</th></tr>
              <tr>
                <th>Kalem</th><th>Dönem</th><th>Son Tarih</th>
                <th class="sag">Tutar</th><th class="orta" style="width:70px">Ödendi</th>
                <th class="sag" style="width:104px">İşlem</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($g['ozel'] as $o): ?>
              <tr class="<?= (int) $o['odendi'] === 1 ? 'od-odendi' : '' ?>">
                <td>
                  <b><?= esc($o['baslik']) ?></b>
                  <?php if ($o['tekrar'] === 'AYLIK'): ?>
                    <span class="rozet mavi" style="font-size:10px"
                          title="Her ay otomatik oluşur<?= ! empty($o['tekrar_bitis'])
                              ? ' — ' . trTarih($o['tekrar_bitis']) . ' tarihine kadar' : ' (süresiz)' ?>">
                      🔁<?= ! empty($o['tekrar_bitis']) ? ' → ' . trTarih($o['tekrar_bitis']) : '' ?>
                    </span>
                    <a href="<?= site_url('odeme/tekrar-durdur/' . $o['id']) ?>"
                       class="kucuk-yazi" style="color:var(--kirmizi,#dc2626)"
                       data-onay="'<?= esc($o['baslik'], 'js') ?>' kaleminin aylık tekrarı durdurulacak. Gelecek aylardaki ödenmemiş kopyaları da silinecek. Onaylıyor musunuz?">
                      durdur
                    </a>
                  <?php endif; ?>
                  <?php if (! empty($o['aciklama'])): ?>
                    <div class="kucuk-yazi"><?= esc($o['aciklama']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="kucuk-yazi"><?= esc($o['donem_etiketi'] ?: '—') ?></td>
                <td class="kucuk-yazi"><?= trTarih($o['son_tarih']) ?></td>
                <td class="sag kalin"><?= number_format((float) $o['tutar'], 2, ',', '.') ?></td>
                <td class="orta">
                  <input type="checkbox" class="ozel-odendi" data-id="<?= $o['id'] ?>"
                         <?= (int) $o['odendi'] === 1 ? 'checked' : '' ?>>
                </td>
                <td class="sag" style="white-space:nowrap">
                  <button type="button" class="btn ikincil mini"
                          onclick='ozelAc(<?= json_encode($o, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Düzenle</button>
                  <a href="<?= site_url('odeme/ozel-sil/' . $o['id']) ?>" class="btn kirmizi mini"
                     data-onay="Bu ödeme kalemi silinsin mi?">Sil</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="od-ara">
                <td colspan="3" class="sag">DİĞER ÖDEMELER ARA TOPLAMI</td>
                <td class="sag" style="color:var(--mor)"><?= number_format($ozelTop, 2, ',', '.') ?></td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>

      <div class="od-genel">
        <b>MÜKELLEF GENEL TOPLAMI</b>
        <b style="font-size:16px;color:var(--yesil)"><?= number_format($genelTop, 2, ',', '.') ?> ₺</b>
      </div>
    </div>
  </div>
<?php endforeach; ?>
