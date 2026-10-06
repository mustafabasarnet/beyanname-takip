<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<form method="post" action="<?= site_url('profil') ?>">
<?= csrf_field() ?>
<div class="kart" style="max-width:640px">
  <div class="kart-baslik"><h2>👤 Profil Bilgilerim</h2></div>
  <div class="kart-govde">
    <div class="form-grid">
      <div class="form-grup tam"><label>Ad Soyad</label>
        <input type="text" name="ad_soyad" class="girdi" value="<?= esc($kullanici['ad_soyad']) ?>" required></div>
      <div class="form-grup"><label>Kullanıcı Adı</label>
        <input type="text" class="girdi" value="<?= esc($kullanici['kullanici_adi']) ?>" disabled></div>
      <div class="form-grup"><label>Telefon</label>
        <input type="text" name="telefon" class="girdi" value="<?= esc($kullanici['telefon'] ?? '') ?>"></div>
    </div>
    <div class="bolucu"></div>
    <h3 style="font-size:14px;margin-bottom:12px">🔒 Şifre Değiştir</h3>
    <div class="form-grid">
      <div class="form-grup tam"><label>Mevcut Şifre</label>
        <input type="password" name="mevcut_sifre" class="girdi" placeholder="Şifre değiştirmiyorsanız boş bırakın"></div>
      <div class="form-grup"><label>Yeni Şifre</label>
        <input type="password" name="yeni_sifre" class="girdi" minlength="6"></div>
      <div class="form-grup"><label>Yeni Şifre (Tekrar)</label>
        <input type="password" name="yeni_sifre_tekrar" class="girdi" minlength="6"></div>
    </div>

    <?php
    /*
     * ------------------------------------------------------------------
     *  GÖRÜNÜM (TEMA) — kullanıcı bazlı renk şablonu
     * ------------------------------------------------------------------
     *  Seçimler "💾 Kaydet" ile profille birlikte kaydedilir. Ayrıca her
     *  seçim ANINDA sayfaya uygulanır (kaydetmeden deneme imkânı).
     *
     *  Bu kart gizli alanları göndermez kuralı: tema alanları burada,
     *  aynı <form> içinde gönderilir; sunucu whitelist doğrular.
     *  Migration koşulmadıysa ($temaHazir=false) kart bilgi mesajı gösterir.
     * ------------------------------------------------------------------
     */
    $t   = $tercih ?? ['tema' => 'sistem', 'palet' => 'mavi', 'yan_menu' => 'koyu'];
    $hz  = (bool) ($temaHazir ?? false);
    ?>
    <div class="bolucu"></div>
    <h3 style="font-size:14px;margin-bottom:4px">🎨 Görünüm</h3>
    <p class="yardim" style="margin-bottom:14px">
      Seçimleriniz yalnız <b>kendi hesabınızı</b> etkiler; diğer kullanıcılar kendi seçimlerini görür.
    </p>

    <?php if (! $hz): ?>
      <div class="uyari dikkat" style="margin-bottom:14px">
        <span class="ik">⚠️</span>
        <div>Görünüm ayarları için veritabanı güncellemesi gerekiyor:
          <code>database/migration_kullanici_tema.sql</code></div>
      </div>
    <?php endif; ?>

    <div class="gorunum-grid<?= $hz ? '' : ' kapali' ?>">
      <!-- Tema modu -->
      <div>
        <label style="display:block;margin-bottom:7px">Tema</label>
        <div class="tema-sec">
          <?php foreach ($temaModlari ?? [] as $k => $etiket): ?>
            <button type="button" class="tema-dugme<?= ($t['tema'] === $k) ? ' secili' : '' ?>"
                    data-deger="<?= esc($k, 'attr') ?>" data-alan="tema">
              <?= $k === 'sistem' ? '🖥️' : ($k === 'karanlik' ? '🌙' : '☀️') ?> <?= esc($etiket) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="tema" id="gizli-tema" value="<?= esc($t['tema'], 'attr') ?>">
      </div>

      <!-- Renk şablonu -->
      <div style="margin-top:16px">
        <label style="display:block;margin-bottom:7px">Renk şablonu</label>
        <div class="palet-sec">
          <?php foreach ($paletler ?? [] as $k => [$etiket, $renk]): ?>
            <button type="button" class="palet-cip<?= ($t['palet'] === $k) ? ' secili' : '' ?>"
                    data-deger="<?= esc($k, 'attr') ?>" data-alan="palet"
                    title="<?= esc($etiket, 'attr') ?>">
              <i style="background:<?= esc($renk, 'attr') ?>"></i><span><?= esc($etiket) ?></span>
            </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="palet" id="gizli-palet" value="<?= esc($t['palet'], 'attr') ?>">
      </div>

      <!-- Yan menü -->
      <div style="margin-top:16px">
        <label style="display:block;margin-bottom:7px">Yan menü</label>
        <div class="tema-sec">
          <?php foreach ($yanMenuler ?? [] as $k => $etiket): ?>
            <button type="button" class="tema-dugme<?= ($t['yan_menu'] === $k) ? ' secili' : '' ?>"
                    data-deger="<?= esc($k, 'attr') ?>" data-alan="yan_menu">
              <?= $k === 'koyu' ? '🌑' : '◻️' ?> <?= esc($etiket) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="yan_menu" id="gizli-yan_menu" value="<?= esc($t['yan_menu'], 'attr') ?>">
      </div>

      <div class="tema-not">
        <span>👁️</span>
        <div>
          Seçtiğiniz anda görünüm <b>hemen</b> değişir. <b>💾 Kaydet</b> demezseniz sayfadan
          çıktığınızda eski hâline döner.
        </div>
      </div>

      <div class="tema-not" style="background:var(--gri-50)">
        <span>🖨️</span>
        <div>Yazdırma çıktıları her zaman <b>açık tema</b> basılır; seçiminiz kâğıda yansımaz.</div>
      </div>
    </div>

    <div class="form-alt"><button type="submit" class="btn">💾 Kaydet</button></div>
  </div>
