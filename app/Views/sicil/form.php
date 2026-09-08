<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<style>
.mk-sec{position:relative}
.mk-liste{position:absolute;top:calc(100%+4px);left:0;right:0;z-index:70;background:#fff;
  border:1px solid var(--gri-300,#cbd5e1);border-radius:10px;box-shadow:var(--golge-lg);max-height:220px;
  overflow-y:auto;display:none}
.mk-liste.goster{display:block}
.mk-liste .oge{padding:8px 12px;cursor:pointer;font-size:13px}
.mk-liste .oge:hover{background:var(--gri-50,#f8fafc)}
.mk-liste .oge.kucuk-yazi{color:var(--gri-500,#64748b)}
.tur-ipucu{background:var(--gri-50,#f8fafc);border:1px dashed var(--gri-300,#cbd5e1);border-radius:8px;
  padding:8px 12px;font-size:12px;color:var(--gri-600,#475569);grid-column:1/-1;display:none}
.tur-ipucu.goster{display:block}
</style>

<div class="kart-baslik"><h2><?= $baslik ?></h2>
  <span class="kucuk-yazi">Değişiklik kaydedilince bildirim görevleri otomatik oluşur.</span>
</div>

<div id="sicil-bildirim" style="margin-bottom:10px"></div>

<div class="kart">
  <div class="kart-govde">
    <div class="form-grid">
      <!-- Mükellef -->
      <div class="form-grup tam mk-sec">
        <label>Mükellef <b style="color:#dc2626">*</b></label>
        <?php if ($mukellefId > 0): ?>
          <input type="hidden" id="mk_id" value="<?= (int) $mukellefId ?>">
          <input type="text" class="girdi" disabled value="(Mükellef kartından geliyor — id <?= (int) $mukellefId ?>)">
          <span class="kucuk-yazi" id="mk_ozet"></span>
        <?php else: ?>
          <input type="text" id="mk_ara" class="girdi" placeholder="Mükellef ünvanı veya VKN yazın…" autocomplete="off">
          <input type="hidden" id="mk_id" name="mukellef_id">
          <div class="mk-liste" id="mk_liste"></div>
        <?php endif; ?>
      </div>

      <input type="hidden" name="id" id="id" value="<?= (int) ($degisiklik['id'] ?? 0) ?>">

      <!-- Tür -->
      <div class="form-grup tam">
        <label>Değişiklik Türü <b style="color:#dc2626">*</b></label>
        <select id="tur_id" name="turu_id" class="girdi">
          <option value="">— Seçin —</option>
          <?php foreach ($turler as $tid => $tad): ?>
            <option value="<?= (int) $tid ?>" <?= (int) ($degisiklik['turu_id'] ?? 0) === (int) $tid ? 'selected' : '' ?>>
              <?= esc($tad) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="tur-ipucu" id="tur_ipucu"></div>
      </div>

      <div class="form-grup">
        <label>Değişiklik Tarihi <b style="color:#dc2626">*</b></label>
        <input type="date" id="degisiklik_tarihi" name="degisiklik_tarihi" class="girdi"
               value="<?= esc($degisiklik['degisiklik_tarihi'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>">
      </div>

      <div class="form-grup">
        <label>Referans No / Karar No</label>
        <input type="text" name="referans_no" class="girdi" maxlength="100"
               value="<?= esc($degisiklik['referans_no'] ?? '') ?>" placeholder="Tescil / karar no…">
      </div>

      <div class="form-grup">
        <label>Eski Bilgi</label>
        <textarea name="eski_deger" class="girdi" rows="2"
                  placeholder="Değişiklik öncesi değer (ops.)"><?= esc($degisiklik['eski_deger'] ?? '') ?></textarea>
      </div>

      <div class="form-grup">
        <label>Yeni Bilgi <b style="color:#dc2626">*</b></label>
        <textarea name="yeni_deger" class="girdi" rows="2" required
                  placeholder="Değişiklik sonrası değer"><?= esc($degisiklik['yeni_deger'] ?? '') ?></textarea>
      </div>

      <div class="form-grup">
        <label>Özet (ops.)</label>
        <input type="text" name="konu" class="girdi" maxlength="300"
               value="<?= esc($degisiklik['konu'] ?? '') ?>" placeholder="Kısa özet">
      </div>

      <div class="form-grup">
        <label>Açıklama</label>
        <textarea name="aciklama" class="girdi" rows="2"
                  placeholder="Serbest açıklama…"><?= esc($degisiklik['aciklama'] ?? '') ?></textarea>
      </div>

      <div class="form-alt" style="grid-column:1/-1">
        <button type="button" class="btn" id="sicil-kaydet">
          💾 <?= empty($degisiklik) ? 'Kaydet ve Bildirimleri Oluştur' : 'Değişikliği Güncelle' ?>
        </button>
        <a href="<?= site_url($degisiklik !== null ? 'sicil/detay/' . (int) $degisiklik['id'] : 'sicil') ?>" class="btn ikincil">İptal</a>
      </div>
    </div>
  </div>
</div>

<!-- Oluşan görevler sonucu (AJAX sonrası) -->
<div id="sicil-sonuc" style="display:none">
  <div class="kart" style="margin-top:14px">
    <div class="kart-baslik">
      <h2>📤 Oluşturulan Bildirim Görevleri</h2>
      <div class="sag"><a href="#" class="btn ikincil kucuk" id="sonuc-git">Bildirim Görevlerine Git →</a></div>
    </div>
    <div class="kart-govde sikisik">
      <table class="tablo">
        <thead><tr><th>Kurum</th><th>Son Tarih</th><th>Durum</th></tr></thead>
        <tbody id="sonuc-tablo"></tbody>
      </table>
    </div>
  </div>
  <div class="kart" style="margin-top:14px">
    <div class="kart-govde">
      <a href="<?= site_url('sicil/ekle') ?>" class="btn yesil kucuk">+ Yeni Değişiklik Ekle</a>
      <a href="<?= site_url('sicil') ?>" class="btn ikincil kucuk">Listeye Dön</a>
    </div>
  </div>
</div>

<script>
(function () {
  var turIpucu = <?= json_encode($kurallarOz) ?>;
  var mkAra = document.getElementById('mk_ara');
  var mkListe = document.getElementById('mk_liste');
  var mkId = document.getElementById('mk_id');

  // Tür seçilince kural ipucu
  var turSec = document.getElementById('tur_id');
  var ipucu = document.getElementById('tur_ipucu');
  function turGoster() {
    var t = turSec.value;
    var k = turIpucu[t] || [];
    if (k.length === 0) { ipucu.className = 'tur-ipucu'; ipucu.innerHTML = ''; return; }
    ipucu.className = 'tur-ipucu goster';
    ipucu.innerHTML = '📤 Bu türde şu kurumlara bildirim gerekir: '
      + k.map(function (x) {
          var s = x.kurum;
          if (x.sure_tipi === 'BELIRLI_TARIH') s += ' (belirli tarih ' + x.belirli_tarih + ')';
          else if (x.sure_tipi === 'AY') s += ' (' + x.sure_deger + ' ay)';
          else s += ' (' + x.sure_deger + (x.sure_tipi === 'IS_GUNU' ? ' iş günü' : ' gün') + ')';
          return '<b>' + s + '</b>';
        }).join(' · ');
  }
  if (turSec) { turSec.addEventListener('change', turGoster); turGoster(); }

  // Mükellef arama (yalnız yeni kayıtta)
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
  var btn = document.getElementById('sicil-kaydet');
  if (btn) {
    btn.addEventListener('click', function () {
      var form = btn.closest('.form-grid');
      var al = function (n) {
        var el = form.querySelector('[name="' + n + '"]');
        return el ? el.value : '';
      };
      var veri = {
        id: al('id'),
        mukellef_id: mkId.value,
        turu_id: al('turu_id'),
        degisiklik_tarihi: al('degisiklik_tarihi'),
        eski_deger: al('eski_deger'),
        yeni_deger: al('yeni_deger'),
        aciklama: al('aciklama'),
        referans_no: al('referans_no'),
        konu: al('konu')
      };

      BT.post('<?= site_url('sicil/kaydet') ?>', veri)
        .then(function (j) {
          if (!j.durum) throw new Error(j.mesaj || 'Kayıt başarısız.');
          if (j.id && j.gorevler) {
            // Yeni kayıt: sonucu göster
            var detayLink = '<?= site_url('sicil/detay') ?>/' + j.id;
            var govde = document.getElementById('sicil-sonuc');
            var tbody = document.getElementById('sonuc-tablo');
            tbody.innerHTML = '';
            if (j.gorevler.length === 0) {
              tbody.innerHTML = '<tr><td colspan="3" class="orta kucuk-yazi">Bu tür için tanımlı aktif bildirim kuralı yok.</td></tr>';
            } else {
              j.gorevler.forEach(function (g) {
                tbody.innerHTML += '<tr><td><span class="rozet mavi">' + g.kurum_ad + '</span></td>'
                  + '<td><b>' + g.son_tarih + '</b></td><td><span class="rozet gri">Bekliyor</span></td></tr>';
              });
            }
            document.getElementById('sonuc-git').href = detayLink;
            var bilgi = document.getElementById('sicil-bildirim');
            bilgi.innerHTML = '<div class="uyari basari"><span class="ik">✓</span><div>'
              + 'Değişiklik kaydedildi. <b>' + j.gorevler.length + ' bildirim görevi</b> oluşturuldu.</div></div>';
            govde.style.display = '';
            // Formu gizle
            btn.closest('.kart').style.display = 'none';
            var ust = document.querySelector('.kart-baslik');
            if (ust) ust.style.display = 'none';
          } else {
            // Güncelleme: detaya dön
            window.location.href = '<?= site_url('sicil/detay') ?>/' + j.id;
          }
        })
        .catch(function (e) {
          document.getElementById('sicil-bildirim').innerHTML =
            '<div class="uyari hata"><span class="ik">✕</span><div>' + e.message + '</div></div>';
        });
    });
  }
})();
</script>

<?= $this->endSection() ?>
