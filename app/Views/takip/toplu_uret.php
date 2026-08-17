<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<div class="kart" style="max-width:720px">
  <div class="kart-baslik"><h2>🔄 Toplu Dönem Üretimi</h2></div>
  <div class="kart-govde">
    <div class="uyari bilgi"><span class="ik">ℹ</span><div>
      Seçilen yıl için tüm mükelleflerin beyanname dönemleri yeniden hesaplanır.<br><br>
      <b>Güvenli çalışır:</b>
      <ul>
        <li>İşlem görmüş satırlar (Hazır/Onaylandı/Gönderildi) ve notlu satırlar <b>korunur</b>.</li>
        <li>Sadece tarih bilgileri (tatil kaydırması) güncellenir.</li>
        <li>Terk nedeniyle geçersiz kalan ve henüz işlem görmemiş satırlar kaldırılır.</li>
        <li>Faaliyet aralığı ile kesişmeyen dönemler hiç oluşturulmaz.</li>
      </ul>
    </div></div>

    <form method="post" action="<?= site_url('takip/toplu-uret') ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-grup">
          <label>Yıl <span class="zorunlu">*</span></label>
          <select name="yil" required>
            <?php foreach (yilSecenekleri(3, 2) as $y): ?>
              <option value="<?= $y ?>" <?= $y === $yil ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (($aktifKullanici['rol'] ?? '') === 'admin'): ?>
          <div class="form-grup">
            <label>Mali Müşavir</label>
            <select name="musavir_id">
              <option value="">Tüm Müşavirler</option>
              <?php foreach ($musavirler as $mid => $mad): ?>
                <option value="<?= $mid ?>"><?= esc($mad) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>
      <div class="form-alt">
        <button type="submit" class="btn" data-onay="Dönemler yeniden hesaplanacak. Devam edilsin mi?">🔄 Üretimi Başlat</button>
        <a href="<?= site_url('takip') ?>" class="btn ikincil">İptal</a>
      </div>
    </form>
  </div>
</div>
<?= $this->endSection() ?>
