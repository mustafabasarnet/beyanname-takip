#!/usr/bin/env python3
# =====================================================================
#  GELİR VERGİSİ — TARAYICI TESTİ (gerçek yazma / gerçek AJAX)
#
#  Sunucu tarafını gelir_vergisi_http_testi.sh doğruluyor. Bu test ise
#  kullanıcı gider kutusuna YAZDIĞINDA sonucun kaydetmeden, canlı olarak
#  güncellendiğini gerçek tarayıcıda ölçer.
#
#  Ön koşul:
#    - uygulama  http://127.0.0.1:8099
#    - chromium  --headless --remote-debugging-port=9222 --remote-allow-origins=*
#    - veri      bash tests/gelir_vergisi_http_testi.sh en az bir kez çalışmış
#  Kullanım:  python3 tests/tarayici_gelir_vergisi.py
# =====================================================================
import json
import time
import urllib.request

import websocket

B = 'http://127.0.0.1:8099'
g = 0
k = 0


def ol(baslik, beklenen, bulunan):
    global g, k
    if str(beklenen) == str(bulunan):
        print(f'  [OK] {baslik}')
        g += 1
    else:
        print(f'  [HATA] {baslik} (bekl:{beklenen} ger:{bulunan})')
        k += 1


def sekme():
    for t in json.load(urllib.request.urlopen('http://127.0.0.1:9222/json/list')):
        if t.get('type') == 'page':
            return t['webSocketDebuggerUrl']
    raise SystemExit('Sekme bulunamadı')


ws = websocket.create_connection(sekme(), origin='http://127.0.0.1:9222',
                                 suppress_origin=True, timeout=45)
_id = [0]


def cdp(yontem, **p):
    _id[0] += 1
    ws.send(json.dumps({'id': _id[0], 'method': yontem, 'params': p}))
    while True:
        m = json.loads(ws.recv())
        if m.get('id') == _id[0]:
            return m.get('result', {})


def js(ifade):
    r = cdp('Runtime.evaluate', expression=ifade, returnByValue=True, awaitPromise=True)
    return r.get('result', {}).get('value')


def git(url, bekle=2.2):
    cdp('Page.enable')
    cdp('Page.navigate', url=url)
    time.sleep(bekle)


def metin(eid):
    return js(f"(document.getElementById('{eid}')||{{}}).textContent?.trim()||'YOK'")


def yaz(eid, deger):
    """Gerçek kullanıcı gibi yazar: input olayı tetiklenir (AJAX bu olaya bağlı)."""
    js(f"""(()=>{{
      const e=document.getElementById('{eid}');
      e.focus(); e.value={json.dumps(deger)};
      e.dispatchEvent(new Event('input',{{bubbles:true}}));
      return 1}})()""")


print('=== GİRİŞ ===')
git(B + '/giris')
js("""(()=>{const f=document.querySelector('form');
  f.querySelector('[name=kimlik]').value='admin';
  f.querySelector('[name=sifre]').value='Test1234';
  f.submit();return 1})()""")
time.sleep(2.5)
ol('admin girişi yapıldı', True, 'panel' in (js('location.pathname') or ''))

# =====================================================================
print('\n=== 1) HESAP EKRANI YÜKLENİYOR ===')
# =====================================================================
git(B + '/gelir-vergisi/detay/1?yil=2026', 2.6)

ol('sayfa başlığında müşavir adı', True, 'Ali Yılmaz' in (js('document.body.innerText') or ''))
ol('gider kutusu var', True, js("!!document.getElementById('gv-gider')"))
ol('hesap dökümü tablosu var', True, js("!!document.getElementById('c-matrah')"))
ol('tarife tablosu görünür', True, js("document.querySelectorAll('.gv-dilimler tr').length > 3"))
ol('aylık dağılım şeridi 12 ay', 12, js("document.querySelectorAll('.gv-ay').length"))

# Hasılat makbuzlardan otomatik
ol('hasılat makbuzlardan geldi', '550.000,00', metin('c-hasilat'))

# HTTP testi elle stopaj bırakmış olabilir; boşaltıp makbuz değerini ölçüyoruz.
yaz('gv-stopaj', '')
time.sleep(1.6)
ol('stopaj makbuzlardan geldi', '110.000,00', metin('c-stopaj'))

