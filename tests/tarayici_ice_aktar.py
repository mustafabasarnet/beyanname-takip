#!/usr/bin/env python3
"""
Excel/CSV'den mükellef aktarma — tarayıcı (chromium + CDP) uçtan uca testi.

Senaryo:
  1. Mükellef listesinden "Excel'den Aktar" düğmesi görünüyor mu
  2. Şablon indirme bağlantıları var mı
  3. Dosya seçip "Yükle ve Önizle" → önizleme ekranı
  4. Sayaçlar doğru mu, satır durumları doğru mu
  5. "Hiçbirini Seçme" → onay düğmesi kilitlenir
  6. Tek satır seç → sayaç güncellenir
  7. Onayla → mükellef listesine döner, yeni kayıt görünür
  8. Konsol hatası olmamalı

Kullanım:  python3 tests/tarayici_ice_aktar.py
"""
import base64
import json
import os
import subprocess
import sys
import time
import urllib.request

import websocket  # type: ignore

TABAN = os.environ.get('BT_URL', 'http://127.0.0.1:8099')
KULLANICI = os.environ.get('BT_USER', 'admin')
SIFRE = os.environ.get('BT_PASS', 'Test1234')
CSV_YOLU = os.environ.get('BT_CSV', '/tmp/zor.csv')

gecen = 0
kalan_hata = 0


def ol(baslik, beklenen, gercek):
    global gecen, kalan_hata
    if str(beklenen) == str(gercek):
        print(f'  [OK] {baslik}')
        gecen += 1
    else:
        print(f'  [HATA] {baslik} (bekl:{beklenen} ger:{gercek})')
        kalan_hata += 1


