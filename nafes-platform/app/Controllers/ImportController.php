<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Models\ClassRoom;
use App\Services\Audit;
use App\Services\Import\Importer;
use App\Services\Import\Registry;
use App\Services\Spreadsheet;
use App\Services\Uploads;

/**
 * معالج الاستيراد العام: 1) رفع الملف 2) اختيار الورقة ومطابقة الأعمدة 3) معاينة وتحقق 4) استيراد وتقرير
 */
final class ImportController extends Controller
{
    private function importer(Request $request): Importer
    {
        $importer = Registry::make((string) $request->param('kind'));
        $this->authorize($importer->permission());
        return $importer;
    }

    private function state(Request $request): array
    {
        $token = $request->str('token');
        $state = Session::get('imports', [])[$token] ?? null;
        if (!$state || $state['kind'] !== $request->param('kind') || !is_file(STORAGE_PATH . '/imports/' . $state['file'])) {
            flash('warning', 'انتهت جلسة الاستيراد، يرجى رفع الملف من جديد.');
            redirect('/import/' . $request->param('kind'));
        }
        return $state + ['token' => $token];
    }

    private function saveState(string $token, array $state): void
    {
        $all = Session::get('imports', []);
        $all[$token] = $state;
        Session::put('imports', array_slice($all, -5, null, true));
    }

    /** الخطوة 1: رفع الملف */
    public function upload(Request $request): string
    {
        $importer = $this->importer($request);
        $kind = (string) $request->param('kind');
        $batches = $kind === 'questions'
            ? DB::all('SELECT b.*, b.imported AS created, 0 AS updated, u.full_name AS user_name FROM question_import_batches b LEFT JOIN users u ON u.id = b.user_id ORDER BY b.id DESC LIMIT 10')
            : DB::all('SELECT b.*, u.full_name AS user_name FROM import_batches b LEFT JOIN users u ON u.id = b.user_id WHERE b.kind = ? ORDER BY b.id DESC LIMIT 10', [$kind]);
        return $this->view('import/upload', ['title' => $importer->title(), 'importer' => $importer, 'kind' => $kind, 'batches' => $batches]);
    }

    public function store(Request $request): never
    {
        $importer = $this->importer($request);
        $kind = (string) $request->param('kind');
        $file = $request->file('file');
        $saved = $file ? Uploads::storeImport($file) : ['error' => 'يرجى اختيار ملف.'];
        if (isset($saved['error'])) {
            back_with_errors([$saved['error']], '/import/' . $kind);
        }
        try {
            $sheets = Spreadsheet::sheetNames(STORAGE_PATH . '/imports/' . $saved['name']);
        } catch (\Throwable $e) {
            @unlink(STORAGE_PATH . '/imports/' . $saved['name']);
            back_with_errors(['تعذر قراءة الملف: ' . $e->getMessage()], '/import/' . $kind);
        }
        $token = bin2hex(random_bytes(8));
        $this->saveState($token, [
            'kind' => $kind, 'file' => $saved['name'], 'original' => $saved['original'], 'sheets' => $sheets,
            'sheet' => $sheets[0] ?? null, 'mapping' => null, 'options' => [], 'header' => true,
        ]);
        redirect('/import/' . $kind . '/map?token=' . $token);
    }

    /** الخطوة 2: اختيار الورقة ومطابقة الأعمدة */
    public function map(Request $request): string
    {
        $importer = $this->importer($request);
        $state = $this->state($request);
        if ($request->str('sheet') !== '' && in_array($request->str('sheet'), $state['sheets'], true)) {
            $state['sheet'] = $request->str('sheet');
            $state['mapping'] = null;
            $this->saveState($state['token'], $state);
        }
        $rows = Spreadsheet::read(STORAGE_PATH . '/imports/' . $state['file'], $state['sheet'] === 'CSV' ? null : $state['sheet'], 6);
        $headers = $rows[0] ?? [];
        $mapping = $state['mapping'] ?? $importer->autoMap($headers);

        return $this->view('import/map', [
            'title' => $importer->title() . ' — مطابقة الأعمدة', 'importer' => $importer, 'kind' => $request->param('kind'),
            'state' => $state, 'headers' => $headers, 'sample' => array_slice($rows, 1, 4), 'mapping' => $mapping,
            'classes' => ClassRoom::forYear(null, true, true),
        ]);
    }