# =====================================================================
print('\n=== 2) CANLI HESAP — GİDER YAZINCA GÜNCELLENİYOR ===')
# =====================================================================
# Hasılat 550.000 − gider 300.000 = kazanç 250.000
# NOT: bu test HTTP testinden sonra çalışır; o test sigorta/eğitim değerleri
# ve ELLE STOPAJ bırakır. Zinciri saf ölçmek için hepsi sıfırlanır —
# stopaj kutusu boşalınca makbuzlardan gelen 110.000 devreye girer.
yaz('gv-bagkur', '0')
yaz('gv-sigorta', '0')
yaz('gv-egitim', '0')
yaz('gv-stopaj', '')
yaz('gv-gider', '300.000')
time.sleep(1.6)   # 400 ms gecikme + ağ

ol('gider canlı yansıdı', '300.000,00', metin('c-gider'))
ol('kazanç 250.000', '250.000,00', metin('c-kazanc'))
ol('matrah 250.000', '250.000,00', metin('c-matrah'))
# vergi = 28.500 + (250.000−190.000)×%20 = 40.500
ol('vergi 40.500 (2. dilim)', '40.500,00', metin('c-vergi'))
ol('sonuç 69.500 iade (110.000−40.500)', '69.500,00', metin('c-sonuc-tutar'))
ol('etiket İADE oldu', True, 'İADE ALACAKSINIZ' in (metin('gv-sonuc-etiket') or ''))
ol('iade satırı yeşil sınıf aldı', True,
   js("document.getElementById('gv-sonuc-satir').classList.contains('iade')"))
ol('dilim notu 2. dilim gösteriyor', True, '2. dilim' in (metin('gv-dilim-not') or ''))

# --- Türkçe binlik ayırıcı: "1.000.000" bir milyon olmalı ------------
# GERÇEK KUSURDU: eski ayrıştırıcı bunu 1,00 TL okuyordu.
yaz('gv-gider', '1.000.000')
time.sleep(1.6)
ol("'1.000.000' bir milyon olarak okundu", '1.000.000,00', metin('c-gider'))
ol('gider hasılatı aşınca matrah 0', '0,00', metin('c-matrah'))
ol('zararda vergi 0', '0,00', metin('c-vergi'))
ol('zararda stopajın tamamı iade', '110.000,00', metin('c-sonuc-tutar'))

# =====================================================================
print('\n=== 3) ÖDENECEK DURUMU VE %5 İNDİRİM ===')
# =====================================================================
yaz('gv-hasilat', '3.000.000')     # elle hasılat
yaz('gv-stopaj', '200.000')        # elle stopaj
yaz('gv-sigorta', '0')
yaz('gv-egitim', '0')
yaz('gv-gider', '500.000')
time.sleep(1.7)

# matrah 2.500.000 → vergi = 232.500 + 1.500.000×%35 = 757.500
ol('elle hasılat geçerli oldu', '3.000.000,00', metin('c-hasilat'))
ol('elle stopaj geçerli oldu', '200.000,00', metin('c-stopaj'))
ol('matrah 2.500.000', '2.500.000,00', metin('c-matrah'))
ol('vergi 757.500 (4. dilim)', '757.500,00', metin('c-vergi'))
ol('ödenecek 557.500', '557.500,00', metin('c-sonuc-tutar'))
ol('etiket ÖDENECEK oldu', True, 'ÖDEYECEKSİNİZ' in (metin('gv-sonuc-etiket') or ''))
ol('ödenecekte yeşil sınıf kalktı', False,
   js("document.getElementById('gv-sonuc-satir').classList.contains('iade')"))

# %5 uyumlu mükellef indirimi kutusu
js("""(()=>{const e=document.getElementById('gv-uyumlu');
  e.checked=true; e.dispatchEvent(new Event('change',{bubbles:true})); return 1})()""")
time.sleep(1.6)
ol('%5 indirim 37.875 (757.500×0,05)', '37.875,00', metin('c-uyumlu'))
ol('ödenmesi gereken 719.625', '719.625,00', metin('c-odenmesi_gereken'))
ol('indirimle ödenecek 519.625', '519.625,00', metin('c-sonuc-tutar'))

