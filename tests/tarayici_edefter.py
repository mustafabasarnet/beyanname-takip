#!/usr/bin/env python3
# =====================================================================
#  E-DEFTER TAKİBİ — TARAYICI TESTİ
#
#  Sunucu tarafını edefter_testi.sh doğruluyor. Bu test kontrol listesinin
#  gerçekten TIKLANABİLDİĞİNİ, ilerleme çubuğunun anlık güncellendiğini ve
#  panel kartının doğru göründüğünü gerçek tarayıcıda ölçer.
#
#  Ön koşul:
#    - uygulama  http://127.0.0.1:8099
#    - chromium  --headless --remote-debugging-port=9222 --remote-allow-origins=*
#    - veri      bash tests/edefter_testi.sh en az bir kez çalışmış olmalı
#  Kullanım:  python3 tests/tarayici_edefter.py
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


def git(url, bekle=2.8):
    cmd('Page.navigate', {'url': url})
    time.sleep(bekle)


# Belirli satırın (index) ilerleme yüzdesi
def yuzde(i):
    return ev(f"document.querySelectorAll('#ed-govde tr')[{i}].querySelector('.ed-yuzde').textContent")


def durum(i, bekle=None, azami=12):
    """
    Satırın durum kutusu değeri.

    AJAX yanıtı geldikten SONRA JS değeri güncellediği için, beklenen değer
    verilirse kısa aralıklarla yoklanır. Sabit sleep yerine bu yöntem
    yavaş makinede de güvenilir çalışır.
    """
    yol = f"document.querySelectorAll('#ed-govde tr')[{i}].querySelector('.ed-durum')"

    for _ in range(azami):
        v = ev(f"{yol} ? {yol}.value : ''")
        if bekle is None or v == bekle:
            return v
        time.sleep(0.4)

    return ev(f"{yol} ? {yol}.value : ''")


git(B + '/giris')
ev("document.querySelector('[name=kimlik]').value='admin';"
   "document.querySelector('[name=sifre]').value='Test1234';"
   "document.querySelector('form').submit()")
time.sleep(2.8)
ol('Giriş yapıldı', '/panel', ev('location.pathname'))

print('=== 1) ÇİZELGE VE KONTROL LİSTESİ ===')
git(B + '/edefter?yil=2026&ay=0', 3.2)
ol('Çizelge açıldı', True, bool(ev("!!document.querySelector('#ed-tablo')")))
ol('6 adım sütunu', 6, ev("document.querySelectorAll('#ed-tablo thead .ed-adim-basi').length"))
ol('Adım başlıkları doğru', 'Banka Temin,Banka İşleme,Çek İşleme,Mizan Kontrol,Hazır,Onaylandı',
   ev("[...document.querySelectorAll('#ed-tablo thead .ad')].map(e=>e.textContent.trim()).join(',')"))
ol('Kutular etkin (disabled değil)', False,
   ev("document.querySelector('#ed-govde .ed-kutu').disabled"))
ol('Kutuda imleç pointer', 'pointer',
   ev("getComputedStyle(document.querySelector('#ed-govde .ed-kutu')).cursor"))

print('=== 2) SIFIRDAN İŞ AKIŞI (banka → ... → onay) ===')
# Tamamen boş bir satır bul
bos = ev("""(function(){
  var t=[...document.querySelectorAll('#ed-govde tr')];
  for (var i=0;i<t.length;i++){
    if (t[i].querySelectorAll('.ed-kutu.dolu').length===0) return i;
  }
  return -1;
})()""")
ol('Boş satır bulundu', True, bos >= 0)

beklenen = [('Banka Temin', '%17'), ('Banka İşleme', '%33'), ('Çek İşleme', '%50'),
            ('Mizan Kontrol', '%67'), ('Hazır', '%83'), ('Onaylandı', '%100')]

for i, (ad, bek) in enumerate(beklenen):
    ev(f"document.querySelectorAll('#ed-govde tr')[{bos}].querySelectorAll('.ed-kutu')[{i}].click()")
    time.sleep(1.6)
    ol(f'{ad} işaretlendi → {bek}', bek, yuzde(bos))

ol('Son durumda ONAYLANDI', 'ONAYLANDI', durum(bos, 'ONAYLANDI'))
ol('6 kutu da dolu', 6,
   ev(f"document.querySelectorAll('#ed-govde tr')[{bos}].querySelectorAll('.ed-kutu.dolu').length"))
ol('Çubuk tam dolu', True,
   ev(f"""(function(){{
     var tr=document.querySelectorAll('#ed-govde tr')[{bos}];
     var i=tr.querySelector('.ed-cubuk i'), c=tr.querySelector('.ed-cubuk');
     return i.offsetWidth >= c.offsetWidth - 2;
   }})()"""))

print('=== 3) ADIM GERİ ALMA ===')
ev(f"document.querySelectorAll('#ed-govde tr')[{bos}].querySelectorAll('.ed-kutu')[5].click()")
time.sleep(1.8)
ol('Onay kaldırıldı → %83', '%83', yuzde(bos))
ol('Durum HAZIR', 'HAZIR', durum(bos, 'HAZIR'))
ev(f"document.querySelectorAll('#ed-govde tr')[{bos}].querySelectorAll('.ed-kutu')[4].click()")
time.sleep(1.8)
ol('Hazır kaldırıldı → %67', '%67', yuzde(bos))
ol('Durum DEVAM', 'DEVAM', durum(bos, 'DEVAM'))

