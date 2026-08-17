#!/usr/bin/env python3
# =====================================================================
#  KONTROL PANELİ DURUM TABLOSU — TARAYICI TESTİ
#
#  Sunucu tarafını panel_dagilim_testi.sh doğruluyor. Bu test sayılara
#  gerçekten TIKLANABİLDİĞİNİ, açılır pencerenin doğru listeyi getirdiğini
#  ve ay değişince geçici vergilerin tablodan düştüğünü gerçek tarayıcıda
#  ölçer.
#
#  Ön koşul:
#    - uygulama  http://127.0.0.1:8099
#    - chromium  --headless --remote-debugging-port=9222 --remote-allow-origins=*
#    - veri      bash tests/panel_dagilim_testi.sh en az bir kez çalışmış olmalı
#  Kullanım:  python3 tests/tarayici_panel_dagilim.py
# =====================================================================
import json
import time
import urllib.request

import websocket

B = 'http://127.0.0.1:8099'
g = k = 0


def ol(ad, bekl, ger):
    global g, k
    if str(bekl) == str(ger):
        print(f'  [OK] {ad}')
        g += 1
    else:
        print(f'  [HATA] {ad} (bekl:{bekl} ger:{ger})')
        k += 1


def sekme():
    for t in json.load(urllib.request.urlopen('http://127.0.0.1:9222/json/list')):
        if t.get('type') == 'page':
            return t['webSocketDebuggerUrl']
    raise SystemExit('Chromium sekmesi yok')


ws = websocket.create_connection(sekme(), origin='http://127.0.0.1:9222', suppress_origin=True)
_id = [0]
konsol = []


def cmd(m, p=None):
    _id[0] += 1
    ws.send(json.dumps({'id': _id[0], 'method': m, 'params': p or {}}))
    while True:
        r = json.loads(ws.recv())
        if r.get('method') == 'Runtime.exceptionThrown':
            konsol.append(str(r['params']['exceptionDetails'].get('text')))
        if r.get('id') == _id[0]:
            return r


cmd('Page.enable')
cmd('Runtime.enable')


def ev(expr):
    r = cmd('Runtime.evaluate', {'expression': expr, 'returnByValue': True})
    return r.get('result', {}).get('result', {}).get('value')


def git(url, bekle=2.6):
    cmd('Page.navigate', {'url': url})
    time.sleep(bekle)


def turler():
    return ev("[...document.querySelectorAll('.bdk-tur')]"
              ".map(e=>e.textContent.trim()).join(',')")


git(B + '/giris')
ev("document.querySelector('[name=kimlik]').value='admin';"
   "document.querySelector('[name=sifre]').value='Test1234';"
   "document.querySelector('form').submit()")
time.sleep(2.6)
ol('Giriş yapıldı', '/panel', ev('location.pathname'))

print('=== 1) TABLO GÖRÜNÜYOR VE GRAFİĞİN ÜSTÜNDE ===')
git(B + '/panel?yil=2026&ay=8', 3)
ol('Tablo var', True, bool(ev("!!document.querySelector('.bdk-tablo')")))
ol('Tablo aylık grafiğin üstünde', True,
   ev("""(function(){
     var t = document.querySelector('.bdk-kart');
     var gr = [...document.querySelectorAll('h2')]
       .find(function(h){ return h.textContent.includes('Aylık Beyanname Dağılımı'); });
     if(!t || !gr) return false;
     return t.getBoundingClientRect().top < gr.getBoundingClientRect().top;
   })()"""))
ol('6 tür satırı', 6, ev("document.querySelectorAll('.bdk-tablo tbody tr').length"))
ol('TOPLAM satırı var', True, bool(ev("!!document.querySelector('.bdk-tablo tfoot')")))

print('=== 2) AĞUSTOS → GEÇİCİ VERGİLER VAR ===')
agu = turler()
ol('Ağustos türleri', 'KDV1 (Ay),MUHSGK (Ay),Gelir Geçici,Kurum Geçici,Damga,Turizm', agu)
ol('Gelir Geçici görünüyor', True, 'Gelir Geçici' in agu)
ol('Kurum Geçici görünüyor', True, 'Kurum Geçici' in agu)

print('=== 3) EYLÜL → GEÇİCİLER KAYBOLUYOR (asıl istek) ===')
git(B + '/panel?yil=2026&ay=9', 3)
eyl = turler()
ol('Eylül türleri', 'KDV1 (Ay),MUHSGK (Ay),Damga', eyl)
ol('Gelir Geçici KAYBOLDU', False, 'Gelir Geçici' in eyl)
ol('Kurum Geçici KAYBOLDU', False, 'Kurum Geçici' in eyl)
ol('Turizm KAYBOLDU', False, 'Turizm' in eyl)
ol('KDV1 hâlâ duruyor', True, 'KDV1' in eyl)
ol('Satır sayısı 6 → 3', 3, ev("document.querySelectorAll('.bdk-tablo tbody tr').length"))

print('=== 4) AY SEÇİCİSİ TABLOYU SÜRÜYOR ===')
ol('Ay seçicisi Eylül', '9', ev("document.querySelector('[name=ay]').value"))
ol('Tablo başlığında ay yazıyor', True,
   ev("document.querySelector('.bdk-baslik .rozet').textContent.includes('Eylül')"))

print('=== 5) SAYIYA TIKLAYINCA LİSTE AÇILIYOR ===')
git(B + '/panel?yil=2026&ay=8', 3)
ol('Pencere başta kapalı', False,
   ev("document.getElementById('bdk-pencere').classList.contains('acik')"))