    public function saveMap(Request $request): never
    {
        $importer = $this->importer($request);
        $state = $this->state($request);
        $mapping = [];
        foreach (array_keys($importer->fields()) as $key) {
            $value = $request->array('map')[$key] ?? '';
            $mapping[$key] = $value === '' ? null : (int) $value;
        }
        $missing = [];
        foreach ($importer->fields() as $key => [$label, $required]) {
            if ($required && $mapping[$key] === null) {
                $missing[] = "اختر العمود المقابل لحقل «{$label}».";
            }
        }
        if ($missing) {
            back_with_errors($missing, '/import/' . $state['kind'] . '/map?token=' . $state['token']);
        }
        $options = [];
        foreach ($importer->options() as $key => [, $type]) {
            $options[$key] = $type === 'bool' ? $request->bool('opt_' . $key) : $request->str('opt_' . $key);
        }
        if (!empty($options['default_class'])) {
            \App\Services\Scope::authorizeClass((int) $options['default_class']);
        }
        $state['mapping'] = $mapping;
        $state['options'] = $options;
        $state['header']  = $request->bool('has_header');
        $this->saveState($state['token'], $state);
        redirect('/import/' . $state['kind'] . '/preview?token=' . $state['token']);
    }

    /** الخطوة 3: المعاينة والتحقق قبل الحفظ */
    public function preview(Request $request): string
    {
        $importer = $this->importer($request);
        $state = $this->state($request);
        if ($state['mapping'] === null) {
            redirect('/import/' . $state['kind'] . '/map?token=' . $state['token']);
        }
        $validated = $this->validateFile($importer, $state);
        $summary = ['create' => 0, 'update' => 0, 'reject' => 0];
        foreach ($validated as $row) {
            $summary[$row['action']] = ($summary[$row['action']] ?? 0) + 1;
        }
        $filter = $request->str('show', 'all');
        $shown = array_values(array_filter($validated, static fn ($r) => $filter === 'all' || $r['action'] === $filter));

        return $this->view('import/preview', [
            'title' => $importer->title() . ' — المعاينة', 'importer' => $importer, 'kind' => $request->param('kind'),
            'state' => $state, 'rows' => array_slice($shown, 0, 400), 'shownTotal' => count($shown), 'summary' => $summary, 'filter' => $filter,
        ]);
    }

    /** الخطوة 4: الاستيراد الفعلي للسجلات الصحيحة فقط */
    public function run(Request $request): never
    {
        $importer = $this->importer($request);
        $state = $this->state($request);
        @set_time_limit(300);
        $validated = $this->validateFile($importer, $state);
        $report = $importer->import($validated, $state['options']);
        $batchId = $importer->saveBatch($report, $state['original'], count($validated));
        Audit::log('import', $state['kind'], $batchId, ['created' => $report['created'], 'updated' => $report['updated'], 'rejected' => $report['rejected']]);

        // بيانات الدخول المولدة تُحفظ في الجلسة فقط لطباعتها، ولا تُخزن في قاعدة البيانات
        if ($report['credentials']) {
            $creds = Session::get('import_credentials', []);
            $creds[$state['kind'] . '-' . $batchId] = $report['credentials'];
            Session::put('import_credentials', array_slice($creds, -3, null, true));
        }
        @unlink(STORAGE_PATH . '/imports/' . $state['file']);
        $all = Session::get('imports', []);
        unset($all[$state['token']]);
        Session::put('imports', $all);

        redirect('/import/' . $state['kind'] . '/report/' . $batchId);
    }

