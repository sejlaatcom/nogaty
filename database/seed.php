<?php
/**
 * تهيئة البيانات الأساسية والبيانات التجريبية.
 * تُستدعى من المثبّت install.php أو من tools/seed.php
 */
declare(strict_types=1);

/** البيانات الأساسية: الإعدادات، المواد، الصفوف، العام والفصول الدراسية والأسابيع */
function seed_base(array $school = []): void
{
    Settings::setMany([
        'school_name'     => $school['school']    ?? Settings::DEFAULTS['school_name'],
        'education_dept'  => $school['dept']      ?? Settings::DEFAULTS['education_dept'],
        'principal_name'  => $school['principal'] ?? '',
        'ministry_name'   => Settings::DEFAULTS['ministry_name'],
        'country_name'    => Settings::DEFAULTS['country_name'],
        'sheet_title'     => Settings::DEFAULTS['sheet_title'],
        'family_message'  => Settings::DEFAULTS['family_message'],
        'footer_text'     => Settings::DEFAULTS['footer_text'],
        'principal_title' => Settings::DEFAULTS['principal_title'],
        'show_notes_row'  => '0',
        'show_teacher_name' => '1',
        'public_enabled'  => '1',
    ]);

    // المواد الدراسية
    $subjects = ['القرآن الكريم', 'الدراسات الإسلامية', 'لغتي', 'الرياضيات', 'العلوم',
                 'الدراسات الاجتماعية', 'اللغة الإنجليزية', 'المهارات الرقمية', 'التربية الفنية', 'التربية البدنية'];
    foreach ($subjects as $order => $name) {
        Database::run(
            'INSERT INTO subjects (name, sort_order) VALUES (?, ?) ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)',
            [$name, $order + 1]
        );
    }

    // الصفوف الدراسية (المرحلة الابتدائية افتراضياً) مع فصل واحد لكل صف
    $grades = ['الأول الابتدائي', 'الثاني الابتدائي', 'الثالث الابتدائي',
               'الرابع الابتدائي', 'الخامس الابتدائي', 'السادس الابتدائي'];
    foreach ($grades as $order => $name) {
        Database::run(
            'INSERT INTO grades (name, stage, sort_order) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)',
            [$name, 'ابتدائي', $order + 1]
        );
        $gradeId = (int) Database::value('SELECT id FROM grades WHERE name = ?', [$name]);
        Database::run(
            'INSERT INTO classrooms (grade_id, name, sort_order) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE sort_order = 1',
            [$gradeId, 'أ']
        );
    }

    // العام الدراسي الحالي (هجري) والفصول الثلاثة
    $hijriYear = Hijri::currentYear();
    $yearName  = $hijriYear . 'هـ';
    Database::run('INSERT INTO academic_years (name, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_active = 1', [$yearName]);
    $yearId = (int) Database::value('SELECT id FROM academic_years WHERE name = ?', [$yearName]);

    foreach ([1 => 'الفصل الدراسي الأول', 2 => 'الفصل الدراسي الثاني', 3 => 'الفصل الدراسي الثالث'] as $no => $termName) {
        Database::run(
            'INSERT INTO terms (year_id, name, term_no, is_active) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name)',
            [$yearId, $termName, $no, $no === 1 ? 1 : 0]
        );
    }

    // توليد 13 أسبوعاً للفصل الأول ابتداءً من أقرب يوم أحد
    $termId = (int) Database::value('SELECT id FROM terms WHERE year_id = ? AND term_no = 1', [$yearId]);
    if ($termId && (int) Database::value('SELECT COUNT(*) FROM weeks WHERE term_id = ?', [$termId], 0) === 0) {
        $sunday = new DateTimeImmutable('now');
        while ($sunday->format('w') !== '0') {
            $sunday = $sunday->modify('-1 day');
        }
        Plans::generateWeeks($termId, $sunday->format('Y-m-d'), 13);
        Database::run('UPDATE terms SET start_date = (SELECT MIN(start_date) FROM weeks WHERE term_id = ?),
                                        end_date   = (SELECT MAX(end_date)   FROM weeks WHERE term_id = ?)
                       WHERE id = ?', [$termId, $termId, $termId]);
    }
}

/** بيانات تجريبية: معلمون، إسناد، وخطط بحالات مختلفة */
function seed_demo(): void
{
    $yearId = (int) Database::value('SELECT id FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
    if (!$yearId) {
        return;
    }

    $teachers = [
        ['سعد بن ناصر السالم',   'saad',   'القرآن الكريم'],
        ['أحمد بن علي الزهراني', 'ahmed',  'الدراسات الإسلامية'],
        ['ماجد بن فهد العتيبي',  'majed',  'لغتي'],
        ['فهد بن سعود الحربي',   'fahad',  'الرياضيات'],
        ['تركي بن خالد القحطاني','turki',  'العلوم'],
        ['بندر بن عبدالله الدوسري','bandar','الدراسات الاجتماعية'],
        ['وليد بن محمد الشمري',  'walid',  'اللغة الإنجليزية'],
        ['عمر بن يوسف الغامدي',  'omar',   'المهارات الرقمية'],
        ['نايف بن سالم المطيري', 'nayef',  'التربية الفنية'],
    ];

    $classrooms = Database::all('SELECT id FROM classrooms ORDER BY id');
    if (!$classrooms) {
        return;
    }

    foreach ($teachers as [$name, $username, $subjectName]) {
        $subjectId = (int) Database::value('SELECT id FROM subjects WHERE name = ?', [$subjectName], 0);
        if (!$subjectId) {
            continue;
        }
        $teacherId = (int) Database::value('SELECT id FROM users WHERE username = ?', [$username], 0);
        if (!$teacherId) {
            $teacherId = Database::insert('users', [
                'name'          => $name,
                'username'      => $username,
                'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                'role'          => 'teacher',
                'job_title'     => 'معلم',
                'must_change_password' => 1,
            ]);
        }
        foreach ($classrooms as $classroom) {
            Database::run(
                'INSERT IGNORE INTO assignments (teacher_id, subject_id, classroom_id, year_id) VALUES (?, ?, ?, ?)',
                [$teacherId, $subjectId, $classroom['id'], $yearId]
            );
        }
    }

    // خطط تجريبية للأسبوع الأول من الفصل النشط
    $week = Plans::currentWeek();
    if (!$week) {
        return;
    }
    $weekId      = (int) $week['id'];
    $classroomId = (int) $classrooms[0]['id'];

    $samples = [
        'القرآن الكريم'        => ['حفظ سورة البروج', 'تلاوة مجودة', 'مراجعة الحفظ', 'تسميع سورة البروج', 'حفظ الآيات 1-7'],
        'الدراسات الإسلامية'   => ['آداب المسجد', 'الطهارة', 'فضل الصلاة', 'زكاة الفطر', 'مراجعة الوحدة'],
        'لغتي'                 => ['قراءة النص', 'الاسم الموصول', 'كتابة موضوع تعبير', 'يكتب الكلمات صحيحة', 'الجملة الفعلية'],
        'الرياضيات'            => ['الكسور العشرية', 'جمع الكسور', 'طرح الكسور', 'تمارين عامة', 'اختبار قصير'],
        'العلوم'               => ['تحولات الطاقة', 'مصادر الطاقة', 'تجربة الضوء', 'الطاقة الحركية', 'مراجعة الوحدة'],
        'الدراسات الاجتماعية'  => ['إحضار كشاف', 'يقرأ جدول بيانات', 'تقرير التجربة', 'مناطق المملكة', 'رؤية 2030'],
    ];

    $statuses = ['approved', 'approved', 'approved', 'approved', 'submitted', 'submitted'];
    $index    = 0;

    foreach ($samples as $subjectName => $topics) {
        $subjectId = (int) Database::value('SELECT id FROM subjects WHERE name = ?', [$subjectName], 0);
        $teacherId = (int) Database::value(
            'SELECT teacher_id FROM assignments WHERE subject_id = ? AND classroom_id = ? AND year_id = ?',
            [$subjectId, $classroomId, $yearId],
            0
        );
        if (!$subjectId || !$teacherId) {
            continue;
        }

        $plan   = Plans::findOrCreate($weekId, $classroomId, $subjectId, $teacherId);
        $planId = (int) $plan['id'];

        $days = [];
        foreach (array_keys(WEEK_DAYS) as $dayIndex) {
            $days[$dayIndex] = [
                'topic'     => $topics[$dayIndex - 1] ?? '',
                'objective' => 'أن يتقن الطالب ' . ($topics[$dayIndex - 1] ?? ''),
                'homework'  => 'حل تمارين الكتاب صفحة ' . (10 + $dayIndex),
                'notes'     => '',
            ];
        }
        Plans::saveDays($planId, $days);

        $status = $statuses[$index] ?? 'submitted';
        if ($status === 'approved') {
            Database::run(
                "UPDATE plans SET status='approved', submitted_at=NOW(), reviewed_at=NOW(),
                        reviewed_by=(SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1) WHERE id = ?",
                [$planId]
            );
        } else {
            Database::run("UPDATE plans SET status='submitted', submitted_at=NOW() WHERE id = ?", [$planId]);
        }
        $index++;
    }
}

/** إعدادات منصة نافس الافتراضية: المواد والصفوف المستهدفة */
function seed_nafes(): void
{
    $subjectIds = [];
    foreach (['لغتي', 'الرياضيات', 'العلوم'] as $name) {
        $id = (int) Database::value('SELECT id FROM subjects WHERE name = ?', [$name], 0);
        if ($id) {
            $subjectIds[] = $id;
        }
    }
    $gradeIds = [];
    foreach (['الثالث الابتدائي', 'السادس الابتدائي', 'الثالث المتوسط'] as $name) {
        $id = (int) Database::value('SELECT id FROM grades WHERE name = ?', [$name], 0);
        if ($id) {
            $gradeIds[] = $id;
        }
    }
    Settings::setMany([
        'nafes_subject_ids' => implode(',', $subjectIds),
        'nafes_grade_ids'   => implode(',', $gradeIds),
    ]);

    seed_nafes_bank(false);
}

/**
 * إطار مهارات نافس (المجالات والمهارات) من database/nafes_data.php،
 * ومع $withQuestions تُضاف الأسئلة والنصوص التجريبية أيضاً.
 */
function seed_nafes_bank(bool $withQuestions): void
{
    $data    = require __DIR__ . '/nafes_data.php';
    $adminId = (int) Database::value("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1", [], 0) ?: null;

    foreach ($data as $block) {
        $subjectId = (int) Database::value('SELECT id FROM subjects WHERE name = ?', [$block['subject']], 0);
        $gradeId   = (int) Database::value('SELECT id FROM grades WHERE name = ?', [$block['grade']], 0);
        if (!$subjectId || !$gradeId) {
            continue;
        }

        $skillIds = [];
        $order    = 0;
        foreach ($block['domains'] as $domainName => $skills) {
            $domainId = (int) Database::value(
                'SELECT id FROM nafes_domains WHERE subject_id = ? AND grade_id = ? AND name = ?',
                [$subjectId, $gradeId, $domainName],
                0
            ) ?: Database::insert('nafes_domains', [
                'subject_id' => $subjectId, 'grade_id' => $gradeId, 'name' => $domainName, 'sort_order' => ++$order,
            ]);

            $skillOrder = 0;
            foreach ($skills as $code => [$skillName, $questions]) {
                $skillId = (int) Database::value('SELECT id FROM nafes_skills WHERE domain_id = ? AND code = ?', [$domainId, $code], 0)
                    ?: Database::insert('nafes_skills', ['domain_id' => $domainId, 'code' => $code, 'name' => $skillName, 'sort_order' => ++$skillOrder]);
                $skillIds[$code] = $skillId;

                if ($withQuestions) {
                    foreach ($questions as $question) {
                        seed_nafes_question($subjectId, $gradeId, $skillId, null, $question, $adminId);
                    }
                }
            }
        }

        if (!$withQuestions) {
            continue;
        }
        foreach ($block['passages'] ?? [] as $passage) {
            if (Database::value('SELECT id FROM nafes_passages WHERE subject_id = ? AND grade_id = ? AND title = ?', [$subjectId, $gradeId, $passage['title']])) {
                continue;
            }
            $passageId = Database::insert('nafes_passages', [
                'subject_id' => $subjectId, 'grade_id' => $gradeId,
                'title' => $passage['title'], 'body' => $passage['body'], 'created_by' => $adminId,
            ]);
            foreach ($passage['questions'] as $question) {
                $code = array_shift($question);
                seed_nafes_question($subjectId, $gradeId, $skillIds[$code] ?? null, $passageId, $question, $adminId);
            }
        }
    }
}

function seed_nafes_question(int $subjectId, int $gradeId, ?int $skillId, ?int $passageId, array $question, ?int $userId): void
{
    if (Database::value('SELECT id FROM nafes_questions WHERE subject_id = ? AND grade_id = ? AND stem = ?', [$subjectId, $gradeId, $question[0]])) {
        return; // السؤال مضاف مسبقاً
    }
    if (is_bool($question[1])) {
        [$stem, $isTrue, $difficulty] = $question;
        $type    = 'tf';
        $options = [['body' => 'صح', 'is_correct' => $isTrue]];
        $explain = $question[3] ?? '';
    } else {
        [$stem, $choices, $correct, $difficulty] = $question;
        $type    = 'mcq';
        $options = array_map(static fn ($body, $i) => ['body' => $body, 'is_correct' => $i === $correct], $choices, array_keys($choices));
        $explain = $question[4] ?? '';
    }
    QuestionBank::save(null, [
        'subject_id' => $subjectId, 'grade_id' => $gradeId, 'skill_id' => $skillId, 'passage_id' => $passageId,
        'type' => $type, 'stem' => $stem, 'difficulty' => $difficulty, 'explanation' => $explain, 'is_active' => 1,
    ], $options, (int) $userId);
}

/** بيانات نافس التجريبية: أسئلة ونصوص، وطلاب لفصول صفوف نافس */
function seed_nafes_demo(): void
{
    seed_nafes_bank(true);

    $first = ['عبدالله', 'محمد', 'فيصل', 'سلمان', 'خالد', 'يزن', 'ريان', 'مشعل', 'عبدالرحمن', 'نواف', 'راكان', 'مازن'];
    $last  = ['العتيبي', 'القحطاني', 'الحربي', 'الغامدي', 'الشهري', 'الدوسري', 'المطيري', 'الزهراني', 'السبيعي', 'العنزي'];

    $gradeIds = Nafes::idList(Settings::get('nafes_grade_ids'));
    if (!$gradeIds) {
        return;
    }
    $in = implode(',', $gradeIds);
    $classrooms = Database::all("SELECT c.id FROM classrooms c WHERE c.grade_id IN ($in) ORDER BY c.id");

    $n = 0;
    foreach ($classrooms as $classroom) {
        for ($i = 0; $i < 8; $i++, $n++) {
            $username = 'st' . str_pad((string) ($n + 1), 3, '0', STR_PAD_LEFT);
            if (Database::value('SELECT id FROM users WHERE username = ?', [$username])) {
                continue;
            }
            Database::insert('users', [
                'name'          => $first[$n % count($first)] . ' ' . $first[($n * 7 + 3) % count($first)] . ' ' . $last[($n * 3) % count($last)],
                'username'      => $username,
                'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                'role'          => 'student',
                'job_title'     => 'طالب',
                'classroom_id'  => $classroom['id'],
            ]);
        }
    }
}
