<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<style>
/* ===================== YAPIŞKAN NOTLAR — DUVAR ===================== */
.yk-ust{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.yk-ust input[type=search]{flex:1;min-width:200px;padding:8px 12px;border:1px solid var(--cizgi-koyu);
  border-radius:8px;font:inherit;background:var(--yuzey);color:var(--metin)}
.yk-sayac{font-size:12.5px;color:var(--gri-500);font-weight:600;white-space:nowrap}
.yk-durum-yazi{font-size:12px;color:var(--gri-500);margin-left:auto}

.yk-duvar{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:26px 22px;
  padding:14px 6px 30px}
.yk-baslik{grid-column:1/-1;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;
  color:var(--gri-500);font-weight:700;margin-top:4px}

/* Kart: kâğıt rengi, üst şerit, raptiye, hafif eğim */
.yk-kart{position:relative;background:var(--kart);color:#1f2937;min-height:178px;border-radius:4px;
  box-shadow:0 4px 10px rgba(15,23,42,.16);display:flex;flex-direction:column;
  transform:rotate(var(--egim,0deg));transition:transform .18s,box-shadow .18s,opacity .15s;cursor:default}
.yk-kart:hover{transform:rotate(0deg) translateY(-3px);box-shadow:0 10px 22px rgba(15,23,42,.22);z-index:2}
.yk-kart.surukleniyor{opacity:.45}
.yk-kart.hedef{outline:2px dashed #2563eb;outline-offset:5px}
.yk-kart.gizli{display:none}

.yk-raptiye{position:absolute;top:-9px;left:50%;transform:translateX(-50%);width:20px;height:20px;
  border-radius:50%;background:var(--bant);border:3px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.28);z-index:3}

.yk-bant{background:var(--bant);border-radius:4px 4px 0 0;padding:7px 8px 6px 10px;min-height:32px;
  display:flex;align-items:center;gap:6px}
.yk-durum{font-size:11px;font-weight:800;color:#1f2937;opacity:.85;margin-right:auto;white-space:nowrap}
.yk-ikonlar{margin-left:auto;display:flex;gap:2px}
.yk-ikon{background:none;border:0;cursor:pointer;font-size:13px;padding:2px 5px;border-radius:5px;opacity:.6;color:#1f2937}
.yk-ikon:hover{opacity:1;background:rgba(0,0,0,.09)}
.yk-ikon.acik{opacity:1}

.yk-govde{flex:1;padding:8px 12px 4px;font-size:14px;line-height:1.5;color:#1f2937;
  white-space:pre-wrap;word-break:break-word;min-height:98px;outline:none;border-radius:4px}
.yk-govde:focus{background:rgba(255,255,255,.38)}
.yk-govde:empty::before{content:"Notunuzu yazın…";color:rgba(31,41,55,.45)}

.yk-alt{display:flex;justify-content:space-between;align-items:center;padding:0 10px 7px 12px;
  font-size:10.5px;color:rgba(31,41,55,.9);font-weight:600;min-height:18px}
.yk-say.asim{color:#b91c1c;font-weight:800}
.yk-kaydet{font-style:italic}

.yk-renkmenu{position:absolute;top:36px;right:8px;background:var(--yuzey);border:1px solid var(--cizgi);
  border-radius:10px;padding:7px;display:none;gap:6px;z-index:5;box-shadow:0 8px 20px rgba(15,23,42,.22)}
.yk-renkmenu.acik{display:flex}
.yk-renknokta{width:22px;height:22px;border-radius:50%;border:2px solid #fff;cursor:pointer;
  background:var(--nokta);box-shadow:0 0 0 1px #cbd5e1;padding:0}
.yk-renknokta.secili{box-shadow:0 0 0 2px #2563eb}

.yk-bos{grid-column:1/-1;text-align:center;padding:50px 20px;color:var(--gri-500)}
.yk-bos-ikon{font-size:40px;opacity:.55;margin-bottom:8px}

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
  <input type="search" id="yk-ara" placeholder="🔎 Notlarda ara…" aria-label="Notlarda ara">
  <button type="button" class="btn" id="yk-yeni">➕ Yeni not</button>
  <span class="yk-sayac" id="yk-sayac"><?= count($notlar) ?> / <?= (int) $maksKart ?> not</span>
  <span class="yk-durum-yazi" id="yk-genel">Hazır</span>
</div>

<div class="yk-duvar" id="yk-duvar"
     data-maks-kart="<?= (int) $maksKart ?>" data-maks-metin="<?= (int) $maksMetin ?>">
  <?= view('kisisel/_yapiskan_duvar', ['notlar' => $notlar, 'renkler' => $renkler, 'yazi' => $yazi]) ?>
</div>

<div class="yk-gizlilik">
  🔒 Yapışkan notlar kişiseldir. Kartları sürükleyerek sıralayabilir, 📌 ile en üstte sabitleyebilirsiniz.
  Sabitlenenler her zaman üstte kalır.
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

  var duvar   = document.getElementById('yk-duvar');
  var ara     = document.getElementById('yk-ara');
  var sayac   = document.getElementById('yk-sayac');
  var genel   = document.getElementById('yk-genel');
  var zamanlar = {};            // kart id → otomatik kayıt zamanlayıcısı
  var sonAra  = '';

  function genelYaz(metin) { genel.textContent = metin; }
  function saatYaz() {
    var d = new Date();
    return d.toTimeString().slice(0, 5);
  }

  /* Duvarı sunucu HTML'iyle yeniler; arama ve sayaç korunur */
  function duvarYaz(j) {
    if (j.listeHtml) { duvar.innerHTML = j.listeHtml; }
    aramaUygula();
    sayacGuncelle(j.sayi);
  }
  function sayacGuncelle(sayi) {
    var max = parseInt(duvar.getAttribute('data-maks-kart'), 10) || 0;
    var n = (typeof sayi === 'number') ? sayi : duvar.querySelectorAll('.yk-kart').length;
    sayac.textContent = n + ' / ' + max + ' not';
  }

  function istek(url, veri) {
    return BT.post(url, veri).then(function (j) { return j; });
  }
  function hata(e) { BT.bildir(e.message || 'İşlem başarısız.', 'hata'); genelYaz('Hata'); }

  /* ---------- ARAMA ---------- */
  function aramaUygula() {
    var q = (sonAra || '').toLowerCase().trim();
    duvar.querySelectorAll('.yk-kart').forEach(function (k) {
      var metin = (k.querySelector('.yk-govde').innerText || '').toLowerCase();
      k.classList.toggle('gizli', q !== '' && metin.indexOf(q) === -1);
    });
  }
  ara.addEventListener('input', function () { sonAra = ara.value; aramaUygula(); });

  /* ---------- YENİ KART ---------- */
  document.getElementById('yk-yeni').addEventListener('click', function () {
    genelYaz('Ekleniyor…');
    istek(URL.ekle, { metin: '', renk: 'sari' }).then(function (j) {
      duvarYaz(j); genelYaz('Hazır');
      // Yeni kart sıra sonuna düşer → odağı oraya ver
      var kartlar = duvar.querySelectorAll('.yk-kart');
      if (kartlar.length) { kartlar[kartlar.length - 1].querySelector('.yk-govde').focus(); }
    }).catch(hata);
  });

  /* ---------- METİN: yazarken otomatik kayıt ---------- */
  duvar.addEventListener('input', function (e) {
    var govde = e.target.closest('.yk-govde');
    if (!govde) { return; }
    var kart = govde.closest('.yk-kart');
    var id = kart.getAttribute('data-id');
    var metin = govde.innerText.replace(/\n$/, '');

    var say = kart.querySelector('.yk-say');
    say.textContent = metin.length + '/' + MAKS_METIN;
    say.classList.toggle('asim', metin.length > MAKS_METIN);

    genelYaz('Yazılıyor…');
    kart.querySelector('.yk-kaydet').textContent = '';
    clearTimeout(zamanlar[id]);
    zamanlar[id] = setTimeout(function () { metinKaydet(kart, metin); }, 800);
  });

  function metinKaydet(kart, metin) {
    var id = kart.getAttribute('data-id');
    var durum = kart.querySelector('.yk-kaydet');
    if (metin.length > MAKS_METIN) {
      durum.textContent = 'Çok uzun — kaydedilmedi';
      genelYaz('Hata');
      return;
    }
    durum.textContent = 'Kaydediliyor…';
    istek(URL.guncelle, { id: id, metin: metin }).then(function () {
      durum.textContent = 'Kaydedildi ✓ ' + saatYaz();
      genelYaz('Kaydedildi');
    }).catch(function (e) {
      durum.textContent = 'Kaydedilemedi';
      hata(e);
    });
  }

  // Odak kaybolunca bekleyen kaydı hemen yaz
  duvar.addEventListener('focusout', function (e) {
    var govde = e.target.closest && e.target.closest('.yk-govde');
    if (!govde) { return; }
    var kart = govde.closest('.yk-kart');
    var id = kart.getAttribute('data-id');
    if (zamanlar[id]) {
      clearTimeout(zamanlar[id]);
      zamanlar[id] = null;
      metinKaydet(kart, govde.innerText.replace(/\n$/, ''));
    }
  });

  // Yapıştırmada biçim (HTML) gelmesin: yalnız düz metin
  duvar.addEventListener('paste', function (e) {
    if (!e.target.closest('.yk-govde')) { return; }
    e.preventDefault();
    var t = (e.clipboardData || window.clipboardData).getData('text');
    document.execCommand('insertText', false, t);
  });

  /* ---------- İKONLAR: sabitle / renk / sil ---------- */
  duvar.addEventListener('click', function (e) {
    var kart = e.target.closest('.yk-kart');
    if (!kart) { return; }
    var id = kart.getAttribute('data-id');

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
      document.querySelectorAll('.yk-renkmenu.acik').forEach(function (m) {
        if (m !== menu) { m.classList.remove('acik'); }
      });
      menu.classList.toggle('acik');
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

    if (islem === 'sil') {
      if (!window.confirm('Bu yapışkan not kalıcı olarak silinsin mi?')) { return; }
      istek(URL.sil, { id: id })
        .then(function (j) { duvarYaz(j); genelYaz('Silindi'); }).catch(hata);
      return;
    }
  });

  // Açık renk menüsü dışına tıklanınca kapansın
  document.addEventListener('click', function (e) {
    if (e.target.closest('.yk-renkmenu') || e.target.closest('[data-islem="renk"]')) { return; }
    document.querySelectorAll('.yk-renkmenu.acik').forEach(function (m) { m.classList.remove('acik'); });
  });

  /* ---------- SÜRÜKLE-BIRAK (yalnız aynı grup içinde: sabit/diğer) ---------- */
  var suruklenen = null;

  duvar.addEventListener('dragstart', function (e) {
    var kart = e.target.closest('.yk-kart');
    if (!kart) { return; }
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

    // Kartı hedefin önüne yerleştir (görsel olarak anında)
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
  sayacGuncelle();
  genelYaz('Hazır');
}());
</script>
<?= $this->endSection() ?>
