#!/usr/bin/env python3
"""
Sayfalama / sonsuz kaydırma — tarayıcı (chromium + CDP) uçtan uca testi.

Senaryo:
  1. Beyanname takip: ilk parça yükleniyor, sayaç doğru
  2. Aşağı kaydırınca kendiliğinden yeni satırlar ekleniyor
  3. "Daha Fazla Yükle" düğmesi çalışıyor
  4. Yüklenen satırlarda durum değiştirme (yeni satıra olay bağlandı mı)
  5. "Filtredeki tüm kayıtları seç" çalışıyor
  6. Sayfa başına adet değiştirme
  7. Mükellef alfabe şeridi
  8. Konsol hatası olmamalı

Kullanım:  python3 tests/tarayici_sayfalama.py
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
            '--hide-scrollbars', '--window-size=1600,900', '--remote-allow-origins=*',
            '--user-data-dir=/tmp/cdp-sayfa', 'about:blank',
        ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        hedef = None
        for _ in range(60):
            try:
                with urllib.request.urlopen(f'http://127.0.0.1:{port}/json') as r:
                    hedef = next((s for s in json.load(r) if s['type'] == 'page'), None)
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
                a = m['params']['exceptionDetails']
                self.konsol_hatalari.append(
                    a.get('text', '') + ' ' + str(a.get('exception', {}).get('description', '')))
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
            a = s['exceptionDetails']
            self.konsol_hatalari.append(
                str(a.get('exception', {}).get('description', a)))
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

    def ekran(self, yol):
        s = self.cagir('Page.captureScreenshot', format='png')
        with open(yol, 'wb') as f:
            f.write(base64.b64decode(s['data']))

    def kapat(self):
        try:
            self.ws.close()
        finally:
            self.proc.terminate()


def bekle_kosul(t, ifade, sn=15):
    son = time.time() + sn
    while time.time() < son:
        if t.js(ifade) is True:
            return True
        time.sleep(0.3)
    return False


def main():
    t = Tarayici()
    try:
        t.git(f'{TABAN}/giris')
        t.js(f"""(function(){{var f=document.querySelector('form');
            f.querySelector('[name=kimlik]').value={json.dumps(KULLANICI)};
            f.querySelector('[name=sifre]').value={json.dumps(SIFRE)};
            f.submit();return true}})()""")
        time.sleep(1.6)

        # ---------- 1) İlk yükleme ----------
        print('=== 1) Beyanname takip — ilk parça ===')
        t.git(f'{TABAN}/takip?ay=0&yil=2026&adet=100')
        satir = t.js("document.querySelectorAll('#cizelge-govde tr').length")
        toplam = t.js("parseInt(document.getElementById('kaydir-alani').dataset.toplam,10)")
        ol('İlk parçada 100 satır', 100, satir)
        ol('Toplam kayıt > 100', True, toplam > 100)
        ol('Sayaç doğru', '100', t.js("document.getElementById('gosterilen-sayi').innerText"))
        ol('"Daha Fazla" düğmesi görünür', True,
           t.js("document.getElementById('daha-fazla-btn').style.display !== 'none'"))
        t.ekran('/tmp/sp_ilk.png')

        # ---------- 2) Düğmeyle yükleme ----------
        print('\n=== 2) "Daha Fazla Yükle" düğmesi ===')
        t.js('dahaFazlaYukle(); true')
        ol('200 satıra çıktı', True,
           bekle_kosul(t, "document.querySelectorAll('#cizelge-govde tr').length === 200"))
        ol('Sayaç güncellendi', '200', t.js("document.getElementById('gosterilen-sayi').innerText"))
        ol('Çakışma yok (benzersiz id)', True, t.js("""
            (function(){
              var v = Array.from(document.querySelectorAll('.satir-sec')).map(function(c){return c.value});
              return v.length === new Set(v).size;
            })()
        """))

        # ---------- 3) Kaydırarak otomatik yükleme ----------
        print('\n=== 3) Aşağı kaydırınca otomatik yükleme ===')
        onceki = t.js("document.querySelectorAll('#cizelge-govde tr').length")
        t.js('window.scrollTo(0, document.body.scrollHeight); true')
        ol('Kaydırınca kendiliğinden yüklendi', True,
           bekle_kosul(t,
                       f"document.querySelectorAll('#cizelge-govde tr').length > {onceki}"))
        yeni = t.js("document.querySelectorAll('#cizelge-govde tr').length")
        print(f'   {onceki} → {yeni} satır')

        # ---------- 4) Yeni satırda durum değiştirme ----------
        print('\n=== 4) Sonradan yüklenen satırda durum değiştirme ===')
        ol('Yeni satırlara olay bağlandı', True, t.js("""
            (function(){
              var s = document.querySelectorAll('#cizelge-govde .durum-sec');
              var son = s[s.length - 1];
              return son.dataset.bagli === '1';
            })()
        """))
        sonuc = t.js("""
            (function(){
              var s = document.querySelectorAll('#cizelge-govde .durum-sec');
              var son = s[s.length - 1];
              window.__testId = son.dataset.id;
              window.__testEski = son.value;
              son.value = son.value === 'HAZIR' ? 'BEKLIYOR' : 'HAZIR';
              son.dispatchEvent(new Event('change', {bubbles:true}));
              return son.dataset.id;
            })()
        """)
        ol('Durum değişikliği sunucuya gitti', True,
           bekle_kosul(t, "document.body.innerText.indexOf('Durum güncellendi') > -1"))
        print(f'   test edilen kayıt id={sonuc}')

        # ---------- 5) Filtredeki tümünü seç ----------
        print('\n=== 5) Filtredeki tüm kayıtları seç ===')
        t.js("BT.modalKapat(); document.querySelectorAll('.modal-arka').forEach(function(m){m.classList.remove('acik')}); true")
        t.js("""
            (function(){
              document.querySelectorAll('.satir-sec').forEach(function(c){c.checked=true});
              BT.secimGuncelle();
              return true;
            })()
        """)
        time.sleep(0.5)
        ol('"Filtredeki hepsini seç" bağlantısı çıktı', False,
           t.js("document.getElementById('tum-filtre-alani').classList.contains('gizle')"))

        t.js('tumFiltreyiSec(); true')
        ol('Tüm kayıtlar seçildi', True,
           bekle_kosul(t, "window.__tumFiltreSecili === true"))
        secili = t.js("(window.__tumFiltreIdler||[]).length")
        ol('Seçilen adet = toplam kayıt', toplam, secili)
        ol('Sayaç toplamı gösteriyor', str(toplam),
           t.js("document.getElementById('secili-sayi').innerText"))
        t.ekran('/tmp/sp_secim.png')

        t.js('secimiTemizle(); true')
        time.sleep(0.3)
        ol('Seçim temizlendi', 0,
           t.js("document.querySelectorAll('.satir-sec:checked').length"))

        # ---------- 6) Adet değiştirme ----------
        print('\n=== 6) Sayfa başına adet ===')
        t.git(f'{TABAN}/takip?ay=0&yil=2026&adet=25')
        ol('25 kayıt yüklendi', 25,
           t.js("document.querySelectorAll('#cizelge-govde tr').length"))
        ol('Seçici 25 gösteriyor', '25', t.js("document.getElementById('adet-sec').value"))

        # ---------- 7) Alfabe şeridi ----------
        print('\n=== 7) Mükellef alfabe şeridi ===')
        t.git(f'{TABAN}/mukellefler')
        ol('Şerit var', True, t.js("!!document.querySelector('.alfabe')"))
        ol('30 harf düğmesi (29 + #)', 31,
           t.js("document.querySelectorAll('.alfabe a').length"))  # +1 = "Tümü"

        t.js("""(function(){
            var a = Array.from(document.querySelectorAll('.alfabe a'))
                     .filter(function(x){return x.innerText.trim().indexOf('Ş')===0});
            if(a.length){ a[0].click(); return true; } return false;
        })()""")
        time.sleep(1.2)
        t.bekle_hazir()
        ol('Ş harfi seçildi', True, "harf=" in (t.js('location.search') or ''))
        ol('Filtre uyarısı çıktı', True,
           t.js("document.body.innerText.indexOf('ile başlayan') > -1"))
        unvanlar = t.js("""
            Array.from(document.querySelectorAll('td a.kalin')).map(function(a){return a.innerText.trim()})
        """) or []
        print('   listelenen:', unvanlar[:5])
        ol('Hepsi Ş ile başlıyor (S değil)', True,
           all(u.startswith('Ş') for u in unvanlar) if unvanlar else False)
        t.ekran('/tmp/sp_alfabe.png')

        # ---------- 8) Konsol ----------
        print('\n=== 8) Konsol ===')
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
