<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<div class="kart">
  <div class="kart-baslik">
    <h2>👥 Kullanıcılar (<?= count($kullanicilar) ?>)</h2>
    <div class="sag"><a href="<?= site_url('kullanicilar/yeni') ?>" class="btn kucuk">+ Yeni Kullanıcı</a></div>
  </div>
  <div class="kart-govde sikisik">
    <div class="tablo-sar">
      <table class="tablo">
        <thead><tr><th>Ad Soyad</th><th>Kullanıcı Adı</th><th>E-posta</th><th>Rol</th>
          <th>Erişebildiği Mali Müşavirler</th><th>Son Giriş</th><th>Durum</th><th class="sag">İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($kullanicilar as $k): ?>
          <tr>
            <td class="kalin"><?= esc($k['ad_soyad']) ?></td>
            <td class="kucuk-yazi"><?= esc($k['kullanici_adi']) ?></td>
            <td class="kucuk-yazi"><?= esc($k['eposta']) ?></td>
            <td><span class="rozet <?= ['admin'=>'kirmizi','musavir'=>'mor','personel'=>'mavi'][$k['rol']] ?>">
              <?= ['admin'=>'Yönetici','musavir'=>'Mali Müşavir','personel'=>'Personel'][$k['rol']] ?></span></td>
            <td class="kucuk-yazi">
              <?php if ($k['rol'] === 'admin'): ?>
                <span class="rozet mavi">Tümü (yönetici)</span>
              <?php elseif (! empty($k['erisim_musavirleri'])): ?>
                <?php foreach ($k['erisim_musavirleri'] as $em): ?>
                  <span class="rozet" style="background:<?= esc($em['renk']) ?>22;color:<?= esc($em['renk']) ?>">
                    <?= esc($em['ad']) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="rozet kirmizi">Yetki yok</span>
              <?php endif; ?>
            </td>
            <td class="kucuk-yazi"><?= $k['son_giris'] ? trTarih($k['son_giris'], 'd.m.Y H:i') : 'Hiç' ?></td>
            <td><span class="rozet <?= $k['aktif'] ? 'yesil' : 'gri' ?>"><?= $k['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
            <td class="sag">
              <a href="<?= site_url('kullanicilar/duzenle/' . $k['id']) ?>" class="btn ikincil mini">Düzenle</a>
              <a href="<?= site_url('kullanicilar/sil/' . $k['id']) ?>" class="btn kirmizi mini" data-onay="Kullanıcı silinsin mi?">Sil</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
