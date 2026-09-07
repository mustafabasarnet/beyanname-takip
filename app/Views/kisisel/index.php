<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<style>
/* Kişisel not ekranı — kompakt, temiz */
.kn-kart{background:#fff;border:1px solid var(--gri-200,#e2e8f0);border-radius:12px;overflow:hidden}
.kn-bas{display:flex;align-items:center;gap:8px;padding:10px 16px;border-bottom:1px solid var(--gri-100,#f1f5f9);
  font-weight:700;font-size:14px}
.kn-govde{padding:14px 16px}
.kn-tarih-sec{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.kn-textarea{width:100%;min-height:80px;font:inherit;font-size:13.5px;padding:9px 11px;border:1px solid #cbd5e1;
  border-radius:8px;resize:vertical;color:var(--gri-800,#1e293b)}
.kn-textarea:focus{outline:2px solid var(--ana,#2563eb);outline-offset:-1px}
.gorev-satir{display:flex;align-items:flex-start;gap:10px;padding:9px 4px;border-bottom:1px dashed var(--gri-100,#f1f5f9)}
.gorev-satir:last-child{border-bottom:0}
.gorev-kutu{width:19px;height:19px;border:2px solid var(--gri-300,#cbd5e1);border-radius:5px;background:#fff;
  cursor:pointer;flex-shrink:0;margin-top:2px;display:inline-flex;align-items:center;justify-content:center;color:#fff}
.gorev-kutu:hover{border-color:#059669}
.gorev-metin{flex:1;min-width:0}
.gorev-baslik{font-size:13.5px;color:var(--gri-800,#1e293b)}
.gorev-baslik.tamam{text-decoration:line-through;color:var(--gri-400,#94a3b8)}
.gorev-not{font-size:12px;color:var(--gri-500,#64748b);margin-top:2px;white-space:pre-wrap}
.gorev-not.tamam{text-decoration:line-through}
.gorev-tarih{font-size:11px;color:var(--gri-400,#94a3b8);margin-top:3px}
.gorev-islem{display:flex;gap:2px;flex-shrink:0}
.sil-btn{background:none;border:0;cursor:pointer;font-size:14px;color:var(--gri-300,#cbd5e1);
  padding:2px 6px;border-radius:6px;line-height:1}
.sil-btn:hover{background:#fef2f2;color:#dc2626}
.kn-bos{color:var(--gri-400,#94a3b8);font-size:13px;padding:10px 4px}
.gecmis-satir{display:flex;justify-content:space-between;gap:10px;padding:8px 4px;border-bottom:1px dashed var(--gri-100,#f1f5f9)}
.gecmis-satir:last-child{border-bottom:0}
.gecmis-tarih{font-weight:700;font-size:12.5px;color:var(--gri-600,#475569);white-space:nowrap;min-width:88px}
.gecmis-metin{flex:1;font-size:13px;color:var(--gri-700,#334155);white-space:pre-wrap;word-break:break-word}
/* Etiket + öncelik rozetleri */
.kn-etiket{display:inline-block;background:#e0e7ff;color:#3730a3;font-size:10px;font-weight:700;
  padding:1px 7px;border-radius:99px;margin-left:6px;vertical-align:middle}
.kn-onc{display:inline-block;font-size:9.5px;font-weight:700;padding:1px 7px;border-radius:99px}
.kn-onc.kirmizi{background:#fee2e2;color:#991b1b}
.kn-onc.turuncu{background:#ffedd5;color:#9a3412}
.kn-onc.mavi{background:#dbeafe;color:#1e40af}
.kn-onc.gri{background:#e2e8f0;color:#475569}
/* Satır içi düzenleme */
.duzenle-panel{background:var(--gri-50,#f8fafc);border:1px solid var(--gri-200,#e2e8f0);border-radius:10px;
  padding:10px 12px;margin:2px 0 8px}
.duzenle-panel .form-grid{grid-template-columns:1fr 130px 150px}
.duzenle-panel input[type=text],.duzenle-panel select,.duzenle-panel input[type=date]{font-size:13px;padding:6px 9px}
</style>

<div class="kart-baslik" style="margin:0 0 4px">
  <h2>📝 Kişisel Notlarım</h2>
  <span class="kucuk-yazi">Yalnız siz görürsünüz — yönetici dahil kimse erişemez.</span>
</div>

<div id="kn-bildirim" style="margin-bottom:10px"></div>

<!-- ================= GÜNLÜK NOT ================= -->
<div class="kn-kart" style="margin-bottom:16px">
  <div class="kn-bas">🗓️ Günlük Not</div>
  <div class="kn-govde">
    <div class="kn-tarih-sec" style="margin-bottom:10px">
      <label class="kucuk-yazi" for="tarih-sec" style="font-weight:700">Tarih:</label>
      <input type="date" id="tarih-sec" name="tarih-sec" class="girdi" style="width:auto;padding:6px 10px"
             value="<?= esc($tarih) ?>" onchange="if(this.value) location='<?= site_url('kisisel') ?>?tarih='+this.value">
      <?php if ($tarih !== $bugun): ?>
        <a href="<?= site_url('kisisel') ?>" class="btn ikincil kucuk">Bugüne dön</a>
      <?php endif; ?>
    </div>

    <div class="kn-tarih-sec" style="align-items:stretch">
      <textarea id="kn-not-metin" class="kn-textarea"
                placeholder="<?= $tarih === $bugun ? 'Bugün ne yapılacak, ne düşünüyorsun?…' : 'Bu güne not bırak…' ?>"><?= esc($gunNotu['metin'] ?? '') ?></textarea>
      <button type="button" class="btn" id="kn-not-kaydet" style="align-self:flex-end">💾 Notu Kaydet</button>
    </div>
  </div>
</div>

<!-- ================= TO-DO ================= -->
<div class="kn-kart">
  <div class="kn-bas">✅ Yapılacaklar
    <span class="kucuk-yazi" id="kn-sayac" style="font-weight:400;margin-left:auto"></span>
  </div>
  <div class="kn-govde">

    <form id="kn-gorev-form" class="form-grid" style="grid-template-columns:1.3fr 1fr 110px 130px 130px auto;align-items:end;margin-bottom:10px">
      <div class="form-grup">
        <label>Görev</label>
        <input type="text" name="baslik" class="girdi" required maxlength="200" placeholder="Örn. Müşteriyi ara">
      </div>
      <div class="form-grup">
        <label>Not (ops.)</label>
        <input type="text" name="metin" class="girdi" maxlength="500" placeholder="Ayrıntı…">
      </div>
      <div class="form-grup">
        <label>Öncelik</label>
        <select name="oncelik" class="girdi">
          <option value="normal">Normal</option>
          <option value="dusuk">Düşük</option>
          <option value="yuksek">Yüksek</option>
          <option value="acil">Acil</option>
        </select>
      </div>
      <div class="form-grup">
        <label>Etiket</label>
        <input type="text" name="etiket" class="girdi" maxlength="60" placeholder="Toplantı, Ödeme…">
      </div>
      <div class="form-grup">
        <label>Son Tarih</label>
        <input type="date" name="son_tarih" class="girdi">
      </div>
      <div class="form-grup">
        <button type="submit" class="btn">+ Ekle</button>
      </div>
    </form>

    <!-- Açık + tamamlanan görevler (AJAX ile yeniden çizilir) -->
    <div id="kn-todo-liste">
      <?= $this->include('kisisel/_todo_liste') ?>
    </div>

  </div>
</div>

<!-- ================= GEÇMİŞ GÜNLÜK NOTLAR ================= -->
<div class="kn-kart" style="margin-top:16px">
  <div class="kn-bas">🕘 Son Günlük Notlar</div>
  <div class="kn-govde">
    <div id="kn-gecmis">
      <?= $this->include('kisisel/_gecmis') ?>
    </div>
  </div>
</div>

<script>
(function () {
  var liste  = document.getElementById('kn-todo-liste');
  var sayac  = document.getElementById('kn-sayac');
  var bild   = document.getElementById('kn-bildirim');
  var form   = document.getElementById('kn-gorev-form');

  function sayacGuncelle(a, b) {
    sayac.textContent = a + ' açık' + (b > 0 ? ' · ' + b + ' tamamlandı' : '');
  }
  sayacGuncelle(<?= count($acik) ?>, <?= count($biten) ?>);

  function mesaj(metin, tip) {
    bild.innerHTML = '<div class="uyari ' + (tip === 'hata' ? 'hata' : 'basari') + '"><span class="ik">'
      + (tip === 'hata' ? '✕' : '✓') + '</span><div>' + (metin || '') + '</div></div>';
    setTimeout(function () { bild.innerHTML = ''; }, 4000);
  }

  // Listeyi AJAX yanıtındaki HTML ile değiştir, olayları yeniden bağla
  function listeGuncelle(j, okMesaj) {
    liste.innerHTML = j.listeHtml;
    sayacGuncelle(j.acikSayisi || 0, j.bitenSayisi || 0);
    bagla();
    if (j.mesaj || okMesaj) mesaj(j.mesaj || okMesaj, 'ok');
  }

  // ---- Günlük not kaydet ----
  var notBtn = document.getElementById('kn-not-kaydet');
  var notMetin = document.getElementById('kn-not-metin');
  if (notBtn) {
    notBtn.addEventListener('click', function () {
      BT.post('<?= site_url('kisisel/not-kaydet') ?>', {
        tarih: document.getElementById('tarih-sec').value,
        metin: notMetin.value
      }).then(function (j) {
        mesaj(j.mesaj, 'ok');
        if (j.silindi) notMetin.value = '';
        var g = document.getElementById('kn-gecmis');
        if (g && j.gecmisHtml) g.innerHTML = j.gecmisHtml;
        bagla();
      }).catch(function (e) { mesaj(e.message, 'hata'); });
    });
  }

  // ---- Görev ekle ----
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      BT.post('<?= site_url('kisisel/gorev-ekle') ?>', {
        baslik:   (fd.get('baslik') || '').trim(),
        metin:    fd.get('metin') || '',
        oncelik:  fd.get('oncelik') || 'normal',
        etiket:   fd.get('etiket') || '',
        son_tarih: fd.get('son_tarih') || ''
      }).then(function (j) {
        if (!j.durum) throw new Error(j.mesaj || 'Görev eklenemedi.');
        form.reset();
        listeGuncelle(j);
      }).catch(function (e) { mesaj(e.message, 'hata'); });
    });
  }

  // ---- Olay bağlama (listeye özgü) ----
  function bagla() {
    if (!liste) return;

    // Tamamla / geri aç
    liste.querySelectorAll('.kn-tamamla').forEach(function (b) {
      b.addEventListener('click', function () {
        var satir = b.closest('[data-gorev]');
        if (!satir) return;
        BT.post('<?= site_url('kisisel/gorev-ters') ?>', { id: satir.getAttribute('data-gorev') })
          .then(function (j) { listeGuncelle(j); })
          .catch(function (e) { mesaj(e.message, 'hata'); });
      });
    });

    // Sil (açık ya da tamamlanmış görev)
    liste.querySelectorAll('.kn-sil').forEach(function (b) {
      b.addEventListener('click', function () {
        if (!confirm('Görev kalıcı olarak silinsin mi?')) return;
        var satir = b.closest('[data-gorev]');
        if (!satir) return;
        BT.post('<?= site_url('kisisel/gorev-sil') ?>', { id: satir.getAttribute('data-gorev') })
          .then(function (j) { listeGuncelle(j); })
          .catch(function (e) { mesaj(e.message, 'hata'); });
      });
    });

    // Düzenle — satır altına inline panel açar
    liste.querySelectorAll('.kn-duzenle').forEach(function (b) {
      b.addEventListener('click', function () {
        var satir = b.closest('[data-gorev]');
        if (!satir) return;
        if (satir.querySelector('.duzenle-panel')) { satir.querySelector('.duzenle-panel').remove(); return; }
        duzenlePanelAc(satir);
      });
    });
  }

  function duzenlePanelAc(satir) {
    var d = satir.dataset;
    var alan = document.createElement('div');
    alan.className = 'duzenle-panel';
    alan.innerHTML =
      '<div class="form-grid" style="grid-template-columns:1fr 1.2fr 110px 120px 130px auto;align-items:end">'
      + '  <div class="form-grup"><label>Görev</label><input type="text" class="girdi du-baslik" maxlength="200"></div>'
      + '  <div class="form-grup"><label>Not</label><input type="text" class="girdi du-metin" maxlength="500"></div>'
      + '  <div class="form-grup"><label>Öncelik</label><select class="girdi du-oncelik">'
      + '      <option value="dusuk">Düşük</option><option value="normal">Normal</option>'
      + '      <option value="yuksek">Yüksek</option><option value="acil">Acil</option></select></div>'
      + '  <div class="form-grup"><label>Etiket</label><input type="text" class="girdi du-etiket" maxlength="60"></div>'
      + '  <div class="form-grup"><label>Son Tarih</label><input type="date" class="girdi du-son"></div>'
      + '  <div class="form-grup" style="display:flex;gap:6px"><button type="button" class="btn kucuk du-kaydet">💾 Kaydet</button>'
      + '  <button type="button" class="btn ikincil kucuk du-vazgec">Vazgeç</button></div></div>';

    // Değerleri doldur
    alan.querySelector('.du-baslik').value = d.baslik || '';
    alan.querySelector('.du-metin').value = d.metin || '';
    alan.querySelector('.du-etiket').value = d.etiket || '';
    alan.querySelector('.du-son').value = d.son || '';
    var onc = alan.querySelector('.du-oncelik');
    onc.value = d.oncelik || 'normal';

    alan.querySelector('.du-vazgec').addEventListener('click', function () { alan.remove(); });
    alan.querySelector('.du-kaydet').addEventListener('click', function () {
      BT.post('<?= site_url('kisisel/gorev-guncelle') ?>', {
        id: satir.getAttribute('data-gorev'),
        baslik: alan.querySelector('.du-baslik').value.trim(),
        metin: alan.querySelector('.du-metin').value,
        oncelik: alan.querySelector('.du-oncelik').value,
        etiket: alan.querySelector('.du-etiket').value,
        son_tarih: alan.querySelector('.du-son').value
      }).then(function (j) {
        if (!j.durum) throw new Error(j.mesaj || 'Güncellenemedi.');
        listeGuncelle(j);
      }).catch(function (e) { mesaj(e.message, 'hata'); });
    });

    satir.after(alan);
    alan.querySelector('.du-baslik').focus();
  }

  // ---- Geçmiş günlük not sil ----
  var gecmisKutu = document.getElementById('kn-gecmis');
  function gecmisBagla() {
    if (!gecmisKutu) return;
    gecmisKutu.querySelectorAll('.kn-gecmis-sil').forEach(function (b) {
      b.addEventListener('click', function () {
        if (!confirm('Bu not silinsin mi?')) return;
        BT.post('<?= site_url('kisisel/gecmis-sil') ?>', {
          id: b.getAttribute('data-id'),
          tarih: document.getElementById('tarih-sec').value || ''
        }).then(function (j) {
          if (j.gecmisHtml) gecmisKutu.innerHTML = j.gecmisHtml;
          gecmisBagla();
          mesaj(j.mesaj || 'Not silindi.', 'ok');
        }).catch(function (e) { mesaj(e.message, 'hata'); });
      });
    });
  }

  bagla();
  gecmisBagla();
})();
</script>

<?= $this->endSection() ?>
