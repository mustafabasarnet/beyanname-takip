<?php
/**
 * ÇOKLU SEÇİM KUTUSU (ortak parça)
 *
 * Tıklanınca açılan panelde onay kutuları sunar. Kapalıyken "3 tür seçili"
 * gibi bir özet yazar. Arama kutusu, "Tümünü Seç" ve "Temizle" içerir.
 *
 * Neden yerleşik <select multiple> değil?
 *   Tarayıcının kendi çoklu listesi Ctrl+tık gerektirir; fare ile yanlışlıkla
 *   tek seçime düşmek çok kolaydır. Büro kullanıcısı için onay kutusu daha
 *   güvenli ve öğrenmesi gereksiz bir kalıp.
 *
 * BEKLENEN DEĞİŞKENLER
 *   $cs_ad      string  Form alan adı (dizi olarak gönderilir: ad[])
 *   $cs_etiket  string  Üstteki etiket
 *   $cs_ogeler  array   [deger => görünen_ad]
 *   $cs_secili  array   Seçili değerler (string dizisi)
 *   $cs_tekil   string  Tekil ad ("tür", "durum") — özet metninde kullanılır
 *   $cs_genislik string (isteğe bağlı) kutu genişliği, öntanımlı 190px
 *
 * NOT: Bu dosya include ile çağrılır (üst görünümün değişkenleri taşınsın
 * diye); $this->include() kullanılmaz — CI4'te yerel değişkenler taşınmaz.
 */
$cs_secili    = array_map('strval', $cs_secili ?? []);
$cs_tekil     = $cs_tekil ?? 'öge';
$cs_genislik  = $cs_genislik ?? '190px';
$cs_toplam    = count($cs_ogeler);

/*
 * KRİTİK GÖMÜLÜ STİL
 *
 * Panel varsayılan olarak GİZLİ olmalı; gizleme yalnız stil.css'te
 * dursaydı, o dosya kopyalanmamış bir kurulumda panel sürekli AÇIK
 * görünür ve filtre çubuğunu dağıtırdı. Bu yüzden en kritik iki kural
 * (gizle/göster) sayfaya bir kez gömülür. Görsel ayrıntılar stil.css'te.
 */
// NOT: `static` her include'da SIFIRLANIR (aynı dosya iki kez dahil
// edildiğinde yeni bir kapsam açılır); bu yüzden global bayrak kullanılır.
$cs_stilBasildi = ! empty($GLOBALS['__coklu_stil']);
$cs_secimSay  = count($cs_secili);

// Özet metni: hiç seçilmemiş = Tümü, hepsi seçili = Tümü (N)
if ($cs_secimSay === 0 || $cs_secimSay === $cs_toplam) {
    $cs_ozet = 'Tümü';
} elseif ($cs_secimSay === 1) {
    $cs_ozet = (string) ($cs_ogeler[$cs_secili[0]] ?? '1 ' . $cs_tekil);
} else {
    $cs_ozet = $cs_secimSay . ' ' . $cs_tekil . ' seçili';
}
?>
<?php if (! $cs_stilBasildi): $GLOBALS['__coklu_stil'] = true; ?>
<style>
.coklu-sec{position:relative}
.coklu-panel{display:none}
.coklu-sec.acik .coklu-panel{display:block}
.coklu-dugme{width:100%;display:flex;align-items:center;justify-content:space-between;
  gap:6px;padding:7px 10px;font:inherit;font-size:13px;text-align:left;cursor:pointer;
  background:#fff;border:1px solid #cbd5e1;border-radius:8px}
</style>
<?php endif; ?>
<div class="form-grup">
  <label><?= esc($cs_etiket) ?></label>

  <div class="coklu-sec" data-coklu="<?= esc($cs_ad, 'attr') ?>"
       data-tekil="<?= esc($cs_tekil, 'attr') ?>" data-kirli="0"
       style="width:<?= esc($cs_genislik, 'attr') ?>">

    <button type="button" class="coklu-dugme<?= $cs_secimSay > 0 && $cs_secimSay < $cs_toplam ? ' dolu' : '' ?>"
            onclick="cokluAc(this)" aria-expanded="false">
      <span class="cs-ozet"><?= esc($cs_ozet) ?></span>
      <span class="cs-ok">▾</span>
    </button>

    <div class="coklu-panel">
      <div class="cs-ust">
        <input type="text" class="girdi cs-ara" placeholder="Ara…"
               oninput="cokluAra(this)" onclick="event.stopPropagation()">
        <div class="cs-islem">
          <button type="button" onclick="cokluTumu(this, true)">Tümünü Seç</button>
          <button type="button" onclick="cokluTumu(this, false)">Temizle</button>
        </div>
      </div>

      <div class="cs-liste">
        <?php foreach ($cs_ogeler as $cs_d => $cs_g):
            $cs_d   = (string) $cs_d;
            $cs_isa = in_array($cs_d, $cs_secili, true);
        ?>
          <label class="cs-oge" data-metin="<?= esc(mb_strtolower($cs_g, 'UTF-8'), 'attr') ?>">
            <input type="checkbox" name="<?= esc($cs_ad, 'attr') ?>[]"
                   value="<?= esc($cs_d, 'attr') ?>" <?= $cs_isa ? 'checked' : '' ?>
                   onchange="cokluDegisti(this)">
            <span><?= esc($cs_g) ?></span>
          </label>
        <?php endforeach; ?>
        <div class="cs-bos gizle">Eşleşen kayıt yok</div>
      </div>

      <div class="cs-alt">
        <button type="button" class="btn kucuk" onclick="cokluUygula(this)">Uygula</button>
      </div>
    </div>
  </div>
</div>
