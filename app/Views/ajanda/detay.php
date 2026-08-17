<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php include APPPATH . 'Views/ajanda/_stil.php'; ?>

<div class="aj-detay-ust">
  <a href="<?= site_url('ajanda') ?>" class="btn ikincil kucuk">← Ajandaya Dön</a>

  <div style="flex:1;min-width:220px">
    <h2 style="margin:0;display:flex;align-items:center;gap:9px;flex-wrap:wrap">
      <span class="aj-serit" style="background:<?= esc($k['renk_efektif']) ?>;min-height:22px"></span>
      <?= esc($k['baslik']) ?>
    </h2>
    <div class="kucuk-yazi" style="margin-top:5px">
      <?= esc($k['olusturan_adi']) ?> oluşturdu ·
      <?= trTarih(substr((string) $k['created_at'], 0, 10)) ?>
    </div>
  </div>

  <div class="btn-grup">
    <?php if ($duzenlenir): ?>
      <?php if ($k['durum'] === 'BEKLIYOR'): ?>
        <button type="button" class="btn yesil kucuk" id="aj-yapildi">✓ Yapıldı</button>
        <button type="button" class="btn ikincil kucuk" id="aj-iptal">✕ İptal</button>
      <?php else: ?>
        <button type="button" class="btn ikincil kucuk" id="aj-geri">↺ Yeniden Aç</button>
      <?php endif; ?>
      <a href="<?= site_url('ajanda/duzenle/' . (int) $k['id']) ?>" class="btn kucuk">✏️ Düzenle</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($k['durum'] === 'BEKLIYOR' && $k['gecikmis']): ?>
  <div class="uyari kirmizi">
    ⚠ Bu iş <b><?= abs($k['kalan_gun']) ?> gün</b> gecikti
    (<?= trTarih($k['tarih']) ?> tarihliydi).
  </div>
<?php elseif ($k['durum'] === 'BEKLIYOR' && $k['bugun']): ?>
  <div class="uyari mavi">📌 Bu iş <b>bugün</b> yapılacak.</div>
<?php endif; ?>

