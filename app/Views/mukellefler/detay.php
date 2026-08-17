<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
$terkli = ! empty($mukellef['terk_tarihi']);
?>

<!-- ============ ÜST BİLGİ ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🏢 <?= esc($mukellef['unvan']) ?></h2>
    <div class="sag">
      <a href="<?= site_url('mukellefler/duzenle/' . $mukellef['id']) ?>" class="btn ikincil kucuk">✏️ Düzenle</a>
      <a href="<?= site_url('mukellefler/cizelge/' . $mukellef['id'] . '?yil=' . $yil) ?>" target="_blank" class="btn ikincil kucuk">🖨️ Çizelge Yazdır</a>
      <a href="<?= site_url('mukellefler/donem-uret/' . $mukellef['id'] . '?yil=' . $yil) ?>" class="btn kucuk"
         data-onay="<?= $yil ?> yılı dönemleri yeniden hesaplanacak. Devam edilsin mi?">🔄 Dönemleri Yenile</a>
      <button class="btn ikincil kucuk" data-modal-ac="gecmis-modal">🧹 Geçmişi Kapat</button>
      <button class="btn turuncu kucuk" data-modal-ac="terk-modal">📕 Terk İşlemi</button>
    </div>
  </div>

  <div class="kart-govde">
    <?php $ggD = gencGirisimciDurum($mukellef); ?>
    <?php if ($ggD['var']): ?>
      <div class="uyari <?= $ggD['gecerli'] ? 'basari' : 'dikkat' ?>">
        <span class="ik">🌱</span>
        <div>
          <b>Genç Girişimci Kazanç İstisnası</b> — <?= esc($ggD['metin']) ?>
          <?php if ($ggD['baslangic'] !== null): ?>
            <br>Geçerlilik: <b><?= $ggD['baslangic'] ?> – <?= $ggD['bitis'] ?></b>
            (<?= $ggD['toplam'] ?> vergilendirme dönemi)
          <?php endif; ?>
          <?php if (! empty($mukellef['gg_not'])): ?>
            <br><span class="kucuk-yazi"><?= esc($mukellef['gg_not']) ?></span>
          <?php endif; ?>
          <?php if (! $ggD['gecerli'] && $ggD['donem'] !== null): ?>
            <br><b class="metin-kirmizi">Dikkat:</b> İstisna süresi dolmuştur,
            gelir/geçici vergi hesabında uygulanmaz.
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($terkli): ?>
      <div class="uyari dikkat">
        <span class="ik">⚠</span>
        <div>
          <b>Bu mükellef <?= trTarih($mukellef['terk_tarihi']) ?> tarihinde terk etmiştir.</b>
          <?= esc($mukellef['terk_nedeni'] ?: '') ?><br>
          Terk tarihinden sonraki dönemler için beyanname satırı oluşturulmaz.
          Terk yılına ait yıllık gelir/kurumlar vergisi beyannamesi ise izleyen yıl verileceği için çizelgede yer alır.
        </div>
      </div>
    <?php endif; ?>

    <div class="bilgi-liste">
      <div class="oge"><div class="et">VKN / TCKN</div><div class="dg"><?= esc(vknTckn($mukellef)) ?></div></div>
      <div class="oge"><div class="et">Mükellef Tipi</div><div class="dg"><?= mukellefTipiAdi($mukellef['mukellef_tipi']) ?></div></div>
      <div class="oge"><div class="et">Defter Tipi</div><div class="dg"><?= defterTipiAdi($mukellef['defter_tipi']) ?></div></div>
      <div class="oge"><div class="et">Genç Girişimci</div>
        <div class="dg"><?= $ggD['var'] ? esc($ggD['metin']) : 'Hayır' ?></div></div>
      <?php
      // Açık olan indirim/kısıtlamalar (tür süzgeci yok — kartta hepsi görünür).
      // Migration çalıştırılmamışsa satır hiç gösterilmez.
      $indAlanVar = array_key_exists('ind_bagkur', $mukellef);
      $indListe   = ($indAlanVar && function_exists('mukellefIndirimleri'))
          ? mukellefIndirimleri($mukellef) : [];
      ?>
      <?php if ($indAlanVar): ?>
      <div class="oge"><div class="et">İndirim / Kısıtlama</div>
        <div class="dg">
          <?php if ($indListe === []): ?>
            Yok
          <?php else: ?>
            <div style="display:flex;flex-wrap:wrap;gap:4px">
              <?php foreach ($indListe as $i): ?>
                <span class="rozet <?= $i['sinif'] ?>" style="font-size:10.5px;padding:2px 7px"
                      title="<?= esc($i['ad'] . ($i['not'] !== '' ? ' — ' . $i['not'] : '')) ?>">
                  <?= $i['ikon'] ?> <?= esc($i['kisa']) ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div></div>
      <?php endif; ?>
      <div class="oge"><div class="et">Vergi Dairesi</div><div class="dg"><?= esc($mukellef['vergi_dairesi'] ?: '-') ?></div></div>
      <div class="oge"><div class="et">İşe Başlama</div><div class="dg"><?= trTarih($mukellef['ise_baslama_tarihi']) ?></div></div>
      <?php if (! empty($mukellef['takip_baslangic'])): ?>
        <div class="oge"><div class="et">Takip Başlangıcı</div>
          <div class="dg" style="color:var(--ana)"><?= trTarih($mukellef['takip_baslangic']) ?></div></div>
      <?php endif; ?>
      <?php if (! empty($maliYetki)): ?>
        <div class="oge"><div class="et">Muhasebe Ücreti</div>
          <div class="dg"><?= $mukellef['muhasebe_ucreti'] !== null
              ? number_format((float) $mukellef['muhasebe_ucreti'], 2, ',', '.') . ' ₺' : '-' ?></div></div>
      <?php endif; ?>
      <div class="oge"><div class="et">Terk Tarihi</div>
        <div class="dg <?= $terkli ? 'metin-kirmizi' : '' ?>"><?= $terkli ? trTarih($mukellef['terk_tarihi']) : 'Faaliyet devam ediyor' ?></div></div>
      <div class="oge"><div class="et">Mali Müşavir</div><div class="dg"><?= esc($musavir['ad_soyad'] ?? '-') ?></div></div>
      <div class="oge"><div class="et">Sorumlu Personel</div><div class="dg"><?= esc($sorumlu['ad_soyad'] ?? '-') ?></div></div>
      <div class="oge"><div class="et">SGK Sicil</div><div class="dg"><?= esc($mukellef['sgk_isyeri_sicil'] ?: '-') ?></div></div>
      <div class="oge"><div class="et">Telefon</div><div class="dg"><?= esc($mukellef['telefon'] ?: '-') ?></div></div>
      <div class="oge"><div class="et">Yetkili</div><div class="dg"><?= esc($mukellef['yetkili_kisi'] ?: '-') ?></div></div>
    </div>

    <?php if (! empty($mukellef['notlar'])): ?>
      <div class="bolucu"></div>
      <div class="uyari bilgi"><span class="ik">📌</span><div><?= nl2br(esc($mukellef['notlar'])) ?></div></div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ BEYANNAME TÜRLERİ ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>🗂️ Verilen Beyannameler</h2></div>
  <div class="kart-govde">
    <?php if ($turler === []): ?>
      <div class="uyari dikkat"><span class="ik">⚠</span><div>
        Bu mükellef için beyanname türü seçilmemiş.
        <a href="<?= site_url('mukellefler/duzenle/' . $mukellef['id']) ?>">Düzenle</a> ekranından seçim yapın.
      </div></div>
    <?php else: ?>
      <div class="satir">
        <?php foreach ($turler as $t): ?>
          <span class="tur-rozet" style="background:<?= esc($t['renk']) ?>" title="<?= esc($t['aciklama']) ?>">
            <?= esc($t['ad']) ?> • <?= periyotAdi($t['periyot_override'] ?: $t['periyot']) ?>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ YILLIK ÇİZELGE MATRİSİ ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>📝 <?= $yil ?> Yılı Beyanname Çizelgesi</h2>
    <?= gencGirisimciRozet($mukellef, $yil) ?>
    <div class="sag">
      <form method="get" style="display:flex;gap:8px;align-items:center">
        <select name="yil" data-oto-filtre style="padding:5px 9px;font-size:12.5px">
          <?php foreach (yilSecenekleri(4, 2) as $y): ?>
            <option value="<?= $y ?>" <?= $y === $yil ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <div class="kart-govde sikisik">
    <?php if ($matris === []): ?>
      <div class="tablo-bos">
        <span class="ikon">📭</span>
        <?= $yil ?> yılı için dönem bulunmuyor.<br>
        <span class="kucuk-yazi">
          Mükellefin faaliyet aralığı bu yıl ile kesişmiyor olabilir
          (işe başlama: <?= trTarih($mukellef['ise_baslama_tarihi']) ?><?= $terkli ? ', terk: ' . trTarih($mukellef['terk_tarihi']) : '' ?>).
        </span>
        <div class="mt16">
          <a href="<?= site_url('mukellefler/donem-uret/' . $mukellef['id'] . '?yil=' . $yil) ?>" class="btn kucuk">🔄 Dönem Üret</a>
        </div>
      </div>
    <?php else: ?>
      <div class="tablo-sar">
        <table class="matris">
          <thead>
            <tr>
              <th class="sol-sabit">Beyanname</th>
              <?php for ($i = 1; $i <= 12; $i++): ?>
                <th title="<?= ayAdi($i) ?>"><?= ayKisa($i) ?></th>
              <?php endfor; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($matris as $turId => $bilgi):
              $tur     = $bilgi['tur'];
              $donemler = $bilgi['donemler'] ?? [];
              $periyot  = $tur['periyot'];
          ?>
            <tr>
              <td class="sol-sabit">
                <span class="tur-rozet" style="background:<?= esc($tur['renk']) ?>"><?= esc($tur['kisa']) ?></span>
                <div class="kucuk-yazi" style="font-weight:400"><?= periyotAdi($periyot) ?></div>
              </td>

              <?php
              // Her ay için: o aya son tarihi düşen dönem var mı?
              $ayHarita = [];
              foreach ($donemler as $dNo => $d) {
                  $ayHarita[(int) date('n', strtotime($d['son_tarih']))][] = $d;
              }
              ?>

              <?php for ($ay = 1; $ay <= 12; $ay++):
                  $hucreler = $ayHarita[$ay] ?? [];
              ?>
                <?php if ($hucreler === []): ?>
                  <td class="hucre bos"></td>
                <?php else: foreach ($hucreler as $d):
                    $kalan  = kalanGunMetni($d['son_tarih']);
                    $gec    = in_array($d['durum'], ['BEKLIYOR', 'HAZIR'], true) && $kalan['gun'] < 0;
                    $sinif  = $gec ? 'd-gecikmis' : 'd-' . strtolower($d['durum']);
                    $simge  = ['BEKLIYOR'=>'○','HAZIR'=>'◐','ONAYLANDI'=>'●','VERILMEYECEK'=>'—'][$d['durum']] ?? '○';
                ?>
                  <td class="hucre <?= $sinif ?>" data-id="<?= $d['id'] ?>" data-durum="<?= $d['durum'] ?>"
                      onclick="hucreDegistir(this)"
                      title="<?= esc($d['donem_adi']) ?> — Son tarih: <?= trTarih($d['son_tarih']) ?><?= $d['kaydirma_nedeni'] ? ' (' . esc($d['kaydirma_nedeni']) . ' nedeniyle kaydırıldı)' : '' ?>">
                    <?= $simge ?>
                    <span class="tarih"><?= date('d', strtotime($d['son_tarih'])) ?><?php
                        // Son tarihi başka bir yıla düşüyorsa yılı da göster (örn. Yıllık GV -> '27)
                        $sonYil = (int) date('Y', strtotime($d['son_tarih']));
                        echo $sonYil !== (int) $yil ? '<b>/' . date('y', strtotime($d['son_tarih'])) . '</b>' : '';
                    ?></span>
                  </td>
                <?php endforeach; endif; ?>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="padding:12px 18px;border-top:1px solid var(--gri-200)" class="satir kucuk-yazi">
        <b>Gösterge:</b>
        <span class="rozet bekliyor">○ Bekliyor</span>
        <span class="rozet hazir">◐ Hazır</span>
        <span class="rozet onaylandi">● Onaylandı</span>
        <span class="rozet kirmizi">Gecikmiş</span>
        <span>— Hücreye tıklayarak durumu ilerletebilirsiniz. Küçük rakam = son gün.</span>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ AYLIK NOTLAR ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>📌 <?= $yil ?> Aylık Notlar</h2></div>
  <div class="kart-govde">
    <div class="tur-grid">
      <?php for ($ay = 1; $ay <= 12; $ay++): ?>
        <div class="oge" style="padding:11px 13px;background:var(--gri-50);border:1px solid var(--gri-200);border-radius:8px">
          <div class="et kucuk-yazi kalin"><?= ayAdi($ay) ?></div>
          <div class="not-hucre <?= ! empty($notlar[$ay]) ? 'dolu' : '' ?>"
               data-ay="<?= $ay ?>" onclick="aylikNot(this)" style="margin-top:5px;min-height:22px">
            <?php if (! empty($notlar[$ay])): ?>
              <span class="not-metin"><?= esc($notlar[$ay]) ?></span>
            <?php else: ?>
              <span class="not-metin not-bos">+ not ekle</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- ============ GEÇMİŞİ KAPAT MODALI ============ -->
