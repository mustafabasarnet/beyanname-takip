#!/usr/bin/env python3
# =====================================================================
#  ÖZET KART — TARAYICI TESTİ (gerçek tıklama)
#
#  Sunucu tarafını ozet_kart_testi.sh doğruluyor. Bu test ise kartların
#  gerçekten TIKLANABİLİR olduğunu, tıklayınca listenin süzüldüğünü ve
#  tekrar tıklayınca süzgecin kalktığını gerçek tarayıcıda ölçer.
#
#  Ön koşul:
#    - uygulama  http://127.0.0.1:8099
#    - chromium  --headless --remote-debugging-port=9222 --remote-allow-origins=*
#    - veri      bash tests/ozet_kart_testi.sh en az bir kez çalıştırılmış olmalı
#                (108 kayıtlık test seti onu kurar)
#  Kullanım:  python3 tests/tarayici_ozet_kart.py
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


def ev(expr):
    r = cmd('Runtime.evaluate', {'expression': expr, 'returnByValue': True})
    return r.get('result', {}).get('result', {}).get('value')


def git(url, bekle=2.2):
    cmd('Page.navigate', {'url': url})
    time.sleep(bekle)


# Kart değerlerini sözlük olarak okur
KART_JS = ("Object.fromEntries([...document.querySelectorAll('.stat')]"
           ".map(e=>[e.querySelector('.etiket').textContent.trim(),"
           "e.querySelector('.deger').textContent.trim()]))")
SATIR_JS = "document.querySelectorAll('#cizelge-govde tr').length"


def kartlar():
    return ev(KART_JS) or {}


def tikla(etiket):
    ev("[...document.querySelectorAll('.stat')].find(e=>"
       f"e.querySelector('.etiket').textContent.trim()=='{etiket}').click()")
    time.sleep(2.2)


# ---------------------------------------------------------------
git(B + '/giris')
ev("document.querySelector('[name=kimlik]').value='admin';"
   "document.querySelector('[name=sifre]').value='Test1234';"
   "document.querySelector('form').submit()")
time.sleep(2.5)
ol('Giriş yapıldı', '/panel', ev('location.pathname'))

print('=== 1) KARTLAR TÜR + AY FİLTRESİNİ İZLİYOR ===')
git(B + '/takip?yil=2026&ay=8&tur_id=1&mod=beyan', 2.5)
kdv = kartlar()
ol('Ağustos+KDV1 Toplam=3', '3', kdv.get('Toplam'))
ol('Ağustos+KDV1 Onaylandı=2', '2', kdv.get('Onaylandı'))
ol('Ağustos+KDV1 Hazır=1', '1', kdv.get('Hazır'))
ol('Liste 3 satır', 3, ev(SATIR_JS))

git(B + '/takip?yil=2026&ay=8&tur_id=4&mod=beyan', 2.5)
sgk = kartlar()
ol('Tür değişince sayaç değişti', True, kdv.get('Onaylandı') != sgk.get('Onaylandı'))
ol('Ağustos+MUHSGK Bekliyor=2', '2', sgk.get('Bekliyor'))

print('=== 2) KARTA TIKLAYINCA LİSTE SÜZÜLÜYOR ===')
git(B + '/takip?yil=2026&ay=8&tur_id=1&mod=beyan', 2.5)
tikla('Onaylandı')
ol('URL durum=ONAYLANDI aldı', True, 'durum=ONAYLANDI' in (ev('location.search') or ''))
ol('URL tür filtresini korudu', True, 'tur_id=1' in (ev('location.search') or ''))
ol('URL ay filtresini korudu', True, 'ay=8' in (ev('location.search') or ''))
ol('Liste 2 satıra düştü', 2, ev(SATIR_JS))
ol('Seçili kart Onaylandı', 'Onaylandı',
   (ev("document.querySelector('.stat.secili .etiket')?.textContent") or '').strip())
ol('Durum kutusu ONAYLANDI', 'ONAYLANDI', ev("document.querySelector('[name=durum]').value"))

sonra = kartlar()
ol('Süzgeç açıkken Toplam hâlâ 3', '3', sonra.get('Toplam'))
ol('Süzgeç açıkken Hazır hâlâ 1', '1', sonra.get('Hazır'))
ol('Süzgeç notu görünüyor', True, bool(ev("!!document.querySelector('.ozet-not')")))
ol('Soluk kart var', True, bool(ev("!!document.querySelector('.stat.sonuk')")))

print('=== 3) AYNI KARTA TEKRAR TIKLAYINCA SÜZGEÇ KALKIYOR ===')
tikla('Onaylandı')
ol('durum parametresi kalktı', False, 'durum=' in (ev('location.search') or ''))
ol('Liste 3 satıra döndü', 3, ev(SATIR_JS))
ol('Süzgeç notu kayboldu', False, bool(ev("!!document.querySelector('.ozet-not')")))

print('=== 4) GECİKMİŞ KARTI ===')
git(B + '/takip?yil=2026&ay=0&mod=beyan', 2.5)
tikla('Gecikmiş')
ol('gecikmis=1 uygulandı', True, 'gecikmis=1' in (ev('location.search') or ''))
ol('Gecikmiş onay kutusu işaretli', True, bool(ev("document.querySelector('[name=gecikmis]').checked")))
ol('Tüm Aylar korundu (ay=0 tuzağı)', '0', ev("document.querySelector('[name=ay]').value"))
ol('Kartlar hâlâ 108 sayıyor', '108', kartlar().get('Toplam'))

print('=== 5) TOPLAM KARTI SÜZGECİ TEMİZLER ===')
tikla('Toplam')
ol('gecikmis kalktı', False, 'gecikmis' in (ev('location.search') or ''))
ol('Toplam kartı seçili', 'Toplam',
   (ev("document.querySelector('.stat.secili .etiket')?.textContent") or '').strip())

print('=== 6) KARTLAR GÖRSEL OLARAK BAĞLANTI ===')
git(B + '/takip?yil=2026&ay=8&tur_id=1&mod=beyan', 2.5)
ol('Kartlar <a> etiketi', 6, ev("document.querySelectorAll('a.stat').length"))
ol('İmleç pointer', 'pointer',
   ev("getComputedStyle(document.querySelector('a.stat')).cursor"))
ol('Kart başlığı (title) var', True,
   bool(ev("!!document.querySelector('a.stat').title")))

print()
print('================================================')
print(f'  GEÇEN: {g}    KALAN: {k}    TOPLAM: {g + k}')
print('================================================')
ws.close()
raise SystemExit(0 if k == 0 else 1)