# =====================================================================
print('\n=== 4) AJAX KAYDETMİYOR (yalnız önizleme) ===')
# =====================================================================
ol('durum "kaydedilmedi" uyarısı var', True,
   'kaydedilmedi' in (metin('gv-durum') or ''))

git(B + '/gelir-vergisi/detay/1?yil=2026', 2.4)
# Sayfa yenilenince KAYITLI değere döner. HTTP testinin son bıraktığı gider
# 530.000'dir (21. bölüm "vergi yükü" senaryosu bunu yazar).
ol('yenilemede kayıtlı gider geri geldi', '530.000,00', metin('c-gider'))
ol('yenilemede canlı değer kaybolur (3.000.000 yok)', '550.000,00', metin('c-hasilat'))

# =====================================================================
print('\n=== 5) KAYDETME KALICI ===')
# =====================================================================
yaz('gv-gider', '250.000')
yaz('gv-bagkur', '50.000')
yaz('gv-sigorta', '0')
yaz('gv-egitim', '0')
yaz('gv-stopaj', '')       # makbuz stopajı geçerli olsun
time.sleep(1.4)
js("document.getElementById('gv-form').submit()")
time.sleep(2.6)

ol('kaydetme sonrası başarı mesajı', True,
   'kaydedildi' in (js('document.body.innerText') or '').lower())
ol('kayıtlı gider 250.000', '250.000,00', metin('c-gider'))
# kazanç 300.000 − bağkur 50.000 = matrah 250.000
ol('kayıtlı bağkur matraha yansıdı (250.000)', '250.000,00', metin('c-matrah'))

# =====================================================================
print('\n=== 6) LİSTE EKRANI VE GEZİNME ===')
# =====================================================================
git(B + '/gelir-vergisi?yil=2026', 2.4)
ol('listede 2 müşavir satırı', 2,
   js("document.querySelectorAll('.gvl-tablo tbody tr').length"))
ol('liste toplam satırı var', True,
   js("!!document.querySelector('.gvl-tablo tfoot')"))
ol('Ali Yılmaz listede', True, 'Ali Yılmaz' in (js('document.body.innerText') or ''))

# Müşavir adına tıklayınca hesap ekranı açılmalı
js("""(()=>{const a=[...document.querySelectorAll('.gvl-tablo tbody a.kalin')]
  .find(x=>x.textContent.includes('Ali')); a.click(); return 1})()""")
time.sleep(2.4)
ol('müşavir adına tıklayınca hesap açıldı', True,
   '/gelir-vergisi/detay/' in (js('location.pathname') or ''))

# =====================================================================
print('\n=== 7) TARİFE EKRANI ===')
# =====================================================================
git(B + '/gelir-vergisi/tarife?yil=2026', 2.4)
ol('ücret dışı sekmesi açık', True,
   js("document.getElementById('sekme-ucret-disi').style.display !== 'none'"))
ol('ücret sekmesi gizli', True,
   js("document.getElementById('sekme-ucret').style.display === 'none'"))

js("""(()=>{[...document.querySelectorAll('.tr-sekme button')]
  .find(b=>b.dataset.sekme==='ucret').click(); return 1})()""")
time.sleep(0.6)
ol('sekme değişti: ücret göründü', True,
   js("document.getElementById('sekme-ucret').style.display !== 'none'"))
ol('sekme değişti: ücret dışı gizlendi', True,
   js("document.getElementById('sekme-ucret-disi').style.display === 'none'"))

ol('tarife satırları düzenlenebilir (readonly değil)', False,
   js("""[...document.querySelectorAll('#sekme-ucret-disi .tr-para')]
        .some(e=>e.hasAttribute('readonly'))"""))
ol('okunuş sütunu dolu', True,
   js("""!!document.querySelector('#sekme-ucret-disi .tr-okunus')
        ?.textContent.includes('%')"""))


# =====================================================================
print('\n=== 8) SINIRLI İNDİRİMLER — TAVAN CANLI KAYIYOR ===')
# =====================================================================
git(B + '/gelir-vergisi/detay/1?yil=2026', 2.5)

