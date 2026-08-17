<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ödeme Bildirimi</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Roboto,Arial,sans-serif;color:#1e293b;">

  <!-- Sarmalayıcı -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0">
    <tr><td align="center">

      <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">

        <!-- Başlık şeridi -->
        <tr>
          <td style="background:#2563eb;padding:20px 28px">
            <div style="color:#ffffff;font-size:20px;font-weight:700">Ödeme Bildirimi</div>
            <div style="color:#bfdbfe;font-size:13px;margin-top:4px">
              <?= ! empty($filtre['ay']) ? ayAdi((int) $filtre['ay']) . ' ' : '' ?><?= esc($filtre['yil']) ?> dönemi
              • Düzenleme: <?= trTarih(date('Y-m-d')) ?>
            </div>
          </td>
        </tr>

        <tr><td style="padding:24px 28px">

          <?php
          $mid       = $grup['mukellef']['id'] ?? ($mukellef['id'] ?? 0);
          $bosDurum  = ($grup === null && empty($ucretDahil));

          $beyanToplam = 0.0;
          foreach (($grup['satirlar'] ?? []) as $bs) { $beyanToplam += (float) $bs['odenecek']; }
          $ozelToplam = 0.0;
          foreach (($grup['ozel'] ?? []) as $oz) { $ozelToplam += (float) $oz['tutar']; }
          $ucretTutar  = ! empty($ucretDahil) ? (float) $ucret : 0.0;
          $genelToplam = $beyanToplam + $ozelToplam + $ucretTutar;
          ?>

          <!-- Mükellef bilgileri -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px">
            <tr>
              <td style="padding:4px 0"><span style="color:#64748b;font-size:12px">MÜKELLEF</span><br>
                <b style="font-size:15px"><?= esc($grup['mukellef']['unvan'] ?? $mukellef['unvan']) ?></b></td>
            </tr>
            <tr>
              <td style="padding:2px 0;color:#475569;font-size:13px">
                VKN / TCKN: <b><?= esc($grup['mukellef']['vkn'] ?? vknTckn($mukellef)) ?></b>
                &nbsp;•&nbsp; Vergi Dairesi: <b><?= esc(($grup['mukellef']['vergi_dairesi'] ?? $mukellef['vergi_dairesi']) ?: '-') ?></b>
              </td>
            </tr>
          </table>

          <?php if ($bosDurum): ?>

            <p style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:14px 16px;font-size:14px;color:#92400e">
              <b><?= ! empty($filtre['ay']) ? ayAdi((int) $filtre['ay']) . ' ' : '' ?><?= esc($filtre['yil']) ?></b>
              döneminde bu mükellef için ödenecek beyanname bulunamadı.
              <?php if (! empty($ucretDahil)): ?>
                Bu e-posta yalnızca muhasebe ücreti bildirimini içerir.
              <?php endif; ?>
            </p>

          <?php else: ?>

            <!-- Beyanname tablosu -->
            <?php if (! empty($grup['satirlar'])): ?>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:14px">
                <tr style="background:#f8fafc">
                  <th align="left" style="padding:9px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0">Beyanname</th>
                  <th align="left" style="padding:9px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0">Dönem</th>
                  <th align="left" style="padding:9px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0">Son Ödeme</th>
                  <th align="right" style="padding:9px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0">Tahakkuk</th>
                  <th align="right" style="padding:9px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0">Damga</th>
                  <th align="right" style="padding:9px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0">Ödenecek</th>
                </tr>
                <?php foreach ($grup['satirlar'] as $s): ?>
                  <tr>
                    <td style="padding:8px 10px;font-size:13px;border:1px solid #e2e8f0"><?= esc($s['tur_adi']) ?></td>
                    <td style="padding:8px 10px;font-size:13px;border:1px solid #e2e8f0"><?= esc($s['donem_adi']) ?></td>
                    <td style="padding:8px 10px;font-size:13px;border:1px solid #e2e8f0"><?= trTarih($s['efektif_odeme_tarihi'] ?? $s['son_tarih']) ?></td>
                    <td align="right" style="padding:8px 10px;font-size:13px;border:1px solid #e2e8f0"><?= number_format((float) $s['tahakkuk_tutari'], 2, ',', '.') ?></td>
                    <td align="right" style="padding:8px 10px;font-size:13px;border:1px solid #e2e8f0"><?= (float) $s['hesaplanan_damga'] > 0 ? number_format((float) $s['hesaplanan_damga'], 2, ',', '.') : '—' ?></td>
                    <td align="right" style="padding:8px 10px;font-size:13px;font-weight:700;border:1px solid #e2e8f0"><?= number_format((float) $s['odenecek'], 2, ',', '.') ?> ₺</td>
                  </tr>
                <?php endforeach; ?>
                <tr style="background:#f8fafc">
                  <td colspan="5" align="right" style="padding:9px 10px;font-size:13px;font-weight:700;border:1px solid #e2e8f0">BEYANNAME TOPLAMI</td>
                  <td align="right" style="padding:9px 10px;font-size:13px;font-weight:700;border:1px solid #e2e8f0"><?= number_format($beyanToplam, 2, ',', '.') ?> ₺</td>
                </tr>
              </table>
            <?php endif; ?>

            <!-- Diğer ödemeler -->
            <?php if (! empty($grup['ozel'])): ?>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:14px">
                <tr style="background:#f8fafc">
                  <th align="left" style="padding:9px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0">Diğer Ödemeler</th>
                  <th align="left" style="padding:9px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0">Dönem</th>
                  <th align="left" style="padding:9px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0">Son Tarih</th>
                  <th align="right" style="padding:9px 10px;font-size:12px;color:#475569;border:1px solid #e2e8f0">Tutar</th>
                </tr>
                <?php foreach ($grup['ozel'] as $o): ?>
                  <tr>
                    <td style="padding:8px 10px;font-size:13px;border:1px solid #e2e8f0"><b><?= esc($o['baslik']) ?></b></td>
                    <td style="padding:8px 10px;font-size:13px;border:1px solid #e2e8f0"><?= esc($o['donem_etiketi'] ?: '—') ?></td>
                    <td style="padding:8px 10px;font-size:13px;border:1px solid #e2e8f0"><?= trTarih($o['son_tarih']) ?></td>
                    <td align="right" style="padding:8px 10px;font-size:13px;font-weight:700;border:1px solid #e2e8f0"><?= number_format((float) $o['tutar'], 2, ',', '.') ?> ₺</td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>

            <!-- Muhasebe ücreti -->
            <?php if (! empty($ucretDahil)): ?>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:14px">
                <tr>
                  <td style="padding:8px 10px;font-size:13px;border:1px solid #e2e8f0"><b>Muhasebe Ücreti</b></td>
                  <td align="right" style="padding:8px 10px;font-size:13px;font-weight:700;border:1px solid #e2e8f0"><?= number_format($ucretTutar, 2, ',', '.') ?> ₺</td>
                </tr>
              </table>
            <?php endif; ?>

            <!-- Genel toplam -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border:2px solid #059669;border-radius:10px;margin-top:8px">
              <tr>
                <td style="padding:14px 16px">
                  <div style="color:#475569;font-size:12px">
                    <?php if ($beyanToplam > 0): ?>Beyanname <?= number_format($beyanToplam, 2, ',', '.') ?><?php endif; ?>
                    <?php if ($ozelToplam > 0): ?> + Diğer Ödemeler <?= number_format($ozelToplam, 2, ',', '.') ?><?php endif; ?>
                    <?php if (! empty($ucretDahil)): ?> + Muhasebe Ücreti <?= number_format($ucretTutar, 2, ',', '.') ?><?php endif; ?>
                  </div>
                  <div style="font-size:24px;font-weight:800;color:#059669">TOPLAM: <?= number_format($genelToplam, 2, ',', '.') ?> ₺</div>
                </td>
              </tr>
            </table>

          <?php endif; ?>

          <!-- Alt not -->
          <p style="margin-top:22px;padding-top:14px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px;line-height:1.5">
            Bu bildirim bilgilendirme amaçlıdır. Vergi ödemelerinizi son ödeme tarihine kadar
            vergi dairesine veya anlaşmalı bankalara yapabilirsiniz.
            <?php if (! empty($firmaAdi)): ?><br><b style="color:#64748b"><?= esc($firmaAdi) ?></b><?php endif; ?>
          </p>

        </td></tr>
      </table>

    </td></tr>
  </table>

</body>
</html>
