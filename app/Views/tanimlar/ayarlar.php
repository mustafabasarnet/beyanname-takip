<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<?php
$a     = array_column($ayarlar, null, 'anahtar');
$deger = static fn ($k, $v = '') => $a[$k]['deger'] ?? $v;
$acik  = static fn ($k, $v = 0) => (int) ($a[$k]['deger'] ?? $v) === 1;

/*
 * Ekranda düzenlenebilen ayarların listesi.
 * Sayfanın sonundaki "Diğer Ayarlar" bölümü, bu listede OLMAYAN ama
 * veritabanında bulunan ayarları otomatik gösterir; böylece ileride
 * eklenen bir ayar arayüzde görünmeden kalmaz.
 */
$bilinen = [
    'cumartesi_tatil', 'pazar_tatil', 'arife_tatil_sayilsin', 'mali_tatil_uygula',
    'otomatik_donem_uret', 'firma_adi', 'uyari_gun_sayisi',
    'evrak_donem_kaydirma', 'evrak_sayfa_adedi',
    'damga_otomatik_ekle', 'bildirim_ucret_varsayilan',
    'gg_istisna_donem', 'karsit_uyari_gun',
    'edefter_aylik_ay_sonra', 'edefter_ucaylik_ay_sonra',
    'edefter_gun_gercek', 'edefter_gun_tuzel',
    'edefter_aralik_gercek_ay', 'edefter_aralik_tuzel_ay',
    'edefter_otomatik_uret', 'edefter_uyari_gun',
];

$digerleri = array_filter(
    $ayarlar,
    static fn ($x) => ! in_array($x['anahtar'], $bilinen, true)
);
?>

<form method="post" action="<?= site_url('tanimlar/ayarlar') ?>">
<?= csrf_field() ?>

<!-- ============ SON TARİH KURALLARI ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>⚙️ Son Tarih Hesaplama Kuralları</h2></div>
  <div class="kart-govde">
    <div class="uyari dikkat"><span class="ik">⚠</span><div>
      Bu ayarları değiştirdikten sonra mevcut kayıtların yeniden hesaplanması için
      <a href="<?= site_url('takip/toplu-uret') ?>"><b>Toplu Dönem Üretimi</b></a> çalıştırmalısınız.
    </div></div>

    <div class="form-grid">
      <div class="form-grup tam">
        <label class="onay"><input type="checkbox" name="ayar[cumartesi_tatil]" value="1" <?= $acik('cumartesi_tatil') ? 'checked' : '' ?>>
          <span><b>Cumartesi iş günü sayılmasın</b><br><span class="yardim">Son gün cumartesiye denk gelirse pazartesiye kayar</span></span></label>
      </div>
      <div class="form-grup tam">
        <label class="onay"><input type="checkbox" name="ayar[pazar_tatil]" value="1" <?= $acik('pazar_tatil') ? 'checked' : '' ?>>
          <span><b>Pazar iş günü sayılmasın</b></span></label>
      </div>
      <div class="form-grup tam">
        <label class="onay"><input type="checkbox" name="ayar[arife_tatil_sayilsin]" value="1" <?= $acik('arife_tatil_sayilsin') ? 'checked' : '' ?>>
          <span><b>Yarım gün arifeler tatil sayılsın</b><br><span class="yardim">Kapalıysa arife günü son gün olarak kabul edilir</span></span></label>
      </div>
      <div class="form-grup tam">
        <label class="onay"><input type="checkbox" name="ayar[mali_tatil_uygula]" value="1" <?= $acik('mali_tatil_uygula') ? 'checked' : '' ?>>
          <span><b>Mali tatil (1-20 Temmuz) uygulansın</b><br><span class="yardim">5604 s. Kanun: bu aralığa denk gelen son günler 27 Temmuz'a uzar</span></span></label>
      </div>
      <div class="form-grup tam">
        <label class="onay"><input type="checkbox" name="ayar[otomatik_donem_uret]" value="1" <?= $acik('otomatik_donem_uret') ? 'checked' : '' ?>>
          <span><b>Mükellef kaydedilince dönemler otomatik üretilsin</b></span></label>
      </div>
    </div>
  </div>
</div>

