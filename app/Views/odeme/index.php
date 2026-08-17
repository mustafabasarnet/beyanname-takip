<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<!-- ============ FİLTRE ============ -->
<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Beyan Yılı</label>
    <select name="yil" data-oto-filtre>
      <?php foreach (yilSecenekleri() as $y): ?>
        <option value="<?= $y ?>" <?= (int) $filtre['yil'] === $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Beyan Ayı (son tarih)</label>
    <select name="ay" data-oto-filtre>
      <option value="">Tüm Aylar</option>
      <?php for ($a = 1; $a <= 12; $a++): ?>
        <option value="<?= $a ?>" <?= (int) $filtre['ay'] === $a ? 'selected' : '' ?>><?= ayAdi($a) ?></option>
      <?php endfor; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Ödeme Durumu</label>
    <select name="odendi" data-oto-filtre>
      <option value="">Tümü</option>
      <option value="0" <?= $filtre['odendi'] === '0' ? 'selected' : '' ?>>Ödenmedi</option>
      <option value="1" <?= $filtre['odendi'] === '1' ? 'selected' : '' ?>>Ödendi</option>
    </select>
  </div>

  <?php if (count($musavirler) > 1): ?>
    <div class="form-grup">
      <label>Mali Müşavir</label>
      <select name="musavir_id" data-oto-filtre>
        <option value="">Tümü</option>
        <?php foreach ($musavirler as $mid => $mad): ?>
          <option value="<?= $mid ?>"
            <?= secilenMusavirId($filtre['musavir_id'] ?? null) === (int) $mid ? 'selected' : '' ?>>
            <?= esc($mad) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="form-grup" style="min-width:170px">
    <label>Mükellef Ara</label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>" placeholder="Ünvan / VKN">
  </div>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url('odeme') ?>" class="btn ikincil kucuk">Sıfırla</a>
    <?php // $qs controller'dan da gelir (bkz. Odeme::index); burada filtre
          // çubuğundaki bağlantılar için hesaplanır — ikisi aynı değeri üretir.
          $qs = http_build_query(array_filter([
        'yil' => $filtre['yil'], 'ay' => $filtre['ay'], 'odendi' => $filtre['odendi'], 'q' => $filtre['q'],
    ], static fn ($v) => $v !== null && $v !== '')); ?>
    <button type="button" class="btn mor kucuk" onclick="ozelAc()">+ Özel Ödeme</button>
    <?php if (! empty($filtre['ay'])): ?>
      <a href="<?= site_url('odeme/tekrar-uret?' . $qs) ?>" class="btn ikincil kucuk"
         title="Bağkur gibi aylık tekrar eden kalemleri bu aya getirir">🔁 Tekrarlıları Getir</a>
    <?php endif; ?>
    <a href="<?= site_url('odeme/excel?' . $qs) ?>" class="btn yesil kucuk">📊 Excel</a>
    <a href="<?= site_url('odeme/yazdir?' . $qs) ?>" target="_blank" class="btn ikincil kucuk">🖨️ Yazdır</a>
  </div>
</form>

<?php if (! empty($tekrarUretilen)): ?>
  <div class="uyari basari mb16">
    <span class="ik">🔁</span>
    <div>
      Aylık tekrar eden <b><?= (int) $tekrarUretilen ?></b> ödeme kalemi
      bu ay için otomatik oluşturuldu.
    </div>
  </div>
<?php endif; ?>

<div class="uyari bilgi" style="padding:9px 14px;font-size:13px">
  <span class="ik">ℹ</span>
  <div>
    Tahakkuk tutarları <b>damga vergisi hariç</b> girilir; listede her satıra
    ilgili türün <a href="<?= site_url('tanimlar/damga?yil=' . $filtre['yil']) ?>">sabit damga vergisi</a>
    eklenerek ödenecek tutar hesaplanır.
    Yalnızca <b>Onaylandı</b> durumundaki beyannameler listelenir.
    Liste <b>ödeme son tarihine</b> göre gruplanır — SGK gibi ödemesi beyandan farklı olan
    türler kendi ödeme ayında görünür.
  </div>
</div>

