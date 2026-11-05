<?php
// Satu kontrol per field; definisi dan nilai berasal dari vehicle-form.php.
$helpId = $type === 'textarea' || $name === 'nik' ? 'hint-' . $name : '';
$describedBy = trim(($invalid ? 'error-' . $name : '') . ' ' . $helpId);
?>
<div class="template-input-row <?= e($positionClass) ?> <?= $invalid ? 'has-error' : '' ?>">
    <label for="<?= e($name) ?>"><?= e($label) ?><?= $required ? ' <span class="required-star" aria-hidden="true">*</span>' : '' ?></label>
    <span class="template-colon" aria-hidden="true">:</span>
    <div class="template-input-control">
        <?php if ($type === 'textarea'): ?>
            <textarea class="form-control <?= $invalid ? 'is-invalid' : '' ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" rows="<?= $name === 'catatan' ? 3 : 2 ?>" maxlength="<?= $max ?>" <?= $required ? 'required' : '' ?> placeholder="<?= e($placeholder) ?>" <?= $invalid ? 'aria-invalid="true"' : '' ?> <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?>><?= e($value) ?></textarea>
            <div class="form-text template-input-hint" id="hint-<?= e($name) ?>"><span data-count-for="<?= e($name) ?>"><?= text_length((string) $value) ?></span>/<?= $max ?> karakter</div>
        <?php elseif ($type === 'select'): ?>
            <select class="form-select <?= $invalid ? 'is-invalid' : '' ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?> <?= $invalid ? 'aria-invalid="true"' : '' ?> <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?>>
                <option value="">Pilih...</option>
                <?php foreach (field_options($name) as $option): ?>
                    <option value="<?= e($option) ?>" <?= $value === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input class="form-control <?= $invalid ? 'is-invalid' : '' ?>" type="<?= $type === 'year' ? 'number' : e($type) ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" <?= $required ? 'required' : '' ?> maxlength="<?= $max ?>" placeholder="<?= e($placeholder) ?>" <?= $type === 'year' ? 'min="1900" max="' . ((int) date('Y') + 1) . '" step="1"' : '' ?> <?= $type === 'number' ? 'min="0" max="100000" step="1"' : '' ?> <?= $name === 'nik' ? 'inputmode="numeric" pattern="[0-9]{16}"' : '' ?> <?= $type === 'date' ? 'min="1900-01-01" max="2199-12-31"' : '' ?> <?= $invalid ? 'aria-invalid="true"' : '' ?> <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?>>
            <?php if ($name === 'nik'): ?><div class="form-text template-input-hint" id="hint-nik">16 digit, termasuk angka nol di depan.</div><?php endif; ?>
        <?php endif; ?>
        <?php if ($invalid): ?><div class="invalid-feedback d-block" id="error-<?= e($name) ?>"><?= e($errors[$name]) ?></div><?php endif; ?>
    </div>
</div>
