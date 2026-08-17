<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="kart">
  <div class="kart-baslik">
    <h2>📑 <?= esc($liste['ad']) ?></h2>
    <div class="sag">
      <span class="kucuk-yazi"><?= count($satirlar) ?> mükellef</span>
      <button class="btn ikincil kucuk"
              onclick='listeAc(<?= json_encode($liste, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                               <?= json_encode(array_map("intval", $secilenler)) ?>)'>✏️ Düzenle</button>
      <?php $dq = 'yil=' . $yil . '&ay=' . ($ay ?? 0); ?>
      <a href="<?= site_url('odeme/liste-yazdir/' . $liste['id'] . '?' . $dq) ?>" target="_blank" class="btn kucuk">🖨️ Yazdır</a>
      <a href="<?= site_url('odeme/liste-yazdir/' . $liste['id'] . '?' . $dq . '&detay=1') ?>" target="_blank" class="btn ikincil kucuk">🖨️ Detaylı</a>
      <a href="<?= site_url('odeme/liste-excel/' . $liste['id'] . '?' . $dq) ?>" class="btn yesil kucuk">📊 Excel</a>
      <a href="<?= site_url('odeme/listeler') ?>" class="btn ikincil kucuk">← Listeler</a>
    </div>
  </div>

  <div class="kart-govde">
    <?php if (! empty($liste['aciklama'])): ?>
      <div class="kucuk-yazi mb8"><?= esc($liste['aciklama']) ?></div>
    <?php endif; ?>

    <!-- Dönem seçici: liste kalıcıdır, dönem burada değişir -->
    <form method="get" class="satir mb16" style="gap:10px;align-items:flex-end;flex-wrap:wrap">
      <div class="form-grup" style="gap:4px">
        <label style="font-size:11px;text-transform:uppercase;color:var(--gri-500)">Dönem Yılı</label>
        <select name="yil" data-oto-filtre style="padding:7px 10px">
          <?php foreach (yilSecenekleri(3, 1) as $y): ?>
            <option value="<?= $y ?>" <?= (int) $yil === $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grup" style="gap:4px">
        <label style="font-size:11px;text-transform:uppercase;color:var(--gri-500)">Ay</label>
        <select name="ay" data-oto-filtre style="padding:7px 10px">
          <option value="0" <?= $ay === null ? 'selected' : '' ?>>Tüm Yıl</option>
          <?php for ($a = 1; $a <= 12; $a++): ?>
            <option value="<?= $a ?>" <?= (int) $ay === $a ? 'selected' : '' ?>><?= ayAdi($a) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <button type="submit" class="btn kucuk">🔄 Dönemi Getir</button>
      <span class="rozet mavi" style="margin-bottom:6px">
        <?= $ay !== null ? ayAdi((int) $ay) . ' ' : 'Tüm Yıl ' ?><?= $yil ?>
      </span>
    </form>
    <div class="satir">
      <span class="rozet mavi">Beyanname tahakkukları</span>
      <?php if ((int) $liste['ozel_dahil'] === 1): ?>
        <span class="rozet mor">Özel ödeme kalemlerim</span>
      <?php endif; ?>
      <?php if ((int) $liste['ucret_dahil'] === 1): ?>
        <span class="rozet yesil">Muhasebe ücreti</span>
      <?php endif; ?>
      <span class="kucuk-yazi">— tutarlar her açılışta güncel hesaplanır</span>
    </div>
  </div>
</div>

<!-- ============ ÖZET ============ -->
<div class="stat-grid">
  <div class="stat"><div class="etiket">Mükellef</div><div class="deger"><?= count($satirlar) ?></div></div>
  <div class="stat mor"><div class="etiket">Beyanname</div>
    <div class="deger" style="font-size:20px"><?= number_format($toplam['beyanname'], 2, ',', '.') ?></div><div class="alt">₺</div></div>
  <?php if ((int) $liste['ozel_dahil'] === 1): ?>
    <div class="stat turuncu"><div class="etiket">Özel Ödemeler</div>
      <div class="deger" style="font-size:20px"><?= number_format($toplam['ozel'], 2, ',', '.') ?></div><div class="alt">₺</div></div>
  <?php endif; ?>
  <?php if ((int) $liste['ucret_dahil'] === 1): ?>
    <div class="stat"><div class="etiket">Muhasebe Ücreti</div>
      <div class="deger" style="font-size:20px"><?= number_format($toplam['ucret'], 2, ',', '.') ?></div><div class="alt">₺</div></div>
  <?php endif; ?>
  <div class="stat yesil"><div class="etiket">GENEL TOPLAM</div>
    <div class="deger" style="font-size:22px"><?= number_format($toplam['genel'], 2, ',', '.') ?></div><div class="alt">₺</div></div>
