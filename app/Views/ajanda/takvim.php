<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php include APPPATH . 'Views/ajanda/_stil.php'; ?>

<?php
$ilk     = sprintf('%04d-%02d-01', $yil, $ay);
$gunSay  = (int) date('t', strtotime($ilk));
$bugun   = date('Y-m-d');

// Haftanın hangi gününde başlıyor? (Pazartesi = 0)
$baslangic = ((int) date('N', strtotime($ilk))) - 1;

$oncekiAy = date('Y-n', strtotime($ilk . ' -1 month'));
$sonrakiAy = date('Y-n', strtotime($ilk . ' +1 month'));
[$oy, $oa] = explode('-', $oncekiAy);
[$sy, $sa] = explode('-', $sonrakiAy);

/** Filtreyi koruyarak ay bağlantısı üretir */
$ayLink = static function ($y, $a) use ($filtre) {
    $p = array_filter($filtre, static fn ($v) => $v !== null && $v !== '');
    $p['yil'] = (int) $y;
    $p['ay']  = (int) $a;

    return site_url('ajanda/takvim') . '?' . http_build_query($p);
};

$haftaGun = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
?>

<div class="aj-ay-bar">
  <h2>📅 <?= ayAdi($ay) ?> <?= $yil ?></h2>

  <div class="btn-grup">
    <a href="<?= $ayLink($oy, $oa) ?>" class="btn ikincil kucuk">‹ Önceki</a>
    <a href="<?= $ayLink(date('Y'), date('n')) ?>" class="btn ikincil kucuk">Bugün</a>
    <a href="<?= $ayLink($sy, $sa) ?>" class="btn ikincil kucuk">Sonraki ›</a>
  </div>

  <div class="btn-grup" style="margin-left:auto">
    <a href="<?= site_url('ajanda/yeni') ?>" class="btn kucuk">+ Yeni Kayıt</a>
    <a href="<?= site_url('ajanda') ?>" class="btn ikincil kucuk">📋 Liste</a>
  </div>
</div>

<!-- Sayaç şeridi -->
<div class="aj-sayac">
  <a href="<?= site_url('ajanda?durum=BEKLIYOR&bit=' . date('Y-m-d', strtotime('-1 day'))) ?>"
     class="<?= $sayaclar['gecikmis'] > 0 ? 'kirmizi' : '' ?>">
    <div class="et">Gecikmiş</div>
    <div class="dg <?= $sayaclar['gecikmis'] > 0 ? 'kirmizi' : '' ?>"><?= (int) $sayaclar['gecikmis'] ?></div>
  </a>
  <a href="<?= site_url('ajanda?durum=BEKLIYOR&bas=' . $bugun . '&bit=' . $bugun) ?>"
     class="<?= $sayaclar['bugun'] > 0 ? 'turuncu' : '' ?>">
    <div class="et">Bugün</div>
    <div class="dg <?= $sayaclar['bugun'] > 0 ? 'turuncu' : '' ?>"><?= (int) $sayaclar['bugun'] ?></div>
  </a>
  <a href="<?= site_url('ajanda?durum=BEKLIYOR&bas=' . $bugun) ?>">
    <div class="et">Yaklaşan</div>
    <div class="dg mavi"><?= (int) $sayaclar['yaklasan'] ?></div>
  </a>
  <a href="<?= site_url('ajanda?durum=BEKLIYOR') ?>">
    <div class="et">Bu Ayda</div>
    <div class="dg"><?= array_sum(array_map('count', $gunler)) ?></div>
  </a>
</div>

<?php
$hedef   = 'ajanda/takvim';
$takvimMi = true;
include APPPATH . 'Views/ajanda/_filtre.php';
?>

<div class="kart">
  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="aj-takvim">
        <thead>
          <tr>
            <?php foreach ($haftaGun as $g): ?>
              <th><?= $g ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $hucre = 0;
          echo '<tr>';

          // Ayın ilk gününe kadar boş hücreler
          for ($b = 0; $b < $baslangic; $b++) {
              echo '<td class="bos"></td>';
              $hucre++;
          }

          for ($g = 1; $g <= $gunSay; $g++) {
              $tarih   = sprintf('%04d-%02d-%02d', $yil, $ay, $g);
              $olaylar = $gunler[$tarih] ?? [];
              $haftaSonu = in_array((int) date('N', strtotime($tarih)), [6, 7], true);

              $sinif = [];

              if ($tarih === $bugun) {
                  $sinif[] = 'bugun';
              }

              if ($haftaSonu) {
                  $sinif[] = 'hafta-sonu';
              }

              echo '<td class="' . implode(' ', $sinif) . '">';
              echo '<div class="aj-gun-no"><span>' . $g . '</span>';
              echo '<a href="' . site_url('ajanda/yeni?tarih=' . $tarih)
                 . '" class="aj-gun-ekle" title="Bu güne kayıt ekle">+</a></div>';

              // En çok 3 olay göster, gerisini "+N daha" ile listeye yolla
              foreach (array_slice($olaylar, 0, 3) as $o) {
                  $kapali = $o['durum'] !== 'BEKLIYOR' ? ' kapali' : '';
                  $saat   = ! empty($o['saat']) ? '<span class="s">' . substr($o['saat'], 0, 5) . '</span>' : '';

                  echo '<a href="' . site_url('ajanda/detay/' . (int) $o['id']) . '"'
                     . ' class="aj-olay' . $kapali . '"'
                     . ' style="background:' . esc($o['renk_efektif'], 'attr') . '"'
                     . ' title="' . esc($o['baslik'], 'attr') . '">'
                     . $saat . esc(kisalt($o['baslik'], 22)) . '</a>';
              }

              if (count($olaylar) > 3) {
                  echo '<a class="aj-daha" href="'
                     . site_url('ajanda?bas=' . $tarih . '&bit=' . $tarih) . '">+'
                     . (count($olaylar) - 3) . ' daha</a>';
              }

              echo '</td>';
              $hucre++;

              if ($hucre % 7 === 0 && $g < $gunSay) {
                  echo '</tr><tr>';
              }
          }

          // Satırı tamamla
          while ($hucre % 7 !== 0) {
              echo '<td class="bos"></td>';
              $hucre++;
          }

          echo '</tr>';
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<p class="kucuk-yazi" style="margin-top:10px;line-height:1.6">
  Renkler <b>önceliği</b> gösterir (kayıtta özel renk seçildiyse o kullanılır).
  Bir güne kayıt eklemek için hücrenin sağ üstündeki <b>+</b> işaretine,
  ayrıntı için kaydın üzerine tıklayın. Bir günde 3'ten çok iş varsa
  <b>+N daha</b> bağlantısı o günün listesini açar.
</p>

<?= $this->endSection() ?>
