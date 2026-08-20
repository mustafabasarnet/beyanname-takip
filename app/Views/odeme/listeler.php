<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="uyari bilgi">
  <span class="ik">ℹ</span>
  <div>
    Kendi seçtiğiniz mükelleflerden <b>kalıcı bir mükellef grubu</b> oluşturun.
    <b>Dönem listeye gömülü değildir</b> — listeyi bir kez oluşturur, her ay
    açarken yıl/ay seçersiniz. Tutarlar seçtiğiniz döneme göre
    <b>onaylanmış beyanname tahakkuklarından</b> ve
    <b>özel ödeme kalemlerinizden</b> güncel olarak hesaplanır.
    Listeleriniz ve özel ödeme kalemleriniz <b>size özeldir</b>
    (yalnızca siz ve yönetici görebilir).
  </div>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>📑 Ödeme Listelerim (<?= count($listeler) ?>)</h2>
    <div class="sag">
      <?php /* Bu sayfa menüden kaldırıldı; geri dönüş bağlantısı şart. */ ?>
      <a href="<?= site_url('odeme') ?>" class="btn ikincil kucuk">← Ödeme Listesi</a>
      <button class="btn kucuk" onclick="listeAc()">+ Yeni Liste</button>
    </div>
  </div>

  <div class="kart-govde sikisik">
    <?php if ($listeler === []): ?>
      <div class="tablo-bos">
        <span class="ikon">📋</span>
        Henüz kayıtlı listeniz yok.<br>
        <span class="kucuk-yazi">
          Örn. "Kendi Mükelleflerim" adında bir grup oluşturup istediğiniz
          mükellefleri seçin; her ay aynı listeyi kullanırsınız.
        </span>
        <div class="mt16"><button class="btn kucuk" onclick="listeAc()">+ İlk Listeyi Oluştur</button></div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead>
            <tr>
              <th>Liste Adı</th>
              <th>Varsayılan Dönem</th>
              <th>Mali Müşavir</th>
              <th class="orta">Mükellef</th>
              <th>İçerik</th>
              <?php if (($aktifKullanici['rol'] ?? '') === 'admin'): ?>
                <th>Sahibi</th>
              <?php endif; ?>
              <th class="sag">İşlem</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($listeler as $l): ?>
            <tr>
              <td>
                <a href="<?= site_url('odeme/liste/' . $l['id']) ?>" class="kalin"><?= esc($l['ad']) ?></a>
                <?php if (! empty($l['aciklama'])): ?>
                  <div class="kucuk-yazi"><?= esc($l['aciklama']) ?></div>
                <?php endif; ?>
              </td>
              <td class="kucuk-yazi">
                <?php if (empty($l['yil']) && $l['ay'] === null): ?>
                  <span class="metin-gri">İçinde bulunulan ay</span>
                <?php else: ?>
                  <?= $l['ay'] !== null ? ayAdi((int) $l['ay']) . ' ' : '' ?><?= ! empty($l['yil']) ? (int) $l['yil'] : '' ?>
                <?php endif; ?>
              </td>
              <td class="kucuk-yazi">
                <?php if (! empty($l['musavir_adi'])): ?>
                  <span class="rozet" style="background:<?= esc($l['musavir_renk']) ?>22;color:<?= esc($l['musavir_renk']) ?>">
                    <?= esc($l['musavir_adi']) ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="orta kalin"><?= (int) $l['mukellef_sayisi'] ?></td>
              <td class="kucuk-yazi">
                <span class="rozet mavi">Beyanname</span>
                <?php if ((int) $l['ozel_dahil'] === 1): ?><span class="rozet mor">Özel Ödeme</span><?php endif; ?>
                <?php if ((int) $l['ucret_dahil'] === 1): ?><span class="rozet yesil">Muh. Ücreti</span><?php endif; ?>
              </td>
              <?php if (($aktifKullanici['rol'] ?? '') === 'admin'): ?>
                <td class="kucuk-yazi"><?= esc($l['sahip_adi'] ?? '-') ?></td>
              <?php endif; ?>
              <td class="sag" style="white-space:nowrap">
                <a href="<?= site_url('odeme/liste/' . $l['id']) ?>" class="btn ikincil mini">Aç</a>
                <a href="<?= site_url('odeme/liste-yazdir/' . $l['id']) ?>" target="_blank" class="btn ikincil mini">🖨️</a>
                <a href="<?= site_url('odeme/liste-sil/' . $l['id']) ?>" class="btn kirmizi mini"
                   data-onay="'<?= esc($l['ad'], 'js') ?>' listesi silinsin mi?">Sil</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?= $this->include('odeme/_liste_form') ?>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<?= $this->include('odeme/_liste_form_js') ?>
<?= $this->endSection() ?>
