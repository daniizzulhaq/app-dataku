<form class="preview-options no-print" method="get" action="<?= e(basename($_SERVER['SCRIPT_NAME'])) ?>">
<?php if (isset($vehicle)): ?><input type="hidden" name="id" value="<?= (int) $vehicle['id'] ?>"><?php endif; ?>
<label for="position">Posisi cetak</label>
<select class="form-select" id="position" name="position">
    <?php foreach (['left' => 'Kolom kiri', 'middle' => 'Kolom tengah', 'right' => 'Kolom kanan'] as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $printPosition === $value ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
</select>
<button class="btn btn-outline-secondary" type="submit">Terapkan</button>
<span class="text-muted">Kertas landscape · Isi portrait · Satu STNK per cetak.</span>
</form>