<div class="modal-arka" id="gecmis-modal">
  <div class="modal">
    <form method="post" action="<?= site_url('mukellefler/gecmisi-kapat/' . $mukellef['id']) ?>">
      <?= csrf_field() ?>
      <div class="modal-baslik">
        <h3>🧹 Geçmiş Dönemleri Kapat</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
      </div>
      <div class="modal-govde">
        <div class="uyari bilgi"><span class="ik">ℹ</span><div>
          Mükellefi sonradan devraldıysanız, girdiğiniz tarihten <b>önce son tarihi dolan</b>
          ve hâlâ <b>Bekliyor</b> durumundaki dönemler topluca işaretlenir —
          böylece gecikmiş görünmezler.<br><br>
          <b>Zaten işlem görmüş satırlara dokunulmaz.</b> Kalıcı çözüm için mükellef kartındaki
          <a href="<?= site_url('mukellefler/duzenle/' . $mukellef['id']) ?>">Takip Başlangıcı</a>
          alanını doldurabilirsiniz.
        </div></div>

        <div class="form-grid">
          <div class="form-grup">
            <label>Bu tarihten öncekiler</label>
            <input type="date" name="tarih" class="girdi" required
                   value="<?= esc($mukellef['takip_baslangic'] ?: date('Y-m-01')) ?>">
          </div>
          <div class="form-grup">
            <label>Hangi durumla işaretlensin?</label>
            <select name="durum">
              <option value="ONAYLANDI">Onaylandı (verildi kabul et)</option>
              <option value="VERILMEYECEK">Verilmeyecek (takip dışı)</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-alt">
        <button type="button" class="btn ikincil" data-modal-kapat>İptal</button>
        <button type="submit" class="btn">Uygula</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ TERK MODALI ============ -->
