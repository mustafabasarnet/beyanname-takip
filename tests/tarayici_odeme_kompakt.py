#!/usr/bin/env python3
# =====================================================================
#  ÖDEME LİSTESİ KOMPAKT TASARIM — TARAYICI TESTİ
#
#  Sunucu tarafını odeme_kompakt_testi.sh doğruluyor. Bu test katlama
#  davranışını, sayfa yüksekliğindeki kazancı ve ödendi işaretlemesinin
#  başlık rozetini güncellemesini gerçek tarayıcıda ölçer.
#
#  Ön koşul:
#    - uygulama  http://127.0.0.1:8099
#    - chromium  --headless --remote-debugging-port=9222 --remote-allow-origins=*
#    - veri      bash tests/odeme_kompakt_testi.sh en az bir kez çalışmış olmalı
#  Kullanım:  python3 tests/tarayici_odeme_kompakt.py
# =====================================================================
import json
import time
import urllib.request

import websocket

B = 'http://127.0.0.1:8099'
g = k = 0
konsol = []


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
        if r.get('method') == 'Runtime.exceptionThrown':
            konsol.append(str(r['params']['exceptionDetails'].get('text')))
        if r.get('id') == _id[0]:
            return r


cmd('Page.enable')
cmd('Runtime.enable')


def ev(expr):
    r = cmd('Runtime.evaluate', {'expression': expr, 'returnByValue': True})
    return r.get('result', {}).get('result', {}).get('value')


def git(url, bekle=3.2):
    cmd('Page.navigate', {'url': url})
    time.sleep(bekle)


def acikSayisi():
    return ev("[...document.querySelectorAll('.od-govde')].filter(e=>!e.hidden).length")


git(B + '/giris')
ev("document.querySelector('[name=kimlik]').value='admin';"
   "document.querySelector('[name=sifre]').value='Test1234';"
   "document.querySelector('form').submit()")
time.sleep(2.8)
ol('Giriş yapıldı', '/panel', ev('location.pathname'))

print('=== 1) VARSAYILAN KAPALI ===')
git(B + '/odeme?yil=2026&ay=8&adet=25')
ol('25 grup basıldı', 25, ev("document.querySelectorAll('.od-grup').length"))
ol('Hiçbir detay açık değil', 0, acikSayisi())
ol('Başlık düğmeleri erişilebilir', 25,
   ev("document.querySelectorAll('.od-bas[aria-expanded]').length"))

kapaliYukseklik = ev('document.body.scrollHeight')
print(f'      (kapalı sayfa yüksekliği: {kapaliYukseklik} px)')

print('=== 2) TIKLAYINCA AÇILIYOR ===')
ev("document.querySelectorAll('.od-bas')[2].click()")
time.sleep(0.8)
ol('Bir detay açıldı', 1, acikSayisi())
ol('aria-expanded true oldu', 'true',
   ev("document.querySelectorAll('.od-bas')[2].getAttribute('aria-expanded')"))
ol('Detayda tablo var', True,
   ev("!!document.querySelectorAll('.od-govde')[2].querySelector('table')"))
ol('Detayda ara toplam var', True,
   ev("document.querySelectorAll('.od-govde')[2].innerText.includes('ARA TOPLAMI')"))
ol('Bildirim düğmesi var', True,
   ev("!!document.querySelectorAll('.od-govde')[2].querySelector('a[href*=\"bildirim\"]')"))

ev("document.querySelectorAll('.od-bas')[2].click()")
time.sleep(0.8)
ol('Tekrar tıklayınca kapandı', 0, acikSayisi())

print('=== 3) TÜMÜNÜ AÇ / KAPAT ===')
ev("document.getElementById('od-tumunu').click()")
time.sleep(1.2)
ol('Tümü açıldı', 25, acikSayisi())
ol('Düğme metni değişti', True,
   ev("document.getElementById('od-tumunu').textContent.includes('Kapat')"))

acikYukseklik = ev('document.body.scrollHeight')
print(f'      (açık sayfa yüksekliği: {acikYukseklik} px)')
ol('Kompakt görünüm en az %60 kısa', True,
   kapaliYukseklik < acikYukseklik * 0.4)

ev("document.getElementById('od-tumunu').click()")
time.sleep(1.2)
ol('Tümü kapandı', 0, acikSayisi())
ol('Düğme metni geri döndü', True,
   ev("document.getElementById('od-tumunu').textContent.includes('Aç')"))