<!-- ============ EVRAK TAKİP ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>📁 Evrak Takip</h2></div>
  <div class="kart-govde">
    <div class="form-grid">
      <div class="form-grup">
        <label>Dönem Kaydırma (ay)</label>
        <select name="ayar[evrak_donem_kaydirma]" class="girdi">
          <?php
          $kayAcik = (int) $deger('evrak_donem_kaydirma', '1');
          $secenek = [
              0 => '0 — Kaydırma yok (seçilen ay = evrak dönemi)',
              1 => '1 — Ağustos seçilir → Temmuz dönemi gelir (önerilen)',
              2 => '2 — İki ay geri (Ağustos → Haziran)',
              3 => '3 — Üç ay geri',
          ];
          ?>
          <?php foreach ($secenek as $k => $v): ?>
            <option value="<?= $k ?>" <?= $kayAcik === $k ? 'selected' : '' ?>><?= esc($v) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="yardim">
          Evraklar bir ay gecikmeli toplanır. <b>Ağustos'ta Temmuz'un</b> evraklarını
          alıyorsanız <b>1</b> seçin.
        </span>
      </div>

      <div class="form-grup">
        <label>Sayfa Başına Mükellef</label>
        <select name="ayar[evrak_sayfa_adedi]" class="girdi">
          <?php $evAdet = (int) $deger('evrak_sayfa_adedi', '50'); ?>
          <?php foreach ([25, 50, 100, 250] as $s): ?>
            <option value="<?= $s ?>" <?= $evAdet === $s ? 'selected' : '' ?>><?= $s ?> mükellef</option>
          <?php endforeach; ?>
        </select>
        <span class="yardim">Evrak çizelgesinde ilk açılışta yüklenen satır sayısı</span>
      </div>
    </div>
  </div>
</div>

<!-- ============ ÖDEME VE TAHAKKUK ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>💰 Ödeme ve Tahakkuk</h2></div>
  <div class="kart-govde">
    <div class="form-grid">
      <div class="form-grup tam">
        <label class="onay"><input type="checkbox" name="ayar[damga_otomatik_ekle]" value="1" <?= $acik('damga_otomatik_ekle', 1) ? 'checked' : '' ?>>
          <span><b>Damga vergisi ödeme listesine otomatik eklensin</b><br>
          <span class="yardim">
            Kapatırsanız yalnızca girdiğiniz tahakkuk tutarı görünür.
            Tutarlar <a href="<?= site_url('tanimlar/damga') ?>">Damga Vergisi Tutarları</a> ekranından yönetilir.
          </span></span></label>
      </div>

      <div class="form-grup tam">
        <label class="onay"><input type="checkbox" name="ayar[bildirim_ucret_varsayilan]" value="1" <?= $acik('bildirim_ucret_varsayilan') ? 'checked' : '' ?>>
          <span><b>Ödeme bildiriminde muhasebe ücreti varsayılan olarak dahil olsun</b><br>
          <span class="yardim">Kapalıyken bildirim ekranında isterseniz tek tıkla ekleyebilirsiniz</span></span></label>
      </div>
    </div>
  </div>
</div>

<!-- ============ UYARILAR VE İSTİSNALAR ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>🔔 Uyarılar ve İstisnalar</h2></div>
  <div class="kart-govde">
    <div class="form-grid">
      <div class="form-grup">
        <label>Beyanname Uyarı Gün Sayısı</label>
        <input type="number" name="ayar[uyari_gun_sayisi]" class="girdi" min="1" max="30"
               value="<?= esc($deger('uyari_gun_sayisi', '5')) ?>">
        <span class="yardim">Son X gün kala satır "yaklaşıyor" olarak işaretlenir</span>
      </div>

      <div class="form-grup">
        <label>Karşıt İnceleme Uyarı Günü</label>
        <input type="number" name="ayar[karsit_uyari_gun]" class="girdi" min="1" max="60"
               value="<?= esc($deger('karsit_uyari_gun', '7')) ?>">
        <span class="yardim">Tutanak cevabı için son X gün kala uyarı verilir</span>
      </div>

      <div class="form-grup">
        <label>Genç Girişimci İstisna Dönemi</label>
        <input type="number" name="ayar[gg_istisna_donem]" class="girdi" min="1" max="10"
               value="<?= esc($deger('gg_istisna_donem', '3')) ?>">
        <span class="yardim">
          GVK mükerrer 20: kaç vergilendirme dönemi geçerli
          (kanuni değer <b>3</b>, değiştirmeniz önerilmez)
        </span>
      </div>
    </div>
  </div>