print('=== 4) SAYFA YENİLENİNCE KORUNUYOR ===')
git(B + '/edefter?yil=2026&ay=0', 3.2)
ol('İşaretler kalıcı (%67)', '%67', yuzde(bos))
ol('4 kutu dolu kaldı', 4,
   ev(f"document.querySelectorAll('#ed-govde tr')[{bos}].querySelectorAll('.ed-kutu.dolu').length"))

print('=== 5) DURUM KUTUSU — YÜKLENMEYECEK ===')
ev(f"""(function(){{
  var s=document.querySelectorAll('#ed-govde tr')[{bos}].querySelector('.ed-durum');
  s.value='YUKLENMEYECEK'; s.dispatchEvent(new Event('change',{{bubbles:true}}));
}})()""")
time.sleep(1.8)
pasif = False
for _ in range(12):
    pasif = ev(f"document.querySelectorAll('#ed-govde tr')[{bos}].classList.contains('ed-pasif')")
    if pasif:
        break
    time.sleep(0.4)
ol('Satır soluklaştı', True, pasif)
ev(f"""(function(){{
  var s=document.querySelectorAll('#ed-govde tr')[{bos}].querySelector('.ed-durum');
  s.value='BEKLIYOR'; s.dispatchEvent(new Event('change',{{bubbles:true}}));
}})()""")
time.sleep(1.8)
ol('Geri alınca adımdan hesaplandı', 'DEVAM', durum(bos, 'DEVAM'))

print('=== 6) FİLTRELER ===')
git(B + '/edefter?yil=2026&ay=0&donem_tipi=UC_AYLIK', 3)
ol('Üç aylık filtresi çalışıyor', True,
   ev("""[...document.querySelectorAll('#ed-govde tr')]
        .every(function(t){ return t.textContent.includes('3 Aylık'); })"""))
git(B + '/edefter?yil=2026&ay=0&donem_tipi=AYLIK', 3)
ol('Aylık filtresi çalışıyor', True,
   ev("""[...document.querySelectorAll('#ed-govde tr')]
        .every(function(t){ return t.querySelector('.rozet').textContent.trim()==='Aylık'; })"""))
ol('Filtre kutusu seçili kalıyor', 'AYLIK', ev("document.querySelector('[name=donem_tipi]').value"))

print('=== 7) PANEL KARTI ===')
git(B + '/panel?yil=2026&ay=8', 3)
ol('E-Defter kartı var', True, bool(ev("!!document.querySelector('.edk-kart')")))
ol('Kart başlığı', 'E-Defter',
   ev("document.querySelector('.edk-kart h2').textContent.trim()"))
ol('Dönem rozeti var', True,
   ev("!!document.querySelector('.edk-kart .rozet')"))
ol('Toplam/Yüklenen/Kalan etiketleri', True,
   ev("""(function(){
     var t=document.querySelector('.edk-kart').innerText.toUpperCase();
     return t.includes('TOPLAM') && t.includes('YÜKLENEN') && t.includes('KALAN');
   })()"""))
ol('Sayılar tıklanabilir bağlantı', True,
   ev("document.querySelectorAll('.edk-kart a.edk-bag').length >= 3"))
ol('Bağlantı e-defter listesine gidiyor', True,
   ev("document.querySelector('.edk-kart a.edk-bag').href.includes('/edefter')"))
ol('İlerleme çubuğu var', True, bool(ev("!!document.querySelector('.edk-cubuk')")))
ol('"Takip Listesi" düğmesi', True,
   ev("document.querySelector('.edk-kart .btn').textContent.includes('Takip Listesi')"))

# Karttaki bağlantı gerçekten süzülü liste açıyor mu?
ev("document.querySelector('.edk-kart a.edk-bag').click()")
time.sleep(3)
ol('Karttan listeye geçildi', True, '/edefter' in (ev('location.pathname') or ''))
ol('Ay filtresi taşındı', '8', ev("document.querySelector('[name=ay]').value"))

print('=== 8) GÖRSEL DÜZEN ===')
git(B + '/edefter?yil=2026&ay=0', 3)
ol('Tablo taşmıyor', True,
   ev("""(function(){
     var t=document.querySelector('#ed-tablo');
     return t.scrollWidth <= t.parentElement.scrollWidth + 2;
   })()"""))
ol('Kutular görünür', 0,
   ev("[...document.querySelectorAll('#ed-govde .ed-kutu')].filter(e=>e.offsetWidth===0).length"))
ol('Menüde E-Defter Takip', True,
   ev("document.body.innerText.includes('E-Defter Takip')"))

print('=== 9) KONSOL ===')
ol('JS hatası yok', 0, len(konsol))
for h in konsol:
    print('      >>', h)

print()
print('================================================')
print(f'  GEÇEN: {g}    KALAN: {k}    TOPLAM: {g + k}')
print('================================================')
ws.close()
raise SystemExit(0 if k == 0 else 1)
