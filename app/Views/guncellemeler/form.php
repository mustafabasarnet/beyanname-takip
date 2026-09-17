<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
/**
 * GÜNCELLEME LOGLARI — YENİ / DÜZENLE FORMU (yalnız yönetici)
 *
 * İçerik serbest metin; satır başındaki işaret maddenin türünü belirler:
 *   + eklenen   ~ değişen   - kaldırılan   ! düzeltilen   (işaretsiz = not)
 */
$kayit  = $kayit ?? [];
$turler = $turler ?? [];
$duzenleme = ! empty($kayit['id']);
?>

<style>
.gn-ornek{background:var(--gri-50,#f8fafc);border:1px dashed var(--gri-300,#cbd5e1);
  border-radius:10px;padding:11px 13px;font-size:12.5px;color:var(--gri-600,#475569);margin-top:8px}
.gn-ornek code{background:#fff;border:1px solid var(--gri-200,#e2e8f0);border-radius:6px;
  padding:1px 6px;font-weight:700}
.gn-ornek .satir{margin-top:5px;display:flex;gap:8px;align-items:center}
.gn-onizleme{border:1px solid var(--gri-200,#e2e8f0);border-radius:12px;padding:12px 14px;margin-top:14px}
.gn-onizleme .bas{font-weight:700;font-size:13px;margin-bottom:8px;color:var(--gri-700,#334155)}
.gn-madde{display:flex;gap:10px;align-items:flex-start;padding:7px 0;
  border-top:1px dashed var(--gri-100,#f1f5f9);font-size:13.5px;color:var(--gri-700,#334155)}
.gn-madde:first-of-type{border-top:0}
.gn-madde .rz{flex:0 0 auto;font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:99px;white-space:nowrap}
.gn-madde .rz.yesil{background:#d1fae5;color:#065f46}
.gn-madde .rz.mavi{background:#dbeafe;color:#1e40af}
.gn-madde .rz.turuncu{background:#ffedd5;color:#9a3412}
.gn-madde .rz.gri{background:#e2e8f0;color:#475569}
.gn-madde .mt b{color:var(--gri-900,#0f172a)}
</style>

<div class="kart-baslik" style="margin:0 0 12px">
  <h2><?= $duzenleme ? '✏️ Güncelleme Düzenle' : '➕ Yeni Güncelleme' ?></h2>
  <a href="<?= site_url('guncellemeler') ?>" class="btn ikincil kucuk" style="margin-left:auto">← Listeye Dön</a>
</div>

<form method="post" action="<?= site_url('guncellemeler/kaydet') ?>" id="gn-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($kayit['id'] ?? 0) ?>">

  <div class="kart">
    <div class="kart-govde">
      <div class="form-grid">
        <div class="form-grup">
          <label>Sürüm <span class="zorunlu">*</span></label>
          <input type="text" name="versiyon" class="girdi" maxlength="20" required
                 value="<?= esc($kayit['versiyon'] ?? '') ?>" placeholder="Örn: 1.4.1">
        </div>
        <div class="form-grup">
          <label>Yayın Tarihi <span class="zorunlu">*</span></label>
          <input type="date" name="tarih" class="girdi" required
                 value="<?= esc($kayit['tarih'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-grup tam">
          <label>Başlık <span class="zorunlu">*</span></label>
          <input type="text" name="baslik" class="girdi" maxlength="200" required
                 value="<?= esc($kayit['baslik'] ?? '') ?>"
                 placeholder="Örn: Makbuz Takip formdan giriş">
        </div>
        <div class="form-grup tam">
          <label>Maddeler <span class="zorunlu">*</span> — her satır bir madde</label>
          <textarea name="icerik" id="gn-icerik" class="girdi" rows="9" required
                    style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px"
                    placeholder="+ Eklenen bir özellik&#10;~ Değişen bir davranış&#10;! Düzeltilen bir hata&#10;- Kaldırılan bir bölüm"><?= esc($kayit['icerik'] ?? '') ?></textarea>

          <div class="gn-ornek">
            <b>Satır başı işaretleri:</b>
            <?php foreach ($turler as $kod => $t): ?>
              <?php if ($kod === 'not') { continue; } ?>
              <span class="satir">
                <code><?= esc($t['işaret']) ?></code>
                <span><?= esc($t['ikon'] . ' ' . $t['ad']) ?> olarak gösterilir</span>
              </span>
            <?php endforeach; ?>
            <span class="satir">
              <code>(işaretsiz)</code>
              <span>• Not — düz madde olarak listelenir</span>
            </span>
            <div style="margin-top:8px">
              Metin içinde <code>**kalın**</code> yazarsanız vurgulu gösterilir.
            </div>
          </div>
        </div>
        <div class="form-grup tam">
          <label class="onay">
            <input type="checkbox" name="aktif" value="1"
                   <?= (int) ($kayit['aktif'] ?? 1) === 1 ? 'checked' : '' ?>>
            <span>Yayında (kullanıcılara girişte gösterilsin)</span>
          </label>
        </div>
      </div>

      <!-- Canlı önizleme: kaydedince kullanıcının göreceği biçim -->
      <div class="gn-onizleme">
        <div class="bas">👁 Kullanıcı girişte böyle görecek</div>
        <div id="gn-onizleme-liste"></div>
      </div>
    </div>
  </div>

  <div class="kart">
    <div class="kart-govde">
      <div class="form-alt" style="margin:0">
        <button type="submit" class="btn">💾 Kaydet</button>
        <a href="<?= site_url('guncellemeler') ?>" class="btn ikincil">İptal</a>
      </div>
    </div>
  </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
(function () {
  'use strict';

  var TURLER = <?= json_encode(array_map(static fn ($t) => [
      'isaret' => $t['işaret'], 'ad' => $t['ad'], 'ikon' => $t['ikon'], 'renk' => $t['renk'],
      'tur'    => '',
  ], $turler), JSON_UNESCAPED_UNICODE) ?>;

  // İşaret → tür eşlemesi
  var ISARET = { '+': 'eklendi', '~': 'degisti', '!': 'duzeltildi', '-': 'kaldirildi' };
  var META = {};
  Object.keys(TURLER).forEach(function (kod) {
    META[kod] = TURLER[kod];
  });
  META['eklendi']    = { ad: 'Eklendi',    ikon: '✨', renk: 'yesil' };
  META['degisti']    = { ad: 'Değişti',    ikon: '🔄', renk: 'mavi' };
  META['duzeltildi'] = { ad: 'Düzeltildi', ikon: '🐞', renk: 'turuncu' };
  META['kaldirildi'] = { ad: 'Kaldırıldı', ikon: '🗑', renk: 'gri' };
  META['not']        = { ad: 'Not',        ikon: '•',  renk: 'gri' };

  var alan = document.getElementById('gn-icerik');
  var kutu = document.getElementById('gn-onizleme-liste');

  // Güvenli **kalın** biçimlendirme (önce metin düğümü, sonra <b>)
  function metinYaz(hedef, metin) {
    String(metin).split(/(\*\*[^*]+\*\*)/g).forEach(function (p) {
      if (p.length > 4 && p.slice(0, 2) === '**' && p.slice(-2) === '**') {
        var b = document.createElement('b');
        b.textContent = p.slice(2, -2);
        hedef.appendChild(b);
      } else if (p !== '') {
        hedef.appendChild(document.createTextNode(p));
      }
    });
  }

  function onizle() {
    kutu.innerHTML = '';

    alan.value.split(/\r\n|\r|\n/).forEach(function (ham) {
      var satir = String(ham).trim();
      if (satir === '') { return; }

      var tur = 'not';

      Object.keys(ISARET).forEach(function (isaret) {
        if (satir.indexOf(isaret) === 0) {
          tur   = ISARET[isaret];
          satir = satir.slice(1).trim();
        }
      });
      if (satir === '') { return; }

      var m = META[tur] || META['not'];

      var div = document.createElement('div');
      div.className = 'gn-madde';

      var rz = document.createElement('span');
      rz.className = 'rz ' + m.renk;
      rz.textContent = m.ikon + ' ' + m.ad;
      div.appendChild(rz);

      var mt = document.createElement('div');
      mt.className = 'mt';
      metinYaz(mt, satir);
      div.appendChild(mt);

      kutu.appendChild(div);
    });

    if (!kutu.children.length) {
      var bos = document.createElement('div');
      bos.className = 'kucuk-yazi';
      bos.textContent = 'Madde yazdıkça burası dolacak.';
      kutu.appendChild(bos);
    }
  }

  alan.addEventListener('input', onizle);
  onizle();
}());
</script>
<?= $this->endSection() ?>
