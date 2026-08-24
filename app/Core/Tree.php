<?php
/**
 * أدوات شجرة الأرشفة والهيكل التنظيمي (مستويات غير محدودة)
 */
class Tree
{
    /** بناء شجرة متداخلة من قائمة مسطّحة */
    public static function build(array $rows, ?int $parentId = null, string $parentKey = 'parent_id'): array
    {
        $branch = [];
        foreach ($rows as $row) {
            $rowParent = $row[$parentKey] !== null ? (int) $row[$parentKey] : null;
            if ($rowParent === $parentId) {
                $children = self::build($rows, (int) $row['id'], $parentKey);
                if ($children) {
                    $row['children'] = $children;
                }
                $branch[] = $row;
            }
        }
        return $branch;
    }

    /** قائمة مسطّحة مع بادئة تدل على العمق (للقوائم المنسدلة) */
    public static function flatten(array $tree, int $depth = 0, string $prefix = '— '): array
    {
        $out = [];
        foreach ($tree as $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);
            $node['_depth'] = $depth;
            $node['_label'] = str_repeat($prefix, $depth) . $node['name'];
            $out[] = $node;
            if ($children) {
                $out = array_merge($out, self::flatten($children, $depth + 1, $prefix));
            }
        }
        return $out;
    }

    /** كل معرفات المجلد وفروعه */
    public static function descendantIds(string $table, int $id): array
    {
        $ids  = [$id];
        $queue = [$id];
        $guard = 0;
        while ($queue && $guard++ < 500) {
            $placeholders = implode(',', array_fill(0, count($queue), '?'));
            $rows = Database::all(
                "SELECT id FROM `$table` WHERE parent_id IN ($placeholders)",
                $queue
            );
            $queue = array_map(static fn($r) => (int) $r['id'], $rows);
            $ids   = array_merge($ids, $queue);
        }
        return array_values(array_unique($ids));
    }

    /** مسار المجلد من الجذر (سلسلة التنقل) */
    public static function breadcrumb(string $table, ?int $id): array
    {
        $chain = [];
        $guard = 0;
        while ($id && $guard++ < 100) {
            $row = Database::first("SELECT id, parent_id, name FROM `$table` WHERE id = ?", [$id]);
            if (!$row) {
                break;
            }
            array_unshift($chain, $row);
            $id = $row['parent_id'] ? (int) $row['parent_id'] : null;
        }
        return $chain;
    }

    /** إعادة حساب المسار والعمق لمجلد وفروعه */
    public static function refreshPath(int $folderId): void
    {
        $chain = self::breadcrumb('folders', $folderId);
        $path  = '/' . implode('/', array_map(static fn($n) => $n['id'], $chain)) . '/';
        Database::update('folders', ['path' => $path, 'depth' => count($chain) - 1],
            'id = :id', ['id' => $folderId]);

        $children = Database::all('SELECT id FROM folders WHERE parent_id = ?', [$folderId]);
        foreach ($children as $c) {
            self::refreshPath((int) $c['id']);
        }
    }

    /** التأكد من عدم نقل مجلد داخل أحد فروعه */
    public static function isDescendantOf(string $table, int $candidateId, int $ancestorId): bool
    {
        return in_array($candidateId, self::descendantIds($table, $ancestorId), true);
    }
}
