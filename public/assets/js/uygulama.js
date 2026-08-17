/* =====================================================================
   BEYANNAME TAKİP — Ortak JavaScript
   ===================================================================== */
(function () {
  'use strict';

  window.BT = window.BT || {};

  // ---------------------------------------------------------------
  // Bildirim (toast)
  // ---------------------------------------------------------------
  BT.bildir = function (mesaj, tip) {
    tip = tip || 'basari';
    var kap = document.getElementById('bildirimler');
    if (!kap) {
      kap = document.createElement('div');
      kap.id = 'bildirimler';
      document.body.appendChild(kap);
    }
    var ikonlar = { basari: '✓', hata: '✕', bilgi: 'ℹ' };
    var el = document.createElement('div');
    el.className = 'bildirim ' + tip;
    el.innerHTML = '<span>' + (ikonlar[tip] || '') + '</span><span></span>';
    el.lastChild.textContent = mesaj;
    kap.appendChild(el);

    setTimeout(function () {
      el.classList.add('cikis');
      setTimeout(function () { el.remove(); }, 220);
    }, 3200);
  };

  // ---------------------------------------------------------------
  // CSRF yönetimi (CI4 token rotasyonu için)
  // ---------------------------------------------------------------
  BT.csrf = {
    ad: function () {
      var m = document.querySelector('meta[name="csrf-ad"]');
      return m ? m.content : 'csrf_test_name';
    },
    deger: function () {
      var m = document.querySelector('meta[name="csrf-token"]');
      return m ? m.content : '';
    },
    guncelle: function (yeni) {
      if (!yeni) return;
      var m = document.querySelector('meta[name="csrf-token"]');
      if (m) m.content = yeni;
      document.querySelectorAll('input[name="' + BT.csrf.ad() + '"]').forEach(function (i) {
        i.value = yeni;
      });
    }
  };

  // ---------------------------------------------------------------
  // AJAX POST yardımcısı
  // ---------------------------------------------------------------
  BT.post = function (url, veri) {
    var fd = new FormData();
    Object.keys(veri).forEach(function (k) {
      var v = veri[k];
      if (Array.isArray(v)) {
        v.forEach(function (x) { fd.append(k + '[]', x); });
      } else {
        fd.append(k, v);
      }
    });
    fd.append(BT.csrf.ad(), BT.csrf.deger());

    return fetch(url, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) {
        return r.json().catch(function () {
          throw new Error('Sunucu yanıtı okunamadı.');
        }).then(function (j) {
          if (j.csrf) BT.csrf.guncelle(j.csrf);
          if (!r.ok) throw new Error(j.mesaj || 'İşlem başarısız.');
          return j;
        });
      });
  };

  // ---------------------------------------------------------------
  // Modal
  // ---------------------------------------------------------------
  BT.modalAc = function (id) {
    var m = document.getElementById(id);
    if (m) {
      m.classList.add('acik');
      document.body.style.overflow = 'hidden';
      var ilk = m.querySelector('input:not([type=hidden]),select,textarea');
      if (ilk) setTimeout(function () { ilk.focus(); }, 60);
    }
  };
  BT.modalKapat = function (id) {
    var m = id ? document.getElementById(id) : document.querySelector('.modal-arka.acik');
    if (m) {
      m.classList.remove('acik');
      document.body.style.overflow = '';
    }
  };

  // ---------------------------------------------------------------
  // Genel olay bağlama
  // ---------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {

    // Yan menü (mobil)
    var menuAc   = document.querySelector('.menu-ac');
    var yanMenu  = document.querySelector('.yan-menu');
    var karartma = document.querySelector('.karartma');

    if (menuAc && yanMenu) {
      menuAc.addEventListener('click', function () {
        yanMenu.classList.toggle('acik');
        if (karartma) karartma.classList.toggle('acik');
      });
    }
    if (karartma) {
      karartma.addEventListener('click', function () {
        yanMenu.classList.remove('acik');
        karartma.classList.remove('acik');
      });
    }

    // Modal kapatma
    document.addEventListener('click', function (e) {
      if (e.target.classList.contains('modal-arka')) BT.modalKapat();
      var kapat = e.target.closest('[data-modal-kapat]');
      if (kapat) BT.modalKapat(kapat.getAttribute('data-modal-kapat') || null);
      var ac = e.target.closest('[data-modal-ac]');
      if (ac) { e.preventDefault(); BT.modalAc(ac.getAttribute('data-modal-ac')); }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') BT.modalKapat();
    });

    // Silme onayı
    document.addEventListener('click', function (e) {
      var link = e.target.closest('[data-onay]');
      if (link && !confirm(link.getAttribute('data-onay'))) {
        e.preventDefault();
        return false;
      }
    });

    // Filtre otomatik gönderim
    document.querySelectorAll('[data-oto-filtre]').forEach(function (el) {
      el.addEventListener('change', function () { el.form.submit(); });
    });

    // Tablo canlı arama
    document.querySelectorAll('[data-tablo-ara]').forEach(function (inp) {
      inp.addEventListener('keyup', function () {
        var hedef = document.querySelector(inp.getAttribute('data-tablo-ara'));
        if (!hedef) return;
        var q = inp.value.toLocaleLowerCase('tr');
        hedef.querySelectorAll('tbody tr').forEach(function (tr) {
          tr.style.display = tr.textContent.toLocaleLowerCase('tr').indexOf(q) > -1 ? '' : 'none';
        });
      });
    });

    // Tümünü seç
    document.querySelectorAll('[data-tumunu-sec]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        document.querySelectorAll(cb.getAttribute('data-tumunu-sec')).forEach(function (h) {
          h.checked = cb.checked;
        });
        BT.secimGuncelle();
      });
    });

    document.addEventListener('change', function (e) {
      if (e.target.classList && e.target.classList.contains('satir-sec')) BT.secimGuncelle();
    });

    // Uyarıları otomatik gizle
    document.querySelectorAll('.uyari.basari').forEach(function (u) {
      setTimeout(function () {
        u.style.transition = 'opacity .4s';
        u.style.opacity = '0';
        setTimeout(function () { u.remove(); }, 400);
      }, 5000);
    });
  });

  // Seçili satır sayacı
  BT.secimGuncelle = function () {
    var secili = document.querySelectorAll('.satir-sec:checked').length;
    var kutu   = document.getElementById('toplu-islem-kutusu');
    var sayac  = document.getElementById('secili-sayi');
    if (sayac) sayac.textContent = secili;
    if (kutu) kutu.classList.toggle('gizle', secili === 0);
  };

  BT.seciliIdler = function () {
    return Array.prototype.map.call(
      document.querySelectorAll('.satir-sec:checked'),
      function (c) { return c.value; }
    );
  };

})();
