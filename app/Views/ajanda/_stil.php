<style>
/* ===================== AJANDA — ORTAK STİLLER =====================
   Stiller gömülüdür: stil.css kopyalanmasa da ekran doğru görünür. */

/* Sayaç şeridi */
.aj-sayac{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.aj-sayac a{flex:1;min-width:130px;text-decoration:none;border:1px solid var(--gri-200,#e2e8f0);
  border-radius:10px;padding:10px 13px;background:var(--yuzey);transition:.15s;display:block}
.aj-sayac a:hover{border-color:var(--ana,#2563eb);transform:translateY(-1px)}
.aj-sayac .et{font-size:10.5px;text-transform:uppercase;letter-spacing:.3px;
  color:var(--gri-500,#64748b);font-weight:700}
.aj-sayac .dg{font-size:22px;font-weight:800;line-height:1.15;margin-top:2px}
.aj-sayac .kirmizi .dg{color:var(--kirmizi-koyu)}
.aj-sayac .turuncu .dg{color:var(--turuncu-koyu)}
.aj-sayac .mavi .dg{color:var(--ana-uzeri)}
.aj-sayac .yesil .dg{color:var(--yesil-metin)}
.aj-sayac a.kirmizi{background:var(--kirmizi-acik);border-color:var(--kirmizi-kenar)}
.aj-sayac a.turuncu{background:var(--turuncu-acik);border-color:var(--turuncu-kenar)}

/* Liste tablosu */
.aj-tablo{width:100%;border-collapse:collapse}
.aj-tablo th{font-size:10.5px;text-transform:uppercase;letter-spacing:.3px;
  color:var(--gri-500,#64748b);font-weight:700;padding:8px 9px;text-align:left;
  border-bottom:1px solid var(--gri-200,#e2e8f0);white-space:nowrap}
.aj-tablo th.orta{text-align:center}
.aj-tablo td{padding:9px;border-bottom:1px solid var(--gri-100,#f1f5f9);
  font-size:13px;vertical-align:top}
.aj-tablo td.orta{text-align:center}
.aj-tablo tbody tr:hover{background:var(--gri-50,#f8fafc)}
.aj-tablo tr.gecikmis td{background:var(--kirmizi-acik)}
.aj-tablo tr.bugun td{background:var(--ana-acik)}
.aj-tablo tr.kapali td{opacity:.55}
.aj-tablo tr.kapali .aj-baslik{text-decoration:line-through}

/* Sol renk şeridi (öncelik) */
.aj-serit{display:inline-block;width:4px;height:100%;min-height:26px;border-radius:99px;
  vertical-align:middle;margin-right:8px}

.aj-baslik{font-weight:700;color:var(--gri-900,#0f172a);text-decoration:none}
.aj-baslik:hover{color:var(--ana,#2563eb)}
.aj-alt{font-size:11.5px;color:var(--gri-500,#64748b);margin-top:3px;line-height:1.45}
.aj-tarih{white-space:nowrap;font-variant-numeric:tabular-nums;font-weight:600}
.aj-saat{font-size:11px;color:var(--gri-500,#64748b);display:block}

/* Rozetler */
.aj-rozet{display:inline-block;padding:2px 8px;border-radius:99px;
  font-size:10.5px;font-weight:700;white-space:nowrap}
.aj-rozet.dusuk{background:var(--gri-100);color:var(--gri-600)}
.aj-rozet.normal{background:var(--ana-acik);color:var(--ana-metin)}
.aj-rozet.yuksek{background:#ffedd5;color:var(--sari-metin-koyu)}
.aj-rozet.acil{background:var(--kirmizi-acik);color:var(--kirmizi-metin)}
.aj-rozet.g-kisisel{background:#f3e8ff;color:#6b21a8}
.aj-rozet.g-genel{background:#dcfce7;color:#166534}
.aj-rozet.g-gorev{background:var(--sari-acik);color:#92400e}
.aj-rozet.g-musavir{background:#e0e7ff;color:#3730a3}
.aj-rozet.d-BEKLIYOR{background:var(--ana-acik);color:var(--ana-metin)}
.aj-rozet.d-YAPILDI{background:#dcfce7;color:#166534}
.aj-rozet.d-IPTAL{background:var(--gri-200);color:var(--gri-600)}
.aj-rozet.gec{background:var(--kirmizi);color:#fff}
.aj-rozet.etiket{background:var(--gri-100);color:var(--gri-700)}

.aj-islem{display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap}

/* Takvim ızgarası */
.aj-takvim{width:100%;border-collapse:collapse;table-layout:fixed}
.aj-takvim th{background:var(--gri-50,#f8fafc);padding:8px 4px;font-size:11px;
  text-transform:uppercase;letter-spacing:.3px;color:var(--gri-600,#475569);
  border:1px solid var(--gri-200,#e2e8f0);text-align:center;font-weight:700}
.aj-takvim td{border:1px solid var(--gri-200,#e2e8f0);vertical-align:top;
  height:104px;padding:4px;position:relative}
.aj-takvim td.bos{background:var(--gri-50,#f8fafc)}
.aj-takvim td.bugun{background:var(--ana-acik);box-shadow:inset 0 0 0 2px var(--ana,#2563eb)}
.aj-takvim td.hafta-sonu{background:#fcfcfd}
.aj-gun-no{font-size:12px;font-weight:700;color:var(--gri-600,#475569);
  display:flex;align-items:center;justify-content:space-between}
.aj-gun-ekle{opacity:0;text-decoration:none;font-size:14px;color:var(--ana,#2563eb);
  transition:.12s;padding:0 3px;line-height:1}
.aj-takvim td:hover .aj-gun-ekle{opacity:1}
.aj-olay{display:block;margin-top:3px;padding:3px 6px;border-radius:5px;
  font-size:11px;line-height:1.3;text-decoration:none;color:#fff;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.aj-olay:hover{filter:brightness(.92);color:#fff}
.aj-olay.kapali{opacity:.5;text-decoration:line-through}
.aj-olay .s{font-weight:700;margin-right:3px}
.aj-daha{display:block;font-size:10.5px;color:var(--gri-500,#64748b);
  margin-top:3px;text-decoration:none}
.aj-daha:hover{color:var(--ana,#2563eb)}

/* Takvim başlık şeridi */
.aj-ay-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.aj-ay-bar h2{margin:0;font-size:17px}

/* Form */
.aj-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px}
.aj-form-grid .tam{grid-column:1/-1}
.aj-yardim{font-size:11.5px;color:var(--gri-500,#64748b);line-height:1.45}
.aj-kutu{border:1px solid var(--gri-200,#e2e8f0);border-radius:9px;
  padding:12px 14px;background:var(--gri-50,#f8fafc)}
.aj-kutu h4{margin:0 0 10px;font-size:12px;text-transform:uppercase;
  letter-spacing:.3px;color:var(--gri-600,#475569)}
.aj-renk-sec{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.aj-renk-sec label{cursor:pointer}
.aj-renk-sec input{position:absolute;opacity:0;width:0;height:0}
.aj-renk-yuvarlak{display:inline-block;width:24px;height:24px;border-radius:50%;
  border:2px solid transparent;transition:.15s}
.aj-renk-sec input:checked + .aj-renk-yuvarlak{border-color:var(--gri-900,#0f172a);
  transform:scale(1.14)}

/* Detay */
.aj-detay-ust{display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.aj-bilgi-satir{display:flex;padding:8px 0;border-bottom:1px solid var(--gri-100,#f1f5f9);
  font-size:13.5px;gap:12px}
.aj-bilgi-satir .et{width:150px;flex:0 0 150px;color:var(--gri-500,#64748b);font-weight:600}
.aj-bilgi-satir .dg{flex:1}
.aj-ek-liste{list-style:none;padding:0;margin:0}
.aj-ek-liste li{display:flex;align-items:center;gap:10px;padding:7px 0;
  border-bottom:1px solid var(--gri-100,#f1f5f9);font-size:13px}
.aj-ek-liste .boyut{color:var(--gri-500,#64748b);font-size:11.5px;margin-left:auto}

/* Giriş uyarı penceresi */
.aj-uyari-ort{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9998;
  display:flex;align-items:center;justify-content:center;padding:20px}
.aj-uyari{background:var(--yuzey);border-radius:14px;max-width:560px;width:100%;
  max-height:80vh;overflow:auto;box-shadow:0 20px 50px rgba(0,0,0,.3)}
.aj-uyari-bas{padding:16px 20px;border-bottom:1px solid var(--gri-200,#e2e8f0);
  display:flex;align-items:center;gap:10px}
.aj-uyari-bas h3{margin:0;font-size:16px}
.aj-uyari-govde{padding:8px 20px 16px}
.aj-uyari-alt{padding:12px 20px;border-top:1px solid var(--gri-200,#e2e8f0);
  display:flex;gap:10px;justify-content:flex-end;background:var(--gri-50,#f8fafc);
  border-radius:0 0 14px 14px}
.aj-uyari-is{display:flex;align-items:center;gap:10px;padding:9px 0;
  border-bottom:1px solid var(--gri-100,#f1f5f9)}
.aj-uyari-is:last-child{border-bottom:0}
.aj-uyari-nokta{width:10px;height:10px;border-radius:50%;flex:0 0 10px}
.aj-uyari-is .ad{flex:1;font-size:13.5px}
.aj-uyari-is .ad small{display:block;color:var(--gri-500,#64748b);font-size:11.5px}

/* Menü rozeti */
.menu-rozet{display:inline-block;min-width:18px;padding:1px 6px;border-radius:99px;
  background:var(--kirmizi);color:#fff;font-size:10.5px;font-weight:700;
  text-align:center;margin-left:auto}

@media(max-width:760px){
  .aj-takvim td{height:auto;min-height:64px}
  .aj-bilgi-satir{flex-direction:column;gap:2px}
  .aj-bilgi-satir .et{width:auto;flex:none}
}
</style>
