<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<style>
/* ===================== YAPIŞKAN NOTLAR — DUVAR ===================== */
.yk-ust{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.yk-ust input[type=search]{flex:1;min-width:200px;padding:8px 12px;border:1px solid var(--cizgi-koyu);
  border-radius:8px;font:inherit;background:var(--yuzey);color:var(--metin)}
.yk-sayac{font-size:12.5px;color:var(--gri-500);font-weight:600;white-space:nowrap}
.yk-durum-yazi{font-size:12px;color:var(--gri-500);margin-left:auto}
.yk-kisayol-ipucu{font-size:12px;color:var(--gri-500);display:flex;gap:4px;align-items:center}
.yk-kisayol-ipucu kbd{background:var(--yuzey);border:1px solid var(--cizgi-koyu);border-bottom-width:2px;
  border-radius:5px;padding:0 5px;font:600 11px ui-monospace,Menlo,monospace;color:var(--metin)}

.yk-duvar{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:26px 22px;
  padding:14px 6px 30px}
.yk-baslik{grid-column:1/-1;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;
  color:var(--gri-500);font-weight:700;margin-top:4px}

/* Kart: kâğıt rengi, üst şerit, raptiye, hafif eğim */
.yk-kart{position:relative;background:var(--kart);color:#1f2937;min-height:190px;border-radius:4px;
  box-shadow:0 4px 10px rgba(15,23,42,.16);display:flex;flex-direction:column;
  transform:rotate(var(--egim,0deg));transition:transform .18s,box-shadow .18s,opacity .15s;cursor:default}
.yk-kart:hover{transform:rotate(0deg) translateY(-3px);box-shadow:0 10px 22px rgba(15,23,42,.22);z-index:2}
.yk-kart.surukleniyor{opacity:.45}
.yk-kart.hedef{outline:2px dashed #2563eb;outline-offset:5px}
.yk-kart.gizli{display:none}
.yk-kart[data-hat-durum="gecmis"]{box-shadow:0 4px 10px rgba(15,23,42,.16),inset 0 0 0 2px #b91c1c}

.yk-raptiye{position:absolute;top:-9px;left:50%;transform:translateX(-50%);width:20px;height:20px;
  border-radius:50%;background:var(--bant);border:3px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.28);z-index:3}

.yk-bant{background:var(--bant);border-radius:4px 4px 0 0;padding:6px 6px 6px 10px;min-height:34px;
  display:flex;align-items:center;gap:6px}
.yk-baslik-alan{flex:1;min-width:0;background:transparent;border:0;border-bottom:1px dashed rgba(31,41,55,.35);
  font-weight:700;font-size:12.5px;line-height:1.2;font-family:inherit;color:#1f2937;padding:3px 0;outline:none}
.yk-baslik-alan::placeholder{color:rgba(31,41,55,.55);font-weight:600}
.yk-baslik-alan:focus{border-bottom-color:#1f2937;background:rgba(255,255,255,.25)}
.yk-ikonlar{margin-left:auto;display:flex;gap:1px;flex:0 0 auto}
.yk-ikon{background:none;border:0;cursor:pointer;font-size:13px;padding:2px 4px;border-radius:5px;opacity:.6;color:#1f2937}
.yk-ikon:hover{opacity:1;background:rgba(0,0,0,.09)}
.yk-ikon.acik{opacity:1}

.yk-govde{flex:1;padding:8px 12px 4px;font-size:14px;line-height:1.5;color:#1f2937;
  white-space:pre-wrap;word-break:break-word;min-height:92px;outline:none;border-radius:4px}
.yk-govde:focus{background:rgba(255,255,255,.38)}
.yk-govde:empty::before{content:"Notunuzu yazın…";color:rgba(31,41,55,.45)}

.yk-alt{display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding:0 8px 7px 10px;min-height:26px;
  font-size:10.5px;color:rgba(31,41,55,.9);font-weight:600}
.yk-hat{border:0;cursor:pointer;font:inherit;font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;
  background:rgba(255,255,255,.55);color:#1f2937}
.yk-hat:hover{background:rgba(255,255,255,.85)}
.yk-hat-gecmis{background:#b91c1c;color:#fff}
.yk-hat-bugun{background:#1f2937;color:#fff}
.yk-hat-yakin{background:#fde68a;color:#78350f}
.yk-hat-tamam{background:rgba(255,255,255,.35);color:rgba(31,41,55,.7);text-decoration:line-through}
.yk-tamam{border:0;cursor:pointer;font:inherit;font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;
  background:#1f2937;color:#fff}
.yk-tamam:hover{background:#000}
.yk-say{margin-left:auto}
.yk-say.asim{color:#b91c1c;font-weight:800}
.yk-kaydet{font-style:italic;min-width:0}

.yk-renkmenu,.yk-hatsec{position:absolute;right:8px;background:var(--yuzey);border:1px solid var(--cizgi);
  border-radius:10px;padding:7px;display:none;z-index:5;box-shadow:0 8px 20px rgba(15,23,42,.22)}
.yk-renkmenu{top:38px;gap:6px}
.yk-renkmenu.acik{display:flex}
.yk-renknokta{width:22px;height:22px;border-radius:50%;border:2px solid #fff;cursor:pointer;
  background:var(--nokta);box-shadow:0 0 0 1px #cbd5e1;padding:0}
.yk-renknokta.secili{box-shadow:0 0 0 2px #2563eb}
.yk-hatsec{top:38px;left:8px;right:auto;flex-direction:column;gap:6px;min-width:200px;color:var(--metin)}
.yk-hatsec.acik{display:flex}
.yk-hatsec-etiket{font-size:11.5px;font-weight:700;color:var(--gri-600)}
.yk-hatsec-tarih{font:inherit;font-size:13px;padding:5px 7px;border:1px solid var(--cizgi-koyu);border-radius:6px;
  background:var(--yuzey);color:var(--metin)}
.yk-hatsec-dugme{display:flex;gap:6px}
.yk-kucuk{background:#2563eb;color:#fff;border:0;border-radius:7px;padding:4px 10px;font:inherit;font-size:12px;
  font-weight:600;cursor:pointer}
.yk-kucuk.ik{background:var(--yuzey);color:var(--metin);border:1px solid var(--cizgi-koyu)}

.yk-bos{grid-column:1/-1;text-align:center;padding:50px 20px;color:var(--gri-500)}
.yk-bos-ikon{font-size:40px;opacity:.55;margin-bottom:8px}

/* Arşiv bölümü */
.yk-arsiv-kutu{border:1px dashed var(--cizgi-koyu);border-radius:10px;background:var(--yuzey);margin:8px 0 14px}
.yk-arsiv-bas{padding:10px 14px;font-weight:700;font-size:13px;cursor:pointer;color:var(--gri-600);
  user-select:none;display:flex;align-items:center;gap:8px;background:none;border:0;width:100%;font-family:inherit;text-align:left}
.yk-arsiv-liste{display:none;padding:0 12px 12px;flex-direction:column;gap:8px}
.yk-arsiv-liste.acik{display:flex}
.yk-arsiv-satir{display:flex;align-items:center;gap:10px;background:var(--gri-50);border:1px solid var(--cizgi);
  border-radius:9px;padding:8px 10px}
.yk-arsiv-ac{flex:1;min-width:0;font-size:12.5px;color:var(--gri-600);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.yk-arsiv-ac b{color:var(--metin)}
.yk-arsiv-bos{font-size:12.5px;color:var(--gri-500);padding:6px 2px}

/* Kısayol penceresi */
.yk-tus{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:9000}
.yk-tus.acik{display:flex}
.yk-tus-pencere{background:var(--yuzey);border-radius:14px;padding:20px 22px;min-width:300px;color:var(--metin);
  box-shadow:0 20px 50px rgba(0,0,0,.3)}
.yk-tus-pencere h3{margin:0 0 10px;font-size:15px}
.yk-tus-pencere td{padding:6px 14px 6px 0;font-size:13px}
.yk-tus-pencere kbd{background:var(--yuzey);border:1px solid var(--cizgi-koyu);border-bottom-width:2px;border-radius:5px;
  padding:1px 6px;font:600 11px ui-monospace,Menlo,monospace;color:var(--metin)}

.yk-gizlilik{margin-top:6px;padding:10px 14px;background:var(--yesil-cok-acik);border:1px solid var(--yesil-kenar-acik);
  border-radius:10px;font-size:12.5px;color:var(--yesil-metin-koyu)}

@media (prefers-reduced-motion:reduce){.yk-kart{transition:none}}
</style>

<?= $this->include('kisisel/_sekme') ?>

<div class="kart-baslik" style="margin:0 0 6px;border:0;padding:0;background:none">
  <h2>📌 Yapışkan Notlar</h2>
  <span class="kucuk-yazi">Yalnız siz görürsünüz — yönetici dâhil kimse erişemez.</span>
</div>

<div class="yk-ust">
  <input type="search" id="yk-ara" placeholder="🔎 Notlarda ara… ( / )" aria-label="Notlarda ara">
  <button type="button" class="btn" id="yk-yeni">➕ Yeni not</button>
  <button type="button" class="btn ikincil" id="yk-yardim" title="Klavye kısayolları">⌨ Kısayollar</button>
  <span class="yk-sayac" id="yk-sayac"><?= count($notlar) ?> / <?= (int) $maksKart ?> not</span>
  <span class="yk-durum-yazi" id="yk-genel">Hazır</span>
</div>

<div class="yk-duvar" id="yk-duvar"
     data-maks-kart="<?= (int) $maksKart ?>" data-maks-metin="<?= (int) $maksMetin ?>">
  <?= view('kisisel/_yapiskan_duvar', ['notlar' => $notlar, 'renkler' => $renkler, 'yazi' => $yazi, 'bugun' => $bugun]) ?>
</div>

<div class="yk-arsiv-kutu">
  <button type="button" class="yk-arsiv-bas" id="yk-arsiv-baslik" aria-expanded="false">
    <span>🗃 Arşiv</span> <span id="yk-arsiv-say">(<?= count($arsivNotlar) ?>)</span> <span id="yk-arsiv-ok">▸</span>
  </button>
  <div class="yk-arsiv-liste" id="yk-arsiv-liste"><?= view('kisisel/_yapiskan_arsiv', ['arsivNotlar' => $arsivNotlar]) ?></div>
</div>

<div class="yk-gizlilik">
  🔒 Yapışkan notlar kişiseldir. Hatırlatma tarihi geldiğinde menüdeki Kişisel Notlar rozetinde ve oturum açınca
  çıkan pencerede görünür; <b>✓ Tamam</b> dediğinizde kaybolur (tarih silinmez).
</div>

<div class="yk-tus" id="yk-tus" role="dialog" aria-label="Klavye kısayolları">
  <div class="yk-tus-pencere">
    <h3>⌨ Klavye kısayolları</h3>
    <table>
      <tr><td><kbd>Ctrl</kbd> + <kbd>Enter</kbd></td><td>Yeni yapışkan not</td></tr>
      <tr><td><kbd>Esc</kbd></td><td>Düzenlemeyi / açık pencereyi kapat</td></tr>
      <tr><td><kbd>/</kbd></td><td>Aramaya odaklan</td></tr>
    </table>
    <div style="margin-top:14px;text-align:right"><button type="button" class="btn" id="yk-tus-kapat">Tamam</button></div>
  </div>
</div>

<script>
(function () {
  var URL = {
    ekle:     '<?= site_url('kisisel/yapiskan/ekle') ?>',
    guncelle: '<?= site_url('kisisel/yapiskan/guncelle') ?>',
    sil:      '<?= site_url('kisisel/yapiskan/sil') ?>',
    sirala:   '<?= site_url('kisisel/yapiskan/sirala') ?>'
  };
  var MAKS_METIN = <?= (int) $maksMetin ?>;
  var MAKS_BASLIK = <?= (int) $maksBaslik ?>;

  var duvar    = document.getElementById('yk-duvar');
  var ara      = document.getElementById('yk-ara');
  var sayac    = document.getElementById('yk-sayac');
  var genel    = document.getElementById('yk-genel');
  var arsivListe = document.getElementById('yk-arsiv-liste');
  var zamanlar = {};     // "m<id>" / "b<id>" → otomatik kayıt zamanlayıcısı
  var sonAra   = '';

  function genelYaz(metin) { genel.textContent = metin; }
  function saat() { return new Date().toTimeString().slice(0, 5); }

  /* ---------- Sunucu yanıtını uygula (duvar + arşiv + sayaçlar) ---------- */
  function duvarYaz(j) {
    if (j.listeHtml) { duvar.innerHTML = j.listeHtml; }
    if (j.arsivHtml) { arsivListe.innerHTML = j.arsivHtml; }
    aramaUygula();
    sayacGuncelle(j.sayi);
    arsivSayiYaz(j.arsivSayi);
  }
  function sayacGuncelle(sayi) {
    var max = parseInt(duvar.getAttribute('data-maks-kart'), 10) || 0;
    var n = (typeof sayi === 'number') ? sayi : duvar.querySelectorAll('.yk-kart').length;
    sayac.textContent = n + ' / ' + max + ' not';
  }
  function arsivSayiYaz(n) {
    if (typeof n !== 'number') { n = arsivListe.querySelectorAll('.yk-arsiv-satir').length; }
    document.getElementById('yk-arsiv-say').textContent = '(' + n + ')';
  }

  function istek(url, veri) { return BT.post(url, veri); }
  function hata(e) { BT.bildir((e && e.message) || 'İşlem başarısız.', 'hata'); genelYaz('Hata'); }

  /* ---------- ARAMA (başlık + metin) ---------- */
  function aramaUygula() {
    var q = (sonAra || '').toLowerCase().trim();
    duvar.querySelectorAll('.yk-kart').forEach(function (k) {
      var baslik = (k.querySelector('.yk-baslik-alan').value || '');
      var metin  = (k.querySelector('.yk-govde').innerText || '');
      var hayat  = (baslik + ' ' + metin).toLowerCase();
      k.classList.toggle('gizli', q !== '' && hayat.indexOf(q) === -1);
    });
  }
  ara.addEventListener('input', function () { sonAra = ara.value; aramaUygula(); });

  /* ---------- YENİ KART ---------- */
  function yeniKart() {
    genelYaz('Ekleniyor…');
    istek(URL.ekle, { metin: '', renk: 'sari' }).then(function (j) {
      duvarYaz(j); genelYaz('Hazır');
      var kartlar = duvar.querySelectorAll('.yk-kart');
      if (kartlar.length) { kartlar[kartlar.length - 1].querySelector('.yk-govde').focus(); }
    }).catch(hata);
  }
  document.getElementById('yk-yeni').addEventListener('click', yeniKart);

  /* ---------- METİN ve BAŞLIK: yazarken otomatik kayıt (duvar yenilenmez) ---------- */
  function sayacYaz(kart, metin) {
    var say = kart.querySelector('.yk-say');
    say.textContent = metin.length + '/' + MAKS_METIN;
    say.classList.toggle('asim', metin.length > MAKS_METIN);
  }

  duvar.addEventListener('input', function (e) {
    var govde = e.target.closest('.yk-govde');
    var baslikAlan = e.target.classList && e.target.classList.contains('yk-baslik-alan') ? e.target : null;

    if (govde) {
      var kart = govde.closest('.yk-kart');
      var metin = govde.innerText.replace(/\n$/, '');
      sayacYaz(kart, metin);
      genelYaz('Yazılıyor…');
      kart.querySelector('.yk-kaydet').textContent = '';
      clearTimeout(zamanlar['m' + kart.dataset.id]);
      zamanlar['m' + kart.dataset.id] = setTimeout(function () { metinKaydet(kart, metin); }, 800);
    }

    if (baslikAlan) {
      var k2 = baslikAlan.closest('.yk-kart');
      clearTimeout(zamanlar['b' + k2.dataset.id]);
      zamanlar['b' + k2.dataset.id] = setTimeout(function () { baslikKaydet(k2, baslikAlan.value); }, 800);
    }
  });

  function metinKaydet(kart, metin) {
    var durum = kart.querySelector('.yk-kaydet');
    if (metin.length > MAKS_METIN) { durum.textContent = 'Çok uzun — kaydedilmedi'; genelYaz('Hata'); return; }
    durum.textContent = 'Kaydediliyor…';
    istek(URL.guncelle, { id: kart.dataset.id, metin: metin }).then(function () {
      durum.textContent = 'Kaydedildi ✓ ' + saat(); genelYaz('Kaydedildi');
    }).catch(function (e) { durum.textContent = 'Kaydedilemedi'; hata(e); });
  }

  function baslikKaydet(kart, baslik) {
    baslik = (baslik || '').trim().slice(0, MAKS_BASLIK);
    genelYaz('Kaydediliyor…');
    istek(URL.guncelle, { id: kart.dataset.id, baslik: baslik }).then(function () {
      genelYaz('Başlık kaydedildi');
    }).catch(hata);
  }

  // Odak kaybolunca bekleyen kayıt hemen yazılsın
  duvar.addEventListener('focusout', function (e) {
    var kart = e.target.closest && e.target.closest('.yk-kart');
    if (!kart) { return; }
    var id = kart.dataset.id;
    if (e.target.classList.contains('yk-govde') && zamanlar['m' + id]) {
      clearTimeout(zamanlar['m' + id]); zamanlar['m' + id] = null;
      metinKaydet(kart, e.target.innerText.replace(/\n$/, ''));
    }
    if (e.target.classList.contains('yk-baslik-alan') && zamanlar['b' + id]) {
      clearTimeout(zamanlar['b' + id]); zamanlar['b' + id] = null;
      baslikKaydet(kart, e.target.value);
    }
  });

  // Yapıştırmada biçim gelmesin: yalnız düz metin
  duvar.addEventListener('paste', function (e) {
    if (!e.target.closest('.yk-govde')) { return; }
    e.preventDefault();
    var t = (e.clipboardData || window.clipboardData).getData('text');
    document.execCommand('insertText', false, t);
  });

  // Başlık alanında Enter = odağı bırak (kayıt focusout'ta yapılır)
  duvar.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.classList.contains('yk-baslik-alan')) { e.preventDefault(); e.target.blur(); }
  });

  /* ---------- DÜĞMELER ---------- */
  function kapatMenuler(istisna) {
    document.querySelectorAll('.yk-renkmenu.acik,.yk-hatsec.acik').forEach(function (m) {
      if (m !== istisna) { m.classList.remove('acik'); }
    });
  }

  duvar.addEventListener('click', function (e) {
    var kart = e.target.closest('.yk-kart');
    if (!kart) { return; }
    var id = kart.dataset.id;

    var renkNokta = e.target.closest('.yk-renknokta');
    if (renkNokta) {
      istek(URL.guncelle, { id: id, renk: renkNokta.getAttribute('data-renk') })
        .then(function (j) { duvarYaz(j); genelYaz('Renk değişti'); }).catch(hata);
      return;
    }

    var ikon = e.target.closest('[data-islem]');
    if (!ikon) { return; }
    var islem = ikon.getAttribute('data-islem');

    if (islem === 'renk') {
      var menu = kart.querySelector('.yk-renkmenu');
      kapatMenuler(menu);
      menu.classList.toggle('acik');
      return;
    }

    if (islem === 'hat') {
      var sec = kart.querySelector('.yk-hatsec');
      kapatMenuler(sec);
      sec.classList.toggle('acik');
      if (sec.classList.contains('acik')) { sec.querySelector('.yk-hatsec-tarih').focus(); }
      return;
    }

    if (islem === 'hat-kaydet') {
      var t = kart.querySelector('.yk-hatsec-tarih').value;
      if (!t) { BT.bildir('Lütfen bir tarih seçin (kaldırmak için Kaldır).', 'hata'); return; }
      istek(URL.guncelle, { id: id, hatirlat: t })
        .then(function (j) { duvarYaz(j); genelYaz('Hatırlatma kaydedildi'); }).catch(hata);
      return;
    }

    if (islem === 'hat-sil') {
      istek(URL.guncelle, { id: id, hatirlat: '' })
        .then(function (j) { duvarYaz(j); genelYaz('Hatırlatma kaldırıldı'); }).catch(hata);
      return;
    }

    if (islem === 'tamam') {
      istek(URL.guncelle, { id: id, tamam: '1' })
        .then(function (j) { duvarYaz(j); genelYaz('Hatırlatma tamamlandı ✓'); }).catch(hata);
      return;
    }

    if (islem === 'sabit') {
      var yeniSabit = kart.getAttribute('data-sabit') === '1' ? '0' : '1';
      genelYaz('Kaydediliyor…');
      istek(URL.guncelle, { id: id, sabit: yeniSabit })
        .then(function (j) { duvarYaz(j); genelYaz(yeniSabit === '1' ? 'Sabitlendi' : 'Sabit kaldırıldı'); })
        .catch(hata);
      return;
    }

    if (islem === 'arsiv') {
      istek(URL.guncelle, { id: id, arsiv: '1' })
        .then(function (j) { duvarYaz(j); genelYaz('Arşive taşındı'); }).catch(hata);
      return;
    }

    if (islem === 'sil') {
      if (!window.confirm('Bu yapışkan not kalıcı olarak silinsin mi?')) { return; }
      istek(URL.sil, { id: id })
        .then(function (j) { duvarYaz(j); genelYaz('Silindi'); }).catch(hata);
    }
  });

  // Arşiv listesi: geri al / kalıcı sil
  arsivListe.addEventListener('click', function (e) {
    var b = e.target.closest('[data-arsiv-islem]');
    if (!b) { return; }
    var satir = b.closest('.yk-arsiv-satir');
    var id = satir.dataset.id;

    if (b.getAttribute('data-arsiv-islem') === 'geri') {
      istek(URL.guncelle, { id: id, arsiv: '0' })
        .then(function (j) { duvarYaz(j); genelYaz('Geri alındı'); }).catch(hata);
      return;
    }
    if (b.getAttribute('data-arsiv-islem') === 'sil') {
      if (!window.confirm('Arşivdeki bu not kalıcı olarak silinsin mi?')) { return; }
      istek(URL.sil, { id: id })
        .then(function (j) { duvarYaz(j); genelYaz('Kalıcı silindi'); }).catch(hata);
    }
  });

  // Arşiv bölümünü aç/kapat
  document.getElementById('yk-arsiv-baslik').addEventListener('click', function () {
    var acik = !arsivListe.classList.contains('acik');
    arsivListe.classList.toggle('acik', acik);
    this.setAttribute('aria-expanded', acik ? 'true' : 'false');
    document.getElementById('yk-arsiv-ok').textContent = acik ? '▾' : '▸';
  });

  // Açık menü/pencereler dışına tıklanınca kapansın
  document.addEventListener('click', function (e) {
    if (e.target.closest('.yk-renkmenu,.yk-hatsec,[data-islem="renk"],[data-islem="hat"]')) { return; }
    kapatMenuler(null);
  });

  /* ---------- KLAVYE KISAYOLLARI (E4) ---------- */
  var tus = document.getElementById('yk-tus');
  function tusAc(acik) { tus.classList.toggle('acik', acik); }
  document.getElementById('yk-yardim').addEventListener('click', function () { tusAc(true); });
  document.getElementById('yk-tus-kapat').addEventListener('click', function () { tusAc(false); });
  tus.addEventListener('click', function (e) { if (e.target === tus) { tusAc(false); } });

  document.addEventListener('keydown', function (e) {
    var duzenleniyor = e.target && e.target.closest && e.target.closest('.yk-govde,.yk-baslik-alan,#yk-ara,input,textarea');

    if (e.ctrlKey && e.key === 'Enter') { e.preventDefault(); yeniKart(); return; }

    if (e.key === 'Escape') {
      if (tus.classList.contains('acik')) { tusAc(false); return; }
      if (document.querySelector('.yk-renkmenu.acik,.yk-hatsec.acik')) { kapatMenuler(null); return; }
      if (e.target && e.target.blur) { e.target.blur(); }
      return;
    }

    if (e.key === '/' && !duzenleniyor) { e.preventDefault(); ara.focus(); }
  });

  /* ---------- SÜRÜKLE-BIRAK (yalnız aynı grup içinde: sabit/diğer) ---------- */
  var suruklenen = null;

  duvar.addEventListener('dragstart', function (e) {
    var kart = e.target.closest('.yk-kart');
    if (!kart || e.target.closest('.yk-govde,.yk-baslik-alan')) { e.preventDefault(); return; }
    suruklenen = kart;
    kart.classList.add('surukleniyor');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', kart.getAttribute('data-id'));
  });

  duvar.addEventListener('dragover', function (e) {
    var hedef = e.target.closest('.yk-kart');
    if (!hedef || !suruklenen || hedef === suruklenen) { return; }
    if (hedef.getAttribute('data-sabit') !== suruklenen.getAttribute('data-sabit')) { return; }
    e.preventDefault();
    duvar.querySelectorAll('.hedef').forEach(function (x) { x.classList.remove('hedef'); });
    hedef.classList.add('hedef');
  });

  duvar.addEventListener('drop', function (e) {
    var hedef = e.target.closest('.yk-kart');
    if (!hedef || !suruklenen || hedef === suruklenen) { return; }
    if (hedef.getAttribute('data-sabit') !== suruklenen.getAttribute('data-sabit')) { return; }
    e.preventDefault();

    var kartlar = Array.prototype.slice.call(duvar.querySelectorAll('.yk-kart'));
    var yeniSira = kartlar.filter(function (k) { return k !== suruklenen; });
    yeniSira.splice(yeniSira.indexOf(hedef), 0, suruklenen);
    yeniSira.forEach(function (k) { duvar.appendChild(k); });

    var idler = yeniSira.map(function (k) { return k.getAttribute('data-id'); });
    genelYaz('Sıra kaydediliyor…');
    istek(URL.sirala, { idler: idler }).then(function (j) { duvarYaz(j); genelYaz('Sıra kaydedildi'); })
      .catch(hata);
  });

  duvar.addEventListener('dragend', function () {
    duvar.querySelectorAll('.surukleniyor,.hedef').forEach(function (x) {
      x.classList.remove('surukleniyor', 'hedef');
    });
    suruklenen = null;
  });

  /* ---------- İLK DURUM ---------- */
  aramaUygula();
  sayacGuncelle();
  genelYaz('Hazır');
}());
</script>
<?= $this->endSection() ?>
