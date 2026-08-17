<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<!-- ============ FİLTRE ============ -->
<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Durum</label>
    <select name="durum" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($durumlar as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($filtre['durum'] ?? '') === $k ? 'selected' : '' ?>><?= esc($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Geliş Yılı</label>
    <select name="yil" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach (yilSecenekleri(4, 1) as $y): ?>
        <option value="<?= $y ?>" <?= (int) ($filtre['yil'] ?? 0) === $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if (count($musavirler) > 1): ?>
    <div class="form-grup">
      <label>Mali Müşavir</label>
      <select name="musavir_id" data-oto-filtre>
        <option value="">Tümü</option>
        <?php // Seçim korunmalı: yenilemede/filtrede "Tümü"ye dönmesin
              $secKM = secilenMusavirId($filtre['musavir_id'] ?? null); ?>
        <?php foreach ($musavirler as $mid => $mad): ?>
          <option value="<?= $mid ?>" <?= $secKM === (int) $mid ? 'selected' : '' ?>><?= esc($mad) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="form-grup" style="min-width:190px">
    <label>Ara</label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>" placeholder="Mükellef / YMM">
  </div>

  <div class="form-grup">
    <label class="onay" style="margin-top:18px">
      <input type="checkbox" name="gecikmis" value="1" <?= ! empty($filtre['gecikmis']) ? 'checked' : '' ?> data-oto-filtre>
      Süresi geçenler
    </label>
  </div>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <button type="button" class="btn yesil kucuk" onclick="kiAc()">+ Yeni Tutanak</button>
    <?php $qs = http_build_query(array_filter([
        'durum' => $filtre['durum'] ?? '', 'yil' => $filtre['yil'] ?? '',
        'q' => $filtre['q'] ?? '', 'gecikmis' => $filtre['gecikmis'] ?? '',
    ], static fn ($v) => $v !== null && $v !== '')); ?>
    <a href="<?= site_url('karsit/excel?' . $qs) ?>" class="btn ikincil kucuk">📊 Excel</a>
    <a href="<?= site_url('karsit/yazdir?' . $qs) ?>" target="_blank" class="btn ikincil kucuk">🖨️ Yazdır</a>
  </div>
</form>

<!-- ============ ÖZET ============ -->
<div class="stat-grid">
  <div class="stat"><div class="etiket">Toplam Tutanak</div><div class="deger"><?= (int) $ozet['toplam'] ?></div></div>
  <div class="stat turuncu"><div class="etiket">Cevap Bekliyor</div>
    <div class="deger"><?= (int) ($ozet['cevap_bekliyor'] ?? 0) ?></div><div class="alt">İşlem yapılmadı</div></div>
  <div class="stat sari"><div class="etiket">Hazırlanıyor</div>
    <div class="deger"><?= (int) ($ozet['hazirlaniyor'] ?? 0) ?></div></div>
  <div class="stat yesil"><div class="etiket">Gönderildi</div>
    <div class="deger"><?= (int) ($ozet['gonderildi'] ?? 0) ?></div><div class="alt">Tamamlandı</div></div>
  <div class="stat kirmizi"><div class="etiket">Süresi Geçti</div>
    <div class="deger"><?= (int) ($ozet['gecikmis'] ?? 0) ?></div><div class="alt">Acil</div></div>
</div>

<!-- ============ LİSTE ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🔍 Karşıt İnceleme Tutanakları (<?= count($kayitlar) ?>)</h2>
    <div class="sag kucuk-yazi">Durum hücresinden değiştirebilirsiniz</div>
  </div>

  <div class="kart-govde sikisik">
    <?php if ($kayitlar === []): ?>
      <div class="tablo-bos">
        <span class="ikon">📭</span>
        Kayıtlı tutanak yok.
        <div class="mt16"><button class="btn kucuk" onclick="kiAc()">+ İlk Tutanağı Ekle</button></div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead>
            <tr>
              <th>Mükellef</th>
              <th>YMM / Büro</th>
              <th>Geliş Tarihi</th>
              <th>Son Cevap</th>
              <th>Kalan</th>
              <th>Gönderim</th>
              <th>Durum</th>
              <th style="min-width:140px">Not</th>
              <th class="sag">İşlem</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($kayitlar as $k):
              $gecikti = ! empty($k['son_cevap_tarihi'])
                  && $k['son_cevap_tarihi'] < date('Y-m-d')
                  && in_array($k['durum'], ['CEVAP_BEKLIYOR', 'HAZIRLANIYOR'], true);
              $kalan = ! empty($k['son_cevap_tarihi']) ? kalanGunMetni($k['son_cevap_tarihi']) : null;
          ?>
            <tr class="<?= $gecikti ? 'gecikmis-satir' : '' ?>">
              <td>
                <a href="<?= site_url('mukellefler/detay/' . $k['mukellef_id']) ?>" class="kalin">
                  <?= esc(kisalt($k['mukellef_unvan'], 28)) ?>
                </a>
                <div class="kucuk-yazi"><?= esc($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no']) ?></div>
              </td>
              <td><?= esc($k['ymm_adi']) ?></td>
              <td class="kucuk-yazi"><?= trTarih($k['gelis_tarihi']) ?></td>
              <td class="kucuk-yazi"><?= $k['son_cevap_tarihi'] ? trTarih($k['son_cevap_tarihi']) : '—' ?></td>
              <td>
                <?php if ($kalan !== null && in_array($k['durum'], ['CEVAP_BEKLIYOR', 'HAZIRLANIYOR'], true)): ?>
                  <span class="rozet <?= $kalan['sinif'] ?>"><?= esc($kalan['metin']) ?></span>
                <?php else: ?>
                  <span class="kucuk-yazi metin-gri">—</span>
                <?php endif; ?>
              </td>
              <td class="kucuk-yazi gonderim-<?= $k['id'] ?>">
                <?= $k['gonderim_tarihi'] ? trTarih($k['gonderim_tarihi']) : '—' ?>
              </td>
              <td>
                <select class="girdi ki-durum" data-id="<?= $k['id'] ?>"
                        style="padding:4px 8px;font-size:12px;min-width:125px;font-weight:600">
                  <?php foreach ($durumlar as $dk => $dv): ?>
                    <option value="<?= $dk ?>" <?= $k['durum'] === $dk ? 'selected' : '' ?>><?= esc($dv) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="not-hucre <?= ! empty($k['not_metni']) ? 'dolu' : '' ?>"
                  data-id="<?= $k['id'] ?>" onclick="kiNot(this)">
                <?php if (! empty($k['not_metni'])): ?>
                  <span class="not-metin">📌 <?= esc($k['not_metni']) ?></span>
                <?php else: ?>
                  <span class="not-metin not-bos">+ not ekle</span>
                <?php endif; ?>
              </td>
              <td class="sag" style="white-space:nowrap">
                <button class="btn ikincil mini"
                        onclick='kiAc(<?= json_encode($k, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Düzenle</button>
                <a href="<?= site_url('karsit/sil/' . $k['id']) ?>" class="btn kirmizi mini"
                   data-onay="Tutanak silinsin mi?">Sil</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ FORM MODALI ============ -->
<div class="modal-arka" id="ki-modal">
  <div class="modal genis">
    <form method="post" action="<?= site_url('karsit/kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="ki-id">
      <div class="modal-baslik">
        <h3 id="ki-baslik">🔍 Karşıt İnceleme Tutanağı</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
      </div>
      <div class="modal-govde">
        <div class="form-grid">
          <div class="form-grup tam">
            <label>Mükellef <span class="zorunlu">*</span></label>
            <select name="mukellef_id" id="ki-mukellef" required>
              <option value="">— Seçiniz —</option>
              <?php foreach ($mukellefler as $mid => $mad): ?>
                <option value="<?= $mid ?>"><?= esc($mad) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-grup tam">
            <label>YMM / Büro Adı <span class="zorunlu">*</span></label>
            <input type="text" name="ymm_adi" id="ki-ymm" class="girdi" required
                   list="ymm-liste" placeholder="Tutanağı gönderen YMM">
            <datalist id="ymm-liste">
              <?php foreach ($ymmler as $y): ?><option value="<?= esc($y) ?>"><?php endforeach; ?>
            </datalist>
          </div>

          <div class="form-grup">
            <label>Geliş Tarihi <span class="zorunlu">*</span></label>
            <input type="date" name="gelis_tarihi" id="ki-gelis" class="girdi" required>
          </div>

          <div class="form-grup">
            <label>Son Cevap Tarihi</label>
            <input type="date" name="son_cevap_tarihi" id="ki-son" class="girdi">
            <span class="yardim">Boş bırakılabilir</span>
          </div>

          <div class="form-grup">
            <label>Gönderim Tarihi</label>
            <input type="date" name="gonderim_tarihi" id="ki-gonderim" class="girdi">
          </div>

          <div class="form-grup">
            <label>Durum</label>
            <select name="durum" id="ki-durum-f">
              <?php foreach ($durumlar as $dk => $dv): ?>
                <option value="<?= $dk ?>"><?= esc($dv) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-grup tam">
            <label>Not</label>
            <textarea name="not_metni" id="ki-not" rows="3"></textarea>
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
function kiAc(k) {
  k = k || {};
  document.getElementById('ki-baslik').textContent = k.id
    ? '🔍 Tutanağı Düzenle' : '🔍 Yeni Karşıt İnceleme Tutanağı';

  document.getElementById('ki-id').value       = k.id || '';
  document.getElementById('ki-mukellef').value = k.mukellef_id || '';
  document.getElementById('ki-ymm').value      = k.ymm_adi || '';
  document.getElementById('ki-gelis').value    = k.gelis_tarihi ? String(k.gelis_tarihi).substr(0, 10) : '<?= date('Y-m-d') ?>';
  document.getElementById('ki-son').value      = k.son_cevap_tarihi ? String(k.son_cevap_tarihi).substr(0, 10) : '';
  document.getElementById('ki-gonderim').value = k.gonderim_tarihi ? String(k.gonderim_tarihi).substr(0, 10) : '';
  document.getElementById('ki-durum-f').value  = k.durum || 'CEVAP_BEKLIYOR';
  document.getElementById('ki-not').value      = k.not_metni || '';

  BT.modalAc('ki-modal');
}

// Durum değiştirme
document.querySelectorAll('.ki-durum').forEach(function (sel) {
  sel.dataset.eski = sel.value;
  sel.addEventListener('change', function () {
    var id = sel.dataset.id;
    sel.disabled = true;
    BT.post('<?= site_url('karsit/durum') ?>', { id: id, durum: sel.value })
      .then(function (j) {
        BT.bildir(j.mesaj, 'basari');
        sel.dataset.eski = sel.value;
        var g = document.querySelector('.gonderim-' + id);
        if (g) g.textContent = j.gonderim_tarihi || '—';
        if (j.yeni_durum === 'GONDERILDI' || j.yeni_durum === 'IPTAL') {
          sel.closest('tr').classList.remove('gecikmis-satir');
        }
      })
      .catch(function (e) { BT.bildir(e.message, 'hata'); sel.value = sel.dataset.eski; })
      .finally(function () { sel.disabled = false; });
  });
});

// Not düzenleme
function kiNot(td) {
  if (td.querySelector('textarea')) return;
  var id = td.dataset.id;
  var span = td.querySelector('.not-metin');
  var mevcut = span.classList.contains('not-bos') ? '' : span.textContent.replace('📌 ', '').trim();

  var ta = document.createElement('textarea');
  ta.className = 'girdi'; ta.value = mevcut; ta.rows = 2;
  ta.style.cssText = 'font-size:12px;padding:4px 7px;min-height:44px';
  td.innerHTML = ''; td.appendChild(ta); ta.focus();

  function kaydet() {
    var yeni = ta.value.trim();
    BT.post('<?= site_url('karsit/not') ?>', { id: id, not: yeni })
      .then(function () { BT.bildir('Not kaydedildi.', 'basari'); yaz(yeni); })
      .catch(function (e) { BT.bildir(e.message, 'hata'); yaz(mevcut); });
  }
  function yaz(d) {
    td.classList.toggle('dolu', d !== '');
    td.innerHTML = d !== ''
      ? '<span class="not-metin">📌 ' + d.replace(/</g, '&lt;') + '</span>'
      : '<span class="not-metin not-bos">+ not ekle</span>';
  }
  ta.addEventListener('blur', kaydet);
  ta.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ta.blur(); }
    if (e.key === 'Escape') { ta.removeEventListener('blur', kaydet); yaz(mevcut); }
  });
}
</script>
<?= $this->endSection() ?>
