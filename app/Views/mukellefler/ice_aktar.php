<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="kart mb16">
  <div class="kart-baslik">
    <h2>📥 Excel’den Mükellef Aktarma</h2>
    <a href="<?= site_url('mukellefler') ?>" class="btn ikincil kucuk" style="margin-left:auto">← Mükellef Listesi</a>
  </div>

  <div class="kart-govde">

    <!-- ---------- ADIMLAR ---------- -->
    <div class="adim-grid mb16">
      <div class="adim">
        <div class="adim-no">1</div>
        <div>
          <b>Şablonu indirin</b>
          <p class="kucuk-yazi">Sütun sırası sabittir, değiştirmeyin.</p>
          <div class="btn-grup mt8">
            <a href="<?= site_url('mukellefler/sablon-indir') ?>" class="btn yesil kucuk">
              ⬇ Örnekli Şablon
            </a>
            <a href="<?= site_url('mukellefler/sablon-indir?bos=1') ?>" class="btn ikincil kucuk">
              ⬇ Boş Şablon
            </a>
          </div>
        </div>
      </div>

      <div class="adim">
        <div class="adim-no">2</div>
        <div>
          <b>Excel’de doldurun</b>
          <p class="kucuk-yazi">
            Örnek satırları silin, kendi mükelleflerinizi yazın.
            Sütun eklemeyin/silmeyin.
          </p>
        </div>
      </div>

      <div class="adim">
        <div class="adim-no">3</div>
        <div>
          <b>CSV olarak kaydedin</b>
          <p class="kucuk-yazi">
            Excel → <b>Dosya → Farklı Kaydet</b> →
            <b>CSV (Ayırıcı sınırlı) (*.csv)</b>
          </p>
        </div>
      </div>

      <div class="adim">
        <div class="adim-no">4</div>
        <div>
          <b>Yükleyin ve kontrol edin</b>
          <p class="kucuk-yazi">
            Önce <b>önizleme</b> gelir. Onaylamadan hiçbir kayıt oluşmaz.
          </p>
        </div>
      </div>
    </div>

    <!-- ---------- YÜKLEME FORMU ---------- -->
    <form method="post" action="<?= site_url('mukellefler/ice-aktar/onizle') ?>"
          enctype="multipart/form-data" id="aktar-form">
      <?= csrf_field() ?>

      <div class="form-grid">
        <div class="form-grup">
          <label>CSV Dosyası <span class="zorunlu">*</span></label>
          <input type="file" name="dosya" id="dosya" class="girdi" accept=".csv,text/csv" required>
          <span class="ipucu">Yalnızca .csv — en fazla 2 MB</span>
        </div>

        <div class="form-grup">
          <label>Mali Müşavir <span class="zorunlu">*</span></label>
          <select name="musavir_id" class="girdi" required>
            <?php foreach ($musavirler as $mid => $mad): ?>
              <option value="<?= $mid ?>" <?= (int) ($varsayilan ?? 0) === (int) $mid ? 'selected' : '' ?>>
                <?= esc($mad) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="ipucu">Dosyadaki tüm mükellefler bu müşavire bağlanır</span>
        </div>
      </div>

      <div class="uyari bilgi mt16">
        <span class="ik">ℹ</span>
        <div>
          <b>Aynı VKN/TCKN sistemde zaten varsa o satır atlanır</b> — mevcut kaydınız
          değiştirilmez. Önizlemede hangi satırların atlanacağını göreceksiniz.
        </div>
      </div>

      <div class="btn-grup mt16">
        <button type="submit" class="btn">🔍 Yükle ve Önizle</button>
        <a href="<?= site_url('mukellefler') ?>" class="btn ikincil">İptal</a>
      </div>
    </form>
  </div>
</div>

