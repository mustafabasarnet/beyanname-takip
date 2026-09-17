<?php
/**
 * KİŞİSEL TO-DO — GİRİŞ HATIRLATMASI (popup)
 *
 * Son tarihi geçen ("dünden kalanlar") ve bugün son gün olan kişisel görevleri
 * günde bir kez, giriş sonrası ilk sayfada gösterir.
 *
 * Davranış:
 *   - Ajanda giriş uyarısı açıksa önce onun kapanması beklenir (üst üste çıkmaz).
 *   - Kapatınca (Anladım / dışına tık / ESC) sunucuya "okundu" yazılır; aynı
 *     gün bir daha gösterilmez.
 *   - Satırdaki kutudan görev doğrudan "yapıldı" işaretlenebilir; hepsi
 *     tamamlanınca pencere kendiliğinden kapanır.
 *
 * Veri: GET kisisel/giris-uyarisi · Okundu: POST kisisel/uyari-okundu
 */
?>
<div id="ks-uyari-ort" class="ks-uyari-ort" style="display:none">
  <div class="ks-uyari">
    <div class="ks-uyari-bas">
      <span style="font-size:22px">📝</span>
      <div>
        <h3>Kişisel To-Do Hatırlatması</h3>
        <small id="ks-uyari-ozet"></small>
      </div>
    </div>
    <div class="ks-uyari-govde" id="ks-uyari-liste"></div>
    <div class="ks-uyari-alt">
      <a href="<?= site_url('kisisel') ?>" class="btn ikincil kucuk">📝 Kişisel Notları Aç</a>
      <button type="button" class="btn kucuk" id="ks-uyari-kapat">Anladım</button>
    </div>
  </div>
</div>

<style>
.ks-uyari-ort{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;
  display:flex;align-items:center;justify-content:center;padding:20px}
.ks-uyari{background:#fff;border-radius:14px;max-width:620px;width:100%;
  max-height:82vh;overflow:auto;box-shadow:0 20px 50px rgba(0,0,0,.3)}
