<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Models\Year;

final class DashboardController extends Controller
{
    public function index(Request $request): string
    {
        $yearId = Year::currentId();
        $stats = [
            'students' => (int) DB::value("SELECT COUNT(*) FROM students WHERE status = 'active'", [], 0),
            'teachers' => (int) DB::value('SELECT COUNT(*) FROM teachers', [], 0),
            'classes'  => (int) DB::value('SELECT COUNT(*) FROM classes WHERE academic_year_id = ? AND is_active = 1', [$yearId], 0),
            'questions'=> (int) DB::value("SELECT COUNT(*) FROM questions WHERE status = 'published'", [], 0),
        ];
        return $this->view('dashboard/index', ['title' => 'لوحة المؤشرات', 'stats' => $stats]);
    }
}
