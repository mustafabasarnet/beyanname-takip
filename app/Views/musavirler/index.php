<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="kart">
  <div class="kart-baslik">
    <h2>👨‍💼 Mali Müşavirler (<?= count($musavirler) ?>)</h2>
    <div class="sag">
      <?php if (empty($saltOkunur)): ?>
        <a href="<?= site_url('musavirler/yeni') ?>" class="btn kucuk">+ Yeni Mali Müşavir</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($musavirler === []): ?>
      <div class="tablo-bos"><span class="ikon">👤</span>Kayıtlı mali müşavir yok.</div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead><tr><th>Ünvan</th><th>Ad Soyad</th><th>Büro</th><th>Ruhsat No</th>
            <th>Telefon</th><th class="orta">Mükellef</th><th>Durum</th><th class="sag">İşlem</th></tr></thead>
          <tbody>
          <?php foreach ($musavirler as $m): ?>
            <tr>
              <td><span class="rozet" style="background:<?= esc($m['renk']) ?>22;color:<?= esc($m['renk']) ?>">
                <?= esc($m['unvan'] ?: '-') ?></span></td>
              <td class="kalin"><?= esc($m['ad_soyad']) ?></td>
              <td class="kucuk-yazi"><?= esc($m['buro_adi'] ?: '-') ?></td>
              <td class="kucuk-yazi"><?= esc($m['ruhsat_no'] ?: '-') ?></td>
              <td class="kucuk-yazi"><?= esc($m['telefon'] ?: '-') ?></td>
              <td class="orta"><b><?= (int) ($sayilar[$m['id']] ?? 0) ?></b></td>
              <td><span class="rozet <?= $m['aktif'] ? 'yesil' : 'gri' ?>"><?= $m['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
              <td class="sag" style="white-space:nowrap">
                <a href="<?= site_url('mukellefler?musavir_id=' . $m['id']) ?>" class="btn ikincil mini">Mükellefleri</a>
                <a href="<?= site_url('musavirler/duzenle/' . $m['id']) ?>" class="btn ikincil mini">Düzenle</a>
                <?php if (empty($saltOkunur)): ?>
                  <a href="<?= site_url('musavirler/sil/' . $m['id']) ?>" class="btn kirmizi mini"
                     data-onay="Silinsin mi?">Sil</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?= $this->endSection() ?>
