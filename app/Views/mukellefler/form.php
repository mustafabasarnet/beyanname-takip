<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
$duzenleme = $mukellef !== null;
$aksiyon   = $duzenleme ? site_url('mukellefler/guncelle/' . $mukellef['id']) : site_url('mukellefler/kaydet');
$v = static fn ($alan, $vars = null) => esc(old($alan, $vars ?? ''));
?>

<form method="post" action="<?= $aksiyon ?>" id="mukellef-form">
<?= csrf_field() ?>

<!-- ============ KİMLİK BİLGİLERİ ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🏢 <?= $duzenleme ? 'Mükellef Düzenle' : 'Yeni Mükellef' ?></h2>
    <div class="sag"><a href="<?= site_url('mukellefler') ?>" class="btn ikincil kucuk">← Listeye Dön</a></div>
  </div>

  <div class="kart-govde">
    <div class="form-grid">
      <?php
      // Mali müşavir alanı artık HERKESE gösterilir; listede yalnızca
      // kullanıcının yetkili olduğu müşavirler bulunur.
      $secMus = (int) old('musavir_id', $mukellef['musavir_id'] ?? ($varsayilan ?? 0));
      ?>
      <div class="form-grup">
        <label>Mali Müşavir <span class="zorunlu">*</span></label>
        <select name="musavir_id" id="musavir_sec" required <?= count($musavirler) === 1 ? 'data-tek="1"' : '' ?>>
          <?php if (count($musavirler) !== 1): ?>
            <option value="">— Seçiniz —</option>
          <?php endif; ?>
          <?php foreach ($musavirler as $mid => $mad): ?>
            <option value="<?= $mid ?>" <?= $secMus === (int) $mid ? 'selected' : '' ?>>
              <?= esc($mad) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="yardim">Mükellefin bağlı olduğu portföy. Yalnızca yetkili olduğunuz müşavirler listelenir.</span>
      </div>

      <div class="form-grup">
        <label>Takipten Sorumlu Personel</label>
        <select name="sorumlu_kullanici_id">
          <option value="">— Belirtilmedi —</option>
          <?php
          $secSorumlu = (int) old('sorumlu_kullanici_id', $mukellef['sorumlu_kullanici_id'] ?? 0);
          foreach (($personeller ?? []) as $per):
          ?>
            <option value="<?= $per['id'] ?>" <?= $secSorumlu === (int) $per['id'] ? 'selected' : '' ?>>
              <?= esc($per['ad_soyad']) ?>
              (<?= ['admin'=>'Yönetici','musavir'=>'Mali Müşavir','personel'=>'Personel'][$per['rol']] ?? $per['rol'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <span class="yardim">Bu mükellefin beyanname/evrak takibini yapan kişi.</span>
      </div>

      <div class="form-grup">
        <label>Mükellef Kodu</label>
        <input type="text" name="kod" class="girdi" value="<?= $v('kod', $mukellef['kod'] ?? '') ?>" placeholder="Büro içi kod">
      </div>

      <div class="form-grup tam">
        <label>Ünvan / Ad Soyad <span class="zorunlu">*</span></label>
        <input type="text" name="unvan" class="girdi" required value="<?= $v('unvan', $mukellef['unvan'] ?? '') ?>"
               placeholder="Örn: ABC İnşaat Sanayi ve Ticaret Ltd. Şti.">
      </div>

      <div class="form-grup">
        <label>Mükellef Tipi <span class="zorunlu">*</span></label>
        <select name="mukellef_tipi" id="mukellef_tipi" required>
          <?php $mt = old('mukellef_tipi', $mukellef['mukellef_tipi'] ?? 'gercek'); ?>
          <option value="gercek" <?= $mt === 'gercek' ? 'selected' : '' ?>>Gerçek Kişi (Şahıs)</option>
          <option value="tuzel"  <?= $mt === 'tuzel'  ? 'selected' : '' ?>>Tüzel Kişi (Kurum)</option>
        </select>
        <span class="yardim">Tipe göre gelir/kurumlar vergisi seçenekleri otomatik ayarlanır.</span>
      </div>

      <div class="form-grup">
        <label>Defter Tipi</label>
        <select name="defter_tipi">
          <?php
          $dt = old('defter_tipi', $mukellef['defter_tipi'] ?? 'isletme');
          foreach (['isletme' => 'İşletme Defteri', 'bilanco' => 'Bilanço (Yevmiye)',
                    'serbest_meslek' => 'Serbest Meslek Kazanç Defteri',
                    'basit_usul' => 'Basit Usul', 'diger' => 'Diğer'] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $dt === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php
      /*
       * GENÇ GİRİŞİMCİ İSTİSNASI — yalnızca GERÇEK KİŞİ mükelleflerde.
       * GVK mükerrer 20 şirketlere uygulanmaz; tüzel kişi seçiliyken bu
       * bölüm hiç gösterilmez (JS ile anlık, PHP ile ilk yüklemede).
       */
      $ggTuzel = $mt === 'tuzel';
      ?>
      <div class="form-grup tam" id="gg_bolum"
           style="background:var(--yesil-acik);padding:12px 14px;border-radius:10px;border:1px solid #6ee7b7<?= $ggTuzel ? ';display:none' : '' ?>">
        <label class="onay" style="margin-bottom:8px">
          <input type="checkbox" name="genc_girisimci" id="gg_kutu" value="1"
                 <?= ! $ggTuzel && (int) old('genc_girisimci', $mukellef['genc_girisimci'] ?? 0) === 1 ? 'checked' : '' ?>>
          <span><b>🌱 Genç Girişimci Kazanç İstisnası</b>
            <span class="kucuk-yazi">(GVK mükerrer 20)</span></span>
        </label>

        <div id="gg_alanlar" class="form-grid" style="margin-top:4px">
          <div class="form-grup">
            <label>İstisna Başlangıç Yılı</label>
            <select name="gg_baslangic_yili" id="gg_yil">
              <option value="">İşe başlama yılı</option>
              <?php
              $ggSec = (int) old('gg_baslangic_yili', $mukellef['gg_baslangic_yili'] ?? 0);
              for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 8; $y--): ?>
                <option value="<?= $y ?>" <?= $ggSec === $y ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
            <span class="yardim">İstisna bu yıldan itibaren 3 vergilendirme dönemi geçerlidir.</span>
          </div>
          <div class="form-grup">
            <label>Not</label>
            <input type="text" name="gg_not" class="girdi"
                   value="<?= $v('gg_not', $mukellef['gg_not'] ?? '') ?>"
                   placeholder="Örn: 2024/1 dönemden itibaren">
          </div>
          <?php if (! $ggTuzel && ! empty($mukellef['genc_girisimci'])): ?>
            <?php $ggD = gencGirisimciDurum($mukellef); ?>
            <div class="form-grup tam">
              <span class="rozet <?= $ggD['sinif'] ?>">🌱 <?= esc($ggD['metin']) ?></span>
              <?php if ($ggD['baslangic'] !== null): ?>
                <span class="kucuk-yazi">
                  Geçerlilik: <?= $ggD['baslangic'] ?> – <?= $ggD['bitis'] ?>
                </span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php
      /*
       * E-DEFTER BERAT TAKİBİ
       *
       * "Yok" seçiliyse mükellef e-defter listesine hiç girmez. Aylık veya
       * Üç Aylık seçildiğinde dönemler üretilir ve E-Defter Takip ekranında
       * adım adım izlenir.
       */
      $edAlanVar = in_array('edefter_donem', db_connect()->getFieldNames('mukellefler'), true);
      $edSecili  = old('edefter_donem', $mukellef['edefter_donem'] ?? 'YOK');
      ?>
      <?php if ($edAlanVar): ?>
      <div class="form-grup tam" style="background:#f0fdf4;padding:12px 14px;
           border-radius:10px;border:1px solid #86efac">
        <div style="font-weight:700;margin-bottom:4px">📗 E-Defter Berat Takibi</div>
        <div class="kucuk-yazi" style="margin-bottom:10px">
          Dönem seçilirse mükellef <b>E-Defter Takip</b> listesine girer ve
          berat son tarihleri otomatik hesaplanır.
        </div>

        <div class="form-grid">
          <div class="form-grup">
            <label>E-Defter Dönemi</label>
            <select name="edefter_donem" id="ed_donem">
              <?php foreach (['YOK' => '— Yok (e-defter mükellefi değil) —',
                              'AYLIK' => 'Aylık',
                              'UC_AYLIK' => 'Üç Aylık'] as $ek => $ev): ?>
                <option value="<?= $ek ?>" <?= $edSecili === $ek ? 'selected' : '' ?>><?= $ev ?></option>
              <?php endforeach; ?>
            </select>
            <span class="yardim">Beratın hangi sıklıkta yükleneceği.</span>
          </div>

          <div class="form-grup">
            <label>E-Defterden Sorumlu Personel</label>
            <select name="edefter_sorumlu_id" id="ed_sorumlu">
              <option value="">— Belirtilmedi —</option>
              <?php
              $edSor = (int) old('edefter_sorumlu_id', $mukellef['edefter_sorumlu_id'] ?? 0);
              foreach (($personeller ?? []) as $per):
              ?>
                <option value="<?= $per['id'] ?>" <?= $edSor === (int) $per['id'] ? 'selected' : '' ?>>
                  <?= esc($per['ad_soyad']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="yardim">Banka/çek/mizan işini yürüten kişi.</span>
          </div>

          <div class="form-grup">
            <label>E-Defter Takip Başlangıcı</label>
            <input type="date" name="edefter_baslangic" class="girdi" id="ed_baslangic"
                   value="<?= esc(old('edefter_baslangic', $mukellef['edefter_baslangic'] ?? '')) ?>">
            <span class="yardim">Bu tarihten önceki dönemler oluşturulmaz (boş = tümü).</span>
          </div>
        </div>
      </div>

      <script>
      /* "Yok" seçiliyken sorumlu/başlangıç alanları anlamsız — kilitlenir. */
      (function () {
        var d = document.getElementById('ed_donem');
        if (!d) { return; }
        function tazele() {
          var kapali = d.value === 'YOK';
          ['ed_sorumlu', 'ed_baslangic'].forEach(function (id) {
            var e = document.getElementById(id);
            if (e) { e.disabled = kapali; }
          });
        }
        d.addEventListener('change', tazele);
        tazele();
      })();
      </script>
      <?php endif; ?>

      <?php
      /*
       * İNDİRİM / KISITLAMA TAKİBİ
       *
       * Yıllık gelir–kurumlar ve geçici vergi beyannamelerinde mükellefe göre
       * uygulanan kalemler. Buradaki seçim, Beyanname Takip çizelgesinde
       * ilgili beyanname satırlarında rozet olarak görünür.
       *
       * Tanımlar tek yerden (indirimTanimlari) okunur; yeni bir kalem
       * eklenmek istendiğinde bu görünümü değiştirmek gerekmez.
       */
      $indTanim = indirimTanimlari();

      // migration_indirimler.sql çalıştırılmadıysa bölümü hiç gösterme:
      // kullanıcı işaretler ama kaydedilmez, "çalışmıyor" sanılırdı.
      $indAlanVar = in_array('ind_bagkur', db_connect()->getFieldNames('mukellefler'), true);
      ?>
      <?php if ($indAlanVar): ?>
      <div class="form-grup tam" style="background:var(--gri-50,#f8fafc);padding:12px 14px;
           border-radius:10px;border:1px solid var(--gri-200,#e2e8f0)">
        <div style="font-weight:700;margin-bottom:4px">🧾 İndirim ve Kısıtlamalar</div>
        <div class="kucuk-yazi" style="margin-bottom:10px">
          İşaretlediğiniz kalemler, Beyanname Takip çizelgesinde yalnızca
          <b>ilgili beyannamelerde</b> rozet olarak görünür.
        </div>

        <style>
        /* Bu bölümün stili gömülü: stil.css kopyalanmasa bile düzen bozulmasın */
        .ind-satir{display:grid;grid-template-columns:minmax(230px,1fr) 2fr;gap:10px;
          align-items:center;padding:8px 0;border-top:1px solid var(--gri-100,#f1f5f9)}
        .ind-satir:first-of-type{border-top:0}
        .ind-satir .yardim{display:block;margin-top:2px}
        @media(max-width:700px){.ind-satir{grid-template-columns:1fr}}
        </style>

        <?php foreach ($indTanim as $anahtar => $t): ?>
          <?php
          $acik = (int) old($t['alan'], $mukellef[$t['alan']] ?? 0) === 1;
          // Rozetin hangi beyannamelerde çıkacağını kullanıcıya göster
          $turAdlari = implode(', ', array_map(static fn ($k) => [
              'YILLIK_GV'    => 'Yıllık GV',
              'GELIR_GECICI' => 'Gelir Geçici',
              'KURUMLAR'     => 'Kurumlar',
              'KURUM_GECICI' => 'Kurum Geçici',
          ][$k] ?? $k, $t['turler']));
          ?>
          <div class="ind-satir">
            <label class="onay" style="margin:0">
              <input type="checkbox" name="<?= $t['alan'] ?>" value="1"
                     class="ind-kutu" data-hedef="not_<?= $anahtar ?>"
                     <?= $acik ? 'checked' : '' ?>>
              <span>
                <b><?= $t['ikon'] ?> <?= esc($t['ad']) ?></b>
                <span class="yardim">Rozet: <?= esc($turAdlari) ?></span>
              </span>
            </label>
            <div>
              <input type="text" class="girdi" id="not_<?= $anahtar ?>"
                     name="<?= $t['not_alan'] ?>"
                     value="<?= $v($t['not_alan'], $mukellef[$t['not_alan']] ?? '') ?>"
                     maxlength="200"
                     placeholder="Not (isteğe bağlı) — rozetin üzerine gelince görünür"
                     <?= $acik ? '' : 'disabled' ?>>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <script>
      /* İşaretlenmemiş kalemin not kutusu kapalı kalsın — yanlışlıkla
         doldurulup "acaba uygulanıyor mu?" karışıklığı yaşanmasın. */
      document.querySelectorAll('.ind-kutu').forEach(function (kutu) {
        kutu.addEventListener('change', function () {
          var alan = document.getElementById(kutu.dataset.hedef);
          if (!alan) { return; }
          alan.disabled = !kutu.checked;
          if (!kutu.checked) { alan.value = ''; }
        });
      });
      </script>
      <?php endif; ?>

      <div class="form-grup">
        <label>Vergi Kimlik No</label>
        <input type="text" name="vergi_kimlik_no" class="girdi" maxlength="10" inputmode="numeric"
               value="<?= $v('vergi_kimlik_no', $mukellef['vergi_kimlik_no'] ?? '') ?>">
      </div>

      <div class="form-grup">
        <label>TC Kimlik No</label>
        <input type="text" name="tc_kimlik_no" class="girdi" maxlength="11" inputmode="numeric"
               value="<?= $v('tc_kimlik_no', $mukellef['tc_kimlik_no'] ?? '') ?>">
      </div>

      <div class="form-grup">
        <label>Vergi Dairesi</label>
        <input type="text" name="vergi_dairesi" class="girdi" value="<?= $v('vergi_dairesi', $mukellef['vergi_dairesi'] ?? '') ?>">
      </div>

      <div class="form-grup">
        <label>SGK İşyeri Sicil No</label>
        <input type="text" name="sgk_isyeri_sicil" class="girdi" value="<?= $v('sgk_isyeri_sicil', $mukellef['sgk_isyeri_sicil'] ?? '') ?>">
      </div>

      <?php if (! empty($maliYetki)): ?>
      <div class="form-grup">
        <label>Muhasebe Ücreti (Aylık ₺)</label>
        <input type="text" name="muhasebe_ucreti" class="girdi" inputmode="decimal"
               style="text-align:right;font-weight:600"
               value="<?= $mukellef !== null && $mukellef['muhasebe_ucreti'] !== null
                   ? esc(old('muhasebe_ucreti', number_format((float) $mukellef['muhasebe_ucreti'], 2, ',', '.')))
                   : esc(old('muhasebe_ucreti')) ?>"
               placeholder="0,00">
        <span class="yardim">Ödeme bildirimine isteğe bağlı eklenir.</span>
      </div>

      <div class="form-grup">
        <label>Ücret Açıklaması</label>
        <input type="text" name="ucret_aciklama" class="girdi"
               value="<?= $v('ucret_aciklama', $mukellef['ucret_aciklama'] ?? '') ?>"
               placeholder="Örn: Aylık defter tutma ücreti">
      </div>

      <?php
      /*
       * YILLIK SÖZLEŞME ÜCRETİ — Makbuz Takip modülünün hedef tutarı.
       *
       * Aylık muhasebe ücretinden AYRI tutulur: tarife her yıl yeniden
       * açıklandığı için yıl bazında saklanır (mukellef_ucretleri tablosu),
       * böylece geçmiş yılların tutarı bozulmaz.
       */
      $mkYil   = (int) date('Y');
      $mkVar   = db_connect()->tableExists('mukellef_ucretleri');
      $mkTutar = 0.0;

      if ($mkVar && $mukellef !== null) {
          $mkTutar = (new \App\Models\MakbuzModel())->ucretAl((int) $mukellef['id'], $mkYil);
      }
      ?>
      <?php if ($mkVar): ?>
        <div class="form-grup">
          <label><?= $mkYil ?> Yıllık Sözleşme Ücreti (₺)</label>
          <input type="text" name="yillik_ucret" class="girdi" inputmode="decimal"
                 style="text-align:right;font-weight:600"
                 value="<?= esc(old('yillik_ucret', $mkTutar > 0
                     ? number_format($mkTutar, 2, ',', '.') : '')) ?>"
                 placeholder="0,00">
          <span class="yardim">
            <a href="<?= site_url('makbuz?yil=' . $mkYil) ?>">Makbuz Takip</a>
            modülünde hedef tutar olarak kullanılır.
            <?php if ($mukellef !== null): ?>
              Diğer yıllar için
              <a href="<?= site_url('makbuz/detay/' . (int) $mukellef['id']) ?>">döküm ekranına</a> bakın.
            <?php endif; ?>
          </span>
        </div>
      <?php endif; ?>
      <?php endif; ?>

      <div class="form-grup">
        <label>NACE Kodu</label>
        <input type="text" name="nace_kodu" class="girdi" value="<?= $v('nace_kodu', $mukellef['nace_kodu'] ?? '') ?>">
      </div>

      <div class="form-grup tam">
        <label>Faaliyet Konusu</label>
        <input type="text" name="faaliyet_konusu" class="girdi" value="<?= $v('faaliyet_konusu', $mukellef['faaliyet_konusu'] ?? '') ?>">
      </div>
    </div>
  </div>
</div>

<!-- ============ FAALİYET TARİHLERİ (kritik) ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>📅 Faaliyet Tarihleri</h2></div>
  <div class="kart-govde">
    <div class="uyari bilgi">
      <span class="ik">ℹ</span>
      <div>
        <b>Dönem üretimi bu tarihlere göre yapılır.</b><br>
        Beyanname dönemi ile faaliyet aralığı kesişmiyorsa o dönem çizelgede <u>hiç oluşmaz</u>.
        Örneğin 01.03.2026'da başlayıp 31.03.2026'da terk eden mükellefte;
        Mart KDV1/MUHSGK ve 1. dönem geçici vergi oluşur, <b>2. dönem geçici vergi oluşmaz</b>,
        izleyen yıl verilecek yıllık gelir vergisi ise oluşur.<br><br>
        <b>Takip Başlangıcı:</b> Mükellefi eski tarihli olsa da sonradan devraldıysanız bu alanı
        doldurun. Örn. işe başlama 2019 ama takibi 01.03.2026'da devraldınız → yalnızca
        Mart 2026 ve sonrası oluşur, geçmiş dönemler gecikmiş görünmez.
      </div>
    </div>

    <div class="form-grid">
      <div class="form-grup">
        <label>İşe Başlama Tarihi <span class="zorunlu">*</span></label>
        <input type="date" name="ise_baslama_tarihi" class="girdi" required
               value="<?= $v('ise_baslama_tarihi', $mukellef['ise_baslama_tarihi'] ?? '') ?>">
      </div>

      <div class="form-grup">
        <label>Takip Başlangıcı</label>
        <input type="date" name="takip_baslangic" class="girdi"
               value="<?= $v('takip_baslangic', $mukellef['takip_baslangic'] ?? '') ?>">
        <span class="yardim">
          <b>Bu tarihten önceki dönemler oluşturulmaz.</b>
          Mükellefi sonradan devraldıysanız doldurun; geçmiş dönemler
          "gecikmiş" görünmez.
        </span>
      </div>

      <div class="form-grup">
        <label>Terk / Kapanış Tarihi</label>
        <input type="date" name="terk_tarihi" class="girdi"
               value="<?= $v('terk_tarihi', $mukellef['terk_tarihi'] ?? '') ?>">
        <span class="yardim">Boş bırakırsanız faaliyet devam ediyor sayılır.</span>
      </div>

      <div class="form-grup">
        <label>Terk Nedeni</label>
        <input type="text" name="terk_nedeni" class="girdi" value="<?= $v('terk_nedeni', $mukellef['terk_nedeni'] ?? '') ?>"
               placeholder="Örn: Faaliyet terki, devir, tasfiye">
      </div>

      <div class="form-grup">
        <label>Kayıt Durumu</label>
        <select name="aktif">
          <?php $ak = (int) old('aktif', $mukellef['aktif'] ?? 1); ?>
          <option value="1" <?= $ak === 1 ? 'selected' : '' ?>>Aktif</option>
          <option value="0" <?= $ak === 0 ? 'selected' : '' ?>>Pasif (takip dışı)</option>
        </select>
      </div>
    </div>
  </div>
</div>

<!-- ============ BEYANNAME TÜRLERİ ============ -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🗂️ Verilecek Beyannameler</h2>
    <div class="sag kucuk-yazi">Seçtiğiniz türler için dönemler otomatik oluşturulur</div>
  </div>

  <div class="kart-govde">
    <div class="uyari dikkat" id="celiski-uyari" style="display:none">
      <span class="ik">⚠</span><div id="celiski-metin"></div>
    </div>

    <div class="tur-grid" id="beyanname-tur-grid">
      <?php foreach ($turler as $t):
          $secili = in_array((int) $t['id'], array_map('intval', old('turler', $secilenTurler) ?: []), true);
      ?>
        <label class="tur-kutu <?= $secili ? 'secili' : '' ?>"
               data-id="<?= $t['id'] ?>" data-kod="<?= esc($t['kod']) ?>"
               data-tip="<?= esc($t['mukellef_tipi']) ?>"
               data-celisen="<?= esc($t['celisen_kodlar'] ?? '') ?>">
          <div class="ust">
            <input type="checkbox" name="turler[]" value="<?= $t['id'] ?>" <?= $secili ? 'checked' : '' ?>>
            <b><?= esc($t['ad']) ?></b>
          </div>
          <div class="aciklama">
            <span class="rozet gri" style="font-size:10px"><?= periyotAdi($t['periyot']) ?></span>
            <?= esc($t['aciklama']) ?>
          </div>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
/*
 * TAKİP EDİLMEYEN EVRAK TÜRLERİ
 *
 * Bankası olmayan, çek/senet kullanmayan ya da personeli bulunmayan
 * mükelleflerde ilgili hücre her ay kırmızı "eksik" görünüyordu. Burada
 * işaretlenen türler Aylık Evrak Takibi çizelgesinde TARALI GRİ (takip
 * dışı) çıkar, sayaçlara ve tamamlanma yüzdesine girmez.
 *
 * Ayar KALICIDIR (tüm aylar). Tek bir ay için istisna gerekiyorsa
 * çizelgede hücreye sağ tıklanır.
 *
 * migration_evrak_muafiyet.sql çalıştırılmamışsa bölüm hiç görünmez;
 * program eski davranışıyla çalışmaya devam eder.
 */
$evTurler   = $evrakTurleri   ?? [];
$evMuaf     = array_map('intval', $muafEvrakTurleri ?? []);
$evMuafNot  = $muafEvrakNotlari ?? [];
$evEskiSec  = old('evrak_muaf');
$evSecili   = $evEskiSec !== null ? array_map('intval', (array) $evEskiSec) : $evMuaf;
?>
<?php if ($evTurler !== []): ?>
<div class="kart">
  <div class="kart-baslik">
    <h2>📥 Takip Edilmeyen Evrak Türleri</h2>
    <div class="sag kucuk-yazi">İşaretlenenler evrak çizelgesinde pasif görünür, eksik sayılmaz</div>
  </div>

  <div class="kart-govde">
    <?php /* Bölümün gerçekten gösterildiğini sunucuya bildirir; yoksa
             kaydetme sırasında mevcut muafiyetlere dokunulmaz. */ ?>
    <input type="hidden" name="evrak_muaf_gonderildi" value="1">

    <div class="uyari bilgi" style="margin-bottom:12px">
      <span class="ik">ℹ</span>
      <div>
        Mükellefte <b>hiç bulunmayan</b> evrak türlerini işaretleyin
        (örn. banka hesabı yok, çek/senet kullanmıyor, personeli yok).
        Bu hücreler <b>Aylık Evrak Takibi</b> ekranında kırmızı yerine
        <b>taralı gri</b> görünür ve tamamlanma yüzdesine katılmaz.
        <span class="kucuk-yazi">
          Yalnızca bir ay için istisna gerekiyorsa çizelgede hücreye sağ tıklayın.
        </span>
      </div>
    </div>

    <div class="tur-grid" id="evrak-muaf-grid">
      <?php foreach ($evTurler as $et):
          $etId  = (int) $et['id'];
          $etSec = in_array($etId, $evSecili, true);
          $etNot = (string) old('evrak_muaf_not.' . $etId, $evMuafNot[$etId] ?? '');
      ?>
        <label class="tur-kutu <?= $etSec ? 'secili' : '' ?>" data-evrak-muaf="<?= $etId ?>">
          <div class="ust">
            <input type="checkbox" name="evrak_muaf[]" value="<?= $etId ?>" <?= $etSec ? 'checked' : '' ?>>
            <b><?= esc($et['ad']) ?></b>
          </div>
          <div class="aciklama">
            <input type="text" class="girdi" name="evrak_muaf_not[<?= $etId ?>]"
                   value="<?= esc($etNot) ?>" maxlength="200"
                   placeholder="Neden? (isteğe bağlı)"
                   style="padding:4px 8px;font-size:11px;margin-top:2px"
                   onclick="event.preventDefault();this.focus()">
          </div>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
/* Kutu işaretlendikçe görsel durum güncellensin; not alanı tıklanınca
   etiketin kutuyu tersine çevirmesi engellenir (yukarıda onclick ile). */
(function () {
  var izgara = document.getElementById('evrak-muaf-grid');
  if (!izgara) { return; }

  izgara.querySelectorAll('[data-evrak-muaf] input[type=checkbox]').forEach(function (k) {
    k.addEventListener('change', function () {
      k.closest('.tur-kutu').classList.toggle('secili', k.checked);
    });
  });
})();
</script>
<?php endif; ?>

<!-- ============ İLETİŞİM ============ -->
<div class="kart">
  <div class="kart-baslik"><h2>📞 İletişim ve Notlar</h2></div>
  <div class="kart-govde">
    <div class="form-grid">
      <div class="form-grup">
        <label>Yetkili Kişi</label>
        <input type="text" name="yetkili_kisi" class="girdi" value="<?= $v('yetkili_kisi', $mukellef['yetkili_kisi'] ?? '') ?>">
      </div>
      <div class="form-grup">
        <label>Telefon</label>
        <input type="text" name="telefon" class="girdi" value="<?= $v('telefon', $mukellef['telefon'] ?? '') ?>">
      </div>
      <div class="form-grup">
        <label>E-posta</label>
        <input type="email" name="eposta" class="girdi" value="<?= $v('eposta', $mukellef['eposta'] ?? '') ?>">
      </div>
      <div class="form-grup tam">
        <label>Adres</label>
        <textarea name="adres" rows="2"><?= $v('adres', $mukellef['adres'] ?? '') ?></textarea>
      </div>
      <div class="form-grup tam">
        <label>Notlar</label>
        <textarea name="notlar" rows="3"><?= $v('notlar', $mukellef['notlar'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="form-alt">
      <button type="submit" class="btn">💾 <?= $duzenleme ? 'Güncelle ve Dönemleri Yenile' : 'Kaydet ve Dönemleri Oluştur' ?></button>
      <a href="<?= site_url('mukellefler') ?>" class="btn ikincil">İptal</a>
    </div>
  </div>
</div>

</form>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
// Genç girişimci alanlarını aç/kapa
(function () {
  var k = document.getElementById('gg_kutu');
  var a = document.getElementById('gg_alanlar');
  if (!k || !a) return;
  function uygula() {
    a.style.opacity = k.checked ? '1' : '.45';
    a.querySelectorAll('input,select').forEach(function (i) { i.disabled = !k.checked; });
  }
  k.addEventListener('change', uygula);
  uygula();
})();

// Genç girişimci istisnası şirketlere uygulanmaz (GVK mükerrer 20).
// Mükellef tipi "Tüzel" seçilince bölüm gizlenir ve işaret temizlenir;
// böylece yanlışlıkla tüzel kişide istisna kaydedilemez.
(function () {
  var tip   = document.getElementById('mukellef_tipi');
  var bolum = document.getElementById('gg_bolum');
  var kutu  = document.getElementById('gg_kutu');
  if (!tip || !bolum) return;

  function ggTazele() {
    var tuzel = tip.value === 'tuzel';
    bolum.style.display = tuzel ? 'none' : '';

    if (tuzel && kutu) {
      kutu.checked = false;
      kutu.dispatchEvent(new Event('change'));
    }
  }

  tip.addEventListener('change', ggTazele);
  ggTazele();
})();

<?php /*
  ÖNEMLİ: aşağıdaki seçici yalnızca BEYANNAME ızgarasıyla sınırlıdır.
  Sayfada aynı '.tur-kutu' sınıfını kullanan başka bir bölüm daha var
  (takip edilmeyen evrak türleri); genel seçici onları da mükellef
  tipine göre kilitliyor, kutuları soluklaştırıyordu.
*/ ?>
(function () {
  var izgara   = document.getElementById('beyanname-tur-grid');
  var kutular  = izgara
    ? Array.prototype.slice.call(izgara.querySelectorAll('.tur-kutu'))
    : [];
  var tipSelect = document.getElementById('mukellef_tipi');

  function kodBul(kod) {
    return kutular.filter(function (k) { return k.dataset.kod === kod; })[0];
  }

  // ---------------------------------------------------------------
  // Çelişki kuralı:
  //  Gelir Vergisi seçilince  -> Kurumlar + Kurum Geçici PASİF
  //  Kurumlar seçilince       -> Gelir Vergisi + Gelir Geçici PASİF
  //  KDV1 Aylık <-> KDV1 3 Aylık, MUHSGK Aylık <-> MUHSGK 3 Aylık
  // ---------------------------------------------------------------
  function celiskiUygula() {
    var mesajlar = [];

    // Önce tüm kilitleri kaldır
    kutular.forEach(function (k) {
      k.classList.remove('pasif');
      k.querySelector('input').disabled = false;
      var kilit = k.querySelector('.kilit');
      if (kilit) kilit.remove();
    });

    // Mükellef tipine göre uygunsuz türleri kapat
    var tip = tipSelect ? tipSelect.value : 'gercek';
    kutular.forEach(function (k) {
      var izin = k.dataset.tip; // hepsi | gercek | tuzel
      if (izin !== 'hepsi' && izin !== tip) {
        kilitle(k, izin === 'tuzel' ? 'Kurum' : 'Şahıs');
      }
    });

    // Seçili türlerin çelişenlerini kapat
    kutular.forEach(function (k) {
      var inp = k.querySelector('input');
      if (!inp.checked || inp.disabled) return;

      (k.dataset.celisen || '').split(',').forEach(function (kod) {
        kod = kod.trim();
        if (!kod) return;
        var hedef = kodBul(kod);
        if (!hedef || hedef === k) return;

        var hInp = hedef.querySelector('input');
        if (hInp.checked) {
          hInp.checked = false;
          hedef.classList.remove('secili');
          mesajlar.push(hedef.querySelector('b').textContent + ' otomatik kaldırıldı.');
        }
        kilitle(hedef, 'Pasif');
      });
    });

    var uyari = document.getElementById('celiski-uyari');
    if (mesajlar.length) {
      document.getElementById('celiski-metin').innerHTML =
        '<b>Çakışan seçimler düzeltildi:</b> ' + mesajlar.join(' ');
      uyari.style.display = 'flex';
    } else {
      uyari.style.display = 'none';
    }
  }

  function kilitle(kutu, etiket) {
    var inp = kutu.querySelector('input');
    if (inp.checked) return;          // seçiliyse kilitleme (kullanıcı bilinçli seçmiş)
    inp.checked = false;
    inp.disabled = true;
    kutu.classList.add('pasif');
    kutu.classList.remove('secili');
    if (!kutu.querySelector('.kilit')) {
      var s = document.createElement('span');
      s.className = 'kilit';
      s.textContent = etiket;
      kutu.appendChild(s);
    }
  }

  kutular.forEach(function (k) {
    var inp = k.querySelector('input');
    inp.addEventListener('change', function () {
      k.classList.toggle('secili', inp.checked);
      celiskiUygula();
    });
  });

  if (tipSelect) tipSelect.addEventListener('change', celiskiUygula);

  celiskiUygula();

  // Terk tarihi < işe başlama kontrolü
  document.getElementById('mukellef-form').addEventListener('submit', function (e) {
    var bas = document.querySelector('[name=ise_baslama_tarihi]').value;
    var terk = document.querySelector('[name=terk_tarihi]').value;
    if (bas && terk && terk < bas) {
      e.preventDefault();
      alert('Terk tarihi, işe başlama tarihinden önce olamaz.');
    }
    // disabled input'lar POST edilmez; sorun değil çünkü zaten seçili değiller
  });
})();
</script>
<?= $this->endSection() ?>
