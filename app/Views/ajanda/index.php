<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php include APPPATH . 'Views/ajanda/_stil.php'; ?>

<?php
/** Filtre bağlantısı üretir (sayaç kartları için) */
$fq = static function (array $ek) {
    return site_url('ajanda') . '?' . http_build_query(array_filter($ek,
        static fn ($v) => $v !== null && $v !== ''));
};
$bugun = date('Y-m-d');
?>

<!-- ============ ÜST ARAÇ ÇUBUĞU ============ -->
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
  <h2 style="margin:0">🗓️ Ajanda</h2>
  <span class="kucuk-yazi"><?= (int) $toplamKayit ?> kayıt</span>

  <div class="btn-grup" style="margin-left:auto">
    <a href="<?= site_url('ajanda/yeni') ?>" class="btn kucuk">+ Yeni Kayıt</a>
    <a href="<?= site_url('ajanda/takvim') ?>" class="btn ikincil kucuk">📅 Takvim</a>
    <?php $qs = http_build_query(array_filter($filtre, static fn ($v) => $v !== null && $v !== '')); ?>
    <a href="<?= site_url('ajanda/yazdir?' . $qs) ?>" target="_blank" class="btn ikincil kucuk">🖨️ Yazdır</a>
  </div>
</div>

<!-- ============ SAYAÇLAR ============ -->
<div class="aj-sayac">
  <a href="<?= $fq(['durum' => 'BEKLIYOR', 'bit' => date('Y-m-d', strtotime('-1 day'))]) ?>"
     class="<?= $sayaclar['gecikmis'] > 0 ? 'kirmizi' : '' ?>">
    <div class="et">Gecikmiş</div>
    <div class="dg <?= $sayaclar['gecikmis'] > 0 ? 'kirmizi' : '' ?>"><?= (int) $sayaclar['gecikmis'] ?></div>
  </a>
  <a href="<?= $fq(['durum' => 'BEKLIYOR', 'bas' => $bugun, 'bit' => $bugun]) ?>"
     class="<?= $sayaclar['bugun'] > 0 ? 'turuncu' : '' ?>">
    <div class="et">Bugün</div>
    <div class="dg <?= $sayaclar['bugun'] > 0 ? 'turuncu' : '' ?>"><?= (int) $sayaclar['bugun'] ?></div>
  </a>
  <a href="<?= $fq(['durum' => 'BEKLIYOR', 'bas' => $bugun]) ?>">
    <div class="et">Yaklaşan</div>
    <div class="dg mavi"><?= (int) $sayaclar['yaklasan'] ?></div>
  </a>
  <a href="<?= $fq(['durum' => 'YAPILDI']) ?>">
    <div class="et">Tamamlanan</div>
    <div class="dg yesil">✓</div>
  </a>
</div>

<?php
// Filtre parçası — normal include; değişkenler yukarıda hazır
$hedef = 'ajanda';
include APPPATH . 'Views/ajanda/_filtre.php';
?>

