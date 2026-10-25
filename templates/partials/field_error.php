<?php if (!empty($errors[$name]) && is_string($errors[$name])): ?><span class="field-error" id="err-<?= e($name) ?>"><?= e($errors[$name]) ?></span><?php endif; ?>
