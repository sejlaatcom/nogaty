<?php
/**
 * تقسيم النتائج إلى صفحات
 */
class Paginator
{
    public int $total;
    public int $perPage;
    public int $page;
    public int $pages;
    public array $items;

    public function __construct(array $items, int $total, int $perPage, int $page)
    {
        $this->items   = $items;
        $this->total   = $total;
        $this->perPage = max(1, $perPage);
        $this->pages   = (int) max(1, ceil($total / $this->perPage));
        $this->page    = min(max(1, $page), $this->pages);
    }

    /**
     * تنفيذ استعلام مقسّم إلى صفحات
     */
    public static function make(string $sql, array $params, int $page, ?int $perPage = null): self
    {
        $perPage = $perPage ?: (int) setting('items_per_page', 20);
        $perPage = max(5, min(200, $perPage));

        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _cnt';
        $total    = (int) Database::value($countSql, $params);

        $pages  = (int) max(1, ceil($total / $perPage));
        $page   = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $items = Database::all($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
        return new self($items, $total, $perPage, $page);
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : (($this->page - 1) * $this->perPage) + 1;
    }

    public function to(): int
    {
        return min($this->page * $this->perPage, $this->total);
    }

    /** روابط الصفحات مع الحفاظ على معاملات البحث */
    public function links(): string
    {
        if ($this->pages <= 1) {
            return '';
        }
        $query = $_GET;
        $url = static function (int $p) use ($query): string {
            $query['page'] = $p;
            return 'index.php?' . http_build_query($query);
        };

        $html = '<nav class="pagination" aria-label="تنقل الصفحات"><ul>';
        $html .= $this->page > 1
            ? '<li><a href="' . e($url($this->page - 1)) . '">السابق</a></li>'
            : '<li class="disabled"><span>السابق</span></li>';

        $start = max(1, $this->page - 2);
        $end   = min($this->pages, $this->page + 2);
        if ($start > 1) {
            $html .= '<li><a href="' . e($url(1)) . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="dots"><span>…</span></li>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= $i === $this->page
                ? '<li class="active"><span>' . $i . '</span></li>'
                : '<li><a href="' . e($url($i)) . '">' . $i . '</a></li>';
        }
        if ($end < $this->pages) {
            if ($end < $this->pages - 1) {
                $html .= '<li class="dots"><span>…</span></li>';
            }
            $html .= '<li><a href="' . e($url($this->pages)) . '">' . $this->pages . '</a></li>';
        }
        $html .= $this->page < $this->pages
            ? '<li><a href="' . e($url($this->page + 1)) . '">التالي</a></li>'
            : '<li class="disabled"><span>التالي</span></li>';

        return $html . '</ul></nav>';
    }
}