<!-- ============ ÖZET ============ -->
<div class="stat-grid">
  <div class="stat">
    <div class="etiket">Beyanname</div>
    <div class="deger"><?= (int) $toplam['adet'] ?></div>
    <?php // Sayfalama nedeniyle count($gruplar) yalnızca EKRANA BASILAN grubu
          // verir; sayaç filtreye uyan GERÇEK toplamı göstermeli. ?>
    <div class="alt"><?= number_format((int) ($grupToplam ?? count($gruplar)), 0, ',', '.') ?> mükellef</div>
  </div>
  <div class="stat mor">
    <div class="etiket">Tahakkuk (Damga Hariç)</div>
    <div class="deger" style="font-size:21px"><?= number_format($toplam['tahakkuk'], 2, ',', '.') ?></div>
    <div class="alt">₺</div>
  </div>
  <div class="stat turuncu">
    <div class="etiket">Damga Vergisi</div>
    <div class="deger" style="font-size:21px"><?= number_format($toplam['damga'], 2, ',', '.') ?></div>
    <div class="alt">₺ (otomatik eklendi)</div>
  </div>
  <div class="stat yesil">
    <div class="etiket">Ödenecek Toplam</div>
    <div class="deger" style="font-size:21px"><?= number_format($toplam['genel'], 2, ',', '.') ?></div>
    <div class="alt">₺</div>
  </div>
  <div class="stat">
    <div class="etiket">Ödenen</div>
    <div class="deger" style="font-size:21px"><?= number_format($toplam['odenen'], 2, ',', '.') ?></div>
    <div class="alt">₺ / kalan <?= number_format($toplam['genel'] - $toplam['odenen'], 2, ',', '.') ?></div>
  </div>
</div>

<!-- ============ MÜKELLEF LİSTESİ (kompakt / katlanabilir) ============ -->
<style>
/* Stiller gömülü: stil.css kopyalanmasa bile kompakt görünüm bozulmaz.
   Amaç — beyannameler onaylandıkça sayfanın uzamasını engellemek:
   her mükellef tek satır, detay istenince açılıyor. */