.ks-uyari-bas{padding:15px 20px;border-bottom:1px solid #e2e8f0;
  display:flex;align-items:center;gap:11px;position:sticky;top:0;background:#fff;z-index:2}
.ks-uyari-bas h3{margin:0;font-size:16px}
.ks-uyari-bas small{color:#64748b;font-size:12px}
.ks-uyari-govde{padding:6px 20px 14px}
.ks-uyari-alt{padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;gap:10px;
  justify-content:flex-end;background:#f8fafc;border-radius:0 0 14px 14px;
  position:sticky;bottom:0}
.ks-grup{font-size:11.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;
  color:#64748b;margin:14px 0 2px}
.ks-grup.gec{color:#b91c1c}
.ks-grup.bug{color:#c2410c}
.ks-is{display:flex;align-items:flex-start;gap:10px;padding:9px 0;
  border-bottom:1px solid #f1f5f9}
.ks-is:last-child{border-bottom:0}
.ks-kutu{width:19px;height:19px;flex:0 0 19px;margin-top:2px;border:2px solid #94a3b8;
  border-radius:6px;background:#fff;cursor:pointer;padding:0}
.ks-kutu:hover{border-color:#059669;background:#ecfdf5}
.ks-kutu:disabled{opacity:.5;cursor:default}
.ks-is .ad{flex:1;font-size:13.5px;min-width:0}
.ks-is .ad a{color:#0f172a;font-weight:600;text-decoration:none}
.ks-is .ad a:hover{text-decoration:underline}
.ks-is .ad small{display:block;color:#64748b;font-size:11.5px;margin-top:2px}
.ks-is .ad .not{color:#64748b;font-size:12px;margin-top:2px}
.ks-gec{background:#dc2626;color:#fff;padding:1px 8px;border-radius:99px;
  font-size:10.5px;font-weight:700;white-space:nowrap}
.ks-bug{background:#ea580c;color:#fff;padding:1px 8px;border-radius:99px;
  font-size:10.5px;font-weight:700;white-space:nowrap}
.ks-yak{background:#e2e8f0;color:#334155;padding:1px 8px;border-radius:99px;
  font-size:10.5px;font-weight:700;white-space:nowrap}
.ks-etiket{background:#eef2ff;color:#3730a3;padding:1px 7px;border-radius:6px;
  font-size:10.5px;font-weight:600;margin-left:6px}
.ks-altbilgi{margin-top:14px;padding-top:10px;border-top:1px dashed #e2e8f0;
  color:#64748b;font-size:12px}
</style>

<script>
(function () {
  'use strict';

  var ort = document.getElementById('ks-uyari-ort');
  if (!ort) { return; }

  var CSRF_AD  = <?= json_encode(csrf_token()) ?>;
  var CSRF_DEG = <?= json_encode(csrf_hash()) ?>;

  var veri       = null;   // sunucudan gelen hatırlatma verisi
  var tamamlanan = 0;      // pencere içinden işaretlenen görev sayısı (rozet için)

  // ---- CSRF'li POST yardımcısı (uygulama.js yüklenmeden de çalışır) ----
  function gonder(url, veri) {
    var g = new URLSearchParams();
    g.append(CSRF_AD, CSRF_DEG);

    Object.keys(veri || {}).forEach(function (k) { g.append(k, veri[k]); });

    return fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: g
    }).then(function (r) { return r.json(); });
  }

  function bildir(mesaj, tip) {
    if (window.BT && BT.bildir) { BT.bildir(mesaj, tip); }
  }

  function kapat() {
    ort.style.display = 'none';
    gonder('<?= site_url('kisisel/uyari-okundu') ?>', {}).catch(function () { /* sessiz */ });
  }

  document.getElementById('ks-uyari-kapat').addEventListener('click', kapat);
  ort.addEventListener('click', function (e) { if (e.target === ort) { kapat(); } });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ort.style.display !== 'none') { kapat(); }
  });

  // ---- satır ----
  var ONCELIK_AD = { acil: 'Acil', yuksek: 'Yüksek', normal: 'Normal', dusuk: 'Düşük' };

  function satirYap(g) {
    var satir = document.createElement('div');
    satir.className = 'ks-is';
    satir.setAttribute('data-id', g.id);

    var kutu = document.createElement('button');
    kutu.type = 'button';
    kutu.className = 'ks-kutu';
    kutu.title = 'Yapıldı olarak işaretle';
    kutu.setAttribute('aria-label', 'Yapıldı olarak işaretle');
    kutu.addEventListener('click', function () { tamamla(g.id, satir, kutu); });
    satir.appendChild(kutu);

    var govde = document.createElement('div');
    govde.className = 'ad';

    var bag = document.createElement('a');
    bag.href = '<?= site_url('kisisel') ?>';
    bag.textContent = g.baslik;
    govde.appendChild(bag);

    if (g.etiket) {
      var et = document.createElement('span');
      et.className = 'ks-etiket';
      et.textContent = g.etiket;
      govde.appendChild(et);
    }

    if (g.metin) {
      var nt = document.createElement('div');
      nt.className = 'not';
      nt.textContent = g.metin;
      govde.appendChild(nt);
    }

    var alt = document.createElement('small');
    alt.textContent = '📅 Son: ' + g.son_tarih + ' · ' + (ONCELIK_AD[g.oncelik] || 'Normal') + ' öncelik';
    govde.appendChild(alt);

    satir.appendChild(govde);

    var rz = document.createElement('span');

    if (g.gecikmis) {
      rz.className = 'ks-gec';
      rz.textContent = g.gecikme_gun === 1 ? 'dünden kaldı' : g.gecikme_gun + ' gün gecikti';
    } else if (g.kalan_gun === 0) {
      rz.className = 'ks-bug';
      rz.textContent = 'bugün son gün';
    } else {
      rz.className = 'ks-yak';
      rz.textContent = g.kalan_gun + ' gün kaldı';
    }

    satir.appendChild(rz);

    return satir;
  }

  function grupYap(baslik, sinif, liste) {
    if (!liste || !liste.length) { return null; }

    var kap = document.createElement('div');

    var h = document.createElement('div');
    h.className = 'ks-grup ' + (sinif || '');
    h.textContent = baslik + ' (' + liste.length + ')';
    kap.appendChild(h);

    liste.forEach(function (g) { kap.appendChild(satirYap(g)); });

    return kap;
  }

  function tamamla(id, satir, kutu) {
    kutu.disabled = true;

    gonder('<?= site_url('kisisel/gorev-ters') ?>', { id: id })
      .then(function (j) {
        if (!j.durum) { throw new Error(j.mesaj || 'Güncellenemedi.'); }

        tamamlanan++;
        satir.style.opacity = '.45';
        satir.querySelector('.ad a').style.textDecoration = 'line-through';
        setTimeout(function () {
          if (satir.parentNode) { satir.parentNode.removeChild(satir); }
          kalanGuncelle();
        }, 500);
      })
      .catch(function (e) {
        kutu.disabled = false;
        bildir(e.message, 'hata');
      });
  }

  function kalanGuncelle() {
    var kalan = ort.querySelectorAll('.ks-is').length;

    // Menüdeki Kişisel Notlar rozeti de anında düşsün.
    // Sunucu 'acik' alanıyla kullanıcının TÜM açık görevini bildirir
    // (pencerede listelenmeyen uzak tarihli/tarihsiz görevler dahil);
    // buradan tamamlanan kadarını düşeriz.
    if (window.kisiselRozetGuncelle && veri && typeof veri.acik === 'number') {
      window.kisiselRozetGuncelle(Math.max(0, veri.acik - tamamlanan));
    }

    if (kalan === 0) {
      kapat();
      bildir('Hatırlatmadaki tüm görevler tamamlandı. 🎉', 'basari');

      return;
    }

    var ozet = document.getElementById('ks-uyari-ozet');
    if (ozet) { ozet.textContent = kalan + ' görev bekliyor'; }
  }

  function ciz(v) {
    var kutu = document.getElementById('ks-uyari-liste');
    kutu.innerHTML = '';

    [
      ['⚠ Gecikmiş — dünden kalanlar', 'gec', v.gecikmis],
      ['⏰ Bugün son gün',              'bug', v.bugun],
      ['📅 Yaklaşan (≤ ' + v.gun + ' gün)', '', v.yaklasan]
    ].forEach(function (x) {
      var grup = grupYap(x[0], x[1], x[2]);
      if (grup) { kutu.appendChild(grup); }
    });

    if (v.tarihsiz > 0) {
      var alt = document.createElement('div');
      alt.className = 'ks-altbilgi';
      alt.textContent = 'ℹ Son tarihi belirlenmemiş ' + v.tarihsiz + ' açık görev daha var '
                      + '(son tarih girerseniz bu listede de görünür).';
      kutu.appendChild(alt);
    }

    var ozet = document.getElementById('ks-uyari-ozet');
    if (ozet) { ozet.textContent = v.toplam + ' görev bekliyor'; }

    // Menü rozetini sunucudaki gerçek açık görev sayısıyla eşitle
    if (window.kisiselRozetGuncelle && typeof v.acik === 'number') {
      window.kisiselRozetGuncelle(v.acik);
    }

    ort.style.display = 'flex';
  }

  // ---- Ajanda uyarısı açıksa sıraya gir: iki pencere üst üste çıkmasın ----
  function ajandaAcikMi() {
    var aj = document.getElementById('aj-uyari-ort');

    return aj !== null && aj.style.display !== 'none' && aj.style.display !== '';
  }

  function dene(kalanDeneme) {
    if (ajandaAcikMi() && kalanDeneme > 0) {
      setTimeout(function () { dene(kalanDeneme - 1); }, 400);

      return;
    }

    ciz(veri);
  }

  function baslat() {
    setTimeout(function () { dene(100); }, 350);
  }

  fetch('<?= site_url('kisisel/giris-uyarisi') ?>', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(function (r) { return r.json(); })
    .then(function (v) {
      if (!v.durum || !v.goster) { return; }

      veri = v;

      if (document.readyState === 'complete') { baslat(); }
      else { window.addEventListener('load', baslat); }
    })
    .catch(function () { /* kişisel modül yoksa sessiz geç */ });
}());
</script>