</div>
</form>

<style>
/* ---------------- GÖRÜNÜM (TEMA) KARTI ---------------- */
.gorunum-grid.kapali{opacity:.5;pointer-events:none}
.tema-sec{display:flex;gap:8px;flex-wrap:wrap}
.tema-dugme{background:var(--yuzey);color:var(--gri-700);border:1px solid var(--cizgi-koyu);
  border-radius:var(--radius-sm);padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;transition:.15s}
.tema-dugme:hover{background:var(--gri-50);color:var(--gri-900);border-color:var(--gri-400)}
.tema-dugme.secili{background:var(--ana);color:#fff;border-color:var(--ana);
  box-shadow:0 3px 9px var(--ana-golge)}
.palet-sec{display:flex;gap:9px;flex-wrap:wrap}
.palet-cip{display:flex;flex-direction:column;align-items:center;gap:5px;cursor:pointer;
  background:var(--yuzey);border:2px solid var(--cizgi);border-radius:10px;
  padding:7px 10px 6px;transition:.15s;min-width:66px}
.palet-cip:hover{border-color:var(--gri-400);transform:translateY(-2px)}
.palet-cip i{display:block;width:38px;height:16px;border-radius:5px}
.palet-cip span{font-size:11px;font-weight:700;color:var(--gri-600)}
.palet-cip.secili{border-color:var(--ana);box-shadow:0 0 0 3px var(--ana-halka)}
.palet-cip.secili span{color:var(--gri-900)}
.tema-not{display:flex;gap:9px;align-items:flex-start;margin-top:14px;padding:10px 12px;
  background:var(--ana-acik);color:var(--ana-metin);border-radius:var(--radius-sm);font-size:12.5px}
.tema-not span{font-size:15px;line-height:1.2}
</style>

<script>
/* ==================================================================
   GÖRÜNÜM KARTI — canlı deneme
   ==================================================================
   • Düğmeye basınca <html data-tema/data-palet/data-yan> ANINDA değişir
     ve gizli alan güncellenir (kaydetmeye hazır).
   • Görünüm tercihi SUNUCUDAN basıldığı için sayfa yenilenince kaydedilmiş
     değer geri gelir (kaydedilmemiş deneme kalıcı olmaz).
   ================================================================== */
(function () {
  var kok = document.documentElement;

  function gizliYaz(alan, deger) {
    var el = document.getElementById('gizli-' + alan);
    if (el) { el.value = deger; }
  }

  document.querySelectorAll('.gorunum-grid [data-alan][data-deger]').forEach(function (b) {
    b.addEventListener('click', function () {
      var alan  = b.getAttribute('data-alan');
      var deger = b.getAttribute('data-deger');

      /* Aynı gruptaki seçimi bırak, bu düğmeyi seç */
      document.querySelectorAll('.gorunum-grid [data-alan="' + alan + '"]').forEach(function (x) {
        x.classList.remove('secili');
      });
      b.classList.add('secili');
      gizliYaz(alan, deger);

      /* Canlı uygula: tema "sistem" ise işletim sistemi tercihi uygulanır */
      if (alan === 'tema') {
        var tema = deger;
        if (tema === 'sistem' && window.matchMedia) {
          tema = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'karanlik' : 'acik';
        }
        kok.setAttribute('data-tema', tema);
      } else if (alan === 'palet') {
        kok.setAttribute('data-palet', deger);
      } else if (alan === 'yan_menu') {
        kok.setAttribute('data-yan', deger);
      }

      /* Üst bardaki hızlı geçiş düğmesinin etiketi de güncellensin */
      if (window.temaHizliEtiketYenile) { window.temaHizliEtiketYenile(); }
    });
  });
}());
</script>
<?= $this->endSection() ?>
