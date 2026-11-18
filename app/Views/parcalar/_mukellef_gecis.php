<?php
/**
 * MÜKELLEFE HIZLI GEÇİŞ (üst bar arama kutusu)
 *
 * Amaç: Hangi ekranda olursa olsun, mükellef ünvanı / VKN / TCKN / kod
 * yazarak o mükellefin detayına tek tıkla geçmek. Büroda en sık yapılan
 * iş "falanca mükellefin kartını aç" olduğu için üst barda duruyor.
 *
 * Neden ayrı parça: yalnız ana yerleşimde bir kez basılır; kendi stilini
 * ve betiğini taşır (stil.css kopyalanmamış kurulumda da çalışsın).
 *
 * Veri kaynağı: mevcut AJAX ucu  GET /mukellefler/ara?q=...
 *   - Yetki kapsamı SUNUCUDA süzülür (müşavir/personel yalnız kendi
 *     mükelleflerini görür); bu dosya yalnız gösterir.
 *
 * Klavye: ↓/↑ gez, Enter aç, Esc kapat, Tab ile çık.
 * Erişilebilirlik: combobox + listbox rolleri, aria-activedescendant.
 */
$mg_ara = site_url('mukellefler/ara');
?>
<style>
/* ---- Üst bar: mükellefe hızlı geçiş ---- */
.ust-bar .mk-gecis{position:relative;margin-left:16px;width:min(340px,34vw);flex:0 0 auto}
.mk-kutu{display:flex;align-items:center;gap:8px;background:var(--yuzey);
  border:1px solid var(--cizgi-koyu);border-radius:9px;padding:0 10px;height:36px;
  transition:.15s}
.mk-kutu:focus-within{border-color:var(--ana);box-shadow:0 0 0 3px var(--ana-acik)}
.mk-kutu .mk-ikon{font-size:13px;line-height:1;opacity:.7;flex:none}
.mk-kutu input{flex:1;min-width:0;border:0;background:none;font:inherit;font-size:13px;
  color:var(--gri-900);outline:none;padding:0}
.mk-kutu input::placeholder{color:var(--gri-400)}
.mk-kutu .mk-ok{font-size:10px;opacity:.55;flex:none;transition:.15s}
.mk-gecis.acik .mk-kutu .mk-ok{transform:rotate(180deg)}
.mk-temizle{border:0;background:none;color:var(--gri-400);cursor:pointer;font-size:14px;
  line-height:1;padding:2px 4px;border-radius:6px;flex:none}
.mk-temizle:hover{background:var(--gri-100);color:var(--gri-700)}

/* Açılır liste */
.mk-panel{position:absolute;top:calc(100% + 6px);left:0;right:0;z-index:200;
  background:var(--yuzey);border:1px solid var(--cizgi-koyu);border-radius:10px;
  box-shadow:0 14px 34px rgba(0,0,0,.16);overflow:hidden;display:none;
  flex-direction:column}
.mk-gecis.acik .mk-panel{display:flex}
.mk-liste{max-height:min(62vh,520px);overflow-y:auto;padding:4px;
  overscroll-behavior:contain;scrollbar-width:thin}
/* İnce, temaya uyumlu kaydırma çubuğu (koyu temada da görünür) */
.mk-liste::-webkit-scrollbar{width:10px}
.mk-liste::-webkit-scrollbar-thumb{background:var(--gri-300);border-radius:99px;
  border:2px solid var(--yuzey)}
.mk-liste::-webkit-scrollbar-thumb:hover{background:var(--gri-400)}
.mk-oge{display:grid;grid-template-columns:1fr auto auto;align-items:center;gap:10px;
  padding:8px 9px;border-radius:7px;cursor:pointer;text-decoration:none;color:inherit}
