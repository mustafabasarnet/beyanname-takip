<?php
/**
 * GÜNCELLEME LOGLARI — GİRİŞ PENCERESİ
 *
 * Kullanıcının OKUMADIĞI sürüm notlarını girişte bir kez gösterir.
 * "Okudum" denince o kullanıcı için okundu işaretlenir ve bir daha çıkmaz
 * (okundu bilgisi kişi bazlıdır; başkasını etkilemez).
 *
 * Sıra: ajanda ve kişisel hatırlatma pencereleri açıksa onların kapanması
 * beklenir — üç pencere üst üste çıkmaz.
 *
 * Veri: GET guncellemeler/giris-uyarisi · Okundu: POST guncellemeler/uyari-okundu
 */
?>
<div id="gn-uyari-ort" class="gn-uyari-ort" style="display:none">
  <div class="gn-uyari" role="dialog" aria-modal="true" aria-labelledby="gn-uyari-baslik">
    <div class="gn-uyari-bas">
      <div class="gn-uyari-ikon">🆕</div>
      <div class="gn-uyari-basmetin">
        <h3 id="gn-uyari-baslik">Neler Değişti?</h3>
        <small id="gn-uyari-ozet"></small>
      </div>
      <button type="button" class="gn-uyari-x" id="gn-uyari-x" title="Kapat" aria-label="Kapat">&times;</button>
    </div>

    <div class="gn-uyari-govde" id="gn-uyari-liste"></div>

    <div class="gn-uyari-alt">
      <a href="<?= site_url('guncellemeler') ?>" class="btn ikincil kucuk">📜 Tüm Güncellemeler</a>
      <button type="button" class="btn kucuk gn-okudum" id="gn-uyari-kapat">✓ Okudum, anladım</button>
    </div>
  </div>
</div>

<style>
/* ============ Güncelleme penceresi (modern kart + renkli madde rozetleri) */
.gn-uyari-ort{position:fixed;inset:0;background:rgba(15,23,42,.62);backdrop-filter:blur(3px);
  z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;
  animation:gnFade .18s ease-out}
@keyframes gnFade{from{opacity:0}to{opacity:1}}
@keyframes gnYukari{from{opacity:0;transform:translateY(14px) scale(.985)}to{opacity:1;transform:none}}

.gn-uyari{background:#fff;border-radius:18px;max-width:680px;width:100%;max-height:86vh;
  display:flex;flex-direction:column;overflow:hidden;
  box-shadow:0 24px 60px rgba(15,23,42,.35);animation:gnYukari .22s ease-out}

/* Başlık: mor → mavi degrade, "yeni sürüm" hissi */
.gn-uyari-bas{display:flex;align-items:center;gap:13px;padding:16px 20px;color:#fff;
  background:linear-gradient(120deg,#7c3aed 0%,#2563eb 100%)}
.gn-uyari-ikon{width:42px;height:42px;flex:0 0 42px;border-radius:13px;
  background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;
  font-size:21px;border:1px solid rgba(255,255,255,.25)}
.gn-uyari-basmetin{flex:1;min-width:0}
.gn-uyari-basmetin h3{margin:0;font-size:17px;letter-spacing:-.2px}
.gn-uyari-basmetin small{display:block;color:rgba(255,255,255,.85);font-size:12px;margin-top:2px}
.gn-uyari-x{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);color:#fff;
  width:32px;height:32px;border-radius:10px;font-size:19px;line-height:1;cursor:pointer;
  flex:0 0 auto;transition:.15s}
.gn-uyari-x:hover{background:rgba(255,255,255,.28)}

.gn-uyari-govde{padding:16px 20px 18px;overflow-y:auto}
.gn-uyari-govde::-webkit-scrollbar{width:9px}
.gn-uyari-govde::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:9px}