<!-- ============ LİSTE ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>Kayıtlar</h2>
    <?php if ($toplamSayfa > 1): ?>
      <span class="kucuk-yazi">Sayfa <?= $sayfa ?> / <?= $toplamSayfa ?></span>
    <?php endif; ?>
  </div>

  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="aj-tablo">
        <thead>
          <tr>
            <th style="width:11%">Tarih</th>
            <th>İş</th>
            <th style="width:11%">Öncelik</th>
            <th style="width:13%">Görünürlük</th>
            <th style="width:10%">Durum</th>
            <th style="width:14%"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($kayitlar as $k): ?>
            <?php
              $sinif = $k['durum'] !== 'BEKLIYOR' ? 'kapali'
                     : ($k['gecikmis'] ? 'gecikmis' : ($k['bugun'] ? 'bugun' : ''));
            ?>
            <tr class="<?= $sinif ?>" data-aj-satir="<?= (int) $k['id'] ?>">
              <td>
                <span class="aj-tarih"><?= trTarih($k['tarih']) ?></span>
                <?php if (! empty($k['saat'])): ?>
                  <span class="aj-saat">🕐 <?= substr($k['saat'], 0, 5) ?></span>
                <?php endif; ?>
                <?php if ($k['gecikmis']): ?>
                  <span class="aj-rozet gec" style="margin-top:4px;display:inline-block">
                    <?= abs($k['kalan_gun']) ?> gün geçti
                  </span>
                <?php elseif ($k['bugun'] && $k['durum'] === 'BEKLIYOR'): ?>
                  <span class="aj-rozet acil" style="margin-top:4px;display:inline-block">BUGÜN</span>
                <?php endif; ?>
              </td>

              <td>
                <span class="aj-serit" style="background:<?= esc($k['renk_efektif']) ?>"></span>
                <a href="<?= site_url('ajanda/detay/' . (int) $k['id']) ?>" class="aj-baslik">
                  <?= esc($k['baslik']) ?>
                </a>
                <?php if (! empty($k['etiket'])): ?>
                  <span class="aj-rozet etiket"><?= esc($k['etiket']) ?></span>
                <?php endif; ?>
                <?php if ($k['tekrar'] !== 'yok'): ?>
                  <span class="aj-rozet etiket" title="Tekrarlı iş">🔁</span>
                <?php endif; ?>
                <?php if ($k['ek_sayisi'] > 0): ?>
                  <span class="aj-rozet etiket" title="Dosya eki">📎 <?= $k['ek_sayisi'] ?></span>
                <?php endif; ?>

                <div class="aj-alt">
                  <?php if (! empty($k['mukellef_unvan'])): ?>
                    🏢 <?= esc(kisalt($k['mukellef_unvan'], 34)) ?> ·
                  <?php endif; ?>
                  <?php if (! empty($k['atanan_adi'])): ?>
                    👤 <?= esc($k['atanan_adi']) ?> ·
                  <?php endif; ?>
                  <?= esc($k['olusturan_adi']) ?> oluşturdu
                </div>
              </td>

              <td>
                <span class="aj-rozet <?= esc($k['oncelik']) ?>">
                  <?= esc($oncelikler[$k['oncelik']] ?? $k['oncelik']) ?>
                </span>
              </td>

              <td>
                <span class="aj-rozet g-<?= esc($k['gorunurluk']) ?>">
                  <?php
                  echo match ($k['gorunurluk']) {
                      'kisisel' => '🔒 Kişisel',
                      'genel'   => '👥 Genel',
                      'gorev'   => '📌 Görev',
                      'musavir' => '👨‍💼 ' . kisalt((string) $k['musavir_adi'], 14),
                      default   => esc($k['gorunurluk']),
                  };
                  ?>
                </span>
              </td>

              <td>
                <span class="aj-rozet d-<?= esc($k['durum']) ?>" data-aj-durum="<?= (int) $k['id'] ?>">
                  <?= esc($durumlar[$k['durum']] ?? $k['durum']) ?>
                </span>
              </td>

              <td>
                <div class="aj-islem">
                  <?php if ($k['durum'] === 'BEKLIYOR'): ?>
                    <button type="button" class="btn yesil mini aj-yapildi"
                            data-id="<?= (int) $k['id'] ?>">✓ Yapıldı</button>
                  <?php else: ?>
                    <button type="button" class="btn ikincil mini aj-geri"
                            data-id="<?= (int) $k['id'] ?>">↺ Aç</button>
                  <?php endif; ?>
                  <a href="<?= site_url('ajanda/detay/' . (int) $k['id']) ?>"
                     class="btn ikincil mini">Aç</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if ($kayitlar === []): ?>
            <tr>
              <td colspan="6" style="text-align:center;padding:34px;color:var(--gri-500,#64748b)">
                <div style="font-size:32px;margin-bottom:6px">🗓️</div>
                Kayıt bulunamadı.
                <br><a href="<?= site_url('ajanda/yeni') ?>">İlk ajanda kaydını oluşturun</a>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($toplamSayfa > 1): ?>
  <?php
  $sayfaLink = static function (int $s) use ($filtre) {
      $p = array_filter($filtre, static fn ($v) => $v !== null && $v !== '');
      $p['sayfa'] = $s;

      return site_url('ajanda') . '?' . http_build_query($p);
  };
  ?>
  <div style="display:flex;gap:8px;justify-content:center;align-items:center;margin-top:14px">
    <a href="<?= $sayfaLink(max(1, $sayfa - 1)) ?>"
       class="btn ikincil kucuk <?= $sayfa <= 1 ? 'devre-disi' : '' ?>">‹ Önceki</a>
    <span class="kucuk-yazi">Sayfa <?= $sayfa ?> / <?= $toplamSayfa ?></span>
    <a href="<?= $sayfaLink(min($toplamSayfa, $sayfa + 1)) ?>"
       class="btn ikincil kucuk <?= $sayfa >= $toplamSayfa ? 'devre-disi' : '' ?>">Sonraki ›</a>
  </div>
<?php endif; ?>

<script>
(function () {
  'use strict';

  // Mevcut modüllerdeki kalıp: CSRF adı/değeri sunucudan gömülür,
  // yanıt yeni değer döndürürse saklanır (tek kullanımlık olabilir).
  var CSRF_AD  = <?= json_encode(csrf_token()) ?>;
  var CSRF_DEG = <?= json_encode(csrf_hash()) ?>;

  function gonder(url, veri) {
    veri[CSRF_AD] = CSRF_DEG;

    return fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams(veri)
    }).then(function (y) { return y.json(); }).then(function (v) {
      if (v.csrf) { CSRF_DEG = v.csrf; }
      return v;
    });
  }

  function baglama(secici, url) {
    document.querySelectorAll(secici).forEach(function (b) {
      b.addEventListener('click', function () {
        b.disabled = true;
        gonder(url, { id: b.dataset.id })
          .then(function (v) {
            if (!v.durum) { alert(v.mesaj || 'İşlem yapılamadı.'); b.disabled = false; return; }
            location.reload();   // sayaçlar ve sıralama değişir
          })
          .catch(function () { alert('Bağlantı hatası.'); b.disabled = false; });
      });
    });
  }

  baglama('.aj-yapildi', '<?= site_url('ajanda/yapildi') ?>');
  baglama('.aj-geri', '<?= site_url('ajanda/geri-al') ?>');
}());
</script>

<?= $this->endSection() ?>