print('=== 4) ÖDENDİ İŞARETİ BAŞLIĞI GÜNCELLİYOR ===')
# Hiç ödenmemiş bir grup bul
hedef = ev("""(function(){
  var gr=[...document.querySelectorAll('.od-grup')];
  for (var i=0;i<gr.length;i++){
    var kut=gr[i].querySelectorAll('.odendi-kutu, .ozel-odendi');
    var isaretli=[...kut].filter(function(c){return c.checked;}).length;
    if (kut.length>1 && isaretli===0) return i;
  }
  return -1;
})()""")
ol('Ödenmemiş grup bulundu', True, hedef >= 0)

ev(f"document.querySelectorAll('.od-bas')[{hedef}].click()")
time.sleep(0.8)
ol('Başta rozet yok', 0,
   ev(f"document.querySelectorAll('.od-grup')[{hedef}].querySelectorAll('.od-rozet.tamam, .od-rozet.kismi').length"))

# İlk kalemi ödendi işaretle
ev(f"document.querySelectorAll('.od-grup')[{hedef}].querySelector('.odendi-kutu').click()")
time.sleep(2.2)
ol('Kısmi ödeme rozeti çıktı', True,
   bool(ev(f"!!document.querySelectorAll('.od-grup')[{hedef}].querySelector('.od-rozet.kismi')")))
ol('Satır soluklaştı', True,
   ev(f"""(function(){{
     var cb=document.querySelectorAll('.od-grup')[{hedef}].querySelector('.odendi-kutu');
     return cb.closest('tr').classList.contains('od-odendi');
   }})()"""))

# Tüm kalemleri işaretle
ev(f"""(function(){{
  var kut=document.querySelectorAll('.od-grup')[{hedef}].querySelectorAll('.odendi-kutu, .ozel-odendi');
  kut.forEach(function(c){{ if(!c.checked) c.click(); }});
}})()""")
time.sleep(3.5)
ol('Tamamlandı rozeti (✓)', True,
   bool(ev(f"!!document.querySelectorAll('.od-grup')[{hedef}].querySelector('.od-rozet.tamam')")))
ol('Grup soluklaştı', True,
   ev(f"document.querySelectorAll('.od-grup')[{hedef}].classList.contains('od-tamam')"))

# Geri al (sonraki koşumlar etkilenmesin)
ev(f"""(function(){{
  var kut=document.querySelectorAll('.od-grup')[{hedef}].querySelectorAll('.odendi-kutu, .ozel-odendi');
  kut.forEach(function(c){{ if(c.checked) c.click(); }});
}})()""")
time.sleep(3.5)

print('=== 5) SONSUZ KAYDIRMA ===')
git(B + '/odeme?yil=2026&ay=8&adet=25')
ol('Başta 25 grup', 25, ev("document.querySelectorAll('.od-grup').length"))
ev("odDahaFazla()")
time.sleep(3)
ol('Kalanlar yüklendi (40)', 40, ev("document.querySelectorAll('.od-grup').length"))
ol('Sayaç güncellendi', '40', ev("document.getElementById('od-gosterilen').textContent"))
ol('Yeni gruplar da kapalı', 0, acikSayisi())
# Sonradan gelen grupta katlama çalışmalı (olay devri)
ev("document.querySelectorAll('.od-bas')[35].click()")
time.sleep(0.8)
ol('Sonradan gelen grup açılabiliyor', 1, acikSayisi())
# Sonradan gelen grupta ödendi kutusu da çalışmalı
ol('Sonradan gelen grupta kutu var', True,
   ev("!!document.querySelectorAll('.od-grup')[35].querySelector('.odendi-kutu')"))

print('=== 6) GÖRSEL DÜZEN ===')
git(B + '/odeme?yil=2026&ay=8&adet=25')
ol('Başlık satırı tek satırda', True,
   ev("""[...document.querySelectorAll('.od-bas')].every(function(b){ return b.offsetHeight <= 48; })"""))
ol('Tutarlar sağa hizalı', 'right',
   ev("getComputedStyle(document.querySelector('.od-tutar')).textAlign"))
ol('İmleç pointer', 'pointer',
   ev("getComputedStyle(document.querySelector('.od-bas')).cursor"))
ol('Liste yatay taşmıyor', True,
   ev("""(function(){
     var l=document.getElementById('od-liste');
     return l.scrollWidth <= l.clientWidth + 2;
   })()"""))

print('=== 7) KONSOL ===')
ol('JS hatası yok', 0, len(konsol))
for h in konsol:
    print('      >>', h)

print()
print('================================================')
print(f'  GEÇEN: {g}    KALAN: {k}    TOPLAM: {g + k}')
print('================================================')
ws.close()
raise SystemExit(0 if k == 0 else 1)
