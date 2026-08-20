<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<div class="kart">
  <div class="kart-baslik">
    <h2>⏰ Gecikmiş Beyannameler (<?= count($kayitlar) ?>)</h2>
    <div class="sag"><input type="text" class="girdi" data-tablo-ara="#gec-tablo" placeholder="Tabloda ara..." style="width:210px"></div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($kayitlar === []): ?>
      <div class="tablo-bos"><span class="ikon">🎉</span>Gecikmiş beyanname bulunmuyor!</div>
    <?php else: ?>
      <div class="tablo-sar"><table class="tablo" id="gec-tablo">
        <thead><tr><th>Mükellef</th><th>Beyanname</th><th>Dönem</th><th>Son Tarih</th><th>Gecikme</th><th>Durum</th><th>Müşavir</th></tr></thead>
        <tbody>
        <?php foreach ($kayitlar as $k): $kl = kalanGunMetni($k['son_tarih'], $k['durum'] ?? null); ?>
          <tr class="<?= $kl['bitti'] ? '' : 'gecikmis-satir' ?>">
            <td><a href="<?= site_url('mukellefler/detay/' . $k['mukellef_id']) ?>"><?= esc($k['mukellef_unvan']) ?></a></td>
            <td><span class="tur-rozet" style="background:<?= esc($k['tur_renk']) ?>"><?= esc($k['tur_kisa']) ?></span></td>
            <td class="kucuk-yazi"><?= esc($k['donem_adi']) ?></td>
            <td><?= trTarih($k['son_tarih']) ?></td>
            <td>
              <?php if ($kl['bitti']): ?>
                <span class="rozet <?= $kl['sinif'] ?>"><?= esc($kl['metin']) ?></span>
              <?php else: ?>
                <span class="rozet kirmizi"><?= abs($kl['gun']) ?> gün</span>
              <?php endif; ?>
            </td>
            <td><?= durumRozeti($k['durum']) ?></td>
            <td class="kucuk-yazi"><?= esc($k['musavir_adi'] ?? '-') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
  </div>
</div>
<?= $this->endSection() ?>
