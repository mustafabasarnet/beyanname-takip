<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
$oran = $ucret > 0 ? min(100, (int) round($kesilen / $ucret * 100)) : 0;
$vkn  = $mukellef['vergi_kimlik_no'] ?: $mukellef['tc_kimlik_no'];
?>

<style>
.md-ust{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.md-cubuk{height:10px;border-radius:99px;background:var(--gri-200,#e2e8f0);overflow:hidden}
.md-cubuk i{display:block;height:100%;border-radius:99px}
.md-tablo td{font-size:13px;vertical-align:middle}
.md-tablo td.sag,.md-tablo th.sag{text-align:right;font-variant-numeric:tabular-nums}
.md-tablo tr.md-tahsil{background:#f0fdf4}
</style>

<div class="md-ust">
  <a href="<?= site_url('makbuz?yil=' . (int) $yil) ?>" class="btn ikincil kucuk">← Listeye Dön</a>
  <h2 style="margin:0"><?= esc($mukellef['unvan']) ?></h2>
  <span class="kucuk-yazi"><?= esc($vkn) ?></span>
  <form method="get" style="margin-left:auto;display:flex;gap:8px;align-items:center">
    <label class="kucuk-yazi" style="margin:0">Yıl</label>
    <select name="yil" data-oto-filtre style="padding:4px 8px">
      <?php foreach (yilSecenekleri() as $y): ?>
        <option value="<?= $y ?>" <?= (int) $yil === $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<!-- ============ ÖZET ============ -->
<div class="stat-grid">
  <div class="stat mor">
    <div class="etiket"><?= (int) $yil ?> Sözleşme Ücreti</div>
    <div class="deger" style="font-size:21px"><?= number_format($ucret, 2, ',', '.') ?></div>
    <div class="alt">₺</div>
  </div>
  <div class="stat yesil">
    <div class="etiket">Kesilen</div>
    <div class="deger" style="font-size:21px"><?= number_format($kesilen, 2, ',', '.') ?></div>
    <div class="alt">₺ • <?= count($makbuzlar) ?> makbuz</div>
  </div>
  <div class="stat <?= $kalan > 0 ? 'kirmizi' : 'yesil' ?>">
    <div class="etiket">Kalan</div>
    <div class="deger" style="font-size:21px"><?= number_format($kalan, 2, ',', '.') ?></div>
    <div class="alt">₺</div>
  </div>
  <div class="stat">
    <div class="etiket">Tamamlanma</div>
    <div class="deger">%<?= $oran ?></div>
    <div class="alt">
      <span class="md-cubuk" style="display:block;margin-top:6px">
        <i style="width:<?= $oran ?>%;background:<?= $oran >= 100 ? '#059669' : '#2563eb' ?>"></i>
      </span>
    </div>
  </div>
</div>

<?php if ($ucret <= 0): ?>
  <div class="uyari" style="padding:10px 14px;font-size:13px">
    <span class="ik">⚠</span>
    <div>
      Bu mükellef için <b><?= (int) $yil ?></b> yılı sözleşme ücreti girilmemiş.
      Kalan tutar hesaplanamaz. Listeden tutara tıklayarak ya da
      <a href="<?= site_url('makbuz/ice-aktar?kip=ucret&yil=' . (int) $yil) ?>">Excel'den</a> girebilirsiniz.
    </div>
  </div>
<?php endif; ?>

<!-- ============ MAKBUZLAR ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🧾 <?= (int) $yil ?> Yılı Makbuzları</h2>
    <div class="sag">
      <button type="button" class="btn kucuk" onclick="makbuzAc()">+ Makbuz Ekle</button>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($makbuzlar === []): ?>
      <div class="tablo-bos">
        <span class="ikon">🧾</span>
        Bu yıl için kesilmiş makbuz yok.<br>
        <span class="kucuk-yazi">
          <b>+ Makbuz Ekle</b> ile tek tek girebilir veya
          <a href="<?= site_url('makbuz/ice-aktar?kip=makbuz&yil=' . (int) $yil) ?>">Excel'den toplu</a>
          aktarabilirsiniz.
        </span>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo md-tablo">
          <thead>
            <tr>
              <th>Tarih</th><th>Makbuz No</th>
              <th class="sag">Brüt</th><th class="sag">Stopaj</th>
              <th class="sag">KDV</th><th class="sag">Net</th>
              <th>Müşavir</th><th class="orta">Tahsil</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php $tB = $tS = $tK = $tN = 0.0; foreach ($makbuzlar as $m):
              $tB += (float) $m['brut']; $tS += (float) $m['stopaj'];
              $tK += (float) $m['kdv'];  $tN += (float) $m['net']; ?>
            <tr class="<?= (int) $m['tahsil_edildi'] === 1 ? 'md-tahsil' : '' ?>">
              <td><?= trTarih($m['tarih']) ?></td>
              <td class="kucuk-yazi"><?= esc($m['makbuz_no'] ?: '—') ?></td>
              <td class="sag kalin"><?= number_format((float) $m['brut'], 2, ',', '.') ?></td>
              <td class="sag" style="color:var(--turuncu)"><?= number_format((float) $m['stopaj'], 2, ',', '.') ?></td>
              <td class="sag" style="color:var(--mor)"><?= number_format((float) $m['kdv'], 2, ',', '.') ?></td>
              <td class="sag kalin" style="color:var(--yesil)"><?= number_format((float) $m['net'], 2, ',', '.') ?></td>
              <td class="kucuk-yazi"><?= esc(kisalt((string) $m['musavir_adi'], 16) ?: '—') ?></td>
              <td class="orta">
                <input type="checkbox" class="md-tahsil-kutu" data-id="<?= (int) $m['id'] ?>"
                       <?= (int) $m['tahsil_edildi'] === 1 ? 'checked' : '' ?>
                       style="width:17px;height:17px;accent-color:var(--yesil);cursor:pointer"
                       title="<?= $m['tahsil_tarihi'] ? 'Tahsil: ' . trTarih($m['tahsil_tarihi']) : 'Tahsil edildi olarak işaretle' ?>">
              </td>
              <td class="sag" style="white-space:nowrap">
                <button type="button" class="btn ikincil mini"
                        onclick='makbuzAc(<?= json_encode($m, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Düzenle</button>
                <a href="<?= site_url('makbuz/sil/' . (int) $m['id']) ?>" class="btn kirmizi mini"
                   data-onay="Bu makbuz silinsin mi?">Sil</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--gri-50);font-weight:700">
              <td colspan="2" class="sag">TOPLAM</td>
              <td class="sag"><?= number_format($tB, 2, ',', '.') ?></td>
              <td class="sag" style="color:var(--turuncu)"><?= number_format($tS, 2, ',', '.') ?></td>
              <td class="sag" style="color:var(--mor)"><?= number_format($tK, 2, ',', '.') ?></td>
              <td class="sag" style="color:var(--yesil)"><?= number_format($tN, 2, ',', '.') ?></td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ MAKBUZ MODALI ============ -->
<div class="modal-arka" id="makbuz-modal">
  <div class="modal genis">
    <form method="post" action="<?= site_url('makbuz/kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="mb-id">
      <input type="hidden" name="mukellef_id" value="<?= (int) $mukellef['id'] ?>">
      <input type="hidden" name="yil" value="<?= (int) $yil ?>">
      <div class="modal-baslik">
        <h3 id="mb-baslik">🧾 Makbuz Ekle</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
      </div>
      <div class="modal-govde">
        <div class="form-grid">
          <div class="form-grup">
            <label>Makbuz Tarihi <span class="zorunlu">*</span></label>
            <input type="date" name="tarih" id="mb-tarih" class="girdi" required>
          </div>
          <div class="form-grup">
            <label>Makbuz No</label>
            <input type="text" name="makbuz_no" id="mb-no" class="girdi" maxlength="40">
          </div>
          <div class="form-grup">
            <label>Brüt Tutar (₺) <span class="zorunlu">*</span></label>
            <input type="text" name="brut" id="mb-brut" class="girdi" required inputmode="decimal"
                   style="text-align:right;font-weight:700" placeholder="0,00">
            <span class="yardim">Stopaj matrahı (KDV hariç).</span>
          </div>
          <div class="form-grup">
            <label>Stopaj (₺)</label>
            <input type="text" name="stopaj" id="mb-stopaj" class="girdi" inputmode="decimal"
                   style="text-align:right" placeholder="otomatik">
            <span class="yardim">Boş bırakılırsa %<?= rtrim(rtrim(number_format($stopajOran, 2, ',', '.'), '0'), ',') ?> hesaplanır.</span>
          </div>
          <div class="form-grup">
            <label>KDV (₺)</label>
            <input type="text" name="kdv" id="mb-kdv" class="girdi" inputmode="decimal"
                   style="text-align:right" placeholder="otomatik">
            <span class="yardim">Boş bırakılırsa %<?= rtrim(rtrim(number_format($kdvOran, 2, ',', '.'), '0'), ',') ?> hesaplanır.</span>
          </div>
          <div class="form-grup">
            <label>Kesen Mali Müşavir</label>
            <select name="musavir_id" id="mb-musavir">
              <option value="">— Portföy sahibi —</option>
              <?php foreach ($musavirler as $mid => $mad): ?>
                <option value="<?= $mid ?>"><?= esc($mad) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-grup">
            <label class="onay" style="margin-top:22px">
              <input type="checkbox" name="tahsil_edildi" id="mb-tahsil" value="1">
              <span>Tahsil edildi</span>
            </label>
          </div>
          <div class="form-grup">
            <label>Tahsil Tarihi</label>
            <input type="date" name="tahsil_tarihi" id="mb-tahsil-tarih" class="girdi">
          </div>
          <div class="form-grup tam">
            <label>Açıklama</label>
            <input type="text" name="aciklama" id="mb-aciklama" class="girdi" maxlength="250">
          </div>
        </div>

        <div class="uyari bilgi" style="padding:9px 14px;font-size:13px;margin-top:8px">
          <span class="ik">ℹ</span>
          <div>
            <b>Net = Brüt − Stopaj + KDV</b> olarak hesaplanır ve kaydedilir.
            Oranlar sonradan değişse bile bu makbuzun tutarları korunur.
          </div>
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
function makbuzAc(m) {
  m = m || {};
  document.getElementById('mb-baslik').textContent = m.id ? '🧾 Makbuzu Düzenle' : '🧾 Makbuz Ekle';
  document.getElementById('mb-id').value      = m.id || '';
  document.getElementById('mb-tarih').value   = m.tarih ? String(m.tarih).substr(0, 10)
      : '<?= sprintf('%04d-%02d-%02d', (int) $yil, (int) date('n'), (int) date('j')) ?>';
  document.getElementById('mb-no').value      = m.makbuz_no || '';
  document.getElementById('mb-brut').value    = m.brut ? bicim(m.brut) : '';
  document.getElementById('mb-stopaj').value  = m.stopaj ? bicim(m.stopaj) : '';
  document.getElementById('mb-kdv').value     = m.kdv ? bicim(m.kdv) : '';
  document.getElementById('mb-musavir').value = m.musavir_id || '';
  document.getElementById('mb-tahsil').checked = String(m.tahsil_edildi) === '1';
  document.getElementById('mb-tahsil-tarih').value = m.tahsil_tarihi ? String(m.tahsil_tarihi).substr(0, 10) : '';
  document.getElementById('mb-aciklama').value = m.aciklama || '';
  BT.modalAc('makbuz-modal');
}

function bicim(s) {
  return Number(s).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Tutar alanları biçimlendirme
['mb-brut', 'mb-stopaj', 'mb-kdv'].forEach(function (id) {
  var e = document.getElementById(id);
  if (!e) { return; }
  e.addEventListener('blur', function () {
    var v = this.value.trim();
    if (v === '') { return; }
    v = v.replace(/\s/g, '');
    if (v.indexOf(',') > -1) { v = v.replace(/\./g, '').replace(',', '.'); }
    var s = parseFloat(v);
    this.value = isNaN(s) ? '' : bicim(s);
  });
});

// Brüt girilince stopaj/KDV önizlemesi (boşsa)
document.getElementById('mb-brut').addEventListener('blur', function () {
  var v = parseFloat(String(this.value).replace(/\./g, '').replace(',', '.'));
  if (isNaN(v)) { return; }
  var st = document.getElementById('mb-stopaj');
  var kd = document.getElementById('mb-kdv');
  if (st.value.trim() === '') { st.placeholder = bicim(v * <?= $stopajOran ?> / 100); }
  if (kd.value.trim() === '') { kd.placeholder = bicim(v * <?= $kdvOran ?> / 100); }
});

// Tahsil işareti
document.querySelectorAll('.md-tahsil-kutu').forEach(function (cb) {
  cb.addEventListener('change', function () {
    cb.disabled = true;
    BT.post('<?= site_url('makbuz/tahsil') ?>', { id: cb.dataset.id, tahsil: cb.checked ? 1 : 0 })
      .then(function (j) {
        BT.bildir(j.mesaj, 'basari');
        cb.closest('tr').classList.toggle('md-tahsil', cb.checked);
      })
      .catch(function (e) { BT.bildir(e.message, 'hata'); cb.checked = !cb.checked; })
      .finally(function () { cb.disabled = false; });
  });
});
</script>
<?= $this->endSection() ?>
