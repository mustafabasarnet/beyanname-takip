<?php

namespace App\Libraries;

use Config\Database;

/**
 * Veritabanı yedekleme / geri yükleme.
 *
 * mysqldump'a BAĞIMLI DEĞİLDİR — saf PHP ile çalışır, bu yüzden paylaşımlı
 * hostinglerde (shell_exec kapalı olsa bile) sorunsuz kullanılır.
 *
 * Üretilen .sql dosyası:
 *   - DROP TABLE IF EXISTS + CREATE TABLE (şema)
 *   - INSERT INTO ... toplu satırlar (veri)
 *   - Yabancı anahtar kontrolü kapatılıp açılır (sıra bağımsız yüklenebilir)
 */
class Yedekleyici
{
    /** Tek INSERT ifadesine sığdırılacak azami satır */
    public const TOPLU_SATIR = 200;

    /** Tek INSERT ifadesinin azami uzunluğu (bayt) */
    public const AZAMI_SORGU = 900000;

    protected $db;
    protected string $veritabani;

    public function __construct()
    {
        $this->db         = Database::connect();
        $this->veritabani = $this->db->getDatabase();
    }

    // =================================================================
    //  YEDEK ALMA
    // =================================================================

    /**
     * Veritabanı tablolarının listesi (satır sayısı + boyutuyla).
     *
     * @return array<int,array{ad:string,satir:int,boyut:int,boyut_f:string}>
     */
    public function tablolar(): array
    {
        $sorgu = $this->db->query(
            'SELECT TABLE_NAME AS ad, TABLE_ROWS AS satir,
                    (DATA_LENGTH + INDEX_LENGTH) AS boyut
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = "BASE TABLE"
              ORDER BY TABLE_NAME',
            [$this->veritabani]
        );

        $out = [];

        foreach ($sorgu->getResultArray() as $r) {
            // TABLE_ROWS InnoDB'de tahminidir; kesin sayıyı okuyalım
            $kesin = (int) $this->db->table($r['ad'])->countAllResults();

            $out[] = [
                'ad'      => $r['ad'],
                'satir'   => $kesin,
                'boyut'   => (int) $r['boyut'],
                'boyut_f' => $this->boyutYaz((int) $r['boyut']),
            ];
        }

        return $out;
    }

    /**
     * Yedeği üretip doğrudan tarayıcıya akıtır (büyük veritabanlarında
     * belleği şişirmemek için parça parça yazar).
     *
     * @param string[] $tablolar Boş dizi = tüm tablolar
     * @param bool     $veriDahil false ise yalnızca şema
     */
    public function akitarakUret(array $tablolar = [], bool $veriDahil = true): void
    {
        $hedef = $tablolar !== [] ? $tablolar : array_column($this->tablolar(), 'ad');

        $this->yaz($this->baslik($hedef, $veriDahil));

        foreach ($hedef as $tablo) {
            if (! $this->tabloVarMi($tablo)) {
                continue;
            }

            $this->yaz($this->tabloSemasi($tablo));

            if ($veriDahil) {
                $this->tabloVerisiAkit($tablo);
            }
        }

        $this->yaz($this->altbilgi());
    }

    /** Yedek dosyası için önerilen ad */
    public function dosyaAdi(): string
    {
        return 'yedek_' . $this->veritabani . '_' . date('Y-m-d_His') . '.sql';
    }

    // -----------------------------------------------------------------

    protected function baslik(array $tablolar, bool $veriDahil): string
    {
        $s  = "-- =====================================================================\n";
        $s .= "--  BEYANNAME TAKİP — VERİTABANI YEDEĞİ\n";
        $s .= '--  Veritabanı : ' . $this->veritabani . "\n";
        $s .= '--  Tarih      : ' . date('d.m.Y H:i:s') . "\n";
        $s .= '--  Tablolar   : ' . count($tablolar) . ' (' . implode(', ', $tablolar) . ")\n";
        $s .= '--  İçerik     : ' . ($veriDahil ? 'Şema + Veri' : 'Yalnızca şema') . "\n";
        $s .= "--\n";
        $s .= "--  Geri yüklemek için: Sistem → Yedekleme → Geri Yükle\n";
        $s .= "--  veya komut satırından:  mysql -u KULLANICI -p VERITABANI < bu_dosya.sql\n";
        $s .= "-- =====================================================================\n\n";
        $s .= "SET NAMES utf8mb4;\n";
        $s .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $s .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $s .= "SET AUTOCOMMIT = 0;\n";
        $s .= "START TRANSACTION;\n\n";

        return $s;
    }