    public function report(Request $request): string
    {
        $importer = $this->importer($request);
        $kind = (string) $request->param('kind');
        $batch = $this->loadBatch($kind, (int) $request->param('id'));
        $credentials = Session::get('import_credentials', [])[$kind . '-' . $batch['id']] ?? [];

        if ($request->str('download') === 'rejected') {
            $rows = [array_merge(['السطر', 'أسباب الرفض'], array_map(static fn ($f) => $f[0], $importer->fields()))];
            foreach ($batch['report']['rejected_rows'] ?? [] as $r) {
                $line = [$r['line'], implode('؛ ', $r['errors'])];
                foreach (array_keys($importer->fields()) as $key) {
                    $line[] = $r['record'][$key] ?? '';
                }
                $rows[] = $line;
            }
            Spreadsheet::downloadCsv('السجلات-المرفوضة-' . $batch['id'] . '.csv', $rows);
        }
        if ($request->str('download') === 'credentials' && $credentials) {
            $rows = [['الاسم', 'الرقم', 'الفصل', 'اسم المستخدم', 'كلمة المرور الأولية']];
            foreach ($credentials as $c) {
                $rows[] = [$c['name'], $c['number'], $c['class'], $c['username'], $c['password']];
            }
            Spreadsheet::downloadCsv('بيانات-الدخول-' . $batch['id'] . '.csv', $rows);
        }

        return $this->view('import/report', [
            'title' => $importer->title() . ' — التقرير', 'importer' => $importer, 'kind' => $kind,
            'batch' => $batch, 'credentials' => $credentials, 'print' => $request->bool('cards'),
        ], $request->bool('cards') ? 'layouts/print' : 'layouts/app');
    }

    public function template(Request $request): never
    {
        $importer = $this->importer($request);
        $name = 'قالب-' . $importer->title();
        if ($request->str('format') === 'csv') {
            Spreadsheet::downloadCsv($name . '.csv', $importer->templateRows());
        }
        $help = [['الحقل', 'إلزامي', 'الوصف']];
        foreach ($importer->fields() as [$label, $required, , $desc]) {
            $help[] = [$label, $required ? 'نعم' : 'لا', $desc];
        }
        foreach ($importer->instructions() as $line) {
            $help[] = ['', '', $line];
        }
        Spreadsheet::downloadXlsx($name . '.xlsx', ['البيانات' => $importer->templateRows(), 'التعليمات' => $help]);
    }

    private function validateFile(Importer $importer, array $state): array
    {
        if (!is_array($state['mapping'] ?? null)) {
            // تخطي خطوة المطابقة (رابط مباشر أو جلسة قديمة)
            throw new HttpException(422, 'حدّد مطابقة الأعمدة أولاً من خطوة «مطابقة الأعمدة».');
        }
        $rows = Spreadsheet::read(STORAGE_PATH . '/imports/' . $state['file'], $state['sheet'] === 'CSV' ? null : $state['sheet']);
        $records = $importer->mapRows($rows, $state['mapping'], (bool) $state['header']);
        if (count($records) > 3000) {
            throw new HttpException(422, 'الملف يحتوي على أكثر من 3000 سجل — قسّمه إلى عدة ملفات.');
        }
        return $importer->validate($records, $state['options']);
    }

    private function loadBatch(string $kind, int $id): array
    {
        if ($kind === 'questions') {
            $batch = DB::first('SELECT b.*, b.imported AS created, 0 AS updated FROM question_import_batches b WHERE b.id = ?', [$id]);
            if ($batch) {
                $batch['report'] = json_decode((string) $batch['report'], true) ?: [];
            }
        } else {
            $batch = Importer::batch($id);
            if ($batch && $batch['kind'] !== $kind) {
                $batch = null;
            }
        }
        if (!$batch || ((int) $batch['user_id'] !== Auth::id() && !Auth::can('settings.manage'))) {
            throw new HttpException(404);
        }
        return $batch;
    }
}