ol('sigorta primi alanı var', True, js("!!document.getElementById('gv-sigorta')"))
ol('eğitim-sağlık alanı var', True, js("!!document.getElementById('gv-egitim')"))
ol('geçmiş yıl zararı alanı YOK', False, js("!!document.getElementById('gv-zarar')"))
ol('geçici vergi alanı YOK', False, js("!!document.getElementById('gv-gecici')"))
ol('diğer indirimler alanı YOK', False, js("!!document.getElementById('gv-diger-i')"))

# Hasılat 550.000 − gider 250.000 = kazanç 300.000
#   sigorta tavanı 45.000 · eğitim tavanı 30.000
yaz('gv-hasilat', '')
yaz('gv-stopaj', '')
yaz('gv-bagkur', '0')
yaz('gv-gider', '250.000')
yaz('gv-sigorta', '80.000')     # tavanı aşıyor
yaz('gv-egitim', '10.000')      # tavan altı
time.sleep(1.8)

ol('sigorta tavanı 45.000 gösteriliyor', '45.000,00', metin('gv-sigorta-tavan'))
ol('eğitim tavanı 30.000 gösteriliyor', '30.000,00', metin('gv-egitim-tavan'))
ol('sigorta 45.000 ile sınırlandı', '45.000,00', metin('c-sigorta'))
ol('eğitim 10.000 tamamı indi', '10.000,00', metin('c-egitim'))
ol('sigorta aşım uyarısı göründü', True,
   js("document.getElementById('gv-sigorta-asim').style.display !== 'none'"))
ol('sigorta aşımı 35.000', '35.000,00', metin('gv-sigorta-asim-tutar'))
ol('eğitim aşım uyarısı gizli', True,
   js("document.getElementById('gv-egitim-asim').style.display === 'none'"))
ol('indirim toplamı 55.000', '55.000,00', metin('c-indirim_toplam'))
ol('matrah 245.000', '245.000,00', metin('c-matrah'))

# Gider artınca tavan da düşmeli — canlı
yaz('gv-gider', '450.000')      # kazanç 100.000 → tavanlar 15.000 / 10.000
time.sleep(1.8)
ol('gider artınca sigorta tavanı 15.000', '15.000,00', metin('gv-sigorta-tavan'))
ol('gider artınca eğitim tavanı 10.000', '10.000,00', metin('gv-egitim-tavan'))
ol('sigorta yeni tavana indi', '15.000,00', metin('c-sigorta'))
ol('eğitim tam tavanda kaldı', '10.000,00', metin('c-egitim'))

# =====================================================================
print('\n=== 9) KDV TABLOSU — CANLI TOPLAM ===')
# =====================================================================
ol('KDV tablosu var', True, js("!!document.getElementById('kdv-form')"))
ol('12 ay ödenen kutusu', 12,
   js("""document.querySelectorAll('[name^="kdv"][name$="[odenen]"]').length"""))
ol('12 ay indirilecek kutusu', 12,
   js("""document.querySelectorAll('[name^="kdv"][name$="[indirilecek]"]').length"""))


def kdvYaz(ay, alan, deger):
    js(f"""(()=>{{
      const e=document.querySelector('[name="kdv[{ay}][{alan}]"]');
      e.value={json.dumps(deger)};
      e.dispatchEvent(new Event('input',{{bubbles:true}}));
      return 1}})()""")


kdvYaz(1, 'odenen', '10.000')
kdvYaz(1, 'indirilecek', '4.000')
kdvYaz(2, 'odenen', '12.500')
kdvYaz(2, 'indirilecek', '3.500')
time.sleep(0.6)

ol('Ocak ay toplamı 14.000', '14.000,00',
   js("""document.querySelector('[data-ay-toplam="1"]').textContent.trim()"""))
ol('Şubat ay toplamı 16.000', '16.000,00',
   js("""document.querySelector('[data-ay-toplam="2"]').textContent.trim()"""))
ol('ödenen toplamı 22.500', '22.500,00', metin('kdv-t-odenen'))
ol('indirilecek toplamı 7.500', '7.500,00', metin('kdv-t-indirilecek'))
ol('yıllık toplam 30.000', '30.000,00', metin('kdv-t-toplam'))
ol('tfoot toplamı da güncellendi', '30.000,00', metin('kdv-f-toplam'))
ol('dolu kutu yeşil işaretlendi', True,
   js("""document.querySelector('[name="kdv[1][odenen]"]').classList.contains('dolu')"""))

