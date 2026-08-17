<?php
/**
 * İNDİRİM KALEMİ LİSTESİ — PARÇA DOSYA
 *
 * Hem eğitim-sağlık hem sigorta primi için aynı tablo kullanılır.
 *
 * DİKKAT: $this->include() ÜST GÖRÜNÜMÜN YEREL DEĞİŞKENLERİNİ TAŞIMAZ.
 * Bu yüzden burada normal PHP include kullanılır ve gerekli değişkenler
 * çağırmadan önce tanımlanır:
 *   $kalem     'egitim_saglik' | 'sigorta'
 *   $baslik    kart başlığı
 *   $ikon      başlık ikonu
 *   $satirlar  kalem satırları
 *   $turler    tür seçenekleri [deger => etiket]
 *   $tavan     mevzuat üst sınırı (₺)
 *   $oran      üst sınır yüzdesi
 *   $inen      hesaba giren tutar
 *   $asim      sınır yüzünden inmeyen tutar
 *   $musavir, $yil
 */
$toplam = 0.0;

foreach ($satirlar as $s) {
    $toplam += (float) $s['tutar'];
}
?>

<div class="gv-kart kalem-kart" id="<?= $kalem ?>" style="margin-top:16px">
  <div class="gv-kart-bas">
    <span><?= $ikon ?> <?= esc($baslik) ?> — <?= (int) $yil ?></span>
    <span class="kucuk-yazi" style="font-weight:400">
      <?= count($satirlar) ?> belge · toplam
      <b><?= number_format($toplam, 2, ',', '.') ?> ₺</b>
    </span>
  </div>

  <div class="gv-kart-govde">

    <!-- Sınır özeti -->
    <div class="kalem-ozet">
      <div>
        <div class="et">Liste Toplamı</div>
        <div class="dg"><?= number_format($toplam, 2, ',', '.') ?></div>
      </div>
      <div>
        <div class="et">Mevzuat Üst Sınırı (%<?= (int) $oran ?>)</div>
        <div class="dg"><?= number_format($tavan, 2, ',', '.') ?></div>
      </div>
      <div class="<?= $asim > 0 ? 'kirmizi' : 'yesil' ?>">
        <div class="et">Hesaba Giren</div>
        <div class="dg"><?= number_format($inen, 2, ',', '.') ?></div>
      </div>
    </div>

    <?php if ($asim > 0): ?>
      <div class="gv-asim" style="margin:0 0 10px">
        ⚠ Liste toplamı mevzuat sınırını aşıyor:
        <b><?= number_format($asim, 2, ',', '.') ?> ₺</b> indirilemedi.
        Sınır, beyan edilecek kârın %<?= (int) $oran ?>'idir.
      </div>
    <?php endif; ?>

    <!-- Kalem tablosu -->
    <div class="tablo-sar">
      <table class="kalem-tablo">
        <thead>
          <tr>
            <th style="width:11%">Tarih</th>
            <th style="width:13%">Tür</th>
            <th>Açıklama</th>
            <th class="sag" style="width:16%">Tutar (₺)</th>
            <th style="width:9%"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($satirlar as $s): ?>
            <tr data-kalem-satir="<?= (int) $s['id'] ?>">
              <td class="tarih"><?= trTarih($s['tarih']) ?></td>
              <td>
                <span class="kalem-rozet t-<?= esc($s['tur']) ?>">
                  <?= esc($turler[$s['tur']] ?? $s['tur']) ?>
                </span>
              </td>
              <td class="aciklama"><?= esc($s['aciklama'] ?: '—') ?></td>
              <td class="sag tutar"><?= number_format((float) $s['tutar'], 2, ',', '.') ?></td>
              <td class="islem">
                <button type="button" class="btn ikincil mini kalem-duzenle"
                        data-id="<?= (int) $s['id'] ?>"
                        data-tarih="<?= esc($s['tarih']) ?>"
                        data-tur="<?= esc($s['tur']) ?>"
                        data-aciklama="<?= esc($s['aciklama'] ?? '') ?>"
                        data-tutar="<?= number_format((float) $s['tutar'], 2, ',', '.') ?>"
                        data-hedef="<?= $kalem ?>">Düzenle</button>
                <a href="<?= site_url('gelir-vergisi/kalem-sil/' . (int) $s['id']) ?>"
                   class="btn kirmizi mini"
                   onclick="return confirm('Bu kalem silinsin mi?\n<?= esc(trTarih($s['tarih']) . ' · ' . number_format((float) $s['tutar'], 2, ',', '.') . ' ₺', 'js') ?>')">Sil</a>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if ($satirlar === []): ?>
            <tr class="bos">
              <td colspan="5">
                Henüz belge girilmedi. Aşağıdaki satırdan ekleyebilirsiniz.
                <br><span class="kucuk-yazi">
                  Liste boşken, yukarıdaki tek tutarlı giriş kutusu kullanılır.
                </span>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>

        <?php if ($satirlar !== []): ?>
          <tfoot>
            <tr>
              <td colspan="3">TOPLAM (<?= count($satirlar) ?> belge)</td>
              <td class="sag"><?= number_format($toplam, 2, ',', '.') ?></td>
              <td></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>

    <!-- Ekleme / düzenleme satırı -->
    <form method="post" action="<?= site_url('gelir-vergisi/kalem-kaydet') ?>"
          class="kalem-form" id="form-<?= $kalem ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="musavir_id" value="<?= (int) $musavir['id'] ?>">
      <input type="hidden" name="yil" value="<?= (int) $yil ?>">
      <input type="hidden" name="kalem" value="<?= $kalem ?>">
      <input type="hidden" name="id" value="" data-alan="id">

      <div class="alan">
        <label>Tarih</label>
        <input type="date" name="tarih" class="girdi" data-alan="tarih"
               value="<?= date('Y-m-d', strtotime(min(date('Y-m-d'), $yil . '-12-31'))) ?>" required>
      </div>

      <div class="alan">
        <label>Tür</label>
        <select name="tur" data-alan="tur">
          <?php foreach ($turler as $tk => $tv): ?>
            <option value="<?= $tk ?>"><?= esc($tv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="alan genis">
        <label>Açıklama</label>
        <input type="text" name="aciklama" class="girdi" data-alan="aciklama"
               maxlength="250" placeholder="Örn: Özel okul taksiti, hastane faturası…">
      </div>

      <div class="alan">
        <label>Tutar (₺)</label>
        <input type="text" name="tutar" class="girdi para" data-alan="tutar"
               inputmode="decimal" placeholder="0,00" required>
      </div>

      <div class="alan dugme">
        <button type="submit" class="btn kucuk" data-alan="gonder">+ Ekle</button>
        <button type="button" class="btn ikincil kucuk kalem-iptal" style="display:none">Vazgeç</button>
      </div>
    </form>

    <?php if ($satirlar === []): ?>
      <details class="kalem-kopya">
        <summary>📋 Başka yıldan kopyala</summary>
        <form method="post" action="<?= site_url('gelir-vergisi/kalem-kopyala') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="musavir_id" value="<?= (int) $musavir['id'] ?>">
          <input type="hidden" name="kalem" value="<?= $kalem ?>">
          <input type="hidden" name="hedef_yil" value="<?= (int) $yil ?>">
          <label class="kucuk-yazi">Kaynak yıl</label>
          <select name="kaynak_yil">
            <?php foreach (yilSecenekleri() as $y): ?>
              <?php if ($y === (int) $yil) { continue; } ?>
              <option value="<?= $y ?>" <?= $y === (int) $yil - 1 ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn ikincil kucuk">Kopyala</button>
          <span class="kucuk-yazi">Tarihler bu yıla kaydırılır, tutarlar aynı kalır.</span>
        </form>
      </details>
    <?php endif; ?>
  </div>
</div>