    protected function altbilgi(): string
    {
        return "\nCOMMIT;\nSET FOREIGN_KEY_CHECKS = 1;\n\n-- Yedek sonu\n";
    }

    protected function tabloSemasi(string $tablo): string
    {
        $satir = $this->db->query('SHOW CREATE TABLE ' . $this->db->escapeIdentifiers($tablo))
            ->getRowArray();

        $olustur = $satir['Create Table'] ?? ($satir['Create View'] ?? '');

        $s  = "-- ---------------------------------------------------------------------\n";
        $s .= '-- Tablo: ' . $tablo . "\n";
        $s .= "-- ---------------------------------------------------------------------\n";
        $s .= 'DROP TABLE IF EXISTS ' . $this->db->escapeIdentifiers($tablo) . ";\n";
        $s .= $olustur . ";\n\n";

        return $s;
    }

    /** Tablo verisini parça parça (bellek dostu) akıtır */
    protected function tabloVerisiAkit(string $tablo): void
    {
        $toplam = (int) $this->db->table($tablo)->countAllResults();

        if ($toplam === 0) {
            $this->yaz('-- ' . $tablo . " tablosu boş\n\n");

            return;
        }

        $this->yaz('-- ' . $tablo . ' verisi (' . $toplam . " satır)\n");

        $tabloAd  = $this->db->escapeIdentifiers($tablo);
        $sutunlar = $this->db->getFieldNames($tablo);
        $basSutun = implode(', ', array_map(fn ($s) => $this->db->escapeIdentifiers($s), $sutunlar));

        $adim   = 500;
        $ofset  = 0;
        $tampon = [];
        $uzunluk = 0;

        while ($ofset < $toplam) {
            $rows = $this->db->query(
                'SELECT * FROM ' . $tabloAd . ' LIMIT ' . $adim . ' OFFSET ' . $ofset
            )->getResultArray();

            if ($rows === []) {
                break;
            }

            foreach ($rows as $r) {
                $degerler = [];

                foreach ($sutunlar as $s) {
                    $degerler[] = $this->deger($r[$s] ?? null);
                }

                $parca    = '(' . implode(',', $degerler) . ')';
                $tampon[] = $parca;
                $uzunluk += strlen($parca) + 1;

                if (count($tampon) >= self::TOPLU_SATIR || $uzunluk >= self::AZAMI_SORGU) {
                    $this->yaz('INSERT INTO ' . $tabloAd . ' (' . $basSutun . ") VALUES\n"
                        . implode(",\n", $tampon) . ";\n");
                    $tampon  = [];
                    $uzunluk = 0;
                }
            }

            $ofset += $adim;
        }

        if ($tampon !== []) {
            $this->yaz('INSERT INTO ' . $tabloAd . ' (' . $basSutun . ") VALUES\n"
                . implode(",\n", $tampon) . ";\n");
        }

        $this->yaz("\n");
    }

    /** Tek bir değeri güvenli SQL sabitine çevirir */
    protected function deger($v): string
    {
        if ($v === null) {
            return 'NULL';
        }

        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }

