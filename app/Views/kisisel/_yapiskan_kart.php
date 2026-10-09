<?php
/*
 * TEK YAPIŞKAN KART (Faz 1 + Faz 2)
 * Beklenen: $n (kart satırı), $renkler (palet), $bugun (Y-m-d, opsiyonel)
 *
 * Faz 2: başlık (en çok 40), hatırlatma tarihi + rozet, ✓ Tamam, 🗃 arşiv.
 */
use App\Models\StickyNotModel;

$bugun       = $bugun ?? date('Y-m-d');
$renkAnahtar = StickyNotModel::renkGecerli((string) $n['renk']) ? (string) $n['renk'] : 'sari';
$p           = $renkler[$renkAnahtar];
$egim        = StickyNotModel::egim((int) $n['id']);
$uzunluk     = mb_strlen((string) $n['metin']);
$sabit       = (int) $n['sabit'] === 1;

$hatTarih = $n['hatirlat_tarih'] ?? null;
$hatTamam = $n['hatirlat_tamam_at'] ?? null;
$durum    = StickyNotModel::hatirlatmaDurumu($hatTarih, $hatTamam, $bugun);

// Rozet metni (sunucuda tek yerde üretilir)
$hatEtiket = match ($durum) {
    'yok'    => '⏰ Hatırlatma',
    'tamam'  => '✓ ' . trTarih($hatTarih) . ' · tamamlandı',
    'gecmis' => '⏰ ' . trTarih($hatTarih) . ' · geçti',
    'bugun'  => '⏰ Bugün',
    'yakin'  => '⏰ ' . trTarih($hatTarih) . ' · ' . (int) round((strtotime($hatTarih) - strtotime($bugun)) / 86400) . ' gün',
    default  => '⏰ ' . trTarih($hatTarih),
};
$tamamGoster = $hatTarih !== null && $durum !== 'tamam';
?>
<div class="yk-kart" draggable="true"
     data-id="<?= (int) $n['id'] ?>"
     data-sabit="<?= $sabit ? 1 : 0 ?>"
     data-renk="<?= esc($renkAnahtar, 'attr') ?>"
     data-hat-durum="<?= esc($durum, 'attr') ?>"
     style="--kart:<?= esc($p['kart'], 'attr') ?>;--bant:<?= esc($p['bant'], 'attr') ?>;--egim:<?= number_format($egim, 2, '.', '') ?>deg">
  <span class="yk-raptiye" aria-hidden="true"></span>
  <div class="yk-bant">
    <input type="text" class="yk-baslik-alan" maxlength="<?= StickyNotModel::MAX_BASLIK ?>"
           placeholder="Başlık ekle" value="<?= esc((string) ($n['baslik'] ?? ''), 'attr') ?>"
           data-alan="baslik" aria-label="Kart başlığı">
    <span class="yk-ikonlar">
      <button type="button" class="yk-ikon<?= $sabit ? ' acik' : '' ?>" data-islem="sabit"
              title="<?= $sabit ? 'Sabitlemeyi kaldır' : 'Sabitle (en üstte tut)' ?>">📌</button>
      <button type="button" class="yk-ikon" data-islem="renk" title="Renk değiştir">🎨</button>
      <button type="button" class="yk-ikon" data-islem="arsiv" title="Arşive taşı (geri alınabilir)">🗃</button>
      <button type="button" class="yk-ikon" data-islem="sil" title="Notu sil">🗑</button>
    </span>
  </div>
  <div class="yk-govde" contenteditable="true" spellcheck="false" data-alan="metin"
       aria-label="Not metni"><?= esc((string) $n['metin']) ?></div>
  <div class="yk-alt">
    <button type="button" class="yk-hat yk-hat-<?= esc($durum, 'attr') ?>" data-islem="hat"
            title="Hatırlatma tarihini ayarla"><?= esc($hatEtiket) ?></button>
    <?php if ($tamamGoster): ?>
      <button type="button" class="yk-tamam" data-islem="tamam" title="Hatırlatmayı tamamlandı olarak işaretle">✓ Tamam</button>
    <?php endif; ?>
    <span class="yk-say<?= $uzunluk > StickyNotModel::MAX_METIN ? ' asim' : '' ?>"><?= $uzunluk ?>/<?= StickyNotModel::MAX_METIN ?></span>
    <span class="yk-kaydet" aria-live="polite"></span>
  </div>
  <div class="yk-renkmenu" role="menu">
    <?php foreach ($renkler as $anahtar => $r): ?>
      <button type="button" class="yk-renknokta<?= $anahtar === $renkAnahtar ? ' secili' : '' ?>"
              data-renk="<?= esc($anahtar, 'attr') ?>" title="<?= esc($r['ad'], 'attr') ?>"
              style="--nokta:<?= esc($r['kart'], 'attr') ?>"></button>
    <?php endforeach; ?>
  </div>
  <div class="yk-hatsec" role="dialog" aria-label="Hatırlatma tarihi">
    <label class="yk-hatsec-etiket">Hatırlatma tarihi</label>
    <input type="date" class="yk-hatsec-tarih" value="<?= esc((string) ($hatTarih ?? ''), 'attr') ?>">
    <div class="yk-hatsec-dugme">
      <button type="button" class="yk-kucuk" data-islem="hat-kaydet">Kaydet</button>
      <button type="button" class="yk-kucuk ik" data-islem="hat-sil">Kaldır</button>
    </div>
  </div>
</div>
