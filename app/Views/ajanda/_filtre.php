<?php
/**
 * AJANDA FİLTRE ÇUBUĞU — PARÇA DOSYA
 *
 * DİKKAT: $this->include() üst görünümün yerel değişkenlerini TAŞIMAZ.
 * Bu dosya normal include ile çağrılır; gerekli değişkenler önce hazırlanır:
 *   $filtre, $etiketler, $kullanicilar, $gorunurluk, $oncelikler, $durumlar
 *   $hedef  → formun gideceği adres ('ajanda' | 'ajanda/takvim')
 *   $takvimMi → takvimde tarih aralığı alanları gizlenir
 */
$takvimMi = $takvimMi ?? false;
?>
<form method="get" action="<?= site_url($hedef ?? 'ajanda') ?>" class="filtre-bar">
  <?php if ($takvimMi): ?>
    <input type="hidden" name="yil" value="<?= (int) ($yil ?? date('Y')) ?>">
    <input type="hidden" name="ay" value="<?= (int) ($ay ?? date('n')) ?>">
  <?php else: ?>
    <div class="form-grup">
      <label>Başlangıç</label>
      <input type="date" name="bas" class="girdi" value="<?= esc($filtre['bas'] ?? '') ?>">
    </div>
    <div class="form-grup">
      <label>Bitiş</label>
      <input type="date" name="bit" class="girdi" value="<?= esc($filtre['bit'] ?? '') ?>">
    </div>
  <?php endif; ?>

  <div class="form-grup">
    <label>Durum</label>
    <select name="durum" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($durumlar as $dk => $dv): ?>
        <option value="<?= $dk ?>" <?= ($filtre['durum'] ?? '') === $dk ? 'selected' : '' ?>>
          <?= esc($dv) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Öncelik</label>
    <select name="oncelik" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($oncelikler as $ok => $ov): ?>
        <option value="<?= $ok ?>" <?= ($filtre['oncelik'] ?? '') === $ok ? 'selected' : '' ?>>
          <?= esc($ov) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-grup">
    <label>Görünürlük</label>
    <select name="gorunurluk" data-oto-filtre>
      <option value="">Tümü</option>
      <?php foreach ($gorunurluk as $gk => $gv): ?>
        <option value="<?= $gk ?>" <?= ($filtre['gorunurluk'] ?? '') === $gk ? 'selected' : '' ?>>
          <?= esc($gv) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if (count($kullanicilar) > 1): ?>
    <div class="form-grup">
      <label>Atanan</label>
      <select name="atanan_id" data-oto-filtre>
        <option value="">Herkes</option>
        <?php foreach ($kullanicilar as $uk => $uv): ?>
          <option value="<?= $uk ?>" <?= (int) ($filtre['atanan_id'] ?? 0) === $uk ? 'selected' : '' ?>>
            <?= esc($uv) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <?php if ($etiketler !== []): ?>
    <div class="form-grup">
      <label>Etiket</label>
      <select name="etiket" data-oto-filtre>
        <option value="">Tümü</option>
        <?php foreach ($etiketler as $e): ?>
          <option value="<?= esc($e) ?>" <?= ($filtre['etiket'] ?? '') === $e ? 'selected' : '' ?>>
            <?= esc($e) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="form-grup" style="min-width:170px">
    <label>Ara</label>
    <input type="text" name="q" class="girdi" value="<?= esc($filtre['q'] ?? '') ?>"
           placeholder="Başlık, açıklama, mükellef">
  </div>

  <div class="btn-grup">
    <button type="submit" class="btn kucuk">🔍 Filtrele</button>
    <a href="<?= site_url($hedef ?? 'ajanda') ?>" class="btn ikincil kucuk">Sıfırla</a>
  </div>
</form>