</div>

<!-- ============ GENEL ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>🏷️ Genel</h2></div>
  <div class="kart-govde">
    <div class="form-grid">
      <div class="form-grup tam">
        <label>Firma / Büro Adı</label>
        <input type="text" name="ayar[firma_adi]" class="girdi" value="<?= esc($deger('firma_adi')) ?>">
        <span class="yardim">Üst menüde ve yazdırma çıktılarında görünür</span>
      </div>
    </div>
  </div>
</div>

<!-- ============ E-DEFTER ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>📗 E-Defter Berat Ayarları</h2></div>
  <div class="kart-govde">

    <div class="uyari bilgi" style="padding:9px 14px;font-size:13px;margin-bottom:14px">
      <span class="ik">ℹ</span>
      <div>
        Varsayılan değerler <b>GİB e-defter berat yükleme takvimine</b> göre ayarlıdır.
        Berat, dönemi izleyen <b>4.</b> ayda (üç aylıkta <b>3.</b> ayda) yüklenir;
        gün ise gelir vergisi mükellefinde <b>10</b>, diğer mükelleflerde <b>14</b>'tür.
        Aralık dönemleri istisnadır (aşağıda).
      </div>
    </div>

    <div class="form-grid">
      <div class="form-grup">
        <label>Aylık Berat — Kaç Ay Sonra</label>
        <select name="ayar[edefter_aylik_ay_sonra]" class="girdi">
          <?php $edA = (int) $deger('edefter_aylik_ay_sonra', '4'); ?>
          <?php for ($i = 1; $i <= 8; $i++): ?>
            <option value="<?= $i ?>" <?= $edA === $i ? 'selected' : '' ?>><?= $i ?>. ay</option>
          <?php endfor; ?>
        </select>
        <span class="yardim">Varsayılan <b>4</b>: Ocak dönemi → Mayıs ayında yüklenir.</span>
      </div>

      <div class="form-grup">
        <label>Üç Aylık Berat — Kaç Ay Sonra</label>
        <select name="ayar[edefter_ucaylik_ay_sonra]" class="girdi">
          <?php $edU = (int) $deger('edefter_ucaylik_ay_sonra', '3'); ?>
          <?php for ($i = 1; $i <= 8; $i++): ?>
            <option value="<?= $i ?>" <?= $edU === $i ? 'selected' : '' ?>><?= $i ?>. ay</option>
          <?php endfor; ?>
        </select>
        <span class="yardim">Varsayılan <b>3</b>: Oca-Mar dönemi → Haziran ayında yüklenir.</span>
      </div>

      <div class="form-grup">
        <label>Gelir Vergisi Mükellefi — Gün</label>
        <input type="number" name="ayar[edefter_gun_gercek]" class="girdi" min="1" max="31"
               value="<?= esc($deger('edefter_gun_gercek', '10')) ?>">
        <span class="yardim">Gerçek kişi mükelleflerde ayın kaçıncı günü (varsayılan <b>10</b>).</span>
      </div>

      <div class="form-grup">
        <label>Diğer Mükellefler — Gün</label>
        <input type="number" name="ayar[edefter_gun_tuzel]" class="girdi" min="1" max="31"
               value="<?= esc($deger('edefter_gun_tuzel', '14')) ?>">
        <span class="yardim">Kurumlar/tüzel kişilerde ayın kaçıncı günü (varsayılan <b>14</b>).</span>
      </div>
    </div>

    <div style="font-weight:700;margin:16px 0 4px">📅 Aralık Dönemi İstisnası</div>
    <div class="kucuk-yazi" style="margin-bottom:10px">
      Aralık'ta biten dönemlerin beratı, yıllık beyannamenin verileceği ayı
      <b>takip eden ayda</b> yüklenir. Bu kural hem aylık hem üç aylık (4. dönem)
      için geçerlidir.
    </div>

    <div class="form-grid">
      <div class="form-grup">
        <label>Gerçek Kişi — Kaç Ay Sonra</label>
        <select name="ayar[edefter_aralik_gercek_ay]" class="girdi">
          <?php $edAG = (int) $deger('edefter_aralik_gercek_ay', '4'); ?>
          <?php for ($i = 1; $i <= 8; $i++): ?>
            <option value="<?= $i ?>" <?= $edAG === $i ? 'selected' : '' ?>><?= $i ?>. ay</option>
          <?php endfor; ?>
        </select>
        <span class="yardim">Varsayılan <b>4</b>: GV beyanı Mart → berat <b>Nisan</b> ayının 10'u.</span>
      </div>

      <div class="form-grup">
        <label>Tüzel Kişi — Kaç Ay Sonra</label>
        <select name="ayar[edefter_aralik_tuzel_ay]" class="girdi">
          <?php $edAT = (int) $deger('edefter_aralik_tuzel_ay', '5'); ?>
          <?php for ($i = 1; $i <= 8; $i++): ?>
            <option value="<?= $i ?>" <?= $edAT === $i ? 'selected' : '' ?>><?= $i ?>. ay</option>
          <?php endfor; ?>
        </select>
        <span class="yardim">Varsayılan <b>5</b>: Kurumlar beyanı Nisan → berat <b>Mayıs</b> ayının 14'ü.</span>
      </div>

      <div class="form-grup">
        <label>E-Defter Uyarı Gün Sayısı</label>
        <input type="number" name="ayar[edefter_uyari_gun]" class="girdi" min="1" max="60"
               value="<?= esc($deger('edefter_uyari_gun', '10')) ?>">
        <span class="yardim">Berat son tarihine kaç gün kala uyarı verilsin.</span>
      </div>

      <div class="form-grup tam">
        <label class="onay">
          <input type="checkbox" name="ayar[edefter_otomatik_uret]" value="1"
                 <?= $deger('edefter_otomatik_uret', '1') === '1' ? 'checked' : '' ?>>
          <span>Mükellef kaydedilince e-defter dönemleri otomatik üretilsin</span>
        </label>
        <span class="yardim">
          Kapatırsanız dönemleri E-Defter Takip ekranındaki
          <b>🔄 Dönem Üret</b> düğmesiyle elle oluşturursunuz.
        </span>
      </div>
    </div>

    <div class="uyari bilgi" style="margin-top:12px;padding:9px 14px;font-size:13px">
      <span class="ik">ℹ</span>
      <div>
        Bu ayarları değiştirdikten sonra
        <a href="<?= site_url('edefter') ?>">E-Defter Takip</a> ekranından
        <b>🔄 Dönem Üret</b> çalıştırın; mevcut dönemlerin tarihleri güncellenir
        (işaretlediğiniz adımlar korunur).
      </div>
    </div>
  </div>
</div>

<!-- ============ DİĞER (otomatik) ============ -->
<?php if ($digerleri !== []): ?>
<div class="kart">
  <div class="kart-baslik">
    <h2>🔧 Diğer Ayarlar</h2>
    <span class="kucuk-yazi" style="margin-left:auto">
      Bu ayarların özel bir düzenleme alanı yok — değeri doğrudan yazabilirsiniz
    </span>
  </div>
  <div class="kart-govde">
    <div class="form-grid">
      <?php foreach ($digerleri as $x): ?>
        <div class="form-grup">
          <label><code><?= esc($x['anahtar']) ?></code></label>
          <input type="text" name="ayar[<?= esc($x['anahtar'], 'attr') ?>]" class="girdi"
                 value="<?= esc($x['deger']) ?>">
          <?php if (! empty($x['aciklama'])): ?>
            <span class="yardim"><?= esc($x['aciklama']) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="kart">
  <div class="kart-govde">
    <div class="form-alt" style="margin:0">
      <button type="submit" class="btn">💾 Ayarları Kaydet</button>
      <a href="<?= site_url('panel') ?>" class="btn ikincil">İptal</a>
    </div>
  </div>
</div>

</form>
<?= $this->endSection() ?>
