<?php
/*
 * Kişisel Notlar sekmeleri: 📅 Günlük / To-Do  ·  📌 Yapışkan Notlar
 * Aktif sekme, geçerli URL'ye göre belirlenir (ek parametre gerektirmez).
 */
$aktifSekme = str_contains(uri_string(), 'yapiskan') ? 'yapiskan' : 'gunluk';
?>
<nav class="kn-sekme" aria-label="Kişisel not sekmeleri">
  <a href="<?= site_url('kisisel') ?>" class="<?= $aktifSekme === 'gunluk' ? 'aktif' : '' ?>">📅 Günlük / To-Do</a>
  <a href="<?= site_url('kisisel/yapiskan') ?>" class="<?= $aktifSekme === 'yapiskan' ? 'aktif' : '' ?>">📌 Yapışkan Notlar</a>
</nav>
<style>
.kn-sekme{display:flex;gap:4px;border-bottom:1px solid var(--gri-200);margin:0 0 16px;overflow-x:auto}
.kn-sekme a{padding:9px 15px;font-size:13px;font-weight:600;color:var(--gri-500);
  border-bottom:2px solid transparent;white-space:nowrap;text-decoration:none}
.kn-sekme a:hover{color:var(--gri-800)}
.kn-sekme a.aktif{color:var(--ana);border-bottom-color:var(--ana)}
</style>