/* Sürüm kartı */
.gn-surum{border:1px solid var(--gri-200,#e2e8f0);border-radius:14px;padding:13px 15px;
  margin-bottom:12px;background:linear-gradient(180deg,#fbfcfe,#fff)}
.gn-surum:last-child{margin-bottom:0}
.gn-surum-bas{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:9px}
.gn-versiyon{background:#ede9fe;color:#5b21b6;font-weight:800;font-size:11.5px;
  padding:3px 10px;border-radius:99px;letter-spacing:.3px}
.gn-tarih{color:var(--gri-500,#64748b);font-size:12px}
.gn-surum-baslik{font-weight:700;font-size:14.5px;color:var(--gri-900,#0f172a);
  flex:1 1 100%;margin-top:2px}

/* Maddeler */
.gn-madde{display:flex;gap:10px;align-items:flex-start;padding:7px 0;
  border-top:1px dashed var(--gri-100,#f1f5f9);font-size:13.5px;color:var(--gri-700,#334155);
  line-height:1.5}
.gn-madde:first-of-type{border-top:0}
.gn-madde .rz{flex:0 0 auto;font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:99px;
  white-space:nowrap;margin-top:1px;display:inline-flex;align-items:center;gap:4px}
.gn-madde .rz.yesil{background:#d1fae5;color:#065f46}
.gn-madde .rz.mavi{background:#dbeafe;color:#1e40af}
.gn-madde .rz.turuncu{background:#ffedd5;color:#9a3412}
.gn-madde .rz.gri{background:#e2e8f0;color:#475569}
.gn-madde .mt{flex:1;min-width:0}
.gn-madde .mt b{color:var(--gri-900,#0f172a);font-weight:700}

.gn-uyari-alt{padding:13px 20px;border-top:1px solid var(--gri-200,#e2e8f0);
  display:flex;gap:10px;justify-content:space-between;align-items:center;background:#f8fafc}
.gn-okudum{background:#059669}
.gn-okudum:hover{background:#047857}
@media (max-width:560px){
  .gn-uyari-alt{flex-direction:column-reverse;align-items:stretch}
  .gn-uyari-alt .btn{width:100%;justify-content:center}
}
</style>

<script>
(function () {
  'use strict';

  var ort = document.getElementById('gn-uyari-ort');
  if (!ort) { return; }

  var CSRF_AD  = <?= json_encode(csrf_token()) ?>;
  var CSRF_DEG = <?= json_encode(csrf_hash()) ?>;

  var veri = null;   // sunucudan gelen güncellemeler

  function gonder(url, veriObj) {
    var g = new URLSearchParams();
    g.append(CSRF_AD, CSRF_DEG);

    Object.keys(veriObj || {}).forEach(function (k) { g.append(k, veriObj[k]); });

    return fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: g
    }).then(function (r) { return r.json(); });
  }

  function rozetGuncelle(sayi) {
    var el = document.getElementById('guncelleme-menu-rozet');
    if (!el) { return; }

    sayi = parseInt(sayi, 10);
    if (isNaN(sayi) || sayi < 0) { sayi = 0; }

    el.textContent = sayi;
    el.style.display = sayi > 0 ? '' : 'none';
  }

  function kapat() {
    ort.style.display = 'none';

    // GÖSTERİLENLERİ okundu işaretle (kişi bazlı) → bir daha çıkmaz
    var idler = (veri && veri.idler) ? veri.idler : [];

    gonder('<?= site_url('guncellemeler/uyari-okundu') ?>', idler.length ? { 'idler[]': idler } : {})
      .then(function (j) { rozetGuncelle(j && typeof j.kalan === 'number' ? j.kalan : 0); })
      .catch(function () { rozetGuncelle(0); });
  }

  document.getElementById('gn-uyari-kapat').addEventListener('click', kapat);
  document.getElementById('gn-uyari-x').addEventListener('click', kapat);
  ort.addEventListener('click', function (e) { if (e.target === ort) { kapat(); } });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ort.style.display !== 'none') { kapat(); }
  });

  // ---- madde metni: **kalın** desteği (güvenli: önce kaçış, sonra biçim) ----
  function metinYaz(hedef, metin) {
    var parcalar = String(metin).split(/(\*\*[^*]+\*\*)/g);

    parcalar.forEach(function (p) {
      if (p.length > 4 && p.slice(0, 2) === '**' && p.slice(-2) === '**') {
        var b = document.createElement('b');
        b.textContent = p.slice(2, -2);
        hedef.appendChild(b);
      } else if (p !== '') {
        hedef.appendChild(document.createTextNode(p));
      }
    });
  }

  function surumCiz(k) {
    var kap = document.createElement('div');
    kap.className = 'gn-surum';

    var bas = document.createElement('div');
    bas.className = 'gn-surum-bas';

    var v = document.createElement('span');
    v.className = 'gn-versiyon';
    v.textContent = 'v' + k.versiyon;
    bas.appendChild(v);

    var t = document.createElement('span');
    t.className = 'gn-tarih';
    t.textContent = k.tarih;
    bas.appendChild(t);

    var b = document.createElement('div');
    b.className = 'gn-surum-baslik';
    b.textContent = k.baslik;
    bas.appendChild(b);

    kap.appendChild(bas);

    (k.maddeler || []).forEach(function (m) {
      var satir = document.createElement('div');
      satir.className = 'gn-madde';

      var r = document.createElement('span');
      r.className = 'rz ' + (m.renk || 'gri');
      r.textContent = (m.ikon ? m.ikon + ' ' : '') + (m.ad || '');
      satir.appendChild(r);

      var mt = document.createElement('div');
      mt.className = 'mt';
      metinYaz(mt, m.metin || '');
      satir.appendChild(mt);

      kap.appendChild(satir);
    });

    return kap;
  }

  function ciz(v) {
    var kutu = document.getElementById('gn-uyari-liste');
    kutu.innerHTML = '';

    (v.kayitlar || []).forEach(function (k) { kutu.appendChild(surumCiz(k)); });

    var ozet = document.getElementById('gn-uyari-ozet');
    if (ozet) {
      ozet.textContent = v.sayi + ' yeni güncelleme · toplam '
        + (v.kayitlar || []).reduce(function (t, k) { return t + (k.maddeler || []).length; }, 0)
        + ' madde';
    }

    rozetGuncelle(v.sayi || 0);
    ort.style.display = 'flex';
  }

  // ---- Diğer pencereler (ajanda, kişisel) kapanmadan açılmasın ----
  function digerleriKapaliMi() {
    var kapali = function (id) {
      var el = document.getElementById(id);
      return el === null || el.style.display === 'none' || el.style.display === '';
    };

    return kapali('aj-uyari-ort') && kapali('ks-uyari-ort');
  }

  function dene(kalanDeneme) {
    if (!digerleriKapaliMi() && kalanDeneme > 0) {
      setTimeout(function () { dene(kalanDeneme - 1); }, 400);

      return;
    }

    ciz(veri);
  }

  function baslat() {
    setTimeout(function () { dene(150); }, 500);
  }

  fetch('<?= site_url('guncellemeler/giris-uyarisi') ?>', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(function (r) { return r.json(); })
    .then(function (v) {
      if (!v.durum || !v.goster) { return; }

      veri = v;

      if (document.readyState === 'complete') { baslat(); }
      else { window.addEventListener('load', baslat); }
    })
    .catch(function () { /* modül yoksa sessiz geç */ });
}());
</script>