.od-arac{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px}
.od-arac .sag{margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.od-liste{border:1px solid var(--gri-200,#e2e8f0);border-radius:10px;overflow:hidden;background:#fff}
.od-grup{border-bottom:1px solid var(--gri-100,#f1f5f9)}
.od-grup:last-child{border-bottom:0}

/* Katlanabilir başlık — tek satır, 40px */
.od-bas{width:100%;display:flex;align-items:center;gap:10px;padding:9px 14px;
  background:none;border:0;cursor:pointer;text-align:left;font:inherit;font-size:13.5px}
.od-bas:hover{background:var(--gri-50,#f8fafc)}
.od-bas[aria-expanded="true"]{background:var(--ana-acik,#dbeafe)}
.od-ok{display:inline-block;transition:transform .15s;color:var(--gri-400,#94a3b8);font-size:11px;flex:0 0 10px}
.od-bas[aria-expanded="true"] .od-ok{transform:rotate(90deg)}
.od-ad{flex:1;min-width:0;font-weight:600;color:var(--gri-900,#111827);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.od-vkn{font-weight:400;color:var(--gri-400,#94a3b8);font-size:11.5px;margin-left:7px}
.od-adet{font-size:11.5px;color:var(--gri-500,#64748b);white-space:nowrap}
.od-tutar{font-weight:800;color:var(--yesil,#059669);white-space:nowrap;
  min-width:118px;text-align:right;font-variant-numeric:tabular-nums}
.od-rozet{display:inline-block;padding:1px 7px;border-radius:99px;font-size:10.5px;font-weight:700}
.od-rozet.tamam{background:var(--yesil-acik,#d1fae5);color:#047857}
.od-rozet.kismi{background:var(--sari-acik,#fef9c3);color:#a16207}
.od-rozet.ozel{background:var(--mor-acik,#ede9fe);color:var(--mor,#7c3aed)}
.od-grup.od-tamam .od-ad{color:var(--gri-400,#94a3b8)}
.od-grup.od-tamam .od-tutar{color:var(--gri-400,#94a3b8)}

/* Detay */
.od-govde{border-top:1px solid var(--gri-100,#f1f5f9);background:var(--gri-50,#f8fafc)}
.od-ust{display:flex;align-items:center;justify-content:space-between;gap:10px;
  padding:8px 14px;flex-wrap:wrap}
.od-tablo{background:#fff}
.od-tablo th{font-size:10.5px;padding:6px 9px;white-space:nowrap}
.od-tablo td{padding:6px 9px;font-size:12.5px}
.od-tablo tr.od-odendi{opacity:.55}
.od-tablo tr.od-ara{background:var(--gri-50,#f8fafc);font-weight:700;font-size:12px}
.od-tablo input[type=checkbox]{width:17px;height:17px;accent-color:var(--yesil,#059669);cursor:pointer}
.od-ozel{border-top:2px solid var(--mor,#7c3aed)}
.od-ozel-bas{background:var(--mor-acik,#ede9fe);color:var(--mor,#7c3aed)}
.od-genel{display:flex;align-items:center;justify-content:space-between;gap:10px;
  padding:9px 14px;background:var(--gri-100,#f1f5f9);border-top:1px solid var(--gri-200,#e2e8f0)}
.od-kaydir{padding:14px;text-align:center;background:var(--gri-50,#f8fafc);
  border-top:1px solid var(--gri-200,#e2e8f0)}
@media(max-width:700px){
  .od-adet{display:none}
  .od-vkn{display:none}
  .od-tutar{min-width:92px;font-size:12.5px}
}
</style>

<?php if ($gruplar === []): ?>
  <div class="kart"><div class="kart-govde">
    <div class="tablo-bos">
      <span class="ikon">💰</span>
      Bu dönemde ödenecek beyanname bulunamadı.<br>
      <span class="kucuk-yazi">
        Tahakkuk tutarı girmek için <a href="<?= site_url('takip') ?>">Beyanname Takip</a>
        ekranında durumu <b>Onaylandı</b> yapın.<br>
        Bağkur, MTV gibi beyanname dışı ödemeleri
        <b>+ Özel Ödeme</b> düğmesiyle ekleyebilirsiniz.
      </span>
    </div>
  </div></div>
<?php else: ?>

  <div class="od-arac">
    <button type="button" class="btn ikincil kucuk" id="od-tumunu">⊞ Tümünü Aç</button>
    <span class="kucuk-yazi">Detay için mükellefe tıklayın</span>
    <div class="sag">
      <label class="kucuk-yazi" style="display:flex;align-items:center;gap:6px;margin:0">
        Sayfa başına
        <select id="od-adet" class="girdi" style="padding:3px 6px;font-size:12px;width:auto">
          <?php foreach ($adetSecenek as $sc): ?>
            <option value="<?= $sc ?>" <?= (int) $grupAdedi === $sc ? 'selected' : '' ?>><?= $sc ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </div>

  <div class="od-liste" id="od-liste">
    <?= $this->include('odeme/_gruplar') ?>
  </div>

  <div id="od-kaydir" class="od-kaydir"
       data-ofset="<?= count($gruplar) ?>"
       data-toplam="<?= (int) $grupToplam ?>"
       data-adet="<?= (int) $grupAdedi ?>">
    <button type="button" class="btn ikincil" id="od-daha"
            onclick="odDahaFazla()" <?= empty($dahaVar) ? 'style="display:none"' : '' ?>>
      ↓ Daha Fazla Mükellef
    </button>
    <div class="kucuk-yazi" style="margin-top:7px">
      <b id="od-gosterilen"><?= count($gruplar) ?></b> /
      <b><?= number_format((int) $grupToplam, 0, ',', '.') ?></b> mükellef
    </div>
  </div>

  <!-- GENEL TOPLAM -->
  <div class="kart" style="border:2px solid var(--yesil);margin-top:16px">
    <div class="kart-govde">
      <div class="satir arali">
        <div>
          <div class="etiket kucuk-yazi kalin">GENEL TOPLAM</div>
          <div class="kucuk-yazi">
            <?= (int) $toplam['adet'] - (int) ($toplam['ozel_adet'] ?? 0) ?> beyanname
            <?php if (! empty($toplam['ozel_adet'])): ?>
              + <?= (int) $toplam['ozel_adet'] ?> özel kalem
            <?php endif; ?>
            • <?= number_format((int) $grupToplam, 0, ',', '.') ?> mükellef •
            <?= ayAdi((int) $filtre['ay']) ?: 'Tüm aylar' ?> <?= $filtre['yil'] ?>
          </div>
        </div>
        <div style="text-align:right">
          <div class="kucuk-yazi">
            Tahakkuk <?= number_format($toplam['tahakkuk'], 2, ',', '.') ?>
            + Damga <span style="color:var(--turuncu)"><?= number_format($toplam['damga'], 2, ',', '.') ?></span>
          </div>
          <div style="font-size:26px;font-weight:800;color:var(--yesil)">
            <?= number_format($toplam['genel'], 2, ',', '.') ?> ₺
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ============ ÖZEL ÖDEME MODALI ============ -->
<div class="modal-arka" id="ozel-modal">
  <div class="modal genis">
    <form method="post" action="<?= site_url('odeme/ozel-kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="oz-id">
      <div class="modal-baslik">
        <h3 id="oz-baslik">➕ Özel Ödeme Kalemi</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
      </div>
      <div class="modal-govde">
        <div class="uyari bilgi" style="padding:10px 14px;font-size:13px">
          <span class="ik">ℹ</span>
          <div>
            Beyanname dışındaki ödemeleri (Bağkur primi, MTV, harç, ceza vb.) buradan ekleyin.
            Kalem, seçtiğiniz mükellefin ödeme listesine ve bildirimine dahil olur.
          </div>
        </div>

        <div class="form-grid">
          <div class="form-grup tam">
            <label>Mükellef <span class="zorunlu">*</span></label>
            <select name="mukellef_id" id="oz-mukellef" required>
              <option value="">— Seçiniz —</option>
              <?php foreach ($mukellefler as $mid => $mad): ?>
                <option value="<?= $mid ?>"><?= esc($mad) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-grup tam">
            <label>Ödeme Başlığı <span class="zorunlu">*</span></label>
            <input type="text" name="baslik" id="oz-adi" class="girdi" required
                   list="oz-oneri" placeholder="Örn: Bağkur Primi">
            <datalist id="oz-oneri">
              <?php foreach ($onerilen as $o): ?><option value="<?= esc($o) ?>"><?php endforeach; ?>
            </datalist>
          </div>

          <div class="form-grup">
            <label>Tutar (₺) <span class="zorunlu">*</span></label>
            <input type="text" name="tutar" id="oz-tutar" class="girdi" required
                   inputmode="decimal" style="text-align:right;font-weight:700" placeholder="0,00">
          </div>

          <div class="form-grup">
            <label>Son Ödeme Tarihi <span class="zorunlu">*</span></label>
            <input type="date" name="son_tarih" id="oz-tarih" class="girdi" required>
          </div>

          <div class="form-grup">
            <label>Dönem Etiketi</label>
            <input type="text" name="donem_etiketi" id="oz-donem" class="girdi"
                   placeholder="Örn: Nisan 2026">
          </div>

          <div class="form-grup">
            <label>Her ay tekrar etsin mi?</label>
            <select name="tekrar" id="oz-tekrar" onchange="tekrarAlaniGuncelle()">
              <option value="YOK">Hayır (tek seferlik)</option>
              <option value="AYLIK">Evet (aylık tekrar eden)</option>
            </select>
            <span class="yardim">Bağkur gibi düzenli ödemeler için işaretleyin.</span>
          </div>

          <div class="form-grup gizle" id="oz-bitis-alani">
            <label>Tekrar Bitiş Tarihi</label>
            <input type="date" name="tekrar_bitis" id="oz-bitis" class="girdi">
            <span class="yardim">
              Boş bırakırsanız <b>süresiz</b> tekrar eder. Örn. yıl sonunda
              bitecekse 31.12.<?= (int) $filtre['yil'] ?> yazın.
            </span>
          </div>

          <div class="form-grup tam">
            <label>Açıklama</label>
            <input type="text" name="aciklama" id="oz-aciklama" class="girdi">
          </div>

          <input type="hidden" name="durum" id="oz-durum" value="ONAYLANDI">
        </div>
      </div>
      <div class="modal-alt">
        <button type="button" class="btn ikincil" data-modal-kapat>İptal</button>
        <button type="submit" class="btn">💾 Kaydet</button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
// ---------- Özel ödeme kalemi ----------
function ozelAc(o) {
  o = o || {};
  document.getElementById('oz-baslik').textContent = o.id
    ? '➕ Ödeme Kalemini Düzenle' : '➕ Yeni Özel Ödeme Kalemi';

  document.getElementById('oz-id').value       = o.id || '';
  document.getElementById('oz-mukellef').value = o.mukellef_id || '';
  document.getElementById('oz-adi').value      = o.baslik || '';
  document.getElementById('oz-tutar').value    = o.tutar
      ? Number(o.tutar).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
      : '';
  document.getElementById('oz-tarih').value    = o.son_tarih
      ? String(o.son_tarih).substr(0, 10)
      : '<?= sprintf('%04d-%02d-%02d', (int) $filtre['yil'], (int) ($filtre['ay'] ?: date('n')), 1) ?>';
  document.getElementById('oz-donem').value    = o.donem_etiketi
      || '<?= ! empty($filtre['ay']) ? ayAdi((int) $filtre['ay']) . ' ' . $filtre['yil'] : '' ?>';
  document.getElementById('oz-tekrar').value   = o.tekrar || 'YOK';
  document.getElementById('oz-bitis').value    = o.tekrar_bitis
      ? String(o.tekrar_bitis).substr(0, 10) : '';
  document.getElementById('oz-aciklama').value = o.aciklama || '';
  document.getElementById('oz-durum').value    = o.durum || 'ONAYLANDI';

  tekrarAlaniGuncelle();
  BT.modalAc('ozel-modal');
}

/** Bitiş tarihi alanı yalnızca "Evet" seçilince görünür */
function tekrarAlaniGuncelle() {
  var acik = document.getElementById('oz-tekrar').value === 'AYLIK';
  document.getElementById('oz-bitis-alani').className = 'form-grup' + (acik ? '' : ' gizle');
}

// Tutar alanı biçimlendirme
document.getElementById('oz-tutar').addEventListener('blur', function () {
  var v = this.value.trim();
  if (v === '') return;
  v = v.replace(/\s/g, '');
  if (v.indexOf(',') > -1) { v = v.replace(/\./g, '').replace(',', '.'); }
  var s = parseFloat(v);
  this.value = isNaN(s) ? '' : s.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
});

// Onay kutuları olay DEVRİ ile bağlanır; sonradan yüklenen gruplarda da çalışır
document.addEventListener('change', function (e) {
  var cb = e.target.closest('.odendi-kutu, .ozel-odendi');
  if (!cb) { return; }

  var ozelMi = cb.classList.contains('ozel-odendi');
  var url    = ozelMi ? '<?= site_url('odeme/ozel-odendi') ?>' : '<?= site_url('odeme/odendi') ?>';

  cb.disabled = true;
  BT.post(url, { id: cb.dataset.id, odendi: cb.checked ? 1 : 0 })
    .then(function (j) {
      BT.bildir(j.mesaj, 'basari');
      var tr = cb.closest('tr');
      if (tr) { tr.classList.toggle('od-odendi', cb.checked); }
      odGrupTazele(cb.closest('.od-grup'));
    })
    .catch(function (er) { BT.bildir(er.message, 'hata'); cb.checked = !cb.checked; })
    .finally(function () { cb.disabled = false; });
});

/** Grup başlığındaki "x/y ödendi" rozetini ve soluklaştırmayı günceller */
function odGrupTazele(grup) {
  if (!grup) { return; }

  var kutular = grup.querySelectorAll('.odendi-kutu, .ozel-odendi');
  var toplam  = kutular.length;
  var odenen  = 0;
  kutular.forEach(function (k) { if (k.checked) { odenen++; } });

  grup.classList.toggle('od-tamam', toplam > 0 && odenen === toplam);

  var ad = grup.querySelector('.od-ad');
  if (!ad) { return; }

  var eski = ad.querySelector('.od-rozet.tamam, .od-rozet.kismi');
  if (eski) { eski.remove(); }

  var yeni = null;
  if (toplam > 0 && odenen === toplam) {
    yeni = document.createElement('span');
    yeni.className = 'od-rozet tamam';
    yeni.title = 'Tüm kalemler ödendi';
    yeni.textContent = '✓';
  } else if (odenen > 0) {
    yeni = document.createElement('span');
    yeni.className = 'od-rozet kismi';
    yeni.title = odenen + '/' + toplam + ' kalem ödendi';
    yeni.textContent = odenen + '/' + toplam;
  }

  if (yeni) { ad.insertBefore(yeni, ad.querySelector('.od-vkn')); }
}

// ---------- Katlanabilir gruplar ----------
document.addEventListener('click', function (e) {
  var bas = e.target.closest('.od-bas');
  if (!bas) { return; }

  var hedef = document.getElementById(bas.dataset.hedef);
  if (!hedef) { return; }

  var acik = bas.getAttribute('aria-expanded') === 'true';
  bas.setAttribute('aria-expanded', acik ? 'false' : 'true');
  hedef.hidden = acik;
});

// Tümünü aç / kapat
(function () {
  var d = document.getElementById('od-tumunu');
  if (!d) { return; }

  d.addEventListener('click', function () {
    var acilacak = d.dataset.acik !== '1';

    document.querySelectorAll('.od-bas').forEach(function (b) {
      b.setAttribute('aria-expanded', acilacak ? 'true' : 'false');
      var h = document.getElementById(b.dataset.hedef);
      if (h) { h.hidden = !acilacak; }
    });

    d.dataset.acik   = acilacak ? '1' : '0';
    d.textContent    = acilacak ? '⊟ Tümünü Kapat' : '⊞ Tümünü Aç';
  });
})();

// ---------- Sayfa başına adet ----------
(function () {
  var a = document.getElementById('od-adet');
  if (!a) { return; }

  a.addEventListener('change', function () {
    var u = new URL(location.href);
    u.searchParams.set('adet', a.value);
    location.href = u.toString();
  });
})();

// ---------- Sonsuz kaydırma ----------
var odYukleniyor = false;

function odDahaFazla() {
  var alan = document.getElementById('od-kaydir');
  if (!alan || odYukleniyor) { return; }

  var ofset  = parseInt(alan.dataset.ofset, 10) || 0;
  var toplam = parseInt(alan.dataset.toplam, 10) || 0;
  if (ofset >= toplam) { return; }

  odYukleniyor = true;
  var dugme = document.getElementById('od-daha');
  if (dugme) { dugme.textContent = 'Yükleniyor…'; }

  var u = new URL('<?= site_url('odeme/daha-fazla') ?>', location.origin);
  new URLSearchParams(location.search).forEach(function (d, a) { u.searchParams.set(a, d); });
  u.searchParams.set('ofset', ofset);

  fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (y) { return y.json(); })
    .then(function (v) {
      odYukleniyor = false;
      if (dugme) { dugme.textContent = '↓ Daha Fazla Mükellef'; }
      if (!v.durum) { return; }

      document.getElementById('od-liste').insertAdjacentHTML('beforeend', v.html);
      alan.dataset.ofset = v.ofset;
      document.getElementById('od-gosterilen').textContent = v.ofset;
      if (!v.dahaVar && dugme) { dugme.style.display = 'none'; }
    })
    .catch(function () {
      odYukleniyor = false;
      if (dugme) { dugme.textContent = '↓ Daha Fazla Mükellef'; }
    });
}

window.addEventListener('scroll', function () {
  var alan = document.getElementById('od-kaydir');
  if (!alan) { return; }
  if (alan.getBoundingClientRect().top < window.innerHeight + 250) { odDahaFazla(); }
});
</script>
<?= $this->endSection() ?>