class Tarayici:
    def __init__(self, port=9222):
        self.proc = subprocess.Popen([
            'chromium', '--headless=new', f'--remote-debugging-port={port}',
            '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
            '--hide-scrollbars', '--window-size=1700,1100', '--remote-allow-origins=*',
            '--user-data-dir=/tmp/cdp-ia', 'about:blank',
        ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        hedef = None
        for _ in range(60):
            try:
                with urllib.request.urlopen(f'http://127.0.0.1:{port}/json') as r:
                    sekmeler = json.load(r)
                hedef = next((s for s in sekmeler if s['type'] == 'page'), None)
                if hedef:
                    break
            except Exception:
                time.sleep(0.4)
        if not hedef:
            raise RuntimeError('Chromium başlatılamadı')

        self.ws = websocket.create_connection(hedef['webSocketDebuggerUrl'], timeout=30)
        self.id = 0
        self.konsol_hatalari = []
        self.cagir('Page.enable')
        self.cagir('Runtime.enable')
        self.cagir('Log.enable')
        self.cagir('DOM.enable')

    def cagir(self, yontem, **params):
        self.id += 1
        self.ws.send(json.dumps({'id': self.id, 'method': yontem, 'params': params}))
        while True:
            m = json.loads(self.ws.recv())
            if m.get('method') == 'Runtime.exceptionThrown':
                ayr = m['params']['exceptionDetails']
                self.konsol_hatalari.append(
                    ayr.get('text', '') + ' '
                    + str(ayr.get('exception', {}).get('description', '')))
            elif m.get('method') == 'Log.entryAdded' and m['params']['entry']['level'] == 'error':
                g = m['params']['entry']
                if 'favicon' not in g.get('url', '') and 'debugbar' not in g.get('url', ''):
                    self.konsol_hatalari.append(g['text'] + ' <' + g.get('url', '') + '>')
            elif m.get('id') == self.id:
                return m.get('result', {})

    def js(self, ifade):
        s = self.cagir('Runtime.evaluate', expression=ifade,
                       returnByValue=True, awaitPromise=True)
        if 'exceptionDetails' in s:
            ayr = s['exceptionDetails']
            self.konsol_hatalari.append(
                str(ayr.get('exception', {}).get('description', ayr)))
            return None
        return s.get('result', {}).get('value')

    def git(self, url):
        self.cagir('Page.navigate', url=url)
        self.bekle_hazir()

    def bekle_hazir(self, sn=20):
        son = time.time() + sn
        while time.time() < son:
            if self.js('document.readyState') == 'complete':
                time.sleep(0.4)
                return True
            time.sleep(0.2)
        return False

    def dosya_sec(self, secici, yol):
        """Gerçek <input type=file> alanına dosya yükler (CDP DOM.setFileInputFiles)."""
        kok = self.cagir('DOM.getDocument')['root']['nodeId']
        dugum = self.cagir('DOM.querySelector', nodeId=kok, selector=secici)['nodeId']
        self.cagir('DOM.setFileInputFiles', files=[yol], nodeId=dugum)

    def ekran(self, yol):
        s = self.cagir('Page.captureScreenshot', format='png', captureBeyondViewport=True)
        with open(yol, 'wb') as f:
            f.write(base64.b64decode(s['data']))

    def kapat(self):
        try:
            self.ws.close()
        finally:
            self.proc.terminate()


def main():
    t = Tarayici()
    try:
        # ---------- Giriş ----------
        t.git(f'{TABAN}/giris')
        t.js(f"""
            (function(){{
              var f = document.querySelector('form');
              f.querySelector('[name=kimlik]').value = {json.dumps(KULLANICI)};
              f.querySelector('[name=sifre]').value  = {json.dumps(SIFRE)};
              f.submit(); return true;
            }})()
        """)
        time.sleep(1.6)

        # ---------- 1) Liste ekranındaki düğme ----------
        print('=== 1) Mükellef listesindeki aktarma düğmesi ===')
        t.git(f'{TABAN}/mukellefler')
        ol('"Excel’den Aktar" düğmesi var', True,
           t.js("!!document.querySelector('a[href$=\"mukellefler/ice-aktar\"]')"))

        # ---------- 2) Aktarma ekranı ----------
        print('\n=== 2) Aktarma ekranı ===')
        t.git(f'{TABAN}/mukellefler/ice-aktar')
        ol('Örnekli şablon bağlantısı', True,
           t.js("!!document.querySelector('a[href$=\"sablon-indir\"]')"))
        ol('Boş şablon bağlantısı', True,
           t.js("!!document.querySelector('a[href*=\"sablon-indir?bos=1\"]')"))
        ol('Dosya alanı var', True, t.js("!!document.getElementById('dosya')"))
        ol('Beyanname kod listesi var', True,
           t.js("document.body.innerText.indexOf('KURUM_GECICI') > -1"))
        t.ekran('/tmp/ia_form.png')

        # ---------- 3) Dosya yükle ----------
        print('\n=== 3) Dosya yükleyip önizleme ===')
        t.dosya_sec('#dosya', CSV_YOLU)
        ol('Dosya seçildi', 1, t.js("document.getElementById('dosya').files.length"))
        t.js("document.getElementById('aktar-form').submit(); true")
        time.sleep(2.0)
        t.bekle_hazir()

        ol('Önizleme ekranı açıldı', True,
           t.js("document.body.innerText.indexOf('Aktarma Önizlemesi') > -1"))

        sayac = t.js("""
          (function(){
            var o = {};
            document.querySelectorAll('.stat').forEach(function(s){
              o[s.querySelector('.etiket').innerText.trim()] =
                s.querySelector('.deger').innerText.trim();
            });
            return JSON.stringify(o);
          })()
        """)
        print('   sayaçlar:', sayac)
        # Not: CSS text-transform:uppercase uyguladığı için innerText büyük harf gelir
        s = {k.upper(): v for k, v in json.loads(sayac).items()}
        ol('Eklenecek = 8', '8', s.get('EKLENECEK'))
        ol('Atlanacak = 2', '2', s.get('ATLANACAK'))
        ol('Hatalı = 4', '4', s.get('HATALI'))

        ol('Mevcut kayıt uyarısı görünüyor', True,
           t.js("document.body.innerText.indexOf('Sistemde zaten kayıtlı') > -1"))
        ol('Mükerrer uyarısı görünüyor', True,
           t.js("document.body.innerText.indexOf('mükerrer') > -1"))
        ol('Hatalı satır işaretlenemiyor', 8,
           t.js("document.querySelectorAll('.satir-onay').length"))
        t.ekran('/tmp/ia_onizleme.png')

        # ---------- 4) Seçim mantığı ----------
        print('\n=== 4) Seçim davranışı ===')
        t.js('tumunuSec(false); true')
        time.sleep(0.3)
        ol('Hiçbiri seçili değilken düğme kilitli', True,
           t.js("document.getElementById('onayla-btn').disabled"))
        ol('Sayaç 0', '0', t.js("document.getElementById('secili-sayi').innerText"))

        t.js("""
          (function(){
            var k = document.querySelectorAll('.satir-onay');
            k[0].checked = true; k[0].dispatchEvent(new Event('change'));
            k[1].checked = true; k[1].dispatchEvent(new Event('change'));
            return true;
          })()
        """)
        time.sleep(0.3)
        ol('2 seçilince sayaç 2', '2', t.js("document.getElementById('secili-sayi').innerText"))
        ol('Düğme açıldı', False, t.js("document.getElementById('onayla-btn').disabled"))

        # ---------- 5) Onayla ----------
        print('\n=== 5) Aktarmayı onayla ===')
        # Dönem üretimini kapat (test hızlansın)
        t.js("document.querySelector('[name=donem_uret]').checked = false; true")
        t.js("document.getElementById('onay-form').submit(); true")
        time.sleep(2.5)
        t.bekle_hazir()

        ol('Mükellef listesine döndü', True,
           t.js("location.pathname.indexOf('/mukellefler') > -1"))
        ol('Başarı mesajı çıktı', True,
           t.js("document.body.innerText.indexOf('mükellef eklendi') > -1"))
        ol('Yeni mükellef listede', True,
           t.js("document.body.innerText.indexOf('ÖZKAN') > -1"))
        t.ekran('/tmp/ia_sonuc.png')

        # ---------- 6) Konsol ----------
        print('\n=== 6) Konsol ===')
        ol('Konsol hatası yok', 0, len(t.konsol_hatalari))
        for h in t.konsol_hatalari:
            print('      >>', h[:200])

    finally:
        t.kapat()

    print('\n======')
    if kalan_hata == 0:
        print(f'BASARILI ({gecen}/{gecen})')
        return 0
    print(f'{kalan_hata} HATA ({gecen}/{gecen + kalan_hata})')
    return 1


if __name__ == '__main__':
    sys.exit(main())
