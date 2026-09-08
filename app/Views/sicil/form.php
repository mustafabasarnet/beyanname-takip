<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
$duzenleme = $degisiklik !== null;
$seciliSablon = $duzenleme ? (int) $degisiklik['turu_id'] : 0;
?>

<style>
.mk-sec{position:relative}
.mk-liste{position:absolute;top:calc(100%+4px);left:0;right:0;z-index:70;background:#fff;
  border:1px solid var(--gri-300,#cbd5e1);border-radius:10px;box-shadow:var(--golge-lg);max-height:220px;
  overflow-y:auto;display:none}
.mk-liste.goster{display:block}
.mk-liste .oge{padding:8px 12px;cursor:pointer;font-size:13px}
.mk-liste .oge:hover{background:var(--gri-50,#f8fafc)}
.sablon-ipucu{background:var(--gri-50,#f8fafc);border:1px dashed var(--gri-300,#cbd5e1);border-radius:8px;
  padding:8px 12px;font-size:12px;color:var(--gri-600,#475569);grid-column:1/-1;display:none}
.sablon-ipucu.goster{display:block}
.sablon-ipucu .todo{display:inline-block;background:#fff;border:1px solid var(--gri-300,#cbd5e1);
  border-radius:99px;padding:2px 9px;margin:2px 3px 2px 0;font-size:11.5px;color:var(--gri-800)}
</style>

<div id="sicil-bildirim" style="margin-bottom:10px"></div>

<div class="kart">
  <div class="kart-govde">
    <div class="form-grid" id="islem-form">
      <!-- Mükellef -->
      <div class="form-grup tam mk-sec">
        <label>Mükellef <b style="color:#dc2626">*</b></label>
        <?php if ($duzenleme || $mukellefId > 0): ?>
          <input type="hidden" id="mk_id" value="<?= (int) ($degisiklik['mukellef_id'] ?? $mukellefId) ?>">
          <input type="text" class="girdi" disabled value="(Mükellef kartından — <?= esc(kisalt($degisiklik['mukellef_unvan'] ?? '', 60)) ?>)">
        <?php else: ?>
          <input type="text" id="mk_ara" class="girdi" placeholder="Mükellef ünvanı veya VKN yazın…" autocomplete="off">
          <input type="hidden" id="mk_id" name="mukellef_id">
          <div class="mk-liste" id="mk_liste"></div>
        <?php endif; ?>
      </div>

      <input type="hidden" name="id" id="id" value="<?= (int) ($degisiklik['id'] ?? 0) ?>">

      <!-- Şablon -->
      <div class="form-grup tam">
        <label>Şablon <b style="color:#dc2626">*</b></label>
        <?php if ($duzenleme): ?>
          <input type="hidden" id="turu_id" name="turu_id" value="<?= $seciliSablon ?>">
          <input type="text" class="girdi" disabled value="<?= esc($degisiklik['tur_ad'] ?? '') ?>">
          <span class="kucuk-yazi">Şablon sonradan değiştirilemez; yeni işlem açarak değiştirin.</span>
        <?php else: ?>
          <select id="turu_id" name="turu_id" class="girdi">
            <option value="">— Şablon seçin —</option>
            <?php foreach ($turler as $tid => $tad): ?>
              <option value="<?= (int) $tid ?>" <?= $seciliSablon === (int) $tid ? 'selected' : '' ?>>
                <?= esc($tad) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <div class="sablon-ipucu" id="sablon_ipucu"></div>
      </div>

      <div class="form-grup">
        <label>İşlem Tarihi <b style="color:#dc2626">*</b></label>
        <input type="date" id="degisiklik_tarihi" name="degisiklik_tarihi" class="girdi"
               value="<?= esc($degisiklik['degisiklik_tarihi'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>">
        <span class="yardim">Todo son tarihleri bu tarihten ileriye doğru hesaplanır.</span>
      </div>

      <div class="form-grup">
        <label>Açıklama (ops.)</label>
        <textarea name="aciklama" id="aciklama" class="girdi" rows="2"
                  placeholder="Kısa not…"><?= esc($degisiklik['aciklama'] ?? '') ?></textarea>
      </div>

      <div class="form-alt" style="grid-column:1/-1">
        <button type="button" class="btn yesil" id="islem-kaydet">
          💾 <?= $duzenleme ? 'İşlemi Güncelle' : 'Kaydet ve Todo Listesini Oluştur' ?>
        </button>
        <a href="<?= site_url($duzenleme ? 'sicil/detay/' . (int) $degisiklik['id'] : 'sicil') ?>" class="btn ikincil">İptal</a>
      </div>
    </div>
  </div>
</div>

<!-- Kayıt sonrası: oluşan todo listesi -->
<div id="sicil-sonuc" style="display:none">
  <div class="kart" style="margin-top:14px">
    <div class="kart-baslik">
      <h2>📋 Oluşturulan Todo Listesi</h2>
      <div class="sag"><a href="#" class="btn ikincil kucuk" id="sonuc-git">İşlem Detayına Git →</a></div>
    </div>
    <div class="kart-govde sikisik">
      <table class="tablo">
        <thead><tr><th style="width:32px"></th><th>Todo</th><th>Son Tarih</th><th>Durum</th></tr></thead>
        <tbody id="sonuc-tablo"></tbody>
      </table>
    </div>
  </div>
  <div class="kart" style="margin-top:14px">
    <div class="kart-govde">
      <a href="<?= site_url('sicil/ekle') ?>" class="btn yesil kucuk">+ Yeni İşlem Ekle</a>
      <a href="<?= site_url('sicil') ?>" class="btn ikincil kucuk">Listeye Dön</a>
    </div>
  </div>
</div>

<script>
(function () {
  var sablonOz = <?= json_encode($sablonOz) ?>;
  var duzenleme = <?= $duzenleme ? 'true' : 'false' ?>;

  function trTarih(s) {
    if (!s) { return '—'; }
    var p = String(s).split('-');
    return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : s;
  }

  // Şablon seçilince todo önizlemesi
  var turSec = document.getElementById('turu_id');
  var ipucu  = document.getElementById('sablon_ipucu');
  function sablonGoster() {
    var k = sablonOz[turSec.value] || [];
    if (k.length === 0) { ipucu.className = 'sablon-ipucu'; ipucu.innerHTML = ''; return; }
    ipucu.className = 'sablon-ipucu goster';
    ipucu.innerHTML = '<b>Bu şablonda ' + k.length + ' todo tanımlı:</b><br>'
      + k.map(function (x) { return '<span class="todo">' + x.ad + ' · ' + x.ozet + '</span>'; }).join('');
  }
  if (turSec && !duzenleme) { turSec.addEventListener('change', sablonGoster); sablonGoster(); }
  if (duzenleme && ipucu) { ipucu.className = 'sablon-ipucu'; }

  // Mükellef arama (yalnız yeni kayıtta)
  var mkAra = document.getElementById('mk_ara');
  var mkListe = document.getElementById('mk_liste');
  var mkId = document.getElementById('mk_id');
  if (mkAra) {
    var zaman;
    mkAra.addEventListener('input', function () {
      clearTimeout(zaman);
      var q = mkAra.value.trim();
      if (q.length < 2) { mkListe.className = 'mk-liste'; return; }
      zaman = setTimeout(function () {
        fetch('<?= site_url('mukellefler/ara?q=') ?>' + encodeURIComponent(q), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (d) {
          mkListe.innerHTML = '';
          (d.sonuclar || d).forEach(function (m) {
            var div = document.createElement('div');
            div.className = 'oge';
            div.innerHTML = '<b>' + (m.unvan || '') + '</b> <span class="kucuk-yazi">' + (m.vergi_kimlik_no || m.tc_kimlik_no || '') + '</span>';
            div.addEventListener('click', function () {
              mkId.value = m.id;
              mkAra.value = m.unvan;
              mkListe.className = 'mk-liste';
            });
            mkListe.appendChild(div);
          });
          mkListe.className = 'mk-liste' + (mkListe.children.length ? ' goster' : '');
        });
      }, 250);
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.mk-sec')) mkListe.className = 'mk-liste';
    });
  }

  // Kaydet
  var btn = document.getElementById('islem-kaydet');
  if (btn) {
    btn.addEventListener('click', function () {
      if (btn.disabled) { return; }
      var al = function (n) {
        var el = document.getElementById(n) || document.querySelector('[name="' + n + '"]');
        return el ? el.value : '';
      };
      var veri = {
        id: al('id'),
        mukellef_id: (mkId ? mkId.value : ''),
        turu_id: al('turu_id'),
        degisiklik_tarihi: al('degisiklik_tarihi'),
        aciklama: al('aciklama')
      };
      btn.disabled = true;
      BT.post('<?= site_url('sicil/kaydet') ?>', veri)
        .then(function (j) {
          if (!j.durum) throw new Error(j.mesaj || 'Kayıt başarısız.');
          if (j.id && !duzenleme && j.todolar !== undefined) {
            var govde = document.getElementById('sicil-sonuc');
            var tbody = document.getElementById('sonuc-tablo');
            tbody.innerHTML = '';
            if (j.todolar.length === 0) {
              tbody.innerHTML = '<tr><td colspan="4" class="orta kucuk-yazi">Bu şablon için tanımlı aktif todo yok.</td></tr>';
            } else {
              j.todolar.forEach(function (t) {
                tbody.innerHTML += '<tr><td class="orta">☐</td><td><b>' + t.ad + '</b></td>'
                  + '<td><b>' + trTarih(t.son_tarih) + '</b></td>'
                  + '<td><span class="rozet gri">Yapılmadı</span></td></tr>';
              });
            }
            document.getElementById('sonuc-git').href = '<?= site_url('sicil/detay') ?>/' + j.id;
            var uyari = '';
            if (j.todoYok) {
              uyari = '<div class="uyari dikkat"><span class="ik">⚠</span><div>İşlem kaydedildi ancak '
                + 'şablonda aktif todo tanımı yok — Şablonlar ekranından ekleyin.</div></div>';
            }
            document.getElementById('sicil-bildirim').innerHTML =
              '<div class="uyari basari"><span class="ik">✓</span><div>İşlem kaydedildi. '
              + '<b>' + j.todolar.length + ' todo</b> otomatik oluşturuldu.</div></div>' + uyari;
            govde.style.display = '';
            var ust = document.querySelector('.kart');
            if (ust) ust.style.display = 'none';
          } else {
            window.location.href = '<?= site_url('sicil/detay') ?>/' + j.id;
          }
        })
        .catch(function (e) {
          btn.disabled = false;
          document.getElementById('sicil-bildirim').innerHTML =
            '<div class="uyari hata"><span class="ik">✕</span><div>' + e.message + '</div></div>';
        });
    });
  }
})();
</script>

<?= $this->endSection() ?>
