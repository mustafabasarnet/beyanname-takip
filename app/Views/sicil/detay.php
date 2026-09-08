<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
$deg     = $degisiklik;              // detay satırı (+ todolar)
$todolar = $deg['todolar'] ?? [];
$toplam  = count($todolar);          // listedeki tüm todo (takip dışı dahil)
$tamam   = 0;
$acik    = 0;
$gerek   = 0;

foreach ($todolar as $t) {
    if ($t['durum'] === 'TAMAM') {
        $tamam++;
    } elseif (in_array($t['durum'], ['BEKLIYOR', 'HAZIR', 'GONDERILDI'], true)) {
        $acik++;
    } else {
        $gerek++;                    // takip dışı — ilerlemeye katılmaz
    }
}
$hedef = $tamam + $acik;             // değerlendirilen todo sayısı
$oran  = $hedef > 0 ? (int) round($tamam / $hedef * 100) : ($gerek > 0 ? 100 : 0);
$ilerlemeMetin = $hedef > 0 ? $tamam . '/' . $hedef . ' tamamlandı'
                 : ($gerek > 0 ? 'Tümü takip dışı' : 'Todo tanımlı değil');
$altParcalar = [];
if ($hedef > 0) { $altParcalar[] = $acik . ' açık'; }
if ($gerek > 0) { $altParcalar[] = $gerek . ' takip dışı'; }

// Evraklar todo bazında gruplu (kanıt dosyaları)
$evrakMap = [];
foreach ($belgeler ?? [] as $b) {
    $evrakMap[(int) ($b['gorev_id'] ?? 0)][] = $b;
}
$degDurumRozet = match ($deg['durum']) {
    'TAMAM'   => 'yesil',
    'ISLEMDE' => 'sari',
    default   => 'gri',
};
$rol = $aktifKullanici['rol'] ?? 'personel';
?>