.mk-oge:hover,.mk-oge.secili{background:var(--ana-acik)}
.mk-oge .mg-ad{font-size:12.8px;font-weight:600;color:var(--gri-900);
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mk-oge .mg-vkn{font-size:12px;color:var(--gri-500);
  font-variant-numeric:tabular-nums;white-space:nowrap}
.mk-oge .rozet{font-size:10px;padding:2px 7px;letter-spacing:.3px;flex:none}
.mk-bos{padding:14px 12px;font-size:12.5px;color:var(--gri-500);text-align:center}
.mk-alt{border-top:1px solid var(--cizgi);padding:7px 11px;font-size:11.5px;
  color:var(--gri-500);background:var(--gri-50);display:flex;justify-content:space-between;
  align-items:center;gap:8px}
.mk-alt b{color:var(--gri-700);font-variant-numeric:tabular-nums}
.mk-alt .mk-klavye{font-size:10.5px;opacity:.85}
@media (max-width:1100px){ .ust-bar .mk-gecis{width:240px} }
@media (max-width:900px){ .ust-bar .mk-gecis{display:none} }
</style>

<div class="mk-gecis" id="mk-gecis" data-url="<?= esc($mg_ara) ?>">
  <div class="mk-kutu">
    <span class="mk-ikon">🔍</span>
    <input type="text" id="mk-gecis-ara" placeholder="Mükellef ara…" autocomplete="off"
           role="combobox" aria-expanded="false" aria-controls="mk-gecis-liste"
           aria-autocomplete="list" aria-label="Mükellef ara ve kartını aç">
    <button type="button" class="mk-temizle" id="mk-gecis-temizle"
            title="Temizle" aria-label="Aramayı temizle" style="display:none">✕</button>
    <span class="mk-ok">▾</span>
  </div>

  <div class="mk-panel">
    <div class="mk-liste" id="mk-gecis-liste" role="listbox" aria-label="Mükellef sonuçları"></div>
    <div class="mk-alt">
      <span id="mk-gecis-sayac">—</span>
      <span class="mk-klavye">↑↓ gez · Enter aç · Esc kapat</span>
    </div>
  </div>
</div>

<script>
/* Mükellefe hızlı geçiş — AJAX arama + klavye ile gezinme.
   Tek örnek (IIFE): yerleşim her sayfada bir kez basar. */
(function () {
  var kok = document.getElementById('mk-gecis');
  if (!kok || kok.dataset.kuruldu === '1') { return; }
  kok.dataset.kuruldu = '1';

  var giris   = document.getElementById('mk-gecis-ara');
  var panel   = kok.querySelector('.mk-panel');
  var liste   = document.getElementById('mk-gecis-liste');
  var sayac   = document.getElementById('mk-gecis-sayac');
  var temizle = document.getElementById('mk-gecis-temizle');
  var url     = kok.dataset.url;

  var zaman = null;
  var istek  = 0;       // yarış koşulu: geç gelen yanıt yenisini ezmesin
  var ogeIdx = -1;

  /*
   * SONSUZ KAYDIRMA
   *
   * Liste başta 50 kayıtla açılır; kullanıcı listenin sonuna gelince
   * sonraki 50'lik blok AJAX ile eklenir. Böylece 25/50 gibi bir sınır
   * kalmaz, portföyün TAMAMI kaydırarak görülebilir. Arama metni
   * değişince ofset sıfırlanır ve liste baştan çizilir.
   */
  var LIMIT   = 50;
  var ofset   = 0;      // bu aramada çizilmiş kayıt sayısı
  var toplam  = 0;      // eşleşen toplam mükellef (uçtan gelir)
  var dahaVar = false;  // devamı var mı
  var yukleniyor = false;
  var sonQ    = null;   // son çizilen arama metni

  function ac() {
    kok.classList.add('acik');
    giris.setAttribute('aria-expanded', 'true');
  }

  function kapat() {
    kok.classList.remove('acik');
    giris.setAttribute('aria-expanded', 'false');
    ogeIdx = -1;
  }

  function sec(oge) {
    if (!oge) { return; }
    window.location.href = oge.dataset.href;
  }

  function vurgula(i) {
    var ogeler = liste.querySelectorAll('.mk-oge');
    ogeIdx = i;
    ogeler.forEach(function (o, j) { o.classList.toggle('secili', j === i); });
    if (ogeler[i]) {
      ogeler[i].scrollIntoView({ block: 'nearest' });
      giris.setAttribute('aria-activedescendant', ogeler[i].id);
    } else {
      giris.removeAttribute('aria-activedescendant');
    }
  }

  /* Tek satır üret (liste başına ve sonsuz kaydırma eklemelerinde ortak) */
  function ogeYap(m, i) {
      var a = document.createElement('a');
      a.className = 'mk-oge';
      a.id = 'mk-oge-' + i;
      a.href = m.href;
      a.dataset.href = m.href;
      a.setAttribute('role', 'option');
      a.innerHTML = '<span class="mg-ad"></span><span class="mg-vkn"></span><span class="rozet"></span>';
      a.querySelector('.mg-ad').textContent = m.unvan || '';
      a.querySelector('.mg-vkn').textContent = m.vkn || '';
      var rz = a.querySelector('.rozet');
      rz.textContent = m.etiket || '';
      rz.classList.add(m.tip === 'tuzel' ? 'mor' : 'mavi');
      a.addEventListener('click', function (e) { e.preventDefault(); sec(a); });
      a.addEventListener('mouseenter', function () { vurgula(i); });

      return a;
  }

  /* Sonuçları çiz. veri: {durum, sonuclar:[...], toplam, gosterilen, offset, daha_var}
     ekle=true → mevcut listenin altına ekle (sonsuz kaydırma) */
  function ciz(veri, ekle) {
    var sonuclar = (veri && veri.sonuclar) || [];
    var bas = 0;

    if (ekle) {
      bas = liste.querySelectorAll('.mk-oge').length;
      var bosluk = liste.querySelector('.mk-bos');
      if (bosluk) { bosluk.remove(); }
    } else {
      liste.innerHTML = '';
    }

    sonuclar.forEach(function (m, j) {
      liste.appendChild(ogeYap(m, bas + j));
    });

    if (!liste.querySelector('.mk-oge')) {
      var bos = document.createElement('div');
      bos.className = 'mk-bos';
      bos.textContent = giris.value.trim() === ''
        ? 'Mükellef bulunmuyor.'
        : 'Eşleşen mükellef bulunamadı.';
      liste.appendChild(bos);
    }

    toplam  = (veri && typeof veri.toplam === 'number') ? veri.toplam : 0;
    dahaVar = !!(veri && veri.daha_var);
    ofset   = liste.querySelectorAll('.mk-oge').length;

    // Sayaç: çizilen / toplam. Devamı varsa "kaydırın" bilgisi verilir;
    // hepsi yüklendiyse "tümü listelendi" yazar.
    var kalan = Math.max(0, toplam - ofset);
    sayac.innerHTML = ofset + ' / <b>' + toplam + '</b> mükellef'
      + (dahaVar && kalan > 0
          ? ' · <b>' + kalan + '</b> kayıt daha — kaydırın'
          : (toplam > 0 ? ' · tümü listelendi' : ''));
  }

  /* Listenin sonuna gelindi → sonraki bloğu ekle */
  function devam() {
    if (yukleniyor || !dahaVar) { return; }

    yukleniyor = true;
    var benim  = ++istek;

    fetch(url + '?limit=' + LIMIT + '&offset=' + ofset + '&q=' + encodeURIComponent(giris.value.trim()), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (benim !== istek) { return; }
        ciz(d, true);
      })
      .catch(function () { /* sessiz: ağ hatasında liste olduğu gibi kalır */ })
      .then(function () { yukleniyor = false; });
  }

  /* Kaydırma dinleyicisi: sona 40px kalınca devam() */
  liste.addEventListener('scroll', function () {
    if (liste.scrollTop + liste.clientHeight >= liste.scrollHeight - 40) { devam(); }
  });

  /* Sonuç azsa (kaydırma çubuğu yoksa) yine de devamını getir */
  function boslukVarsaDoldur() {
    if (dahaVar && !yukleniyor && liste.scrollHeight <= liste.clientHeight + 4) { devam(); }
  }

  function ara() {
    var q = giris.value.trim();

    // Metin değişti → liste baştan: ofset sıfırlanır
    if (q !== sonQ) { ofset = 0; dahaVar = false; }
    sonQ = q;

    var benim = ++istek;

    fetch(url + '?limit=' + LIMIT + '&offset=0&q=' + encodeURIComponent(q), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (benim !== istek) { return; }   // daha yeni bir istek var
        ciz(d);
        ogeIdx = -1;
        liste.scrollTop = 0;
        ac();
        boslukVarsaDoldur();               // kısa listede devamı hemen gelir
      })
      .catch(function () { /* sessiz: ağ hatasında liste kapalı kalır */ });
  }

  giris.addEventListener('input', function () {
    temizle.style.display = giris.value === '' ? 'none' : '';

    if (zaman) { clearTimeout(zaman); }
    zaman = setTimeout(ara, 180);
  });

  /* Boş kutuya tıklanınca ilk 25 mükellef listelenir (alfabetik). */
  giris.addEventListener('focus', function () { ara(); });

  giris.addEventListener('keydown', function (e) {
    var ogeler = liste.querySelectorAll('.mk-oge');

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!kok.classList.contains('acik')) { ara(); return; }
      vurgula(Math.min(ogeIdx + 1, ogeler.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      vurgula(Math.max(ogeIdx - 1, 0));
    } else if (e.key === 'Enter') {
      if (ogeIdx >= 0 && ogeler[ogeIdx]) { e.preventDefault(); sec(ogeler[ogeIdx]); }
      else if (ogeler[0]) { e.preventDefault(); ara(); }
    } else if (e.key === 'Escape') {
      kapat();
      giris.blur();
    }
  });

  temizle.addEventListener('click', function () {
    giris.value = '';
    temizle.style.display = 'none';
    giris.focus();
    ara();
  });

  /* Dışına tıklanınca kapan */
  document.addEventListener('click', function (e) {
    if (!kok.contains(e.target)) { kapat(); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { kapat(); }
  });
})();
</script>