<div class="kart">
  <div class="kart-baslik">
    <h2>Ayrıntılar</h2>
    <span class="aj-rozet d-<?= esc($k['durum']) ?>"><?= esc($k['durum']) ?></span>
  </div>

  <div class="kart-govde">
    <div class="aj-bilgi-satir">
      <div class="et">Tarih</div>
      <div class="dg">
        <b><?= trTarih($k['tarih']) ?></b>
        <?php if (! empty($k['saat'])): ?> · 🕐 <?= substr($k['saat'], 0, 5) ?><?php endif; ?>
        <?php if (! empty($k['bitis_tarihi'])): ?>
          → <?= trTarih($k['bitis_tarihi']) ?>
        <?php endif; ?>
        <?php if ($k['durum'] === 'BEKLIYOR' && $k['kalan_gun'] > 0): ?>
          <span class="kucuk-yazi">(<?= $k['kalan_gun'] ?> gün kaldı)</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="aj-bilgi-satir">
      <div class="et">Öncelik</div>
      <div class="dg">
        <span class="aj-rozet <?= esc($k['oncelik']) ?>">
          <?= esc($oncelikler[$k['oncelik']] ?? $k['oncelik']) ?>
        </span>
      </div>
    </div>

    <div class="aj-bilgi-satir">
      <div class="et">Görünürlük</div>
      <div class="dg">
        <span class="aj-rozet g-<?= esc($k['gorunurluk']) ?>">
          <?= esc($gorunurluk[$k['gorunurluk']] ?? $k['gorunurluk']) ?>
        </span>
        <?php if (! empty($k['atanan_adi'])): ?>
          <span class="kucuk-yazi">→ 👤 <?= esc($k['atanan_adi']) ?></span>
        <?php endif; ?>
        <?php if (! empty($k['musavir_adi'])): ?>
          <span class="kucuk-yazi">→ 👨‍💼 <?= esc($k['musavir_adi']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <?php if (! empty($k['mukellef_unvan'])): ?>
      <div class="aj-bilgi-satir">
        <div class="et">İlgili Mükellef</div>
        <div class="dg">
          🏢 <a href="<?= site_url('mukellefler/duzenle/' . (int) $k['mukellef_id']) ?>">
            <?= esc($k['mukellef_unvan']) ?>
          </a>
        </div>
      </div>
    <?php endif; ?>

    <?php if (! empty($k['etiket'])): ?>
      <div class="aj-bilgi-satir">
        <div class="et">Etiket</div>
        <div class="dg"><span class="aj-rozet etiket"><?= esc($k['etiket']) ?></span></div>
      </div>
    <?php endif; ?>

    <?php if ($k['tekrar'] !== 'yok'): ?>
      <div class="aj-bilgi-satir">
        <div class="et">Tekrar</div>
        <div class="dg">
          🔁 <?= esc($tekrarlar[$k['tekrar']] ?? $k['tekrar']) ?>
          <?php if (! empty($k['tekrar_bitis'])): ?>
            <span class="kucuk-yazi">(<?= trTarih($k['tekrar_bitis']) ?> tarihine kadar)</span>
          <?php endif; ?>
          <div class="kucuk-yazi" style="margin-top:3px">
            "Yapıldı" işaretlendiğinde tarih otomatik olarak sonraki döneme ötelenir.
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ((int) $k['hatirlat_gun'] > 0): ?>
      <div class="aj-bilgi-satir">
        <div class="et">Hatırlatma</div>
        <div class="dg">🔔 <?= (int) $k['hatirlat_gun'] ?> gün önceden panelde uyarır</div>
      </div>
    <?php endif; ?>

    <?php if (! empty($k['aciklama'])): ?>
      <div class="aj-bilgi-satir">
        <div class="et">Açıklama</div>
        <div class="dg" style="white-space:pre-wrap"><?= esc($k['aciklama']) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($k['durum'] === 'YAPILDI' && ! empty($k['yapildi_at'])): ?>
      <div class="aj-bilgi-satir">
        <div class="et">Tamamlanma</div>
        <div class="dg">
          ✓ <?= trTarih(substr($k['yapildi_at'], 0, 10)) ?>
          <?= substr($k['yapildi_at'], 11, 5) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($ekler !== []): ?>
  <div class="kart">
    <div class="kart-baslik">
      <h2>📎 Dosya Ekleri</h2>
      <span class="kucuk-yazi"><?= count($ekler) ?> dosya</span>
    </div>
    <div class="kart-govde">
      <ul class="aj-ek-liste">
        <?php foreach ($ekler as $e): ?>
          <li>
            📄 <a href="<?= site_url('ajanda/ek/' . (int) $e['id']) ?>"><?= esc($e['dosya_adi']) ?></a>
            <span class="kucuk-yazi"><?= esc($e['yukleyen_adi'] ?? '') ?></span>
            <span class="boyut"><?= number_format($e['boyut'] / 1024, 0, ',', '.') ?> KB</span>
            <?php if ($duzenlenir): ?>
              <a href="<?= site_url('ajanda/ek-sil/' . (int) $e['id']) ?>" class="btn kirmizi mini"
                 onclick="return confirm('Bu dosya silinsin mi?')">Sil</a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<?php if ($duzenlenir && $k['durum'] === 'BEKLIYOR'): ?>
  <div class="kart">
    <div class="kart-baslik"><h2>Hızlı Erteleme</h2></div>
    <div class="kart-govde">
      <div class="btn-grup">
        <button type="button" class="btn ikincil kucuk aj-ertele"
                data-tarih="<?= date('Y-m-d', strtotime('+1 day')) ?>">Yarına</button>
        <button type="button" class="btn ikincil kucuk aj-ertele"
                data-tarih="<?= date('Y-m-d', strtotime('+7 days')) ?>">1 hafta sonra</button>
        <button type="button" class="btn ikincil kucuk aj-ertele"
                data-tarih="<?= date('Y-m-d', strtotime('+1 month')) ?>">1 ay sonra</button>
        <input type="date" id="aj-ertele-tarih" class="girdi" style="max-width:160px">
        <button type="button" class="btn ikincil kucuk" id="aj-ertele-ozel">Seçilen tarihe</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
(function () {
  'use strict';

  var CSRF_AD  = <?= json_encode(csrf_token()) ?>;
  var CSRF_DEG = <?= json_encode(csrf_hash()) ?>;
  var ID       = <?= (int) $k['id'] ?>;

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

  function eylem(dugmeId, url, veriEk) {
    var d = document.getElementById(dugmeId);
    if (!d) { return; }

    d.addEventListener('click', function () {
      d.disabled = true;
      gonder(url, Object.assign({ id: ID }, veriEk || {}))
        .then(function (v) {
          if (!v.durum) { alert(v.mesaj || 'İşlem yapılamadı.'); d.disabled = false; return; }
          location.reload();
        })
        .catch(function () { alert('Bağlantı hatası.'); d.disabled = false; });
    });
  }

  eylem('aj-yapildi', '<?= site_url('ajanda/yapildi') ?>');
  eylem('aj-geri', '<?= site_url('ajanda/geri-al') ?>');
  eylem('aj-iptal', '<?= site_url('ajanda/iptal') ?>');

  document.querySelectorAll('.aj-ertele').forEach(function (b) {
    b.addEventListener('click', function () {
      gonder('<?= site_url('ajanda/ertele') ?>', { id: ID, tarih: b.dataset.tarih })
        .then(function (v) {
          if (!v.durum) { alert(v.mesaj || 'İşlem yapılamadı.'); return; }
          location.reload();
        });
    });
  });

  var ozel = document.getElementById('aj-ertele-ozel');

  if (ozel) {
    ozel.addEventListener('click', function () {
      var t = document.getElementById('aj-ertele-tarih').value;
      if (!t) { alert('Önce tarih seçin.'); return; }

      gonder('<?= site_url('ajanda/ertele') ?>', { id: ID, tarih: t })
        .then(function (v) {
          if (!v.durum) { alert(v.mesaj || 'İşlem yapılamadı.'); return; }
          location.reload();
        });
    });
  }
}());
</script>

<?= $this->endSection() ?>