<style>
.todo-satir{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-bottom:1px solid var(--gri-100,#f1f5f9);
  transition:background .2s}
.todo-satir:last-child{border-bottom:none}
.todo-satir.arka-kirmizi{background:var(--kirmizi-acik,#fee2e2)}
.todo-satir.arka-turuncu{background:var(--turuncu-acik,#ffedd5)}
.todo-satir.arka-sari{background:var(--sari-acik,#fef9c3)}
.todo-satir.arka-yesil{background:var(--yesil-acik,#d1fae5)}
.todo-satir.arka-gri{background:var(--gri-100,#f1f5f9)}
.todo-kutu{margin-top:2px;flex:0 0 auto}
.todo-kutu input{width:20px;height:20px;accent-color:#059669;cursor:pointer}
.todo-ad{font-size:14px;font-weight:600;color:var(--gri-900)}
.todo-ad.yapildi{color:#047857;text-decoration:line-through}
.todo-ayrinti{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:3px;font-size:12px;color:var(--gri-500)}
.todo-islem{margin-left:auto;display:flex;gap:6px;align-items:center;flex:0 0 auto}
.todo-evrak{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:6px}
.evrak-cip{display:inline-flex;align-items:center;gap:4px;background:#fff;
  border:1px solid var(--gri-200,#e2e8f0);border-radius:8px;padding:2px 4px 2px 8px;font-size:11.5px}
.evrak-cip a{color:var(--gri-700,#334155);text-decoration:none;max-width:260px;overflow:hidden;
  text-overflow:ellipsis;white-space:nowrap}
.evrak-cip a:hover{color:var(--ana,#1d4ed8)}
.evrak-sil{border:none;background:none;color:var(--gri-400,#94a3b8);cursor:pointer;font-size:14px;
  line-height:1;padding:0 4px}
.evrak-sil:hover{color:var(--kirmizi,#dc2626)}
.evrak-ekle{display:inline-flex;align-items:center;gap:4px;cursor:pointer;
  color:var(--gri-600,#475569);border:1px dashed var(--gri-300,#cbd5e1);border-radius:8px;
  padding:3px 9px;font-size:11.5px;font-weight:600;user-select:none}
.evrak-ekle:hover{border-color:var(--ana,#1d4ed8);color:var(--ana,#1d4ed8)}
</style>

<div id="sicil-bildirim" style="margin-bottom:10px"></div>

<!-- ÖZET -->
<div class="kart">
  <div class="kart-baslik">
    <h2>🧾 <?= esc($deg['tur_ad']) ?></h2>
    <div class="sag">
      <?php if ($rol === 'admin'): ?>
        <a href="<?= site_url('sicil/sil/' . (int) $deg['id']) ?>" class="btn kirmizi mini"
           data-onay="Bu işlem ve todo geçmişi listeden kaldırılsın mı?">🗑 Sil</a>
      <?php endif; ?>
      <a href="<?= site_url('sicil/duzenle/' . (int) $deg['id']) ?>" class="btn ikincil mini">✏️ Düzenle</a>
      <a href="<?= site_url('sicil') ?>" class="btn ikincil mini">← Listeye Dön</a>
    </div>
  </div>
  <div class="kart-govde">
    <div class="bilgi-liste mb16">
      <div class="oge"><div class="et">Mükellef</div>
        <div class="dg">
          <a href="<?= site_url('mukellefler/detay/' . (int) $deg['mukellef_id']) ?>"><?= esc($deg['mukellef_unvan']) ?></a>
          <span class="kucuk-yazi"> • <?= esc(($deg['vergi_kimlik_no'] ?: $deg['tc_kimlik_no']) ?: '') ?></span>
        </div></div>
      <div class="oge"><div class="et">Şablon</div>
        <div class="dg"><span class="rozet mavi"><?= esc($deg['tur_ad']) ?></span></div></div>
      <div class="oge"><div class="et">İşlem Tarihi</div><div class="dg kalin"><?= trTarih($deg['degisiklik_tarihi']) ?></div></div>
      <div class="oge"><div class="et">Durum</div>
        <div class="dg"><span class="rozet <?= $degDurumRozet ?>" id="deg-durum-rozet"><?= esc(\App\Models\SicilDegisiklikModel::DURUMLAR[$deg['durum']] ?? $deg['durum']) ?></span></div></div>
      <div class="oge"><div class="et">Ekleyen</div><div class="dg"><?= esc($deg['kaydeden_adi'] ?: '—') ?></div></div>
    </div>

    <?php if (! empty($deg['aciklama'])): ?>
      <div class="uyari bilgi" style="margin-bottom:12px"><span class="ik">📌</span>
        <div style="white-space:pre-wrap"><?= nl2br(esc($deg['aciklama'])) ?></div></div>
    <?php endif; ?>

    <!-- İlerleme (takip dışı todolar paydaya girmez) -->
    <div>
      <div class="satir arali" style="justify-content:space-between">
        <b id="ilerleme-metin"><?= $ilerlemeMetin ?></b>
        <span class="kucuk-yazi" id="ilerleme-alt"><?= implode(' · ', $altParcalar) ?></span>
      </div>
      <div class="progress">
        <div class="dolu" id="ilerleme-bar" style="width:<?= $oran ?>%"></div>
      </div>
      <?php if ($hedef === 0 && $toplam > 0): ?>
        <div class="kucuk-yazi" style="margin-top:6px;color:var(--gri-500,#64748b)">
          ⊘ Bu işlemdeki tüm todolar takip dışı bırakıldı.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- TODO LİSTESİ -->
<div class="kart" style="margin-top:14px">
  <div class="kart-baslik">
    <h2>📋 Todo Listesi (<?= $toplam ?>)</h2>
    <div class="sag kucuk-yazi">
      <span class="rozet yesil">☑ Yapıldı</span>
      <span class="rozet sari">⚠ Yaklaşıyor</span>
      <span class="rozet kirmizi">✖ Geçti</span>
    </div>
  </div>
  <div class="kart-govde sikisik">
    <?php if ($todolar === []): ?>
      <div class="tablo-bos"><span class="ikon">📭</span>
        Bu işlem için todo üretilmedi.
        <div class="mt8 kucuk-yazi">Şablonun aktif todo tanımı yok veya kayıt eski sürümle açılmış.</div>
      </div>
    <?php else: ?>
      <?php foreach ($todolar as $t): ?>
        <?php
        $etik = todoKalanEtiketi($t['son_tarih'], (string) $t['durum']);
        $tamMi = $t['durum'] === 'TAMAM';
        $gereksizMi = $t['durum'] === 'GEREKSIZ';
        ?>
        <div class="todo-satir arka-<?= $etik['arka'] ?>" data-id="<?= (int) $t['id'] ?>" id="todo-<?= (int) $t['id'] ?>">
          <div class="todo-kutu">
            <input type="checkbox" class="todo-cb" data-id="<?= (int) $t['id'] ?>"
                   <?= $tamMi ? 'checked' : '' ?> title="<?= $tamMi ? 'Geri aç' : 'Tamamla' ?>">
          </div>
          <div style="flex:1;min-width:0">
            <div class="todo-ad <?= $tamMi ? 'yapildi' : '' ?>" data-rol="ad"><?= esc($t['ad']) ?></div>
            <div class="todo-ayrinti">
              <span>Son Tarih: <b><?= $t['son_tarih'] ? trTarih($t['son_tarih']) : '—' ?></b></span>
              <?php if (! empty($t['kaydirma_nedeni']) && ! $tamMi): ?><span class="kucuk-yazi" title="Tatil kaydırması">↷</span><?php endif; ?>
              <span class="rozet <?= $etik['sinif'] ?: 'gri' ?>" data-rol="etik"><?= esc($etik['metin']) ?></span>
              <?php if ($tamMi): ?>
                <span class="kucuk-yazi" data-rol="yapan">
                  ✓ <?= esc($t['yapan_adi'] ?: '—') ?><?= $t['tamamlanma_tarihi'] ? ' · ' . date('d.m.Y H:i', strtotime($t['tamamlanma_tarihi'])) : '' ?>
                </span>
              <?php endif; ?>
            </div>
            <?php $todoBelgeler = $evrakMap[(int) $t['id']] ?? []; ?>
            <div class="todo-evrak" data-evrak-kap="<?= (int) $t['id'] ?>">
              <?php foreach ($todoBelgeler as $b): ?>
                <span class="evrak-cip" data-evrak-cip="<?= (int) $b['id'] ?>">
                  <a href="<?= site_url('sicil/evrak-indir/' . (int) $b['id']) ?>" title="<?= esc($b['dosya_adi']) ?>">📎 <?= esc(kisalt($b['dosya_adi'], 40)) ?></a>
                  <button type="button" class="evrak-sil" data-id="<?= (int) $b['id'] ?>" title="Evrakı sil">×</button>
                </span>
              <?php endforeach; ?>
              <label class="evrak-ekle" title="Evrak yükle (görevin tamamlandığına dair kanıt dosyası)">
                + 📎 Evrak
                <input type="file" class="evrak-dosya" data-gorev="<?= (int) $t['id'] ?>" hidden
                       accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.xlsx,.xls,.csv,.docx,.doc,.txt,.zip">
              </label>
            </div>
          </div>
          <div class="todo-islem">
            <?php if ($tamMi || $gereksizMi): ?>
              <button type="button" class="btn ikincil mini" data-rol="ac"
                      data-islem="BEKLIYOR" title="Todo'yu geri aç">↩ Geri al</button>
            <?php else: ?>
              <button type="button" class="btn ikincil mini" data-rol="ac"
                      data-islem="GEREKSIZ" title="Takip dışı bırak (geçmiş korunur)">⊘ Takip dışı</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var kapi = document.getElementById('ilerleme-bar');

  function rozetSinif(d) {
    return d === 'TAMAM' ? 'yesil' : (d === 'ISLEMDE' ? 'sari' : 'gri');
  }

  function satirGuncelle(satir, j) {
    var etik = j.etik || { arka: '', sinif: '', metin: '' };
    var tamMi = j.yeni_durum === 'TAMAM';
    var gereksizMi = j.yeni_durum === 'GEREKSIZ';

    // zemin
    satir.className = 'todo-satir arka-' + (etik.arka || '');
    // checkbox
    var cb = satir.querySelector('.todo-cb');
    cb.checked = tamMi;
    // ad (tamamda çizili)
    var ad = satir.querySelector('[data-rol="ad"]');
    ad.classList.toggle('yapildi', tamMi);
    // etiket
    var et = satir.querySelector('[data-rol="etik"]');
    et.className = 'rozet ' + (etik.sinif || 'gri');
    et.textContent = etik.metin;
    // yapan
    var yapan = satir.querySelector('[data-rol="yapan"]');
    if (tamMi) {
      if (!yapan) {
        yapan = document.createElement('span');
        yapan.className = 'kucuk-yazi';
        yapan.setAttribute('data-rol', 'yapan');
        satir.querySelector('.todo-ayrinti').appendChild(yapan);
      }
      yapan.textContent = '✓ ' + (j.yapan_adi || '—') + (j.tamamlanma_tarihi ? ' · ' + j.tamamlanma_tarihi : '');
    } else if (yapan) {
      yapan.remove();
    }
    // işlem butonu
    var btnKap = satir.querySelector('.todo-islem');
    btnKap.innerHTML = '';
    if (tamMi || gereksizMi) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'btn ikincil mini'; b.setAttribute('data-rol', 'ac');
      b.setAttribute('data-islem', 'BEKLIYOR'); b.title = "Todo'yu geri aç";
      b.textContent = '↩ Geri al';
      b.addEventListener('click', function () { istekGonder(satir, 'BEKLIYOR'); });
      btnKap.appendChild(b);
    } else {
      var b2 = document.createElement('button');
      b2.type = 'button'; b2.className = 'btn ikincil mini'; b2.setAttribute('data-rol', 'ac');
      b2.setAttribute('data-islem', 'GEREKSIZ'); b2.title = 'Takip dışı bırak';
      b2.textContent = '⊘ Takip dışı';
      b2.addEventListener('click', function () { istekGonder(satir, 'GEREKSIZ'); });
      btnKap.appendChild(b2);
    }
    // işlem ilerlemesi (takip dışı paydaya girmez) + üst durum
    var tm = parseInt(j.tamam || 0, 10);
    var tp = parseInt(j.toplam || 0, 10);   // değerlendirilen todo (GEREKSIZ hariç)
    var ac = parseInt(j.acik || 0, 10);
    var gk = parseInt(j.gerek || 0, 10);
    var alt = [];
    if (tp > 0) { alt.push(ac + ' açık'); }
    if (gk > 0) { alt.push(gk + ' takip dışı'); }
    var metin;
    if (tp > 0) { metin = tm + '/' + tp + ' tamamlandı'; }
    else if (gk > 0) { metin = 'Tümü takip dışı'; }
    else { metin = 'Todo tanımlı değil'; }
    document.getElementById('ilerleme-metin').textContent = metin;
    document.getElementById('ilerleme-alt').textContent = alt.join(' · ');
    var oran = tp > 0 ? Math.round(tm / tp * 100) : (gk > 0 ? 100 : 0);
    if (kapi) { kapi.style.width = oran + '%'; }
    var rozet = document.getElementById('deg-durum-rozet');
    rozet.className = 'rozet ' + rozetSinif(j.deg_durum);
    rozet.textContent = j.deg_durum_metin || j.deg_durum;
    // Evrak sil butonları durum geçişinde kilitli kalmasın
    satir.querySelectorAll('.evrak-sil').forEach(function (s) { s.disabled = false; });
    BT.bildir(j.mesaj || 'Durum güncellendi.', j.yeni_durum === 'TAMAM' ? 'basari' : 'bilgi');
  }

  function istekGonder(satir, durum) {
    var id = satir.getAttribute('data-id');
    var btnler = satir.querySelectorAll('button'); btnler.forEach(function (b) { b.disabled = true; });
    BT.post('<?= site_url('sicil/todo-durum') ?>', { id: id, durum: durum })
      .then(function (j) {
        if (!j.durum) throw new Error(j.mesaj || 'Güncellenemedi.');
        satirGuncelle(satir, j);
      })
      .catch(function (e) {
        btnler.forEach(function (b) { b.disabled = false; });
        BT.bildir(e.message, 'hata');
      });
  }

  // Checkbox değişimi
  document.querySelectorAll('.todo-cb').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var satir = document.getElementById('todo-' + cb.getAttribute('data-id'));
      istekGonder(satir, cb.checked ? 'TAMAM' : 'BEKLIYOR');
    });
  });

  // "Geri al / Takip dışı" butonları
  document.querySelectorAll('[data-rol="ac"]').forEach(function (b) {
    b.addEventListener('click', function () {
      var satir = b.closest('.todo-satir');
      istekGonder(satir, b.getAttribute('data-islem'));
    });
  });

  // ---------- TODO EVRAKLARI ----------
  // Yükleme: etiket (label) tıklanınca gizli dosya kutusu açılır
  document.querySelectorAll('.evrak-dosya').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var gorev = inp.getAttribute('data-gorev');
      var dosya = inp.files && inp.files[0];
      if (!dosya) { return; }
      var kap = document.querySelector('[data-evrak-kap="' + gorev + '"]');
      var ekle = kap ? kap.querySelector('.evrak-ekle') : null;
      if (ekle) { ekle.style.pointerEvents = 'none'; ekle.style.opacity = '.55'; }
      BT.post('<?= site_url('sicil/evrak-yukle') ?>', { gorev_id: gorev, dosya: dosya })
        .then(function (j) {
          if (!j.durum) throw new Error(j.mesaj || 'Yükleme başarısız.');
          if (kap) {
            var c = document.createElement('span');
            c.className = 'evrak-cip';
            c.setAttribute('data-evrak-cip', j.id);
            var a = document.createElement('a');
            a.href = j.indir; a.title = j.dosya_adi;
            a.textContent = '📎 ' + j.dosya_adi;
            var s = document.createElement('button');
            s.type = 'button'; s.className = 'evrak-sil';
            s.setAttribute('data-id', j.id); s.title = 'Evrakı sil';
            s.textContent = '×';
            c.appendChild(a); c.appendChild(s);
            kap.insertBefore(c, ekle);
          }
          BT.bildir('Evrak yüklendi.', 'basari');
        })
        .catch(function (e) { BT.bildir(e.message, 'hata'); })
        .finally(function () {
          inp.value = '';
          if (ekle) { ekle.style.pointerEvents = ''; ekle.style.opacity = ''; }
        });
    });
  });

  // Silme (chips sonradan eklendiği için delegasyon)
  document.addEventListener('click', function (e) {
    var s = e.target.closest('.evrak-sil');
    if (!s || s.disabled) { return; }
    var id = s.getAttribute('data-id');
    if (!confirm('Bu evrak silinsin mi?')) { return; }
    var cip = s.closest('[data-evrak-cip]');
    s.disabled = true;
    BT.post('<?= site_url('sicil/evrak-sil') ?>/' + id, {})
      .then(function (j) {
        if (!j.durum) throw new Error(j.mesaj || 'Silinemedi.');
        if (cip) { cip.remove(); }
        BT.bildir('Evrak silindi.', 'basari');
      })
      .catch(function (err) {
        s.disabled = false;
        BT.bildir(err.message, 'hata');
      });
  });
})();
</script>

<?= $this->endSection() ?>
