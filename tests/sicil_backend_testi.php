<?php
/**
 * SİCİL MODÜLÜ (SADE ŞABLON / İŞLEM / TODO) — BACKEND MANTIK TESTİ
 *
 * CI4'ü bootConsole ile başlatıp model katmanını gerçek DB üzerinde sınar.
 * Yalnız sicil tablolarına + TEST_SABLONU şablonuna dokunur; temizlik testin
 * sonunda yapılır.
 */
require __DIR__ . '/beyanname-takip/vendor/autoload.php';
require __DIR__ . '/beyanname-takip/app/Config/Paths.php';

use Config\Paths;
use Config\Services;

$paths = new Paths();

if (! defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

if (! defined('FCPATH')) {
    define('FCPATH', __DIR__ . '/beyanname-takip/public/');
}

\CodeIgniter\Boot::bootConsole($paths);

$db = \Config\Database::connect();
$ok = 0; $fail = 0;

function t($ad, $sart, $detay = '') {
    global $ok, $fail;
    if ($sart) { $ok++; echo "  [OK] $ad\n"; }
    else { $fail++; echo "  [HATA] $ad  -- $detay\n"; }
}

// ---------- ÖN TEMİZLİK (önceki koşum kalıntısı) ----------
$db->query("DELETE FROM sicil_bildirim_kurallari WHERE degisiklik_turu_id IN
             (SELECT id FROM sicil_degisiklik_turleri WHERE kod = 'TEST_SABLONU')");
$db->query("DELETE FROM sicil_degisiklik_turleri WHERE kod = 'TEST_SABLONU'");

echo "=== 1) SÜRE HESAPLAYICI ===\n";
$h = new \App\Libraries\SicilSureHesaplayici();

// 07.09.2026 Pazartesi
$r = $h->hesapla('2026-09-07', 'GUN', 15);
t('GUN+15 = 22.09.2026 (Salı)', $r['son_tarih'] === '2026-09-22', $r['son_tarih']);

// IS_GUNU: 5 iş günü → Pzt 14.09 (hafta sonu atlanır)
$r = $h->hesapla('2026-09-07', 'IS_GUNU', 5);
t('IS_GUNU 5 = 14.09.2026', $r['son_tarih'] === '2026-09-14', $r['son_tarih']);

$r = $h->hesapla('2026-09-07', 'AY', 1);
t('AY+1 = 07.10.2026', $r['son_tarih'] === '2026-10-07', $r['son_tarih']);

// Ay sonu taşması: 31 Mart + 1 ay → 30 Nisan
$r = $h->hesapla('2026-03-31', 'AY', 1);
t('AY taşma 31 Mar+1 = 30.04.2026', $r['son_tarih'] === '2026-04-30', $r['son_tarih']);

// GUN: hafta sonu kaydırma — 10.10.2026 Cumartesi → Pazartesi 12.10
$r = $h->hesapla('2026-10-09', 'GUN', 1);
t('GUN hafta sonu kaydırma 10.10(Cmt)→12.10(Pzt)', $r['son_tarih'] === '2026-10-12' && $r['neden'] !== null, $r['son_tarih'] . ' / ' . ($r['neden'] ?? '-'));

echo "=== 2) ŞABLON KAYDET → TODO TANIMLARI (transaction) ===\n";
$turM = new \App\Models\SicilTurModel();
$mkId = (int) $db->table('mukellefler')->where('deleted_at', null)->get()->getRow()->id;

$sablon = $turM->sablonKaydet(
    ['id' => 0, 'ad' => 'Test Şablonu', 'aciklama' => 'otomatik test', 'aktif' => 1],
    [
        ['id' => 0, 'ad' => 'Vergi Dairesine Bildirim', 'sure_tipi' => 'GUN',      'sure_deger' => '15',  'belirli_tarih' => '', 'aktif' => 1],
        ['id' => 0, 'ad' => 'SGK Bildirimi',             'sure_tipi' => 'IS_GUNU', 'sure_deger' => '5',   'belirli_tarih' => '', 'aktif' => 1],
        ['id' => 0, 'ad' => 'Oda Kaydı',                 'sure_tipi' => 'GUN',      'sure_deger' => '25',  'belirli_tarih' => '', 'aktif' => 1],
    ],
    1
);
t('Şablon oluştu (3 todo tanımı)', $sablon['durum'] === true && $sablon['id'] !== null, $sablon['hata'] ?? '-');
$sablonId = (int) $sablon['id'];

$tanimlar = (new \App\Models\SicilKuralModel())->sablonTodoTanimlari($sablonId);
t('3 todo tanımı kayıtlı', count($tanimlar) === 3, 'gelen: ' . count($tanimlar));

// Aynı şablona iki todo aynı "ad" taşıyabilir (kurum kısıtı yok) — sıra benzersiz
$sira = array_map('intval', array_column($tanimlar, 'oncelik'));
t('Tanımlar sıralı (10,20,30)', $sira === [10, 20, 30], json_encode($sira));

echo "=== 3) İŞLEM OLUŞTUR → TODO ÜRETİMİ (transaction) ===\n";
$degM = new \App\Models\SicilDegisiklikModel();
$db->query('DELETE FROM sicil_degisiklikleri'); // önceki koşum temizliği

$sonuc = $degM->olustur([
    'mukellef_id'       => $mkId,
    'turu_id'           => $sablonId,
    'degisiklik_tarihi' => '2026-09-07',
    'aciklama'          => 'Otomatik test işlemi',
], 1);

t('İşlem oluştu', $sonuc['durum'] === true && $sonuc['id'] !== null, $sonuc['hata'] ?? '-');
$degId = (int) $sonuc['id'];
t('3 todo otomatik üretildi', count($sonuc['olusan_todolar']) === 3, 'gelen: ' . count($sonuc['olusan_todolar']));
t('Todo adları kopyalandı', ($sonuc['olusan_todolar'][0]['ad'] ?? '') === 'Vergi Dairesine Bildirim', json_encode($sonuc['olusan_todolar'][0] ?? []));
t('Üst durum ISLEMDE (Devam Ediyor)', ($db->table('sicil_degisiklikleri')->where('id', $degId)->get()->getRow()->durum ?? '') === 'ISLEMDE');

$todolar = (new \App\Models\SicilGorevModel())->islemTodoListesi($degId);
t('Todo listesi 3 satır', count($todolar) === 3, 'gelen: ' . count($todolar));
$map = [];
foreach ($todolar as $g) { $map[$g['ad']] = $g; }
t('VD son tarih 22.09 (15 gün)', ($map['Vergi Dairesine Bildirim']['son_tarih'] ?? '') === '2026-09-22', $map['Vergi Dairesine Bildirim']['son_tarih'] ?? 'yok');
t('SGK son tarih 14.09 (5 iş günü)', ($map['SGK Bildirimi']['son_tarih'] ?? '') === '2026-09-14', $map['SGK Bildirimi']['son_tarih'] ?? 'yok');
t('Oda son tarih 02.10 (25 gün)', ($map['Oda Kaydı']['son_tarih'] ?? '') === '2026-10-02', $map['Oda Kaydı']['son_tarih'] ?? 'yok');

echo "=== 4) MÜKERRER: üretim tekrar çağrılınca çoğalmaz ===\n";
$kayit  = $degM->find($degId);
$tekrar = (new \App\Models\SicilGorevModel())->islemIcinUret($kayit, 1);
t('İkinci üretim 0 yeni todo', $tekrar === [], 'gelen: ' . count($tekrar));

echo "=== 5) İŞLEM TARİHİ GÜNCELLE → AÇIK TODO YENİDEN HESAPLANIR ===\n";
$sonucU = $degM->guncelle($degId, ['degisiklik_tarihi' => '2026-09-14'], 1); // +1 hafta
t('Güncelleme OK', $sonucU['durum'] === true, $sonucU['hata'] ?? '-');
$g2 = (new \App\Models\SicilGorevModel())->where('sicil_degisikligi_id', $degId)->where('ad', 'Vergi Dairesine Bildirim')->first();
t('VD son tarih yeniden hesaplandı (14.09+15gün=29.09)', ($g2['son_tarih'] ?? '') === '2026-09-29', $g2['son_tarih'] ?? 'yok');

echo "=== 6) TODO DURUMU (checkbox) ===\n";
$gorevM  = new \App\Models\SicilGorevModel();
$vdId    = (int) $g2['id'];

$r = $gorevM->tamamla($vdId, 2);
t('Açık todo tamamlanır (TAMAM)', $r['durum'] === true, $r['mesaj'] ?? '-');
$g = $gorevM->find($vdId);
t('yapan=2 damgalandı', ($g['yapan_id'] ?? null) == 2);
t('tamamlanma tarihi doldu', $g['tamamlanma_tarihi'] !== null);

$r = $gorevM->tamamla($vdId, 2);
t('Aynı durum tekrar (noop) kabul', $r['durum'] === true);

$r = $gorevM->gereksizYap($vdId, 2);
t('TAMAM→GEREKSIZ GEÇERSİZ', $r['durum'] === false, 'mesaj: ' . ($r['mesaj'] ?? '-'));

$r = $gorevM->durumDegistir($vdId, 'BEKLIYOR', 1);
t('TAMAM→BEKLIYOR (geri aç) geçerli', $r['durum'] === true, $r['mesaj'] ?? '-');
$g = $gorevM->find($vdId);
t('Geri açılınca yapan/tamamlanma temizlendi', $g['yapan_id'] === null && $g['tamamlanma_tarihi'] === null);

$r = $gorevM->gereksizYap($vdId, 2);
t('Açık todo takip dışı yapılabilir (GEREKSIZ)', $r['durum'] === true, $r['mesaj'] ?? '-');
$r = $gorevM->durumDegistir($vdId, 'BEKLIYOR', 1);
t('GEREKSIZ→BEKLIYOR (geri al) geçerli', $r['durum'] === true, $r['mesaj'] ?? '-');

// Üst durum türetme: tümünü tamamla → İŞLEM TAMAM
foreach ($gorevM->where('sicil_degisikligi_id', $degId)->findAll() as $gg) {
    $gorevM->durumDegistir((int) $gg['id'], 'TAMAM', 2);
}
$degM->durumTure($degId);
t('Tüm todo bitince işlem TAMAM', ($degM->find($degId)['durum'] ?? '') === 'TAMAM');

echo "=== 7) SÜRESİ GEÇEN / YAKLAŞAN / SAYAÇ ===\n";
$gorevM->durumDegistir($vdId, 'BEKLIYOR', 1);
$gorevM->update($vdId, ['son_tarih' => '2026-08-01', 'durum' => 'BEKLIYOR', 'yapan_id' => null]);

$listeG = $degM->listele(['aralik' => 'gecikti']);
t('Aralık=gecikti: işlem listelenir', count($listeG) === 1, 'gelen: ' . count($listeG));

$say = $gorevM->sayaclar();
t('Sayaç: gecikti>=1', ($say['gecikti'] ?? 0) >= 1, json_encode($say));
t('Sayaç: bekleyen>=1', ($say['bekleyen'] ?? 0) >= 1);
t('Sayaç: tamamlanan>=1 (diğer ikisi tamam)', ($say['tamamlanan'] ?? 0) >= 1);

echo "=== 8) DETAY + GEÇMİŞ + LİSTE ===\n";
$det = $degM->detay($degId);
t('Detay todoları taşıyor', $det !== null && is_array($det['todolar'] ?? null) && count($det['todolar']) === 3);
$gec = $degM->gecmis($mkId, null);
t('Geçmişte 1 işlem', count($gec) === 1);
$satir = $gec[0] ?? [];
t('Liste ilerleme alanları var (toplam/tamam/açık)', isset($satir['toplam_todo'], $satir['tamam_todo'], $satir['acik_todo']), json_encode(array_keys($satir)));
t('Liste en yakın son tarihi hesaplıyor', ($satir['en_yakin_son'] ?? null) === '2026-08-01', $satir['en_yakin_son'] ?? '-');

echo "=== 9) GEREKSIZ ILERLEMEYE DAHIL DEGIL + YUMUŞAK SİLME ===\n";
$degM->guncelle($degId, ['degisiklik_tarihi' => '2026-09-07'], 1);

// Yeni işlem: 3 todo — 1 takip dışı, 1 tamam, 1 açık (geçmiş tarihli)
$s2 = $degM->olustur([
    'mukellef_id'       => $mkId,
    'turu_id'           => $sablonId,
    'degisiklik_tarihi' => '2026-09-01',
    'aciklama'          => 'ilerleme/silme testi',
], 1);
t('İşlem 2 oluştu (3 todo)', $s2['durum'] === true && count($s2['olusan_todolar'] ?? []) === 3);
$degId2 = (int) $s2['id'];

$todos2 = (new \App\Models\SicilGorevModel())->islemTodoListesi($degId2);
$gorevM->durumDegistir((int) $todos2[0]['id'], 'GEREKSIZ', 1);
$gorevM->durumDegistir((int) $todos2[1]['id'], 'TAMAM', 1);
$gorevM->update((int) $todos2[2]['id'], ['son_tarih' => '2020-01-01', 'durum' => 'BEKLIYOR']);

$satir2 = null;
foreach ($degM->listele(['mukellef_id' => $mkId]) as $x) {
    if ((int) $x['id'] === $degId2) { $satir2 = $x; break; }
}
t('toplam_todo GEREKSIZ hariç (3-1=2)', (int) ($satir2['toplam_todo'] ?? -1) === 2, (string) ($satir2['toplam_todo'] ?? 'yok'));
t('tamam_todo = 1', (int) ($satir2['tamam_todo'] ?? -1) === 1);
t('gerek_todo = 1', (int) ($satir2['gerek_todo'] ?? -1) === 1);
t('acik_todo = 1', (int) ($satir2['acik_todo'] ?? -1) === 1);

$onceki = (new \App\Models\SicilGorevModel())->sayaclar();
t('Sayaç silmeden önce gecikti>=1', ($onceki['gecikti'] ?? 0) >= 1, json_encode($onceki));

t('sicilSil (soft) başarılı', $degM->sicilSil($degId2) === true);
$silinen = $degM->find($degId2);
t('Kayıt deleted_at aldı (soft)', ($silinen['deleted_at'] ?? null) !== null);
$kaldiMi = false;
foreach ($degM->listele(['mukellef_id' => $mkId]) as $x) {
    if ((int) $x['id'] === $degId2) { $kaldiMi = true; break; }
}
t('Silinen işlem listede GÖRÜNMEZ', $kaldiMi === false);
$sonraki = (new \App\Models\SicilGorevModel())->sayaclar();
t('Silinen işlemin todoları sayaçlara dahil DEĞİL (gecikti azaldı)', ($sonraki['gecikti'] ?? 0) < ($onceki['gecikti'] ?? 0), json_encode($sonraki));

// Temizlik — soft silinen satırı da kaldır
$db->query('DELETE FROM sicil_degisiklikleri');                       // işlem (todo cascade)

echo "=== 10) TEMİZLİK ===\n";
$db->query('DELETE FROM sicil_bildirim_kurallari WHERE degisiklik_turu_id = ' . $sablonId);
$db->query('DELETE FROM sicil_degisiklik_turleri WHERE id = ' . $sablonId);
t('Temizlendi', true);

echo "\n========================================\n";
echo ($fail === 0 ? "TÜM TESTLER BAŞARILI" : "$fail HATA") . " ($ok geçti / " . ($ok + $fail) . ")\n";