<!-- ---------- SÜTUN AÇIKLAMALARI ---------- -->
<div class="kart">
  <div class="kart-baslik">
    <h2>📋 Şablon Sütunları</h2>
    <span class="kucuk-yazi" style="margin-left:auto">
      <span class="zorunlu">*</span> işaretliler zorunludur
    </span>
  </div>

  <div class="tablo-sarmal">
    <table class="tablo">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Sütun</th>
          <th>Ne yazılır?</th>
          <th>Örnek</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $aciklama = [
            'kod'                => ['Büro içi mükellef kodunuz (isteğe bağlı)', 'M001'],
            'unvan'              => ['Mükellefin tam ünvanı / adı soyadı', 'ÖRNEK İNŞAAT LTD. ŞTİ.'],
            'mukellef_tipi'      => ['Gerçek / Tüzel. Boşsa kimlik numarasından anlaşılır', 'Tüzel'],
            'vergi_kimlik_no'    => ['10 haneli VKN (tüzel kişiler)', '1234567890'],
            'tc_kimlik_no'       => ['11 haneli TCKN (gerçek kişiler)', '12345678901'],
            'vergi_dairesi'      => ['Bağlı olduğu vergi dairesi', 'Nevşehir'],
            'defter_tipi'        => ['İşletme / Bilanço / Serbest Meslek / Basit Usul / Diğer', 'Bilanço'],
            'ise_baslama_tarihi' => ['İşe başlama tarihi — <b>gg.aa.yyyy</b>', '01.01.2020'],
            'takip_baslangic'    => ['Bu tarihten önceki dönemler üretilmez (devraldıysanız)', '01.01.2026'],
            'terk_tarihi'        => ['Terk/kapanış tarihi — yoksa boş bırakın', '31.12.2026'],
            'beyannameler'       => ['Virgülle ayrılmış <b>kodlar</b> (aşağıdaki listeye bakın)', 'KDV1_A,MUHSGK_A,KURUMLAR'],
            'genc_girisimci'     => ['Evet / Hayır', 'Evet'],
            'gg_baslangic_yili'  => ['İstisnanın başladığı yıl (Genç Girişimci = Evet ise)', '2024'],
            'muhasebe_ucreti'    => ['Aylık ücret — 5.000,00 veya 5000', '5.000,00'],
            'telefon'            => ['Telefon numarası', '0384 000 00 00'],
            'eposta'             => ['E-posta adresi', 'ornek@firma.com'],
            'yetkili_kisi'       => ['İrtibat kurulan kişi', 'Ahmet Yılmaz'],
            'faaliyet_konusu'    => ['Ne iş yapıyor', 'İnşaat'],
            'nace_kodu'          => ['NACE faaliyet kodu', '4120'],
            'sgk_isyeri_sicil'   => ['SGK işyeri sicil numarası', '1234567890123'],
            'adres'              => ['Açık adres', 'Merkez / Nevşehir'],
            'notlar'             => ['Serbest not', 'Genç girişimci teşviki var'],
        ];
        $sira = 0;
        ?>
        <?php foreach ($sutunlar as $alan => $baslik): $sira++; ?>
          <tr>
            <td class="kucuk-yazi metin-gri"><?= $sira ?></td>
            <td>
              <b><?= esc($baslik) ?></b>
              <?php if (in_array($alan, $zorunlu, true)): ?>
                <span class="zorunlu">*</span>
              <?php endif; ?>
            </td>
            <td class="kucuk-yazi"><?= $aciklama[$alan][0] ?? '' ?></td>
            <td class="kucuk-yazi"><code><?= esc($aciklama[$alan][1] ?? '') ?></code></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ---------- BEYANNAME KODLARI ---------- -->
<div class="kart mt16">
  <div class="kart-baslik">
    <h2>🏷️ Beyanname Kodları</h2>
    <span class="kucuk-yazi" style="margin-left:auto">
      “Beyannameler” sütununa bu kodları virgülle ayırarak yazın
    </span>
  </div>

  <div class="kart-govde">
    <div class="uyari bilgi mb16">
      <span class="ik">💡</span>
      <div>
        <b>Sık kullanılan hazır setler</b> — kopyalayıp yapıştırabilirsiniz:
        <div class="mt8">
          <div class="kod-satir">
            <b>Bilanço / Kurum:</b>
            <code>KDV1_A,MUHSGK_A,KURUMLAR,KURUM_GECICI</code>
          </div>
          <div class="kod-satir">
            <b>İşletme / Şahıs:</b>
            <code>KDV1_A,MUHSGK_A,YILLIK_GV,GELIR_GECICI</code>
          </div>
          <div class="kod-satir">
            <b>Serbest Meslek:</b>
            <code>KDV1_A,MUHSGK_A,YILLIK_GV,GELIR_GECICI</code>
          </div>
          <div class="kod-satir">
            <b>Basit Usul:</b>
            <code>YILLIK_GV</code>
          </div>
        </div>
      </div>
    </div>

    <div class="tur-grid">
      <?php foreach ($turler as $t): ?>
        <div class="tur-kutu" style="cursor:default">
          <div class="ust">
            <span class="tur-rozet" style="background:<?= esc($t['renk']) ?>"><?= esc($t['kisa_ad']) ?></span>
            <code style="margin-left:auto;font-weight:700"><?= esc($t['kod']) ?></code>
          </div>
          <div class="aciklama" style="padding-left:0">
            <?= esc($t['ad']) ?>
            <?php if ($t['mukellef_tipi'] !== 'hepsi'): ?>
              <br><span class="rozet <?= $t['mukellef_tipi'] === 'tuzel' ? 'mor' : 'sari' ?>"
                        style="font-size:10px">
                yalnızca <?= $t['mukellef_tipi'] === 'tuzel' ? 'tüzel' : 'gerçek' ?> kişi
              </span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<style>
.adim-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}
.adim{display:flex;gap:12px;padding:14px;background:var(--gri-50);
      border:1px solid var(--gri-200);border-radius:var(--radius-sm)}
.adim-no{flex:0 0 28px;height:28px;border-radius:50%;background:var(--ana);color:#fff;
         display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px}
.adim p{margin:3px 0 0}
.kod-satir{margin-top:5px}
.kod-satir code{background:#fff;padding:2px 7px;border-radius:5px;
                border:1px solid var(--gri-300);font-size:11.5px}
code{font-family:ui-monospace,Menlo,Consolas,monospace}
</style>

<?= $this->endSection() ?>
