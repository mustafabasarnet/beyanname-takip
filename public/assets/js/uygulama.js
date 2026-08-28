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

/* =================================================================
 *  ÇOKLU SEÇİM KUTUSU
 *
 *  Görünüm parçası: app/Views/parcalar/_coklu_secim.php
 *  Fonksiyonlar global (onclick özniteliklerinden çağrılır).
 *
 *  Davranış: kutu açıkken her tık formu göndermez; kullanıcı seçimini
 *  bitirip "Uygula"ya bastığında ya da paneli kapattığında TEK sefer
 *  gönderilir. Her onay kutusunda sayfa yenilense kullanılamazdı.
 * ================================================================= */
function cokluKapatHepsi(haric) {
  document.querySelectorAll('.coklu-sec.acik').forEach(function (k) {
    if (k === haric) { return; }
    k.classList.remove('acik');
    var d = k.querySelector('.coklu-dugme');
    if (d) { d.setAttribute('aria-expanded', 'false'); }
    // Panel açıkken yapılan değişiklikler kapanışta uygulanır
    if (k.dataset.kirli === '1') { k.dataset.kirli = '0'; cokluGonder(k); }
  });
}

function cokluAc(dugme) {
  var kutu = dugme.closest('.coklu-sec');
  var acik = kutu.classList.contains('acik');

  cokluKapatHepsi(kutu);

  if (acik) {
    kutu.classList.remove('acik');
    dugme.setAttribute('aria-expanded', 'false');
    if (kutu.dataset.kirli === '1') { kutu.dataset.kirli = '0'; cokluGonder(kutu); }
    return;
  }

  kutu.classList.add('acik');
  dugme.setAttribute('aria-expanded', 'true');

  // Panel ekranın sağına taşıyorsa sola hizala
  var panel = kutu.querySelector('.coklu-panel');
  if (panel) {
    panel.classList.remove('sag');
    var r = panel.getBoundingClientRect();
    if (r.right > document.documentElement.clientWidth - 8) { panel.classList.add('sag'); }
  }

  var ara = kutu.querySelector('.cs-ara');
  if (ara) { ara.value = ''; cokluAra(ara); ara.focus(); }
}

function cokluAra(inp) {
  var kutu = inp.closest('.coklu-sec');
  var q    = inp.value.toLocaleLowerCase('tr').trim();
  var bulunan = 0;

  kutu.querySelectorAll('.cs-oge').forEach(function (o) {
    var uyar = q === '' || (o.dataset.metin || '').indexOf(q) > -1;
    o.style.display = uyar ? '' : 'none';
    if (uyar) { bulunan++; }
  });

  var bos = kutu.querySelector('.cs-bos');
  if (bos) { bos.classList.toggle('gizle', bulunan > 0); }
}

/** Tümünü seç / temizle — yalnızca ARAMAYA UYAN ögeleri etkiler */
function cokluTumu(dugme, deger) {
  var kutu = dugme.closest('.coklu-sec');

  kutu.querySelectorAll('.cs-oge').forEach(function (o) {
    if (o.style.display === 'none') { return; }
    var c = o.querySelector('input[type=checkbox]');
    if (c) { c.checked = deger; }
  });

  kutu.dataset.kirli = '1';
  cokluOzetYaz(kutu);
}

function cokluDegisti(kutuGirdi) {
  var kutu = kutuGirdi.closest('.coklu-sec');
  kutu.dataset.kirli = '1';
  cokluOzetYaz(kutu);
}

/** Kapalı düğmedeki özet metnini günceller */
function cokluOzetYaz(kutu) {
  var hepsi  = kutu.querySelectorAll('.cs-oge input[type=checkbox]');
  var secili = kutu.querySelectorAll('.cs-oge input[type=checkbox]:checked');
  var dugme  = kutu.querySelector('.coklu-dugme');
  var ozet   = kutu.querySelector('.cs-ozet');
  if (!ozet) { return; }

  var tekil = kutu.dataset.tekil || 'öge';
  var metin;

  if (secili.length === 0 || secili.length === hepsi.length) {
    metin = 'Tümü';
  } else if (secili.length === 1) {
    var et = secili[0].parentNode.querySelector('span');
    metin = et ? et.textContent.trim() : ('1 ' + tekil);
  } else {
    metin = secili.length + ' ' + tekil + ' seçili';
  }

  ozet.textContent = metin;
  dugme.classList.toggle('dolu', secili.length > 0 && secili.length < hepsi.length);
}

/**
 * Formu gönderir.
 *
 * HEPSİ seçiliyse kutular boşaltılır: "tümü" ile "hepsini tek tek seçtim"
 * aynı sonucu verir ama ikincisi adres çubuğunu 13 parametreyle şişirirdi.
 */
function cokluGonder(kutu) {
  var hepsi  = kutu.querySelectorAll('.cs-oge input[type=checkbox]');
  var secili = kutu.querySelectorAll('.cs-oge input[type=checkbox]:checked');

  if (secili.length === hepsi.length) {
    hepsi.forEach(function (c) { c.checked = false; });
  }

  var form = kutu.closest('form');
  if (form) { form.submit(); }
}

function cokluUygula(dugme) {
  var kutu = dugme.closest('.coklu-sec');
  kutu.dataset.kirli = '0';
  cokluGonder(kutu);
}

document.addEventListener('click', function (e) {
  if (!e.target.closest('.coklu-sec')) { cokluKapatHepsi(null); }
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') { cokluKapatHepsi(null); }
});