        // Sayısal metinleri de tırnaklıyoruz: "007" gibi değerler bozulmasın
        return $this->db->escape((string) $v);
    }

    protected function yaz(string $metin): void
    {
        echo $metin;

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        @flush();
    }

    protected function tabloVarMi(string $tablo): bool
    {
        return in_array($tablo, $this->db->listTables(), true);
    }

    public function boyutYaz(int $bayt): string
    {
        if ($bayt <= 0) {
            return '0 B';
        }

        $birim = ['B', 'KB', 'MB', 'GB'];
        $i     = (int) floor(log($bayt, 1024));
        $i     = min($i, count($birim) - 1);

        return round($bayt / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $birim[$i];
    }

    /** Veritabanının toplam boyutu */
    public function toplamBoyut(): array
    {
        $r = $this->db->query(
            'SELECT SUM(DATA_LENGTH + INDEX_LENGTH) AS b, COUNT(*) AS t
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = "BASE TABLE"',
            [$this->veritabani]
        )->getRowArray();

        return [
            'bayt'    => (int) ($r['b'] ?? 0),
            'boyut_f' => $this->boyutYaz((int) ($r['b'] ?? 0)),
            'tablo'   => (int) ($r['t'] ?? 0),
        ];
    }

    // =================================================================
    //  GERİ YÜKLEME
    // =================================================================

    /**
     * .sql dosyasını çalıştırır.
     *
     * GÜVENLİK: Dosya doğrulanır — beklenen tabloları içermiyorsa veya
     * tehlikeli ifadeler barındırıyorsa reddedilir.
     *
     * @return array{basarili:bool,mesaj:string,calisan:int,hatalar:array}
     */
    public function geriYukle(string $dosyaYolu): array
    {
        if (! is_readable($dosyaYolu)) {
            return $this->hata('Dosya okunamadı.');
        }

        $boyut = filesize($dosyaYolu);

        if ($boyut === false || $boyut === 0) {
            return $this->hata('Dosya boş.');
        }

        $ilk = (string) file_get_contents($dosyaYolu, false, null, 0, 65536);

        // Kaba doğrulama: bu bir SQL yedeği mi?
        if (! preg_match('/CREATE\s+TABLE|INSERT\s+INTO/i', $ilk)) {
            return $this->hata(
                'Bu dosya bir SQL yedeği gibi görünmüyor '
                . '(CREATE TABLE / INSERT INTO bulunamadı).'
            );
        }

        // Tehlikeli ifadeler — başka bir veritabanına geçme / kullanıcı oluşturma
        $tam = (string) file_get_contents($dosyaYolu);

        foreach (['/\bDROP\s+DATABASE\b/i', '/\bCREATE\s+USER\b/i', '/\bGRANT\s+/i',
            '/\bSET\s+PASSWORD\b/i', '/\bINTO\s+OUTFILE\b/i', '/\bLOAD_FILE\s*\(/i'] as $kalip) {
            if (preg_match($kalip, $tam)) {
                return $this->hata(
                    'Dosyada izin verilmeyen bir SQL ifadesi var '
                    . '(DROP DATABASE / GRANT / OUTFILE vb.). Güvenlik için işlem durduruldu.'
                );
            }
        }

        $ifadeler = $this->ifadelereBol($tam);

        if ($ifadeler === []) {
            return $this->hata('Dosyada çalıştırılabilir SQL ifadesi bulunamadı.');
        }

        // ---- Çalıştır ----
        @set_time_limit(0);

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        $calisan = 0;
        $hatalar = [];

        foreach ($ifadeler as $i => $sql) {
            // Önemli: Bir ifadenin başında yorum satırları olabilir
            // ("-- Tablo: x" + DROP TABLE ...). Yorumları ayıklayıp
            // geriye çalıştırılacak SQL kalıyor mu diye bakıyoruz —
            // aksi hâlde yorumla başlayan DROP/CREATE ifadeleri atlanır.
            $sql = $this->yorumlariAyikla($sql);

            if ($sql === '') {
                continue;
            }

            try {
                $this->db->query($sql);
                $calisan++;
            } catch (\Throwable $e) {
                $hatalar[] = [
                    'sira'  => $i + 1,
                    'sql'   => mb_substr($sql, 0, 120),
                    'mesaj' => $e->getMessage(),
                ];

                // İlk 10 hatadan sonra dur — dosya muhtemelen uyumsuz
                if (count($hatalar) >= 10) {
                    break;
                }
            }
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        if ($calisan === 0) {
            return [
                'basarili' => false,
                'mesaj'    => 'Hiçbir SQL ifadesi çalıştırılamadı. Dosya bu veritabanıyla uyumsuz olabilir.',
                'calisan'  => 0,
                'hatalar'  => $hatalar,
            ];
        }

        return [
            'basarili' => $hatalar === [],
            'mesaj'    => $calisan . ' SQL ifadesi çalıştırıldı'
                . ($hatalar !== [] ? ', ' . count($hatalar) . ' hata oluştu.' : '.'),
            'calisan'  => $calisan,
            'hatalar'  => $hatalar,
        ];
    }

    /**
     * Bir SQL ifadesinin başındaki/arasındaki yorum satırlarını ayıklar.
     * Tırnak içindeki "--" ve "#" karakterlerine dokunmaz.
     */
    protected function yorumlariAyikla(string $sql): string
    {
        $out       = '';
        $uzunluk   = strlen($sql);
        $tekTirnak = false;
        $ciftTirnak = false;
        $tersTirnak = false;

        for ($i = 0; $i < $uzunluk; $i++) {
            $k    = $sql[$i];
            $next = $i + 1 < $uzunluk ? $sql[$i + 1] : '';

            if (! $tekTirnak && ! $ciftTirnak && ! $tersTirnak) {
                // Satır yorumu: satır sonuna kadar atla
                if (($k === '-' && $next === '-') || $k === '#') {
                    while ($i < $uzunluk && $sql[$i] !== "\n") {
                        $i++;
                    }

                    $out .= "\n";

                    continue;
                }

                // Blok yorumu
                if ($k === '/' && $next === '*') {
                    $i += 2;

                    while ($i < $uzunluk && ! ($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/')) {
                        $i++;
                    }

                    $i++;
                    $out .= ' ';

                    continue;
                }
            }

            $kacisli = $this->kacisliMi($sql, $i);

            if ($k === "'" && ! $ciftTirnak && ! $tersTirnak && ! $kacisli) {
                $tekTirnak = ! $tekTirnak;
            } elseif ($k === '"' && ! $tekTirnak && ! $tersTirnak && ! $kacisli) {
                $ciftTirnak = ! $ciftTirnak;
            } elseif ($k === '`' && ! $tekTirnak && ! $ciftTirnak) {
                $tersTirnak = ! $tersTirnak;
            }

            $out .= $k;
        }

        return trim($out);
    }

    /**
     * SQL metnini ifadelere böler.
     * Tırnak içindeki ";" karakterlerini ifade sonu saymaz.
     */
    protected function ifadelereBol(string $sql): array
    {
        $ifadeler = [];
        $tampon   = '';
        $uzunluk  = strlen($sql);

        $tekTirnak  = false;
        $ciftTirnak = false;
        $tersTirnak = false;
        $satirYorum = false;
        $blokYorum  = false;

        for ($i = 0; $i < $uzunluk; $i++) {
            $k    = $sql[$i];
            $son  = $i > 0 ? $sql[$i - 1] : '';
            $next = $i + 1 < $uzunluk ? $sql[$i + 1] : '';

            // Yorum durumları
            if ($satirYorum) {
                $tampon .= $k;

                if ($k === "\n") {
                    $satirYorum = false;
                }

                continue;
            }

            if ($blokYorum) {
                $tampon .= $k;

                if ($k === '/' && $son === '*') {
                    $blokYorum = false;
                }

                continue;
            }

            if (! $tekTirnak && ! $ciftTirnak && ! $tersTirnak) {
                if ($k === '-' && $next === '-') {
                    $satirYorum = true;
                    $tampon .= $k;

                    continue;
                }

                if ($k === '#') {
                    $satirYorum = true;
                    $tampon .= $k;

                    continue;
                }

                if ($k === '/' && $next === '*') {
                    $blokYorum = true;
                    $tampon .= $k;

                    continue;
                }
            }

            // Tırnak durumları (kaçış karakteri sayısı tek ise tırnak kaçırılmıştır)
            $kacisli = $this->kacisliMi($sql, $i);

            if ($k === "'" && ! $ciftTirnak && ! $tersTirnak && ! $kacisli) {
                $tekTirnak = ! $tekTirnak;
            } elseif ($k === '"' && ! $tekTirnak && ! $tersTirnak && ! $kacisli) {
                $ciftTirnak = ! $ciftTirnak;
            } elseif ($k === '`' && ! $tekTirnak && ! $ciftTirnak) {
                $tersTirnak = ! $tersTirnak;
            }

            if ($k === ';' && ! $tekTirnak && ! $ciftTirnak && ! $tersTirnak) {
                $ifadeler[] = $tampon;
                $tampon     = '';

                continue;
            }

            $tampon .= $k;
        }

        if (trim($tampon) !== '') {
            $ifadeler[] = $tampon;
        }

        return $ifadeler;
    }

    /** Karakterden önce tek sayıda ters eğik çizgi var mı? */
    protected function kacisliMi(string $s, int $konum): bool
    {
        $sayac = 0;
        $i     = $konum - 1;

        while ($i >= 0 && $s[$i] === '\\') {
            $sayac++;
            $i--;
        }

        return $sayac % 2 === 1;
    }

    protected function hata(string $mesaj): array
    {
        return ['basarili' => false, 'mesaj' => $mesaj, 'calisan' => 0, 'hatalar' => []];
    }
}