# "1.000.000" gibi binlikli girdi doğru okunmalı (JS tarafı)
kdvYaz(3, 'odenen', '1.000.000')
time.sleep(0.5)
ol("JS '1.000.000' bir milyon okudu", '1.030.000,00', metin('kdv-t-toplam'))
kdvYaz(3, 'odenen', '')
time.sleep(0.5)
ol('boşaltınca toplam geri döndü', '30.000,00', metin('kdv-t-toplam'))

# =====================================================================
print('\n=== 10) KDV KAYDEDİLİNCE HESABA GİRİYOR ===')
# =====================================================================
js("document.getElementById('kdv-form').submit()")
time.sleep(2.8)

ol('KDV kaydedildi mesajı', True,
   'kdv' in (js('document.body.innerText') or '').lower())
ol('kayıtlı KDV toplamı 30.000', '30.000,00', metin('kdv-t-toplam'))
ol('hesapta KDV satırı 30.000', '30.000,00', metin('c-kdv'))

# 5. bölümde stopaj kutusu boş kaydedildiği için makbuz stopajı 110.000 geçerli.
# net mahsup = 110.000 − 30.000 KDV = 80.000
ol('net mahsup = stopaj − KDV', '80.000,00', metin('c-mahsup_toplam'))

# KDV'yi stopajın üstüne çıkar → ÖDENECEK olmalı
kdvYaz(1, 'odenen', '300.000')
time.sleep(0.5)
js("document.getElementById('kdv-form').submit()")
time.sleep(2.8)

ol('KDV stopajı aşınca net mahsup negatif', True,
   (metin('c-mahsup_toplam') or '').startswith('-'))
ol('KDV stopajı aşınca etiket ÖDENECEK', True,
   'ÖDEYECEKSİNİZ' in (metin('gv-sonuc-etiket') or ''))
ol('ödenecek satırı yeşil değil', False,
   js("document.getElementById('gv-sonuc-satir').classList.contains('iade')"))


# =====================================================================
print('\n=== 11) İNDİRİM KALEMİ LİSTESİ — ekle / düzenle / sil ===')
# =====================================================================
git(B + '/gelir-vergisi/detay/1?yil=2026', 2.6)

ol('eğitim-sağlık kartı var', True, js("!!document.getElementById('egitim_saglik')"))
ol('sigorta kartı var', True, js("!!document.getElementById('sigorta')"))
ol('eğitim ekleme formu var', True, js("!!document.getElementById('form-egitim_saglik')"))


def kalemEkle(hedef, tarih, tur, aciklama, tutar):
    """Formu doldurup gönderir (gerçek kullanıcı gibi)."""
    js(f"""(()=>{{
      const f=document.getElementById('form-{hedef}');
      f.querySelector('[data-alan="tarih"]').value={json.dumps(tarih)};
      f.querySelector('[data-alan="tur"]').value={json.dumps(tur)};
      f.querySelector('[data-alan="aciklama"]').value={json.dumps(aciklama)};
      f.querySelector('[data-alan="tutar"]').value={json.dumps(tutar)};
      f.submit();return 1}})()""")
    time.sleep(2.6)


def satirSayisi(kalem):
    return js(f"""document.querySelectorAll('#{kalem} tr[data-kalem-satir]').length""")


kalemEkle('egitim_saglik', '2026-03-11', 'egitim', 'Üniversite harcı', '15.000')
ol('1. belge eklendi', 1, satirSayisi('egitim_saglik'))
ol('başarı mesajı çıktı', True,
   'eklendi' in (js('document.body.innerText') or '').lower())

kalemEkle('egitim_saglik', '2026-06-22', 'saglik', 'Diş tedavisi', '9.500')
ol('2. belge eklendi', 2, satirSayisi('egitim_saglik'))

# Liste toplamı ve hesaba yansıma
ol('liste toplamı 24.500', True,
   '24.500,00' in (js("document.getElementById('egitim_saglik').innerText") or ''))
ol('hesap dökümüne yansıdı', '24.500,00', metin('c-egitim'))
ol('elle giriş kutusu kilitlendi', True,
   js("document.getElementById('gv-egitim').hasAttribute('readonly')"))
