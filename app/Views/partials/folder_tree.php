<?php
/**
 * عرض شجرة المجلدات بشكل تكراري
 * المتغيرات : $nodes, $currentId
 */
$currentId = $currentId ?? 0;
?>
<ul class="tree">
<?php foreach ($nodes as $node): $has = !empty($node['children']); ?>
  <li data-id="<?= (int) $node['id'] ?>">
    <a class="node <?= (int) $node['id'] === (int) $currentId ? 'active' : '' ?>"
       href="<?= e(url('folders/index', ['id' => $node['id']])) ?>">
      <span class="toggle"><?= $has ? '▼' : '·' ?></span>
      <span><?= $has ? '📂' : '📁' ?></span>
      <span class="n"><?= e($node['name']) ?></span>
      <?php $n = (int) ($node['docs_count'] ?? 0) + (int) ($node['trx_count'] ?? 0);
            if ($n): ?><span class="c"><?= $n ?></span><?php endif; ?>
    </a>
    <?php if ($has): View::partial('folder_tree', ['nodes' => $node['children'], 'currentId' => $currentId]); endif; ?>
  </li>
<?php endforeach; ?>
</ul>
