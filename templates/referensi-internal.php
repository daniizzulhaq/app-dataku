<figure class="internal-reference-code">
    <?php $qrCaption = array_key_exists('teks_atas_qr', $vehicle) ? (string) $vehicle['teks_atas_qr'] : 'TELITI ULANG'; ?>
    <?php if ($qrCaption !== ''): ?><figcaption><?= e($qrCaption) ?></figcaption><?php endif; ?>
    <img src="assets/images/qr-contoh.jpg" alt="QR dari gambar contoh yang diberikan">
    <span>D5H7GUSPR</span>
</figure>
