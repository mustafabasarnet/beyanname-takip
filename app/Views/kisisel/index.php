<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<style>
/* Kişisel not ekranı — kompakt, temiz */
.kn-kart{background:#fff;border:1px solid var(--gri-200,#e2e8f0);border-radius:12px;overflow:hidden}
.kn-bas{display:flex;align-items:center;gap:8px;padding:10px 16px;border-bottom:1px solid var(--gri-100,#f1f5f9);
  font-weight:700;font-size:14px}
.kn-govde{padding:14px 16px}
.kn-tarih-sec{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.kn-textarea{width:100%;min-height:90px;font:inherit;font-size:13.5px;padding:9px 11px;border:1px solid #cbd5e1;
  border-radius:8px;resize:vertical;color:var(--gri-800,#1e293b)}
.kn-textarea:focus{outline:2px solid var(--ana,#2563eb);outline-offset:-1px}
.gorev-satir{display:flex;align-items:flex-start;gap:10px;padding:8px 4px;border-bottom:1px dashed var(--gri-100,#f1f5f9)}
.gorev-satir:last-child{border-bottom:0}
.gorev-kutu{width:18px;height:18px;accent-color:#059669;cursor:pointer;margin-top:2px;flex-shrink:0}
.gorev-metin{flex:1;min-width:0}
.gorev-baslik{font-size:13.5px;color:var(--gri-800,#1e293b)}
.gorev-baslik.tamam{text-decoration:line-through;color:var(--gri-400,#94a3b8)}
.gorev-not{font-size:12px;color:var(--gri-500,#64748b);margin-top:2px;white-space:pre-wrap}
.gorev-tarih{font-size:11px;color:var(--gri-400,#cbd5e1);margin-top:2px}
.sil-btn{background:none;border:0;cursor:pointer;font-size:14px;color:var(--gri-300,#cbd5e1);
  padding:2px 6px;border-radius:6px;line-height:1}
.sil-btn:hover{background:#fef2f2;color:#dc2626}
.kn-bos{color:var(--gri-400,#94a3b8);font-size:13px;padding:10px 4px}
.gecmis-satir{display:flex;justify-content:space-between;gap:10px;padding:8px 4px;border-bottom:1px dashed var(--gri-100,#f1f5f9)}
.gecmis-satir:last-child{border-bottom:0}
.gecmis-tarih{font-weight:700;font-size:12.5px;color:var(--gri-600,#475569);white-space:nowrap;min-width:88px}
.gecmis-metin{flex:1;font-size:13px;color:var(--gri-700,#334155);white-space:pre-wrap;word-break:break-word}
</style>

<div class="kart-baslik" style="margin:0 0 4px">
  <h2>📝 Kişisel Notlarım</h2>
  <span class="kucuk-yazi">Yalnız siz görürsünüz — yönetici dahil kimse erişemez.</span>
</div>

<!-- İşlem sonucu -->
<?php if (session()->getFlashdata('basari')): ?>
  <div class="uyari basari"><span class="ik">✓</span><div><?= esc(session()->getFlashdata('basari')) ?></div></div>
<?php endif; ?>
<?php if (session()->getFlashdata('hata')): ?>
  <div class="uyari hata"><span class="ik">✕</span><div><?= esc(session()->getFlashdata('hata')) ?></div></div>
<?php endif; ?>

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

    <form method="post" action="<?= site_url('kisisel/not-kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="tarih" value="<?= esc($tarih) ?>">
      <textarea name="metin" class="kn-textarea"
                placeholder="<?= $tarih === $bugun ? 'Bugün ne yapılacak, ne düşünüyorsun?…' : 'Bu güne not bırak…' ?>"><?= esc($gunNotu['metin'] ?? '') ?></textarea>
      <div class="form-alt" style="margin:8px 0 0">
        <button type="submit" class="btn kucuk">💾 Notu Kaydet</button>
        <?php if (! empty($gunNotu)): ?>
          <span class="kucuk-yazi" style="color:var(--yesil,#059669)">✓ <?= esc($tarih) ?> notu var</span>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- ================= TO-DO ================= -->
<div class="kn-kart">
  <div class="kn-bas">✅ Yapılacaklar
    <span class="kucuk-yazi" style="font-weight:400;margin-left:auto">
      <?= count($acik) ?> açık<?= count($biten) > 0 ? ' · ' . count($biten) . ' tamamlandı' : '' ?>
    </span>
  </div>
  <div class="kn-govde">

    <form method="post" action="<?= site_url('kisisel/gorev-ekle') ?>" class="form-grid" style="grid-template-columns:1fr 2fr auto">
      <?= csrf_field() ?>
      <div class="form-grup">
        <label>Görev</label>
        <input type="text" name="baslik" class="girdi" required maxlength="200" placeholder="Örn. Müşteriyi ara">
      </div>
      <div class="form-grup">
        <label>Not (opsiyonel)</label>
        <input type="text" name="metin" class="girdi" maxlength="500" placeholder="Ayrıntı…">
      </div>
      <div class="form-grup" style="align-self:end">
        <button type="submit" class="btn">+ Ekle</button>
      </div>
    </form>

    <!-- Açık görevler -->
    <?php if ($acik === [] && $biten === []): ?>
      <div class="kn-bos">Henüz görev yok. Yukarıdan ekleyin.</div>
    <?php endif; ?>

    <?php foreach ($acik as $g): ?>
      <div class="gorev-satir">
        <form method="post" action="<?= site_url('kisisel/gorev-ters') ?>" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
          <button type="submit" class="gorev-kutu" style="border:2px solid var(--gri-300,#cbd5e1);border-radius:5px;background:#fff"
                  title="Tamamlandı olarak işaretle" aria-label="Tamamla"></button>
        </form>
        <div class="gorev-metin">
          <div class="gorev-baslik"><?= esc($g['baslik']) ?></div>
          <?php if (! empty($g['metin'])): ?><div class="gorev-not"><?= esc($g['metin']) ?></div><?php endif; ?>
        </div>
        <form method="post" action="<?= site_url('kisisel/sil') ?>" style="margin:0"
              onsubmit="return confirm('Görev silinsin mi?')">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
          <button type="submit" class="sil-btn" title="Sil" aria-label="Sil">🗑</button>
        </form>
      </div>
    <?php endforeach; ?>

    <!-- Tamamlananlar -->
    <?php if ($biten !== []): ?>
      <details style="margin-top:6px">
        <summary class="kucuk-yazi" style="cursor:pointer;color:var(--gri-500,#64748b)">
          ✓ Tamamlananlar (<?= count($biten) ?>)
        </summary>
        <?php foreach ($biten as $g): ?>
          <div class="gorev-satir">
            <form method="post" action="<?= site_url('kisisel/gorev-ters') ?>" style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
              <button type="submit" class="gorev-kutu" style="border:2px solid #059669;border-radius:5px;background:#059669"
                      title="Geri aç" aria-label="Geri aç">✓</button>
            </form>
            <div class="gorev-metin">
              <div class="gorev-baslik tamam"><?= esc($g['baslik']) ?></div>
              <?php if (! empty($g['metin'])): ?><div class="gorev-not" style="text-decoration:line-through"><?= esc($g['metin']) ?></div><?php endif; ?>
            </div>
            <form method="post" action="<?= site_url('kisisel/sil') ?>" style="margin:0"
                  onsubmit="return confirm('Görev kalıcı olarak silinsin mi?')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
              <button type="submit" class="sil-btn" title="Sil" aria-label="Sil">🗑</button>
            </form>
          </div>
        <?php endforeach; ?>
      </details>
    <?php endif; ?>

  </div>
</div>

<!-- ================= GEÇMİŞ GÜNLÜK NOTLAR ================= -->
<?php if ($gecmis !== []): ?>
<div class="kn-kart" style="margin-top:16px">
  <div class="kn-bas">🕘 Son Günlük Notlar</div>
  <div class="kn-govde">
    <?php foreach ($gecmis as $n): ?>
      <?php if (($n['tarih'] ?? '') === $tarih) { continue; } /* açıktaki tarih üstte zaten */ ?>
      <div class="gecmis-satir">
        <div class="gecmis-tarih"><?= trTarih($n['tarih']) ?></div>
        <div class="gecmis-metin"><?= esc($n['metin']) ?></div>
        <form method="post" action="<?= site_url('kisisel/sil') ?>" style="margin:0"
              onsubmit="return confirm('Bu not silinsin mi?')">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
          <input type="hidden" name="tur" value="not">
          <button type="submit" class="sil-btn" title="Sil" aria-label="Sil">🗑</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
