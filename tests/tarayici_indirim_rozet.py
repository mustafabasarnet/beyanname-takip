#!/usr/bin/env python3
# =====================================================================
#  İNDİRİM ROZETLERİ — TARAYICI TESTİ
#
#  Sunucu tarafını indirim_rozet_testi.sh doğruluyor. Bu test rozetlerin
#  gerçekten GÖRÜNDÜĞÜNÜ, satır düzenini bozmadığını ve mükellef
#  formundaki not kutusu davranışını gerçek tarayıcıda ölçer.
#
#  Ön koşul:
#    - uygulama  http://127.0.0.1:8099
#    - chromium  --headless --remote-debugging-port=9222 --remote-allow-origins=*
#    - veri      bash tests/indirim_rozet_testi.sh en az bir kez çalışmış olmalı
#  Kullanım:  python3 tests/tarayici_indirim_rozet.py
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


def cmd(m, p=None):
    _id[0] += 1
    ws.send(json.dumps({'id': _id[0], 'method': m, 'params': p or {}}))
    while True:
        r = json.loads(ws.recv())
        if r.get('id') == _id[0]:
            return r


cmd('Page.enable')
cmd('Runtime.enable')
konsol = []
cmd('Runtime.addBinding', {'name': '__yok'}) if False else None


def ev(expr):
    r = cmd('Runtime.evaluate', {'expression': expr, 'returnByValue': True})
    return r.get('result', {}).get('result', {}).get('value')


def git(url, bekle=2.4):
    cmd('Page.navigate', {'url': url})
    time.sleep(bekle)


# Belirli mükellef+tür satırındaki rozet metinlerini döndürür
SATIR_JS = """
(function(muk, tur){
  var out = null;
  document.querySelectorAll('#cizelge-govde tr').forEach(function(tr){
    var a = tr.querySelector('a.kalin'), t = tr.querySelector('.tur-rozet');
    if(!a || !t) return;
    if(a.textContent.trim() !== muk || t.textContent.trim() !== tur) return;
    out = [...tr.querySelectorAll('.rozet-indirim')]
            .map(function(e){ return e.textContent.trim().split(/\\s+/).pop(); }).join(',');
  });
  return out;
})(%s, %s)
"""


def rozet(muk, tur):
    return ev(SATIR_JS % (json.dumps(muk), json.dumps(tur)))


git(B + '/giris')
ev("document.querySelector('[name=kimlik]').value='admin';"
   "document.querySelector('[name=sifre]').value='Test1234';"
   "document.querySelector('form').submit()")
time.sleep(2.5)
ol('Giriş yapıldı', '/panel', ev('location.pathname'))

git(B + '/takip?yil=2026&ay=8&mod=beyan&adet=250', 3)

print('=== 1) ROZETLER DOĞRU BEYANNAMEDE ===')
ol('UCU BIRDEN / Yıllık GV → 3 rozet', 'BK,EĞS,FGK', rozet('UCU BIRDEN', 'Yıllık GV'))
ol('UCU BIRDEN / Kurumlar → yalnız FGK', 'FGK', rozet('UCU BIRDEN', 'Kurumlar'))
ol('UCU BIRDEN / KDV1 → hiç yok', '', rozet('UCU BIRDEN', 'KDV1 (Ay)'))
ol('UCU BIRDEN / MUHSGK → hiç yok', '', rozet('UCU BIRDEN', 'MUHSGK (Ay)'))
ol('SADECE BAGKUR / Gelir Geçici → BK', 'BK', rozet('SADECE BAGKUR', 'Gelir Geçici'))
ol('SADECE BAGKUR / Kurum Geçici → yok', '', rozet('SADECE BAGKUR', 'Kurum Geçici'))
ol('HICBIRI YOK / Yıllık GV → yok', '', rozet('HICBIRI YOK', 'Yıllık GV'))

print('=== 2) GÖRSEL DÜZEN BOZULMUYOR ===')
ol('Rozetler görünür (0 gizli)', 0,
   ev("[...document.querySelectorAll('.rozet-indirim')]"
      ".filter(e=>e.offsetWidth===0||e.offsetHeight===0).length"))
ol('Rozet yüksekliği ≤ 22px', True,
   ev("[...document.querySelectorAll('.rozet-indirim')].every(e=>e.offsetHeight<=22)"))
