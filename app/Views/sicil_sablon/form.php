<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
$duzenleme = $sablon !== null;
$tipler    = \App\Models\SicilKuralModel::SURE_TIP_ADLARI;
if ($satirlar === []) {
    $satirlar = [[
        'id' => 0, 'ad' => '', 'sure_tipi' => 'GUN', 'sure_deger' => '',
        'belirli_tarih' => '', 'aktif' => 1,
    ]];
}
?>

<style>
.satir-ekle-alan{border:2px dashed var(--gri-300,#cbd5e1);border-radius:10px;text-align:center;
  padding:10px;cursor:pointer;color:var(--gri-600,#475569);font-size:13px;font-weight:600;margin-top:10px}
.satir-ekle-alan:hover{border-color:var(--ana);color:var(--ana);background:var(--gri-50,#f8fafc)}
.t-row{display:grid;grid-template-columns:1fr 90px 140px 130px 44px 34px;gap:8px;align-items:end;
  padding:8px 0;border-bottom:1px solid var(--gri-100,#f1f5f9)}
.t-row:last-child{border-bottom:none}
.t-row .sil-btn{height:34px}
</style>

<div class="kart-baslik"><h2><?= $baslik ?></h2>
  <span class="kucuk-yazi">Bu şablona todo eklenince, yeni işlem açıldığında her todo otomatik üretilir ve son tarihi süresine göre hesaplanır.</span>
</div>

<form method="post" action="<?= site_url('sicil-sablon/kaydet') ?>" id="sablon-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($sablon['id'] ?? 0) ?>">

  <div class="kart">
    <div class="kart-govde">
      <div class="form-grid" style="grid-template-columns:1fr 1fr 120px">
        <div class="form-grup">
          <label>Şablon Adı <b style="color:#dc2626">*</b></label>
          <input type="text" name="ad" class="girdi" required maxlength="150"
                 value="<?= esc($sablon['ad'] ?? '') ?>" placeholder="örn. Adres Değişikliği">
        </div>
        <div class="form-grup">
          <label>Açıklama</label>
          <input type="text" name="aciklama" class="girdi" maxlength="300"
                 value="<?= esc($sablon['aciklama'] ?? '') ?>" placeholder="Kısa açıklama (ops.)">
        </div>
        <div class="form-grup" style="align-self:end">
          <label style="display:flex;gap:6px;align-items:center;cursor:pointer">
            <input type="checkbox" name="aktif" value="1" style="width:16px;height:16px"
                   <?= ! $duzenleme || (int) $sablon['aktif'] === 1 ? 'checked' : '' ?>> Aktif
          </label>
        </div>
      </div>
    </div>
  </div>

  <div class="kart" style="margin-top:14px">
    <div class="kart-baslik">
      <h2>📋 Şablonun Todo Tanımları</h2>
      <div class="sag kucuk-yazi">Aktif tikli satırlar yeni işlemde üretilir.</div>
    </div>
    <div class="kart-govde">
      <div class="t-row" style="border:none;padding:0 0 6px">
        <div class="form-grup"><label>Todo Adı</label></div>
        <div class="form-grup"><label>Süre</label></div>
        <div class="form-grup"><label>Süre Türü</label></div>
        <div class="form-grup"><label>Belirli Tarih</label></div>
        <div class="form-grup"><label class="orta">Aktif</label></div>
        <div></div>
      </div>

      <div id="todo-satirlar">
        <?php foreach ($satirlar as $i => $satir): ?>
          <div class="t-row" data-satir>
            <input type="hidden" name="todo_id[]" value="<?= (int) ($satir['id'] ?? 0) ?>">
            <div class="form-grup">
              <input type="text" name="todo_ad[]" class="girdi" maxlength="150"
                     value="<?= esc($satir['ad'] ?? '') ?>"
                     placeholder="örn. Vergi Dairesine Bildirim">
            </div>
            <div class="form-grup">
              <input type="number" name="todo_sure_deger[]" class="girdi" min="1" step="1"
                     value="<?= esc($satir['sure_deger'] ?? '') ?>" placeholder="10" data-deger>
            </div>
            <div class="form-grup">
              <select name="todo_sure_tipi[]" class="girdi" data-tip>
                <?php foreach ($tipler as $tk => $tv): ?>
                  <option value="<?= $tk ?>" <?= ($satir['sure_tipi'] ?? 'GUN') === $tk ? 'selected' : '' ?>><?= esc($tv) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-grup" data-belirli-wrap <?= ($satir['sure_tipi'] ?? 'GUN') === 'BELIRLI_TARIH' ? '' : 'style="display:none"' ?>>
              <input type="date" name="todo_belirli_tarih[]" class="girdi" data-belirli
                     value="<?= esc($satir['belirli_tarih'] ?? '') ?>">
            </div>
            <div class="form-grup" style="text-align:center">
              <input type="checkbox" name="todo_aktif[]" value="1" style="width:17px;height:17px"
                     <?= ! isset($satir['aktif']) || (int) $satir['aktif'] === 1 ? 'checked' : '' ?>>
            </div>
            <div class="form-grup">
              <button type="button" class="btn ikincil sil-btn" data-satir-sil title="Bu satırı kaldır">✕</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="satir-ekle-alan" id="satir-ekle">+ Todo Ekle</div>

      <div class="form-alt">
        <button type="submit" class="btn yesil">💾 Şablonu Kaydet</button>
        <a href="<?= site_url('sicil-sablon') ?>" class="btn ikincil">İptal</a>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  var kap = document.getElementById('todo-satirlar');

  function belirliGoster(select) {
    var satir = select.closest('[data-satir]');
    var belirliMi = select.value === 'BELIRLI_TARIH';
    satir.querySelector('[data-belirli-wrap]').style.display = belirliMi ? '' : 'none';
    satir.querySelector('[data-deger]').disabled = belirliMi;
  }

  function temizle(satir) {
    satir.querySelector('[name="todo_id[]"]').value = '';
    satir.querySelector('[name="todo_ad[]"]').value = '';
    satir.querySelector('[name="todo_sure_deger[]"]').value = '';
    satir.querySelector('[name="todo_belirli_tarih[]"]').value = '';
    var tip = satir.querySelector('[name="todo_sure_tipi[]"]');
    tip.value = 'GUN';
    var ck = satir.querySelector('[name="todo_aktif[]"]');
    ck.checked = true;
    belirliGoster(tip);
  }

  function bagla(satir) {
    satir.querySelector('[data-tip]').addEventListener('change', function () { belirliGoster(this); });
    satir.querySelector('[data-satir-sil]').addEventListener('click', function () {
      var adet = kap.querySelectorAll('[data-satir]').length;
      if (adet <= 1) { temizle(satir); return; }
      satir.remove();
    });
    belirliGoster(satir.querySelector('[data-tip]'));
  }

  kap.querySelectorAll('[data-satir]').forEach(bagla);

  document.getElementById('satir-ekle').addEventListener('click', function () {
    var ornek = kap.querySelector('[data-satir]');
    var yeni = ornek.cloneNode(true);
    temizle(yeni);
    kap.appendChild(yeni);
    bagla(yeni);
    var ilk = yeni.querySelector('input[type="text"]');
    if (ilk) ilk.focus();
  });

  // Enter ile yanlışlıkla form gönderimini önlemek yerine: satır içinde Enter → yeni satır
  kap.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
      e.preventDefault();
      document.getElementById('satir-ekle').click();
    }
  });
})();
</script>

<?= $this->endSection() ?>
