<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<div class="kart">
  <div class="kart-baslik"><h2>📅 <?= ayAdi($ay) ?> <?= $yil ?> Beyanname Takvimi</h2></div>
  <div class="kart-govde"><div id="takvim">Yükleniyor...</div></div>
</div>
<?= $this->endSection() ?>
<?= $this->section('script') ?>
<script>
fetch('<?= site_url('panel/takvim-veri?yil=' . $yil . '&ay=' . $ay) ?>', {credentials:'same-origin'})
  .then(function(r){return r.json()}).then(function(j){
    var gunSayisi = new Date(<?= $yil ?>, <?= $ay ?>, 0).getDate();
    var h = '<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px">';
    var ilk = new Date(<?= $yil ?>, <?= $ay - 1 ?>, 1).getDay();
    ilk = ilk === 0 ? 6 : ilk - 1;
    ['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'].forEach(function(g){
      h += '<div class="kucuk-yazi kalin" style="text-align:center;padding:5px">' + g + '</div>';
    });
    for (var i = 0; i < ilk; i++) h += '<div></div>';
    for (var g = 1; g <= gunSayisi; g++) {
      var liste = j.gunler[g] || [];
      h += '<div style="border:1px solid var(--gri-200);border-radius:8px;padding:7px;min-height:82px">'
         + '<b style="font-size:12px">' + g + '</b>';
      liste.slice(0,4).forEach(function(k){
        h += '<div class="tur-rozet" style="background:' + k.renk + ';display:block;margin-top:3px;font-size:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + k.mukellef + '">' + k.tur + '</div>';
      });
      if (liste.length > 4) h += '<div class="kucuk-yazi">+' + (liste.length-4) + ' daha</div>';
      h += '</div>';
    }
    document.getElementById('takvim').innerHTML = h + '</div>';
  });
</script>
<?= $this->endSection() ?>
