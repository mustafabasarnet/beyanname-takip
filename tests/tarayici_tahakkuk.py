#!/usr/bin/env python3
"""
Tarayıcı (chromium headless + CDP) ile uçtan uca tahakkuk/durum senaryosu.

Senaryo:
  1. Giriş yap, takip çizelgesini aç
  2. Durum -> Onaylandı  => tahakkuk penceresi açılır
  3. Tutar gir + Kaydet  => hücrede tutar + damga görünür
  4. Durum -> Bekliyor   => "Tahakkuk bilgisi ne olsun?" penceresi çıkar
  5a. "Kalsın"           => hücre soluk + "pasif" rozeti
  5b. Tekrar Onaylandı   => "pasif" kalkar
  6. Durum -> Hazır, bu kez "Evet, Sil"  => hücre "—" olur, DB temizlenir
  7. Konsol hatası olmamalı

Kullanım:  python3 tests/tarayici_tahakkuk.py
"""
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
        self.port = port
        self.proc = subprocess.Popen([
            'chromium', '--headless=new', f'--remote-debugging-port={port}',
            '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
            '--hide-scrollbars', '--window-size=1600,1000', '--remote-allow-origins=*',
            '--user-data-dir=/tmp/cdp-profil', 'about:blank',
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

    def cagir(self, yontem, **params):
        self.id += 1
        self.ws.send(json.dumps({'id': self.id, 'method': yontem, 'params': params}))
        while True:
            m = json.loads(self.ws.recv())
            if m.get('method') == 'Runtime.exceptionThrown':
                ayr = m['params']['exceptionDetails']
                self.konsol_hatalari.append(ayr.get('text', '') + ' ' +
                                            str(ayr.get('exception', {}).get('description', '')))
            elif m.get('method') == 'Log.entryAdded' and m['params']['entry']['level'] == 'error':
                girdi = m['params']['entry']
                # favicon / debugbar gibi uygulamayla ilgisiz istekler sayılmaz
                if 'favicon' not in girdi.get('url', '') and 'debugbar' not in girdi.get('url', ''):
                    self.konsol_hatalari.append(girdi['text'] + ' <' + girdi.get('url', '') + '>')
            elif m.get('id') == self.id:
                return m.get('result', {})

    def js(self, ifade):
        s = self.cagir('Runtime.evaluate', expression=ifade,
                       returnByValue=True, awaitPromise=True)
        if 'exceptionDetails' in s:
            ayr = s['exceptionDetails']
            self.konsol_hatalari.append(str(ayr.get('exception', {}).get('description', ayr)))
            return None
        return s.get('result', {}).get('value')

    def git(self, url):
        self.cagir('Page.navigate', url=url)
        self.bekle_hazir()

    def bekle_hazir(self, sn=15):
        son = time.time() + sn
        while time.time() < son:
            if self.js('document.readyState') == 'complete':
                time.sleep(0.35)
                return True
            time.sleep(0.2)
        return False

    def ekran(self, yol):
        s = self.cagir('Page.captureScreenshot', format='png', captureBeyondViewport=True)
        import base64
        with open(yol, 'wb') as f:
            f.write(base64.b64decode(s['data']))

    def kapat(self):
        try:
            self.ws.close()
        finally:
            self.proc.terminate()


def bekle(t, kosul, sn=10):
    son = time.time() + sn
    while time.time() < son:
        if t.js(kosul) is True:
            return True
        time.sleep(0.25)
    return False


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
        time.sleep(1.5)
        t.git(f'{TABAN}/takip?ay=0&yil=2026')
        ol('Çizelge açıldı', True, t.js("!!document.querySelector('.durum-sec')"))

        ilk = t.js("document.querySelector('.durum-sec').dataset.id")
        print(f'  test edilen kayıt id={ilk}')

        secme = f"""
        (function(){{
          var s = document.querySelector('.durum-sec[data-id="{ilk}"]');
          s.value = '%s';
          s.dispatchEvent(new Event('change', {{bubbles:true}}));
          return true;
        }})()
        """

        # ---------- 2) Onaylandı -> tahakkuk penceresi ----------
        print('\n=== 1) Onaylandı seçilince tahakkuk penceresi ===')
        t.js(secme % 'ONAYLANDI')
        ol('Tahakkuk penceresi açıldı', True,
           bekle(t, "document.getElementById('tahakkuk-modal').classList.contains('acik')"))

        # ---------- 3) Tutar gir + kaydet ----------
        print('\n=== 2) Tutar girip kaydetme ===')
        t.js("""
          (function(){
            var i = document.getElementById('th-tutar');
            i.value = '2.500,00';
            i.dispatchEvent(new Event('input', {bubbles:true}));
            tahakkukKaydet(); return true;
          })()
        """)
        ol('Pencere kapandı', True,
           bekle(t, "!document.getElementById('tahakkuk-modal').classList.contains('acik')"))
        hucre = f".tahakkuk-hucre[data-id='{ilk}']"
        ol('Hücrede tutar var', True,
           t.js(f"document.querySelector(\"{hucre}\").innerText.indexOf('2.500,00') > -1"))
        ol('Hücrede damga var', True,
           t.js(f"document.querySelector(\"{hucre}\").innerText.indexOf('damga') > -1"))
        ol('Hücre pasif DEĞİL', False,
           t.js(f"document.querySelector(\"{hucre}\").classList.contains('atil')"))

        # ---------- 4) Durum geri al -> onay penceresi ----------
        print('\n=== 3) Bekliyor seçilince "silinsin mi?" sorusu ===')
        t.js(secme % 'BEKLIYOR')
        ol('Onay penceresi açıldı', True,
           bekle(t, "document.getElementById('th-onay-modal').classList.contains('acik')"))
        ol('Metinde tutar geçiyor', True,
           t.js("document.getElementById('th-onay-metin').innerText.indexOf('2.500,00') > -1"))

        # ---------- 5a) Kalsın ----------
        print('\n=== 4) "Kalsın" seçeneği ===')
        t.js('thOnayKapat(); true')
        time.sleep(0.6)
        ol('Onay penceresi kapandı', False,
           t.js("document.getElementById('th-onay-modal').classList.contains('acik')"))
        ol('Hücre artık pasif (soluk)', True,
           t.js(f"document.querySelector(\"{hucre}\").classList.contains('atil')"))
        ol('"pasif" uyarısı görünüyor', True,
           t.js(f"document.querySelector(\"{hucre}\").innerText.indexOf('pasif') > -1"))
        ol('Tutar hâlâ ekranda', True,
           t.js(f"document.querySelector(\"{hucre}\").innerText.indexOf('2.500,00') > -1"))
        t.ekran('/tmp/ss_pasif.png')

        # ---------- 5b) Tekrar onayla -> pasif kalkar ----------
        print('\n=== 5) Tekrar Onaylandı -> pasif kalkar ===')
        t.js(secme % 'ONAYLANDI')
        bekle(t, "document.getElementById('tahakkuk-modal').classList.contains('acik')")
        ol('Pencerede eski tutar dolu', '2.500,00', t.js("document.getElementById('th-tutar').value"))
        ol('"Tahakkuku Sil" düğmesi görünür', False,
           t.js("document.getElementById('th-sil-btn').classList.contains('gizle')"))
        t.js("BT.modalKapat('tahakkuk-modal'); true")
        time.sleep(0.4)
        ol('Hücre pasif değil', False,
           t.js(f"document.querySelector(\"{hucre}\").classList.contains('atil')"))

        # ---------- 6) Hazır + Sil ----------
        print('\n=== 6) Hazır seçip "Evet, Sil" ===')
        t.js(secme % 'HAZIR')
        ol('Onay penceresi açıldı', True,
           bekle(t, "document.getElementById('th-onay-modal').classList.contains('acik')"))
        t.js('thOnaySil(); true')
        ol('Onay penceresi kapandı', True,
           bekle(t, "!document.getElementById('th-onay-modal').classList.contains('acik')"))
        ol('Hücre "—" oldu', True,
           t.js(f"document.querySelector(\"{hucre}\").innerText.indexOf('—') > -1"))
        ol('Tutar silindi', False,
           t.js(f"document.querySelector(\"{hucre}\").innerText.indexOf('2.500,00') > -1"))
        ol('pasif uyarısı yok', False,
           t.js(f"document.querySelector(\"{hucre}\").innerText.indexOf('pasif') > -1"))

        # ---------- 7) Sayfa yenile: sunucu da aynı şeyi göstermeli ----------
        print('\n=== 7) Sayfa yenilendikten sonra (sunucu tarafı) ===')
        t.git(f'{TABAN}/takip?ay=0&yil=2026')
        ol('Yenilemede de boş', True,
           t.js(f"document.querySelector(\"{hucre}\").innerText.indexOf('—') > -1"))

        # ---------- 8) Ardışık durum değişimi (regresyon) ----------
        print('\n=== 8) Ardışık durum değişimi — JS hatası olmamalı ===')
        for d in ['ONAYLANDI', 'BEKLIYOR', 'HAZIR', 'ONAYLANDI', 'VERILMEYECEK', 'BEKLIYOR']:
            t.js(secme % d)
            time.sleep(0.7)
            t.js("BT.modalKapat('tahakkuk-modal'); BT.modalKapat('th-onay-modal'); true")
            time.sleep(0.2)

        t.ekran('/tmp/ss_son.png')
        gercek_hatalar = [h for h in t.konsol_hatalari
                          if 'favicon' not in h.lower() and 'debugbar' not in h.lower()]
        ol('Konsol hatası yok', 0, len(gercek_hatalar))
        for h in gercek_hatalar:
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
