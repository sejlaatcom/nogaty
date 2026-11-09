-- =====================================================================
--  نظام الخطط الأسبوعية للمعلمين + منصة نافس  |  Weekly Plans & Nafes Platform
--  بنية قاعدة البيانات (MySQL / MariaDB)
--  آمن لإعادة التنفيذ: كل الجداول بصيغة CREATE TABLE IF NOT EXISTS
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- إعدادات النظام (اسم المدرسة، إدارة التعليم، الشعار ... إلخ)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(64)  NOT NULL,
  `value`      TEXT         NULL,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- المستخدمون (مدير المدرسة / وكيل / معلم / طالب)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(150) NOT NULL,
  `username`       VARCHAR(64)  NOT NULL,
  `email`          VARCHAR(150) NULL,
  `phone`          VARCHAR(32)  NULL,
  `national_id`    VARCHAR(20)  NULL,
  `job_title`      VARCHAR(80)  NULL,
  `password_hash`  VARCHAR(255) NOT NULL,
  `role`           ENUM('admin','teacher','student') NOT NULL DEFAULT 'teacher',
  `classroom_id`   INT UNSIGNED NULL,             -- فصل الطالب (للطلاب فقط)
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at`  DATETIME     NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role`, `is_active`),
  KEY `idx_users_classroom` (`classroom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الصفوف الدراسية والفصول
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `grades` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80)  NOT NULL,           -- الصف الخامس الابتدائي
  `stage`      VARCHAR(40)  NOT NULL DEFAULT 'ابتدائي',
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grades_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `classrooms` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grade_id`   INT UNSIGNED NOT NULL,
  `name`       VARCHAR(40)  NOT NULL,           -- أ / 1 / ب
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_classrooms` (`grade_id`, `name`),
  CONSTRAINT `fk_classrooms_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- المواد الدراسية
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subjects` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80)  NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subjects_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الأعوام والفصول الدراسية والأسابيع
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `academic_years` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`      VARCHAR(40)  NOT NULL,            -- 1448هـ
  `is_active` TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_years_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `terms` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year_id`    INT UNSIGNED NOT NULL,
  `name`       VARCHAR(60)  NOT NULL,           -- الفصل الدراسي الأول
  `term_no`    TINYINT      NOT NULL DEFAULT 1,
  `start_date` DATE         NULL,
  `end_date`   DATE         NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_terms` (`year_id`, `term_no`),
  CONSTRAINT `fk_terms_year` FOREIGN KEY (`year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `weeks` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `term_id`     INT UNSIGNED NOT NULL,
  `week_no`     INT          NOT NULL,
  `title`       VARCHAR(80)  NULL,              -- الأسبوع الأول
  `start_date`  DATE         NOT NULL,          -- الأحد (ميلادي)
  `end_date`    DATE         NOT NULL,          -- الخميس (ميلادي)
  `hijri_start` VARCHAR(40)  NULL,              -- 10 ربيع الأول 1448هـ
  `hijri_end`   VARCHAR(40)  NULL,
  `status`      ENUM('open','closed') NOT NULL DEFAULT 'open',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_weeks` (`term_id`, `week_no`),
  KEY `idx_weeks_dates` (`start_date`, `end_date`),
  CONSTRAINT `fk_weeks_term` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- إسناد المعلمين (معلم + مادة + فصل)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignments` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id`   INT UNSIGNED NOT NULL,
  `subject_id`   INT UNSIGNED NOT NULL,
  `classroom_id` INT UNSIGNED NOT NULL,
  `year_id`      INT UNSIGNED NOT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assignment` (`year_id`, `subject_id`, `classroom_id`),
  KEY `idx_assignment_teacher` (`teacher_id`, `year_id`),
  CONSTRAINT `fk_asg_teacher`   FOREIGN KEY (`teacher_id`)   REFERENCES `users` (`id`)          ON DELETE CASCADE,
  CONSTRAINT `fk_asg_subject`   FOREIGN KEY (`subject_id`)   REFERENCES `subjects` (`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_asg_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_asg_year`      FOREIGN KEY (`year_id`)      REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الخطط الأسبوعية
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plans` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `week_id`      INT UNSIGNED NOT NULL,
  `classroom_id` INT UNSIGNED NOT NULL,
  `subject_id`   INT UNSIGNED NOT NULL,
  `teacher_id`   INT UNSIGNED NOT NULL,
  `status`       ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `submitted_at` DATETIME     NULL,
  `reviewed_by`  INT UNSIGNED NULL,
  `reviewed_at`  DATETIME     NULL,
  `review_note`  TEXT         NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan` (`week_id`, `classroom_id`, `subject_id`),
  KEY `idx_plan_teacher` (`teacher_id`, `week_id`),
  KEY `idx_plan_status` (`status`, `week_id`),
  CONSTRAINT `fk_plan_week`      FOREIGN KEY (`week_id`)      REFERENCES `weeks` (`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_plan_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plan_subject`   FOREIGN KEY (`subject_id`)   REFERENCES `subjects` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_plan_teacher`   FOREIGN KEY (`teacher_id`)   REFERENCES `users` (`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_plan_reviewer`  FOREIGN KEY (`reviewed_by`)  REFERENCES `users` (`id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تفاصيل الخطة لكل يوم (1=الأحد ... 5=الخميس)
CREATE TABLE IF NOT EXISTS `plan_days` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_id`   INT UNSIGNED NOT NULL,
  `day_index` TINYINT UNSIGNED NOT NULL,
  `topic`     TEXT NULL,   -- موضوع الدرس
  `objective` TEXT NULL,   -- الهدف التعليمي
  `homework`  TEXT NULL,   -- الواجبات والمهام الأدائية
  `notes`     TEXT NULL,   -- ملاحظات / إثراء
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_day` (`plan_id`, `day_index`),
  CONSTRAINT `fk_planday_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- بيانات إضافية لورقة الفصل (رسالة إلى الأسرة)
CREATE TABLE IF NOT EXISTS `class_week_meta` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `week_id`        INT UNSIGNED NOT NULL,
  `classroom_id`   INT UNSIGNED NOT NULL,
  `family_message` TEXT NULL,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_week_meta` (`week_id`, `classroom_id`),
  CONSTRAINT `fk_cwm_week`      FOREIGN KEY (`week_id`)      REFERENCES `weeks` (`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_cwm_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- سجل العمليات ومحاولات الدخول
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NULL,
  `action`     VARCHAR(60)  NOT NULL,
  `entity`     VARCHAR(60)  NULL,
  `entity_id`  INT UNSIGNED NULL,
  `details`    VARCHAR(500) NULL,
  `ip`         VARCHAR(45)  NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_user` (`user_id`, `created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(64) NOT NULL,
  `ip`         VARCHAR(45) NOT NULL,
  `success`    TINYINT(1)  NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts` (`ip`, `created_at`),
  KEY `idx_attempts_user` (`username`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  منصة نافس — بنك الأسئلة
-- =====================================================================

-- المجالات (مثل: الأعداد والعمليات، علوم الحياة، الفهم القرائي)
CREATE TABLE IF NOT EXISTS `nafes_domains` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id` INT UNSIGNED NOT NULL,
  `grade_id`   INT UNSIGNED NULL,               -- فارغ = لكل الصفوف
  `name`       VARCHAR(150) NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_domains_subject` (`subject_id`, `grade_id`),
  CONSTRAINT `fk_domains_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_domains_grade`   FOREIGN KEY (`grade_id`)   REFERENCES `grades` (`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- المهارات / نواتج التعلم داخل كل مجال
CREATE TABLE IF NOT EXISTS `nafes_skills` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain_id`  INT UNSIGNED NOT NULL,
  `code`       VARCHAR(20)  NULL,               -- رمز المهارة (اختياري) مثل: ر3-1
  `name`       VARCHAR(255) NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_skills_domain` (`domain_id`),
  CONSTRAINT `fk_skills_domain` FOREIGN KEY (`domain_id`) REFERENCES `nafes_domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- نصوص القراءة (يُبنى عليها أكثر من سؤال)
CREATE TABLE IF NOT EXISTS `nafes_passages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id` INT UNSIGNED NOT NULL,
  `grade_id`   INT UNSIGNED NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `body`       TEXT         NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_passages_subject` (`subject_id`, `grade_id`),
  CONSTRAINT `fk_passages_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_passages_grade`   FOREIGN KEY (`grade_id`)   REFERENCES `grades` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_passages_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- الأسئلة (اختيار من متعدد / صح وخطأ)
CREATE TABLE IF NOT EXISTS `nafes_questions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id`  INT UNSIGNED NOT NULL,
  `grade_id`    INT UNSIGNED NOT NULL,
  `skill_id`    INT UNSIGNED NULL,
  `passage_id`  INT UNSIGNED NULL,
  `type`        ENUM('mcq','tf') NOT NULL DEFAULT 'mcq',
  `stem`        TEXT         NOT NULL,          -- نص السؤال
  `image`       VARCHAR(120) NULL,
  `difficulty`  TINYINT UNSIGNED NOT NULL DEFAULT 2,  -- 1 سهل، 2 متوسط، 3 صعب
  `explanation` TEXT         NULL,              -- تفسير الإجابة (يظهر عند المراجعة)
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_questions_filter` (`subject_id`, `grade_id`, `is_active`),
  KEY `idx_questions_skill` (`skill_id`),
  CONSTRAINT `fk_questions_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_questions_grade`   FOREIGN KEY (`grade_id`)   REFERENCES `grades` (`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_questions_skill`   FOREIGN KEY (`skill_id`)   REFERENCES `nafes_skills` (`id`)   ON DELETE SET NULL,
  CONSTRAINT `fk_questions_passage` FOREIGN KEY (`passage_id`) REFERENCES `nafes_passages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_questions_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nafes_options` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `body`        TEXT         NOT NULL,
  `is_correct`  TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_options_question` (`question_id`, `sort_order`),
  CONSTRAINT `fk_options_question` FOREIGN KEY (`question_id`) REFERENCES `nafes_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  منصة نافس — الاختبارات والمحاولات
-- =====================================================================
CREATE TABLE IF NOT EXISTS `nafes_tests` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`             VARCHAR(200) NOT NULL,
  `subject_id`        INT UNSIGNED NOT NULL,
  `grade_id`          INT UNSIGNED NOT NULL,
  `kind`              ENUM('diagnostic','training','mock','final') NOT NULL DEFAULT 'training',
  `instructions`      TEXT         NULL,
  `duration_min`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- 0 = بدون وقت محدد
  `starts_at`         DATETIME     NULL,
  `ends_at`           DATETIME     NULL,
  `max_attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 1,    -- 0 = غير محدود
  `shuffle_questions` TINYINT(1)   NOT NULL DEFAULT 1,
  `shuffle_options`   TINYINT(1)   NOT NULL DEFAULT 1,
  `show_review`       TINYINT(1)   NOT NULL DEFAULT 1,        -- إظهار الإجابات الصحيحة بعد التسليم
  `status`            ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  `created_by`        INT UNSIGNED NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tests_status` (`status`, `subject_id`, `grade_id`),
  CONSTRAINT `fk_tests_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tests_grade`   FOREIGN KEY (`grade_id`)   REFERENCES `grades` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_tests_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nafes_test_questions` (
  `test_id`     INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`test_id`, `question_id`),
  KEY `idx_tq_question` (`question_id`),
  CONSTRAINT `fk_tq_test`     FOREIGN KEY (`test_id`)     REFERENCES `nafes_tests` (`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_tq_question` FOREIGN KEY (`question_id`) REFERENCES `nafes_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- الفصول المستهدفة بالاختبار
CREATE TABLE IF NOT EXISTS `nafes_test_classrooms` (
  `test_id`      INT UNSIGNED NOT NULL,
  `classroom_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`test_id`, `classroom_id`),
  KEY `idx_tc_classroom` (`classroom_id`),
  CONSTRAINT `fk_tc_test`      FOREIGN KEY (`test_id`)      REFERENCES `nafes_tests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tc_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- محاولات الطلاب (اختبار معلَن أو تدريب ذاتي)
CREATE TABLE IF NOT EXISTS `nafes_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id`      INT UNSIGNED NULL,               -- فارغ في التدريب الذاتي
  `student_id`   INT UNSIGNED NOT NULL,
  `classroom_id` INT UNSIGNED NULL,               -- فصل الطالب وقت المحاولة
  `subject_id`   INT UNSIGNED NOT NULL,
  `grade_id`     INT UNSIGNED NOT NULL,
  `skill_id`     INT UNSIGNED NULL,               -- مهارة التدريب الذاتي
  `mode`         ENUM('test','practice') NOT NULL DEFAULT 'test',
  `status`       ENUM('in_progress','submitted') NOT NULL DEFAULT 'in_progress',
  `started_at`   DATETIME     NOT NULL,
  `deadline_at`  DATETIME     NULL,
  `submitted_at` DATETIME     NULL,
  `score`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `total`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `percent`      DECIMAL(5,2) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_student` (`student_id`, `status`),
  KEY `idx_attempts_test` (`test_id`, `status`),
  CONSTRAINT `fk_attempts_test`    FOREIGN KEY (`test_id`)    REFERENCES `nafes_tests` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)        ON DELETE CASCADE,
  CONSTRAINT `fk_attempts_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_attempts_grade`   FOREIGN KEY (`grade_id`)   REFERENCES `grades` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_attempts_skill`   FOREIGN KEY (`skill_id`)   REFERENCES `nafes_skills` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nafes_attempt_answers` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`   INT UNSIGNED NOT NULL,
  `question_id`  INT UNSIGNED NOT NULL,
  `sort_order`   INT          NOT NULL DEFAULT 0,
  `option_order` VARCHAR(120) NULL,               -- ترتيب الخيارات المعروض للطالب (معرفات مفصولة بفواصل)
  `option_id`    INT UNSIGNED NULL,               -- الخيار الذي اختاره الطالب
  `is_correct`   TINYINT(1)   NULL,
  `flagged`      TINYINT(1)   NOT NULL DEFAULT 0, -- علامة «للمراجعة»
  `answered_at`  DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attempt_question` (`attempt_id`, `question_id`),
  KEY `idx_answers_question` (`question_id`),
  CONSTRAINT `fk_answers_attempt`  FOREIGN KEY (`attempt_id`)  REFERENCES `nafes_attempts` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `nafes_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_answers_option`   FOREIGN KEY (`option_id`)   REFERENCES `nafes_options` (`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