ol('kutu değeri liste toplamı oldu', '24.500,00',
   js("document.getElementById('gv-egitim').value"))
ol('"listeden geliyor" notu göründü', True,
   'belgelik liste' in (js("document.getElementById('gv-egitim').closest('.gv-alan').innerText") or ''))

# Tür rozetleri doğru renklendi mi
ol('eğitim rozeti var', True,
   js("""!!document.querySelector('#egitim_saglik .kalem-rozet.t-egitim')"""))
ol('sağlık rozeti var', True,
   js("""!!document.querySelector('#egitim_saglik .kalem-rozet.t-saglik')"""))

# --- Düzenle düğmesi formu dolduruyor mu? ---------------------------
js("""document.querySelector('#egitim_saglik .kalem-duzenle').click()""")
time.sleep(0.7)

ol('düzenlemede form kipi değişti', True,
   js("document.getElementById('form-egitim_saglik').classList.contains('duzenleme')"))
ol('düzenlemede id dolduruldu', True,
   js("""!!document.querySelector('#form-egitim_saglik [data-alan="id"]').value"""))
ol('düzenlemede açıklama yüklendi', 'Üniversite harcı',
   js("""document.querySelector('#form-egitim_saglik [data-alan="aciklama"]').value"""))
ol('düzenlemede tutar yüklendi', '15.000,00',
   js("""document.querySelector('#form-egitim_saglik [data-alan="tutar"]').value"""))
ol('düğme "Güncelle" oldu', True,
   'Güncelle' in (js("""document.querySelector('#form-egitim_saglik [data-alan="gonder"]').textContent""") or ''))
ol('düzenlenen satır vurgulandı', True,
   js("""!!document.querySelector('#egitim_saglik tr.duzenleniyor')"""))
ol('vazgeç düğmesi göründü', True,
   js("""document.querySelector('#form-egitim_saglik .kalem-iptal').style.display !== 'none'"""))

# Vazgeç → yeni kayıt kipine dönmeli
js("""document.querySelector('#form-egitim_saglik .kalem-iptal').click()""")
time.sleep(0.5)
ol('vazgeçince form sıfırlandı', '',
   js("""document.querySelector('#form-egitim_saglik [data-alan="id"]').value"""))
ol('vazgeçince düğme "Ekle" oldu', True,
   'Ekle' in (js("""document.querySelector('#form-egitim_saglik [data-alan="gonder"]').textContent""") or ''))
ol('vazgeçince vurgu kalktı', False,
   js("""!!document.querySelector('#egitim_saglik tr.duzenleniyor')"""))

# --- Gerçek düzenleme: tutarı değiştir ------------------------------
js("""document.querySelector('#egitim_saglik .kalem-duzenle').click()""")
time.sleep(0.6)
js("""(()=>{const f=document.getElementById('form-egitim_saglik');
  f.querySelector('[data-alan="tutar"]').value='20.000';
  f.submit();return 1})()""")
time.sleep(2.6)

ol('düzenleme sonrası satır sayısı aynı', 2, satirSayisi('egitim_saglik'))
ol('düzenleme hesaba yansıdı (29.500)', '29.500,00', metin('c-egitim'))

# --- Sigorta listesi ayrı çalışıyor mu? -----------------------------
kalemEkle('sigorta', '2026-02-01', 'hayat', 'Hayat sigortası', '20.000')
ol('sigorta listesine eklendi', 1, satirSayisi('sigorta'))
ol('eğitim listesi etkilenmedi', 2, satirSayisi('egitim_saglik'))
ol('sigorta hesaba yansıdı', '20.000,00', metin('c-sigorta'))
ol('eğitim tutarı değişmedi', '29.500,00', metin('c-egitim'))

# --- Sınır aşımı listede de çalışıyor mu? ---------------------------
# Bu noktada gider 250.000'dir (5. bölümde kaydedildi):
#   kazanç = 550.000 − 250.000 = 300.000 → sigorta tavanı %15 = 45.000
#   liste 20.000 + 40.000 = 60.000 → 45.000 iner, 15.000 aşım
kalemEkle('sigorta', '2026-08-15', 'sahis', 'Şahıs sigortası', '40.000')
ol('sigorta listesi 2 belge', 2, satirSayisi('sigorta'))
ol('tavanla sınırlandı (45.000)', '45.000,00', metin('c-sigorta'))
ol('kart içinde aşım uyarısı', True,
   'indirilemedi' in (js("document.getElementById('sigorta').innerText") or ''))