<div class="modal-arka" id="terk-modal">
  <div class="modal">
    <form method="post" action="<?= site_url('mukellefler/terk/' . $mukellef['id']) ?>">
      <?= csrf_field() ?>
      <div class="modal-baslik">
        <h3>📕 Terk / Kapanış İşlemi</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
      </div>
      <div class="modal-govde">
        <div class="uyari bilgi"><span class="ik">ℹ</span><div>
          Terk tarihi girildiğinde, terk tarihinden <b>sonra başlayan</b> dönemlere ait
          ve henüz işlem görmemiş beyanname satırları çizelgeden kaldırılır.
          Yıllık gelir/kurumlar vergisi, terk yılını kapsadığı için korunur.
        </div></div>

        <div class="form-grup mb16">
          <label>Terk Tarihi</label>
          <input type="date" name="terk_tarihi" class="girdi" value="<?= esc($mukellef['terk_tarihi'] ?? '') ?>">
          <span class="yardim">Terk kaydını kaldırmak için alanı boşaltıp kaydedin.</span>
        </div>

        <div class="form-grup">
          <label>Terk Nedeni</label>
          <input type="text" name="terk_nedeni" class="girdi" value="<?= esc($mukellef['terk_nedeni'] ?? '') ?>"
                 placeholder="Örn: Faaliyet terki / Tasfiye / Devir">
        </div>
      </div>
      <div class="modal-alt">
        <button type="button" class="btn ikincil" data-modal-kapat>İptal</button>
        <button type="submit" class="btn turuncu">Kaydet ve Dönemleri Güncelle</button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