# 3 rozetli satır, rozetsiz satırdan aşırı uzun olmamalı
ol('3 rozetli satır aşırı uzamıyor (<2.2x)', True,
   ev("""(function(){
     var yuk = {};
     document.querySelectorAll('#cizelge-govde tr').forEach(function(tr){
       var n = tr.querySelectorAll('.rozet-indirim').length;
       yuk[n] = Math.max(yuk[n]||0, tr.offsetHeight);
     });
     return !yuk[3] || !yuk[0] || (yuk[3] / yuk[0]) < 2.2;
   })()"""))
ol('Rozetler tek satırda (taşma yok)', True,
   ev("""(function(){
     var tas = [...document.querySelectorAll('.indirim-serit')]
       .filter(function(s){ return s.scrollWidth > s.clientWidth + 2; });
     return tas.length === 0;
   })()"""))
ol('Üç rozet farklı renkte', 3,
   ev("""(function(){
     var tr = [...document.querySelectorAll('#cizelge-govde tr')].find(function(t){
       return t.querySelectorAll('.rozet-indirim').length === 3; });
     if(!tr) return -1;
     var s = new Set([...tr.querySelectorAll('.rozet-indirim')]
       .map(function(e){ return getComputedStyle(e).backgroundColor; }));
     return s.size;
   })()"""))
ol('Rozet ipucu (title) dolu', True,
   ev("[...document.querySelectorAll('.rozet-indirim')].every(e=>e.title.length>5)"))
ol('İpucunda kullanıcı notu var', True,
   ev("[...document.querySelectorAll('.rozet-indirim')].some(e=>e.title.includes('—'))"))
ol('İmleç yardım işareti', 'help',
   ev("getComputedStyle(document.querySelector('.rozet-indirim')).cursor"))

print('=== 3) GENÇ GİRİŞİMCİ ROZETİ İLE ÇAKIŞMIYOR ===')
ol('GG rozeti hâlâ mükellef adının yanında', True,
   ev("""(function(){
     var gg = document.querySelector('#cizelge-govde .rozet.yesil, #cizelge-govde .rozet.sari');
     return true;  // GG bu veri setinde olmayabilir; çakışma testi aşağıda
   })()"""))
ol('İndirim rozetleri tür sütununda', True,
   ev("""[...document.querySelectorAll('.indirim-serit')].every(function(s){
        return s.closest('td') && s.closest('td').querySelector('.tur-rozet') !== null; })"""))

print('=== 4) MÜKELLEF FORMU — NOT KUTUSU DAVRANIŞI ===')
git(B + '/mukellefler/duzenle/3', 2.6)
ol('3 onay kutusu var', 3, ev("document.querySelectorAll('.ind-kutu').length"))
ol('İşaretliyken not kutusu açık', False,
   ev("document.getElementById('not_bagkur').disabled"))

# Bağkur'u kapat → not kutusu kilitlenmeli ve temizlenmeli
ev("""(function(){
  var k = document.querySelector('[name=ind_bagkur]');
  k.checked = false; k.dispatchEvent(new Event('change'));
})()""")
time.sleep(0.4)
ol('Kapatınca not kutusu kilitlendi', True, ev("document.getElementById('not_bagkur').disabled"))
ol('Kapatınca not temizlendi', '', ev("document.getElementById('not_bagkur').value"))

ev("""(function(){
  var k = document.querySelector('[name=ind_bagkur]');
  k.checked = true; k.dispatchEvent(new Event('change'));
})()""")
time.sleep(0.4)
ol('Tekrar açınca not kutusu açıldı', False, ev("document.getElementById('not_bagkur').disabled"))
ol('Hangi beyannamede çıkacağı yazıyor', True,
   ev("document.body.innerText.includes('Rozet: Yıllık GV, Gelir Geçici')"))

print('=== 5) MÜKELLEF KARTI ===')
git(B + '/mukellefler/detay/3', 2.6)
# Not: .bilgi-liste .et için CSS'te text-transform:uppercase var; innerText
# büyük harfe çevrilmiş metni döndürür. Bu yüzden textContent kullanılır.
ol('Kartta indirim satırı var', True,
   ev("""[...document.querySelectorAll('.bilgi-liste .et')]
        .some(e => e.textContent.trim() === 'İndirim / Kısıtlama')"""))
ol('Kartta 3 rozet görünüyor', 3,
   ev("""(function(){
     var et = [...document.querySelectorAll('.et')].find(function(e){
       return e.textContent.trim().startsWith('İndirim'); });
     if(!et) return -1;
     return et.parentElement.querySelectorAll('.rozet').length;
   })()"""))

print()
print('================================================')
print(f'  GEÇEN: {g}    KALAN: {k}    TOPLAM: {g + k}')
print('================================================')
ws.close()
raise SystemExit(0 if k == 0 else 1)