# Gelir Geçici satırındaki "Kalan" sayısına tıkla
ev("""(function(){
  var tr = [...document.querySelectorAll('.bdk-tablo tbody tr')].find(function(t){
    var e = t.querySelector('.bdk-tur');
    return e && e.textContent.trim() === 'Gelir Geçici'; });
  tr.querySelector('.bdk-say[data-durum=KALAN]').click();
})()""")
time.sleep(2.4)
ol('Pencere açıldı', True,
   ev("document.getElementById('bdk-pencere').classList.contains('acik')"))
ol('Başlık doğru', 'Gelir Geçici — Kalan (Bekliyor + Hazır)',
   ev("document.getElementById('bdk-p-baslik').textContent"))
ol('Listede 10 kayıt', 10, ev("document.querySelectorAll('#bdk-p-govde tbody tr').length"))
ol('Mükellef adı görünüyor', True,
   ev("document.querySelector('#bdk-p-govde tbody tr').textContent.includes('FİRMA')"))
ol('Durum rozeti var', True,
   bool(ev("!!document.querySelector('#bdk-p-govde .rozet')")))
ol('"Takip ekranında aç" bağlantısı tür taşıyor', True,
   ev("document.getElementById('bdk-p-takip').href.includes('tur_id=')"))
ol('Bağlantı ay filtresini taşıyor', True,
   ev("document.getElementById('bdk-p-takip').href.includes('ay=8')"))

print('=== 6) PENCERE KAPANIYOR ===')
ev("document.querySelector('.bdk-p-kapat').click()")
time.sleep(0.6)
ol('Kapat düğmesi çalışıyor', False,
   ev("document.getElementById('bdk-pencere').classList.contains('acik')"))

# Onaylandı sütununa tıkla (Mayıs'ta dolu)
git(B + '/panel?yil=2026&ay=5', 3)
ev("""(function(){
  var tr = [...document.querySelectorAll('.bdk-tablo tbody tr')].find(function(t){
    var e = t.querySelector('.bdk-tur');
    return e && e.textContent.trim() === 'KDV1 (Ay)'; });
  tr.querySelector('.bdk-say[data-durum=ONAYLANDI]').click();
})()""")
time.sleep(2.4)
ol('Onaylandı listesi açıldı', 'KDV1 (Ay) — Onaylandı',
   ev("document.getElementById('bdk-p-baslik').textContent"))
ol('Onaylandı listesinde 20 kayıt', 20,
   ev("document.querySelectorAll('#bdk-p-govde tbody tr').length"))
ol('Hepsi Onaylandı rozetli', True,
   ev("""[...document.querySelectorAll('#bdk-p-govde tbody tr .rozet')]
        .every(function(e){ return e.textContent.trim() === 'Onaylandı'; })"""))

print('=== 7) SIFIR OLAN SAYILAR TIKLANAMAZ ===')
git(B + '/panel?yil=2026&ay=8', 3)
ol('Sıfırlar buton değil', 0,
   ev("document.querySelectorAll('button.bdk-say.bos').length"))
ol('Sıfır hücresi span', True,
   bool(ev("!!document.querySelector('span.bdk-say.bos')")))

print('=== 8) ORAN ÇUBUĞU ===')
ol('Ağustos KDV1 %0 (çubuk boş)', '0',
   ev("""(function(){
     var tr = [...document.querySelectorAll('.bdk-tablo tbody tr')].find(function(t){
       return t.querySelector('.bdk-tur').textContent.trim() === 'KDV1 (Ay)'; });
     return tr.querySelector('.bdk-oran').textContent.replace('%','').trim();
   })()"""))
git(B + '/panel?yil=2026&ay=5', 2.8)
ol('Mayıs KDV1 %100 (çubuk dolu)', '100',
   ev("""(function(){
     var tr = [...document.querySelectorAll('.bdk-tablo tbody tr')].find(function(t){
       return t.querySelector('.bdk-tur').textContent.trim() === 'KDV1 (Ay)'; });
     return tr.querySelector('.bdk-oran').textContent.replace('%','').trim();
   })()"""))
ol('Çubuk genişliği %100', True,
   ev("""(function(){
     var tr = [...document.querySelectorAll('.bdk-tablo tbody tr')].find(function(t){
       return t.querySelector('.bdk-tur').textContent.trim() === 'KDV1 (Ay)'; });
     var i = tr.querySelector('.bdk-cubuk i'), c = tr.querySelector('.bdk-cubuk');
     return i.offsetWidth >= c.offsetWidth - 2;
   })()"""))

print('=== 9) GÖRSEL DÜZEN ===')
ol('Tablo yatay taşmıyor', True,
   ev("""(function(){
     var t = document.querySelector('.bdk-tablo');
     return t.scrollWidth <= t.parentElement.clientWidth + 2;
   })()"""))
ol('Sayı düğmelerinde imleç pointer', 'pointer',
   ev("getComputedStyle(document.querySelector('button.bdk-say')).cursor"))

print('=== 10) KONSOL TEMİZ ===')
ol('JS hatası yok', 0, len(konsol))
for h in konsol:
    print('      >>', h)

print()
print('================================================')
print(f'  GEÇEN: {g}    KALAN: {k}    TOPLAM: {g + k}')
print('================================================')
ws.close()
raise SystemExit(0 if k == 0 else 1)