</div>

<!-- ============ TABLO ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>💰 Ödeme Tablosu</h2></div>
  <div class="kart-govde sikisik">
    <?php if ($satirlar === []): ?>
      <div class="tablo-bos">
        <span class="ikon">📭</span>
        <b><?= $ay !== null ? ayAdi((int) $ay) . ' ' : '' ?><?= $yil ?></b>
        döneminde seçili mükelleflerin ödenecek tutarı bulunamadı.<br>
        Başka bir dönem seçmeyi deneyin.<br>
        <span class="kucuk-yazi">
          Beyannamelerin <b>Onaylandı</b> durumunda ve tahakkuk tutarının girilmiş olması gerekir.
        </span>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="tablo">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Mükellef</th>
              <th>VKN / TCKN</th>
              <th class="sag">Beyanname</th>
              <?php if ((int) $liste['ozel_dahil'] === 1): ?><th class="sag">Özel Ödeme</th><?php endif; ?>
              <?php if ((int) $liste['ucret_dahil'] === 1): ?><th class="sag">Muh. Ücreti</th><?php endif; ?>
              <th class="sag">TOPLAM</th>
              <th class="sag" style="width:90px">Bildirim</th>
            </tr>
          </thead>
          <tbody>
          <?php $i = 1; foreach ($satirlar as $s): ?>
            <tr>
              <td class="kucuk-yazi"><?= $i++ ?></td>
              <td>
                <a href="<?= site_url('mukellefler/detay/' . $s['mukellef']['id']) ?>" class="kalin">
                  <?= esc($s['mukellef']['unvan']) ?>
                </a>
                <?php if (! empty($s['beyannameler'])): ?>
                  <div class="kucuk-yazi">
                    <?php foreach ($s['beyannameler'] as $bn): ?>
                      <span class="tur-rozet" style="background:<?= esc($bn['tur_renk']) ?>;font-size:10px">
                        <?= esc($bn['tur_kisa']) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if (! empty($s['ozel'])): ?>
                  <div class="kucuk-yazi" style="color:var(--mor)">
                    <?php foreach ($s['ozel'] as $oz): ?>
                      + <?= esc($oz['baslik']) ?>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="kucuk-yazi"><?= esc($s['mukellef']['vergi_kimlik_no'] ?: $s['mukellef']['tc_kimlik_no']) ?></td>
              <td class="sag"><?= number_format($s['beyan_top'], 2, ',', '.') ?></td>
              <?php if ((int) $liste['ozel_dahil'] === 1): ?>
                <td class="sag" style="color:var(--mor)">
                  <?= $s['ozel_top'] > 0 ? number_format($s['ozel_top'], 2, ',', '.') : '—' ?>
                </td>
              <?php endif; ?>
              <?php if ((int) $liste['ucret_dahil'] === 1): ?>
                <td class="sag"><?= $s['ucret'] > 0 ? number_format($s['ucret'], 2, ',', '.') : '—' ?></td>
              <?php endif; ?>
              <td class="sag kalin" style="font-size:14px"><?= number_format($s['genel'], 2, ',', '.') ?></td>
              <td class="sag">
                <a href="<?= site_url('odeme/bildirim/' . $s['mukellef']['id']
                    . '?yil=' . $yil . ($ay !== null ? '&ay=' . $ay : '')
                    . '&ucret=' . ((int) $liste['ucret_dahil'] === 1 ? '1' : '0')) ?>"
                   target="_blank" class="btn ikincil mini">🖨️</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--gri-50);font-weight:700;font-size:14px">
              <td colspan="3" class="sag">GENEL TOPLAM</td>
              <td class="sag"><?= number_format($toplam['beyanname'], 2, ',', '.') ?></td>
              <?php if ((int) $liste['ozel_dahil'] === 1): ?>
                <td class="sag" style="color:var(--mor)"><?= number_format($toplam['ozel'], 2, ',', '.') ?></td>
              <?php endif; ?>
              <?php if ((int) $liste['ucret_dahil'] === 1): ?>
                <td class="sag"><?= number_format($toplam['ucret'], 2, ',', '.') ?></td>
              <?php endif; ?>
              <td class="sag" style="color:var(--yesil);font-size:16px">
                <?= number_format($toplam['genel'], 2, ',', '.') ?> ₺
              </td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?= $this->include('odeme/_liste_form') ?>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<?= $this->include('odeme/_liste_form_js') ?>
<?= $this->endSection() ?>
