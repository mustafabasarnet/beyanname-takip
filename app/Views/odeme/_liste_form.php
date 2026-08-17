<div class="modal-arka" id="liste-modal">
  <div class="modal genis">
    <form method="post" action="<?= site_url('odeme/liste-kaydet') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="ls-id">
      <div class="modal-baslik">
        <h3 id="ls-baslik">📑 Ödeme Listesi</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
      </div>

      <div class="modal-govde">
        <div class="form-grid">
          <div class="form-grup tam">
            <label>Liste Adı <span class="zorunlu">*</span></label>
            <input type="text" name="ad" id="ls-ad" class="girdi" required
                   placeholder="Örn: Mustafa Başar Mükellefleri">
            <span class="yardim">
              Dönem adı yazmayın — liste kalıcıdır, dönemi açarken seçersiniz.
            </span>
          </div>

          <div class="form-grup">
            <label>Varsayılan Yıl</label>
            <select name="yil" id="ls-yil">
              <option value="">İçinde bulunulan yıl</option>
              <?php foreach (yilSecenekleri(3, 1) as $y): ?>
                <option value="<?= $y ?>"><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-grup">
            <label>Varsayılan Ay</label>
            <select name="ay" id="ls-ay">
              <option value="">İçinde bulunulan ay</option>
              <option value="0">Tüm Yıl</option>
              <?php for ($a = 1; $a <= 12; $a++): ?>
                <option value="<?= $a ?>"><?= ayAdi($a) ?></option>
              <?php endfor; ?>
            </select>
            <span class="yardim">Liste açılırken ön seçili gelir; istediğinizde değiştirirsiniz.</span>
          </div>

          <div class="form-grup">
            <label>Mali Müşavir (başlıkta görünür)</label>
            <select name="musavir_id" id="ls-musavir">
              <option value="">— Belirtilmedi —</option>
              <?php foreach ($musavirler as $mid => $mad): ?>
                <option value="<?= $mid ?>"><?= esc($mad) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-grup">
            <label>Açıklama</label>
            <input type="text" name="aciklama" id="ls-aciklama" class="girdi">
          </div>

          <div class="form-grup tam">
            <label class="onay">
              <input type="checkbox" name="ozel_dahil" id="ls-ozel" value="1" checked>
              <span>Özel ödeme kalemlerim dahil edilsin <span class="kucuk-yazi">(Bağkur, MTV vb.)</span></span>
            </label>
          </div>
          <div class="form-grup tam">
            <label class="onay">
              <input type="checkbox" name="ucret_dahil" id="ls-ucret" value="1">
              <span>Muhasebe ücreti dahil edilsin</span>
            </label>
          </div>
        </div>

        <div class="bolucu"></div>

        <div class="satir arali mb8">
          <b style="font-size:14px">Listeye Dahil Mükellefler</b>
          <div class="satir">
            <input type="text" id="ls-ara" class="girdi" placeholder="Mükellef ara..." style="width:190px;padding:5px 10px">
            <button type="button" class="btn ikincil mini" onclick="lsHepsi(true)">Tümünü Seç</button>
            <button type="button" class="btn ikincil mini" onclick="lsHepsi(false)">Temizle</button>
            <span class="rozet mavi"><span id="ls-sayac">0</span> seçili</span>
          </div>
        </div>

        <div id="ls-mukellefler"
             style="max-height:320px;overflow:auto;border:1px solid var(--gri-200);border-radius:8px;padding:8px">
          <?php foreach ($mukellefler as $mid => $mad): ?>
            <label class="onay ls-satir" style="padding:5px 6px;border-radius:6px" data-ad="<?= esc(mb_strtolower($mad, 'UTF-8')) ?>">
              <input type="checkbox" name="mukellefler[]" value="<?= $mid ?>" class="ls-mk">
              <span><?= esc($mad) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if ($mukellefler === []): ?>
            <div class="kucuk-yazi">Erişebildiğiniz aktif mükellef bulunamadı.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="modal-alt">
        <button type="button" class="btn ikincil" data-modal-kapat>İptal</button>
        <button type="submit" class="btn">💾 Kaydet</button>
      </div>
    </form>
  </div>
</div>
