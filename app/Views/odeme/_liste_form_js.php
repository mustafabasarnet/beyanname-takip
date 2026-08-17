<script>
function lsSayac() {
  document.getElementById('ls-sayac').textContent =
    document.querySelectorAll('.ls-mk:checked').length;
}

function lsHepsi(sec) {
  document.querySelectorAll('#ls-mukellefler .ls-satir').forEach(function (l) {
    if (l.style.display === 'none') return;      // filtrelenmişleri atla
    l.querySelector('.ls-mk').checked = sec;
  });
  lsSayac();
}

document.getElementById('ls-ara').addEventListener('keyup', function () {
  var q = this.value.toLocaleLowerCase('tr');
  document.querySelectorAll('#ls-mukellefler .ls-satir').forEach(function (l) {
    l.style.display = l.dataset.ad.indexOf(q) > -1 ? '' : 'none';
  });
});

document.querySelectorAll('.ls-mk').forEach(function (c) {
  c.addEventListener('change', lsSayac);
});

/**
 * @param {object} l      Liste kaydı (düzenleme için)
 * @param {array}  secili Seçili mükellef ID'leri
 */
function listeAc(l, secili) {
  l = l || {};
  secili = secili || [];

  document.getElementById('ls-baslik').textContent = l.id ? '📑 Listeyi Düzenle' : '📑 Yeni Ödeme Listesi';
  document.getElementById('ls-id').value       = l.id || '';
  document.getElementById('ls-ad').value       = l.ad || '';
  document.getElementById('ls-yil').value      = l.yil || '';
  document.getElementById('ls-ay').value       = (l.ay === null || l.ay === undefined) ? '' : l.ay;
  document.getElementById('ls-musavir').value  = l.musavir_id || '';
  document.getElementById('ls-aciklama').value = l.aciklama || '';
  document.getElementById('ls-ozel').checked   = l.id ? Number(l.ozel_dahil) === 1 : true;
  document.getElementById('ls-ucret').checked  = Number(l.ucret_dahil) === 1;

  var set = {};
  secili.forEach(function (x) { set[String(x)] = true; });
  document.querySelectorAll('.ls-mk').forEach(function (c) { c.checked = !!set[c.value]; });

  document.getElementById('ls-ara').value = '';
  document.querySelectorAll('#ls-mukellefler .ls-satir').forEach(function (x) { x.style.display = ''; });

  lsSayac();
  BT.modalAc('liste-modal');
}
</script>
