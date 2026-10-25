<?php if ($pages > 1): $q = $_GET; ?>
<nav class="pager" aria-label="Pages">
  <?php if ($page > 1): $q['page'] = $page - 1; ?><a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query($q)) ?>">Previous</a><?php endif; ?>
  <span class="muted">Page <?= (int)$page ?> of <?= (int)$pages ?></span>
  <?php if ($page < $pages): $q['page'] = $page + 1; ?><a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query($q)) ?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
