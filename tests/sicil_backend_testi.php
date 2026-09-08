<?php
/**
 * SİCİL MODÜLÜ — BACKEND MANTIK TESTİ (geliştirme CLI harness)
 * CI4'ü bootConsole ile başlatıp model katmanını gerçek DB üzerinde sınar.
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

// Ay sonu taşması: 31 Mart + 1 ay → 30 Nisan (Nisan 30 gün; Nisan son güne çekilir)
$r = $h->hesapla('2026-03-31', 'AY', 1);
t('AY taşma 31 Mar+1 = 30.04.2026', $r['son_tarih'] === '2026-04-30', $r['son_tarih']);

// BELIRLI_TARIH
$r = $h->hesapla('2026-09-07', 'BELIRLI_TARIH', null, '2026-12-31');
t('BELIRLI_TARIH = 31.12.2026', $r['son_tarih'] === '2026-12-31', $r['son_tarih']);

// GUN: tatil kaydırması — 29.10.2026 (Cumhuriyet Bayramı Perşembe)? takvimde var mı kontrol etme; 10.10.2026 Cumartesi
$r = $h->hesapla('2026-10-09', 'GUN', 1); // 10.10 Cmt → Pazartesi 12.10
t('GUN hafta sonu kaydırma 10.10(Cmt)→12.10(Pzt)', $r['son_tarih'] === '2026-10-12' && $r['neden'] !== null, $r['son_tarih'] . ' / ' . ($r['neden'] ?? '-'));

echo "=== 2) KURAL KAYDET + ÇİFT KORUMA ===\n";
$kuralM = new \App\Models\SicilKuralModel();
$tur1 = 1; // ADRES (seed)
$kurVD = (int) $db->table('kurumlar')->where('kod','VERGI_DAIRESI')->get()->getRow()->id;
$kurSGK = (int) $db->table('kurumlar')->where('kod','SGK')->get()->getRow()->id;

$db->query('DELETE FROM sicil_bildirim_kurallari');

$s1 = $kuralM->kaydet(['degisiklik_turu_id'=>$tur1,'kurum_id'=>$kurVD,'sure_tipi'=>'GUN','sure_deger'=>15,'aktif'=>1]);
t('Kural 1 kaydedildi', $s1['durum'] === true);
$s2 = $kuralM->kaydet(['degisiklik_turu_id'=>$tur1,'kurum_id'=>$kurVD,'sure_tipi'=>'AY','sure_deger'=>1,'aktif'=>1]);
t('Aynı (tür,kurum) 2. kural REDDEDİLİR', $s2['durum'] === false && $s2['hata'] !== null, $s2['hata'] ?? '-');
$s3 = $kuralM->kaydet(['degisiklik_turu_id'=>$tur1,'kurum_id'=>$kurSGK,'sure_tipi'=>'IS_GUNU','sure_deger'=>5,'aktif'=>1]);
t('Kural 2 (SGK) kaydedildi', $s3['durum'] === true);

echo "=== 3) DEĞİŞİKLİK OLUŞTUR → GÖREV ÜRETİMİ (transaction) ===\n";
$degM = new \App\Models\SicilDegisiklikModel();
$mkId = (int) $db->table('mukellefler')->where('deleted_at', null)->get()->getRow()->id;

$db->query('DELETE FROM sicil_degisiklikleri');
$sonuc = $degM->olustur([
    'mukellef_id' => $mkId,
    'turu_id' => $tur1,
    'degisiklik_tarihi' => '2026-09-07',
    'eski_deger' => 'Eski Cad. No:5',
    'yeni_deger' => 'Yeni Cad. No:10',
    'aciklama' => 'Taşınma',
], 1);

t('Değişiklik oluştu', $sonuc['durum'] === true && $sonuc['id'] !== null, $sonuc['hata'] ?? '-');
$degId = $sonuc['id'];
t('2 görev üretildi (VD+SGK)', count($sonuc['olusan_gorevler']) === 2, 'gelen: ' . count($sonuc['olusan_gorevler']));
t('Üst durum ISLEMDE', ($db->table('sicil_degisiklikleri')->where('id',$degId)->get()->getRow()->durum ?? '') === 'ISLEMDE');

// Görevlerin son tarihleri doğru mu?
$gorevler = (new \App\Models\SicilGorevModel())->where('sicil_degisikligi_id',$degId)->findAll();
$map = [];
foreach ($gorevler as $g) { $map[(int)$g['kurum_id']] = $g; }
t('VD son tarih 22.09 (15 gün)', ($map[$kurVD]['son_tarih'] ?? '') === '2026-09-22', $map[$kurVD]['son_tarih'] ?? 'yok');
t('SGK son tarih 14.09 (5 iş günü)', ($map[$kurSGK]['son_tarih'] ?? '') === '2026-09-14', $map[$kurSGK]['son_tarih'] ?? 'yok');

echo "=== 4) MÜKERRER: aynı üretim tekrar çağrılınca çoğalmaz ===\n";
$kayit = $degM->find($degId);
$tekrar = (new \App\Models\SicilGorevModel())->degisiklikIcinUret($kayit, 1);
t('İkinci üretim 0 yeni görev', $tekrar === [], 'gelen: ' . count($tekrar));

echo "=== 5) DEĞİŞİKLİK GÜNCELLE → AÇIK GÖREV YENİDEN HESAPLANIR ===\n";
$sonucU = $degM->guncelle($degId, ['degisiklik_tarihi' => '2026-09-14'], 1); // +1 hafta
t('Güncelleme OK', $sonucU['durum'] === true, $sonucU['hata'] ?? '-');
$g2 = (new \App\Models\SicilGorevModel())->where('sicil_degisikligi_id',$degId)->where('kurum_id',$kurVD)->first();
t('VD son tarih güncellendi (14.09+15gün=29.09)', ($g2['son_tarih'] ?? '') === '2026-09-29', $g2['son_tarih'] ?? 'yok');

echo "=== 6) DURUM GEÇİŞLERİ ===\n";
$gorevM = new \App\Models\SicilGorevModel();
$vdGorevId = (int) $g2['id'];

// Geçiş zinciri: BEKLIYOR → HAZIR → GONDERILDI → TAMAM
$r = $gorevM->durumDegistir($vdGorevId, 'HAZIR', 2);
t('BEKLIYOR→HAZIR geçerli', $r['durum'] === true, $r['mesaj'] ?? '-');

$r = $gorevM->durumDegistir($vdGorevId, 'GONDERILDI', 2); // personel 2
t('HAZIR→GONDERILDI geçerli', $r['durum'] === true, $r['mesaj'] ?? '-');
t('yapan=2 damgalandı', ($gorevM->find($vdGorevId)['yapan_id'] ?? null) == 2);

$r = $gorevM->durumDegistir($vdGorevId, 'TAMAM', 2);
t('GONDERILDI→TAMAM geçerli', $r['durum'] === true);
t('tamamlanma tarihi doldu', $gorevM->find($vdGorevId)['tamamlanma_tarihi'] !== null);

$r = $gorevM->durumDegistir($vdGorevId, 'TAMAM', 2); // zaten TAMAM aynı hedef → noop
t('Aynı durum tekrar (noop) kabul', $r['durum'] === true);

$r = $gorevM->durumDegistir($vdGorevId, 'GEREKSIZ', 2);
t('TAMAM→GEREKSIZ GEÇERSİZ', $r['durum'] === false, 'mesaj: ' . ($r['mesaj'] ?? '-'));

$r = $gorevM->durumDegistir($vdGorevId, 'GONDERILDI', 1); // geri aç (TAMAM→GONDERILDI)
t('TAMAM→GONDERILDI (geri aç) geçerli', $r['durum'] === true, $r['mesaj'] ?? '-');
t('geri açılınca tamamlanma tarihi temizlendi', $gorevM->find($vdGorevId)['tamamlanma_tarihi'] === null);

echo "=== 7) SÜRESİ GEÇEN / YAKLAŞAN ===\n";
// VD görevini eski tarihe çekip gecikmiş saydır
$gorevM->update($vdGorevId, ['son_tarih' => '2026-08-01', 'durum' => 'BEKLIYOR', 'yapan_id' => null]);
echo '    (açık görevler: ';
foreach ($gorevM->where('sicil_degisikligi_id', $degId)->findAll() as $gg) {
    echo '#'.$gg['id'].' kurum='.$gg['kurum_id'].' durum='.$gg['durum'].' son='.$gg['son_tarih'].'  ';
}
echo ")\n";
$gec = $gorevM->gecikmisler(null, 50);
t('Geciken 1 görev listelendi', count($gec) === 1, 'gelen: ' . count($gec));

// SGK görevi hâlâ açık ve yakında (14.09 bugünden +? test bugünü gerçek; yaklaşan içinde olmalı)
$yak = $gorevM->yaklasanlar(null, 15, 50);
t('Yaklaşan (15g) en az 1 görev', count($yak) >= 1);

$say = $gorevM->sayaclar();
t('Sayaç: gecikti>=1', ($say['gecikti'] ?? 0) >= 1, json_encode($say));
t('Sayaç: bekleyen>=1', ($say['bekleyen'] ?? 0) >= 1);

echo "=== 8) DETAY + GEÇMİŞ + LİSTE ===\n";
$det = $degM->detay($degId);
t('Detay görevleri taşıyor', $det !== null && is_array($det['gorevler'] ?? null));
$gec = $degM->gecmis($mkId, null);
t('Geçmiş 1 kayıt', count($gec) === 1);
$liste = $degM->listele(['mukellef_id' => $mkId]);
t('Liste acik_gorev sayacı var', ($liste[0]['acik_gorev'] ?? null) !== null);

echo "=== 9) TEMİZLİK ===\n";
$db->query('DELETE FROM sicil_degisiklikleri');  // cascade görev+belge
$db->query('DELETE FROM sicil_bildirim_kurallari');
t('Temizlendi', true);

echo "\n========================================\n";
echo ($fail === 0 ? "TÜM TESTLER BAŞARILI" : "$fail HATA") . " ($ok geçti / " . ($ok + $fail) . ")\n";