# --- Silme -----------------------------------------------------------
js("""(()=>{const a=document.querySelector('#sigorta a.btn.kirmizi');
  a.removeAttribute('onclick'); a.click(); return 1})()""")
time.sleep(2.6)
ol('sigorta belgesi silindi', 1, satirSayisi('sigorta'))
ol('silme hesaba yansıdı (40.000)', '40.000,00', metin('c-sigorta'))


# =====================================================================
print('\n=== 12) AYLIK GİDER TABLOSU — canlı toplam ve toplama kuralı ===')
# =====================================================================
git(B + '/gelir-vergisi/detay/1?yil=2026', 2.6)

ol('gider tablosu var', True, js("!!document.getElementById('agider-form')"))
ol('12 ay gider kutusu', 12,
   js("""document.querySelectorAll('[name^="agider"][name$="[tutar]"]').length"""))


def giderYaz(ay, deger):
    js(f"""(()=>{{
      const e=document.querySelector('[name="agider[{ay}][tutar]"]');
      e.value={json.dumps(deger)};
      e.dispatchEvent(new Event('input',{{bubbles:true}}));
      return 1}})()""")


# Ekranda kayıtlı gider ne ise onu oku (önceki bölümler değiştirmiş olabilir)
elleGider = js("document.getElementById('gv-gider').value")

giderYaz(1, '5.000')
giderYaz(2, '7.500')
time.sleep(0.6)

ol('tablo toplamı canlı 12.500', '12.500,00', metin('ag-t-toplam'))
ol('tfoot toplamı da güncellendi', '12.500,00', metin('ag-f-toplam'))
ol('dolu kutu yeşil işaretlendi', True,
   js("""document.querySelector('[name="agider[1][tutar]"]').classList.contains('dolu')"""))

# "1.000.000" binlikli girdi doğru okunmalı (JS tarafı)
giderYaz(3, '1.000.000')
time.sleep(0.5)
ol("JS '1.000.000' bir milyon okudu", '1.012.500,00', metin('ag-t-toplam'))
giderYaz(3, '')
time.sleep(0.5)
ol('boşaltınca toplam geri döndü', '12.500,00', metin('ag-t-toplam'))

# Kaydet → hesaba EKLENMELİ (elle girileni ezmemeli)
js("document.getElementById('agider-form').submit()")
time.sleep(2.8)

ol('gider tablosu kaydedildi mesajı', True,
   'gider' in (js('document.body.innerText') or '').lower())
ol('kayıtlı tablo toplamı 12.500', '12.500,00', metin('ag-t-toplam'))
ol('elle girilen kutu DEĞİŞMEDİ', elleGider,
   js("document.getElementById('gv-gider').value"))
ol('gider kırılım notu göründü', True,
   'aylık tablo' in (metin('gv-gider-not') or ''))
ol('hesaba giren = elle + tablo', metin('ag-t-genel'), metin('c-gider'))

# Tabloyu boşaltınca gider elle değere dönmeli
giderYaz(1, '')
giderYaz(2, '')
time.sleep(0.5)
js("document.getElementById('agider-form').submit()")
time.sleep(2.8)
ol('tablo boşalınca gider = elle değer', elleGider,
   js("document.getElementById('gv-gider').value"))
ol('boş tabloda toplam 0', '0,00', metin('ag-t-toplam'))

# =====================================================================
print('\n=== 13) VERGİ YÜKÜ KIRILIMI EKRANDA ===')
# =====================================================================
ol('GV dengesi satırı var', True, js("!!document.getElementById('c-gv-denge')"))
ol('KDV yükü satırı var', True, js("!!document.getElementById('c-kdv-yuk')"))
ol('sonuç etiketi "VERGİ YÜKÜ" diyor', True,
   'VERGİ YÜKÜ' in (metin('gv-sonuc-etiket') or ''))

print()
print('=' * 54)
print(f' GEÇEN: {g}    KALAN: {k}    TOPLAM: {g + k}')
print('=' * 54)
raise SystemExit(1 if k else 0)