var MUKELLEF_ID = <?= (int) $mukellef['id'] ?>, YIL = <?= (int) $yil ?>;
var SIRA = ['BEKLIYOR', 'HAZIR', 'ONAYLANDI', 'VERILMEYECEK'];
var SIMGE = { BEKLIYOR:'○', HAZIR:'◐', ONAYLANDI:'●', VERILMEYECEK:'—' };

// Hücreye tıkla -> bir sonraki duruma geç
function hucreDegistir(td) {
  var mevcut = td.dataset.durum;
  var yeni   = SIRA[(SIRA.indexOf(mevcut) + 1) % SIRA.length];

  BT.post('<?= site_url('takip/durum') ?>', { id: td.dataset.id, durum: yeni })
    .then(function (j) {
      td.dataset.durum = yeni;
      td.className = 'hucre d-' + yeni.toLowerCase();
      var tarih = td.querySelector('.tarih');
      td.innerHTML = SIMGE[yeni] + (tarih ? tarih.outerHTML : '');
      BT.bildir(j.durum_metin + ' olarak işaretlendi.', 'basari');
    })
    .catch(function (e) { BT.bildir(e.message, 'hata'); });
}

// Aylık not
function aylikNot(div) {
  if (div.querySelector('textarea')) return;
  var ay     = div.dataset.ay;
  var span   = div.querySelector('.not-metin');
  var mevcut = span.classList.contains('not-bos') ? '' : span.textContent.trim();

  var ta = document.createElement('textarea');
  ta.className = 'girdi'; ta.value = mevcut; ta.rows = 2;
  ta.style.cssText = 'font-size:12px;padding:4px 7px';
  div.innerHTML = ''; div.appendChild(ta); ta.focus();

  function kaydet() {
    var yeni = ta.value.trim();
    BT.post('<?= site_url('evrak/aylik-not') ?>',
      { mukellef_id: MUKELLEF_ID, yil: YIL, ay: ay, not: yeni })
      .then(function () { BT.bildir('Not kaydedildi.', 'basari'); yaz(yeni); })
      .catch(function (e) { BT.bildir(e.message, 'hata'); yaz(mevcut); });
  }
  function yaz(d) {
    div.classList.toggle('dolu', d !== '');
    div.innerHTML = d !== ''
      ? '<span class="not-metin">' + d.replace(/</g,'&lt;') + '</span>'
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
