-- =====================================================================
--  منصة التدريب على اختبارات نافس
--  مجمع الأمير سعود بن نايف التعليمي بالدمام — القسم المتوسط والثانوي (بنين)
--  قاعدة بيانات لمدرسة واحدة — MySQL 8 / MariaDB 10.4+
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- الإعدادات
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(80)  NOT NULL,
  `value`      TEXT         NULL,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version`    VARCHAR(80) NOT NULL,
  `applied_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- المستخدمون والأدوار والصلاحيات
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`             VARCHAR(64)  NOT NULL,
  `password_hash`        VARCHAR(255) NOT NULL,
  `full_name`            VARCHAR(150) NOT NULL,
  `email`                VARCHAR(150) NULL,
  `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
  `must_change_password` TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login_at`        DATETIME     NULL,
  `created_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(40)  NOT NULL,
  `name`        VARCHAR(80)  NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(60)  NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `group_name` VARCHAR(60)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  KEY `idx_ur_role` (`role_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(64)  NOT NULL,
  `ip`         VARCHAR(45)  NOT NULL,
  `success`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_la_ip` (`ip`, `created_at`),
  KEY `idx_la_user` (`username`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- العام الدراسي والصفوف والفصول والمواد
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `academic_years` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(40)  NOT NULL,             -- 1448هـ
  `starts_on`   DATE         NULL,
  `ends_on`     DATE         NULL,
  `is_current`  TINYINT(1)   NOT NULL DEFAULT 0,
  `is_archived` TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_years_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `grades` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(80)  NOT NULL,          -- الثالث المتوسط
  `stage`           ENUM('primary','intermediate','secondary') NOT NULL DEFAULT 'intermediate',
  `level_order`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_nafes_target` TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grades_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `classes` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id` INT UNSIGNED NOT NULL,
  `grade_id`         INT UNSIGNED NOT NULL,
  `name`             VARCHAR(40)  NOT NULL,         -- 1 / أ
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_classes` (`academic_year_id`, `grade_id`, `name`),
  KEY `idx_classes_grade` (`grade_id`),
  CONSTRAINT `fk_classes_year`  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_classes_grade` FOREIGN KEY (`grade_id`)         REFERENCES `grades` (`id`)         ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subjects` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80)  NOT NULL,
  `code`       VARCHAR(20)  NOT NULL,               -- reading / math / science
  `color`      VARCHAR(9)   NULL,
  `icon`       VARCHAR(40)  NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subjects_name` (`name`),
  UNIQUE KEY `uq_subjects_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الطلاب والمعلمون والتسجيل والإسناد
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `student_number` VARCHAR(30)  NOT NULL,           -- الرقم المدرسي / معرف الطالب
  `status`         ENUM('active','inactive','graduated','transferred_out') NOT NULL DEFAULT 'active',
  `notes`          VARCHAR(500) NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_user` (`user_id`),
  UNIQUE KEY `uq_students_number` (`student_number`),
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teachers` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `employee_number` VARCHAR(30)  NOT NULL,          -- معرف المعلم
  `specialization`  VARCHAR(100) NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teachers_user` (`user_id`),
  UNIQUE KEY `uq_teachers_number` (`employee_number`),
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تسجيل الطالب في فصل لعام دراسي؛ ينتهي التسجيل عند النقل (left_on) ويبقى تاريخياً
CREATE TABLE IF NOT EXISTS `student_class_enrollments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`       INT UNSIGNED NOT NULL,
  `class_id`         INT UNSIGNED NOT NULL,
  `academic_year_id` INT UNSIGNED NOT NULL,
  `enrolled_on`      DATE         NOT NULL,
  `left_on`          DATE         NULL,
  `status`           ENUM('active','transferred','completed') NOT NULL DEFAULT 'active',
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sce_student` (`student_id`, `status`),
  KEY `idx_sce_class` (`class_id`, `status`),
  KEY `idx_sce_year` (`academic_year_id`),
  CONSTRAINT `fk_sce_student` FOREIGN KEY (`student_id`)       REFERENCES `students` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_sce_class`   FOREIGN KEY (`class_id`)         REFERENCES `classes` (`id`)        ON DELETE RESTRICT,
  CONSTRAINT `fk_sce_year`    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إسناد المعلم/المشرف لفصل (ومادة اختيارية: فارغة = إشراف على كل مواد الفصل)
CREATE TABLE IF NOT EXISTS `teacher_assignments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id`       INT UNSIGNED NOT NULL,
  `class_id`         INT UNSIGNED NOT NULL,
  `subject_id`       INT UNSIGNED NULL,
  `academic_year_id` INT UNSIGNED NOT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ta` (`teacher_id`, `class_id`, `subject_id`),
  KEY `idx_ta_class` (`class_id`),
  CONSTRAINT `fk_ta_teacher` FOREIGN KEY (`teacher_id`)       REFERENCES `teachers` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_ta_class`   FOREIGN KEY (`class_id`)         REFERENCES `classes` (`id`)        ON DELETE CASCADE,
  CONSTRAINT `fk_ta_subject` FOREIGN KEY (`subject_id`)       REFERENCES `subjects` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_ta_year`    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `import_batches` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind`       ENUM('students','teachers','taxonomy') NOT NULL,
  `user_id`    INT UNSIGNED NULL,
  `filename`   VARCHAR(200) NOT NULL,
  `total`      INT          NOT NULL DEFAULT 0,
  `created`    INT          NOT NULL DEFAULT 0,
  `updated`    INT          NOT NULL DEFAULT 0,
  `rejected`   INT          NOT NULL DEFAULT 0,
  `report`     LONGTEXT     NULL,                   -- JSON
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ib_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- التصنيفات: المادة ← المجال ← المهارة ← مؤشر الأداء
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `domains` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id` INT UNSIGNED NOT NULL,
  `grade_id`   INT UNSIGNED NULL,                   -- فارغ = لكل الصفوف
  `name`       VARCHAR(150) NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domains` (`subject_id`, `grade_id`, `name`),
  CONSTRAINT `fk_domains_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_domains_grade`   FOREIGN KEY (`grade_id`)   REFERENCES `grades` (`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `skills` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain_id`  INT UNSIGNED NOT NULL,
  `code`       VARCHAR(30)  NULL,
  `name`       VARCHAR(255) NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_skills` (`domain_id`, `name`),
  CONSTRAINT `fk_skills_domain` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `learning_indicators` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `skill_id`    INT UNSIGNED NOT NULL,
  `code`        VARCHAR(30)  NULL,
  `description` VARCHAR(255) NOT NULL,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_indicators` (`skill_id`, `description`),
  CONSTRAINT `fk_ind_skill` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- النصوص القرائية التي يتبعها عدة أسئلة
CREATE TABLE IF NOT EXISTS `passages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id` INT UNSIGNED NOT NULL,
  `grade_id`   INT UNSIGNED NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `body`       MEDIUMTEXT   NOT NULL,
  `source`     VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_passages_sg` (`subject_id`, `grade_id`),
  CONSTRAINT `fk_passages_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_passages_grade`   FOREIGN KEY (`grade_id`)   REFERENCES `grades` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_passages_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- بنك الأسئلة
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `questions` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id`      INT UNSIGNED NOT NULL,
  `grade_id`        INT UNSIGNED NOT NULL,
  `domain_id`       INT UNSIGNED NULL,
  `skill_id`        INT UNSIGNED NULL,
  `indicator_id`    INT UNSIGNED NULL,
  `passage_id`      INT UNSIGNED NULL,
  `type`            ENUM('single','true_false','multiple','matching','ordering','short_answer') NOT NULL DEFAULT 'single',
  `stem`            TEXT         NOT NULL,
  `explanation`     TEXT         NULL,
  `common_mistakes` TEXT         NULL,
  `difficulty`      TINYINT UNSIGNED NOT NULL DEFAULT 2,   -- 1 سهل · 2 متوسط · 3 صعب
  `cognitive_level` ENUM('knowing','applying','reasoning') NOT NULL DEFAULT 'applying',
  `points`          DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `manual_grading`  TINYINT(1)   NOT NULL DEFAULT 0,       -- للإجابة القصيرة: تصحيح يدوي دائماً
  `source`          VARCHAR(255) NULL,
  `usage_rights`    VARCHAR(255) NULL,
  `status`          ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
  `content_hash`    CHAR(64)     NOT NULL,                  -- لاكتشاف التكرار
  `version`         INT UNSIGNED NOT NULL DEFAULT 1,
  `created_by`      INT UNSIGNED NULL,
  `updated_by`      INT UNSIGNED NULL,
  `reviewed_by`     INT UNSIGNED NULL,
  `reviewed_at`     DATETIME     NULL,
  `review_note`     VARCHAR(500) NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_q_filter` (`subject_id`, `grade_id`, `status`),
  KEY `idx_q_skill` (`skill_id`, `status`),
  KEY `idx_q_hash` (`content_hash`),
  CONSTRAINT `fk_q_subject`   FOREIGN KEY (`subject_id`)   REFERENCES `subjects` (`id`)            ON DELETE RESTRICT,
  CONSTRAINT `fk_q_grade`     FOREIGN KEY (`grade_id`)     REFERENCES `grades` (`id`)              ON DELETE RESTRICT,
  CONSTRAINT `fk_q_domain`    FOREIGN KEY (`domain_id`)    REFERENCES `domains` (`id`)             ON DELETE SET NULL,
  CONSTRAINT `fk_q_skill`     FOREIGN KEY (`skill_id`)     REFERENCES `skills` (`id`)              ON DELETE SET NULL,
  CONSTRAINT `fk_q_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `learning_indicators` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_q_passage`   FOREIGN KEY (`passage_id`)   REFERENCES `passages` (`id`)            ON DELETE SET NULL,
  CONSTRAINT `fk_q_creator`   FOREIGN KEY (`created_by`)   REFERENCES `users` (`id`)               ON DELETE SET NULL,
  CONSTRAINT `fk_q_updater`   FOREIGN KEY (`updated_by`)   REFERENCES `users` (`id`)               ON DELETE SET NULL,
  CONSTRAINT `fk_q_reviewer`  FOREIGN KEY (`reviewed_by`)  REFERENCES `users` (`id`)               ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- الخيارات: single/true_false/multiple (is_correct) · matching (body ↔ match_text)
-- ordering (correct_position) · short_answer (الإجابات المقبولة is_correct=1)
CREATE TABLE IF NOT EXISTS `question_options` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id`      INT UNSIGNED NOT NULL,
  `body`             TEXT         NOT NULL,
  `match_text`       TEXT         NULL,
  `is_correct`       TINYINT(1)   NOT NULL DEFAULT 0,
  `correct_position` TINYINT UNSIGNED NULL,
  `feedback`         VARCHAR(500) NULL,             -- تفسير اختيار هذا البديل (خطأ شائع)
  `sort_order`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_qo_question` (`question_id`, `sort_order`),
  CONSTRAINT `fk_qo_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `question_media` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NULL,
  `passage_id`  INT UNSIGNED NULL,
  `file_path`   VARCHAR(255) NOT NULL,              -- نسبي داخل storage/uploads
  `mime`        VARCHAR(60)  NOT NULL,
  `size`        INT UNSIGNED NOT NULL,
  `alt_text`    VARCHAR(255) NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qm_question` (`question_id`),
  CONSTRAINT `fk_qm_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qm_passage`  FOREIGN KEY (`passage_id`)  REFERENCES `passages` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_qm_user`     FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `question_revisions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `version`     INT UNSIGNED NOT NULL,
  `snapshot`    LONGTEXT     NOT NULL,              -- JSON للسؤال وخياراته قبل التعديل
  `changed_by`  INT UNSIGNED NULL,
  `change_note` VARCHAR(255) NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qr_question` (`question_id`, `version`),
  CONSTRAINT `fk_qr_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_user`     FOREIGN KEY (`changed_by`)  REFERENCES `users` (`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `question_import_batches` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NULL,
  `filename`   VARCHAR(200) NOT NULL,
  `sheet`      VARCHAR(100) NULL,
  `total`      INT          NOT NULL DEFAULT 0,
  `imported`   INT          NOT NULL DEFAULT 0,
  `duplicates` INT          NOT NULL DEFAULT 0,
  `rejected`   INT          NOT NULL DEFAULT 0,
  `report`     LONGTEXT     NULL,                   -- JSON
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_qib_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الاختبارات
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tests` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`             VARCHAR(200) NOT NULL,
  `description`       TEXT         NULL,
  `type`              ENUM('diagnostic','training','mock','single_skill','remedial','enrichment','homework','post','competition','practice') NOT NULL DEFAULT 'training',
  `academic_year_id`  INT UNSIGNED NOT NULL,
  `grade_id`          INT UNSIGNED NOT NULL,
  `subject_id`        INT UNSIGNED NULL,            -- فارغ في المحاكاة الشاملة لعدة مواد
  `duration_minutes`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `available_from`    DATETIME     NULL,
  `available_until`   DATETIME     NULL,
  `max_attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- 0 = غير محدود
  `shuffle_questions` TINYINT(1)   NOT NULL DEFAULT 1,
  `shuffle_options`   TINYINT(1)   NOT NULL DEFAULT 1,
  `result_visibility` ENUM('immediate','after_close','manual') NOT NULL DEFAULT 'immediate',
  `results_released`  TINYINT(1)   NOT NULL DEFAULT 0,
  `allow_review`      TINYINT(1)   NOT NULL DEFAULT 1,
  `show_explanations` TINYINT(1)   NOT NULL DEFAULT 1,
  `blueprint`         TEXT         NULL,            -- JSON لمخطط التوليد
  `total_points`      DECIMAL(7,2) NOT NULL DEFAULT 0,
  `status`            ENUM('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
  `remedial_plan_id`  INT UNSIGNED NULL,
  `created_by`        INT UNSIGNED NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tests_status` (`status`, `type`),
  KEY `idx_tests_year` (`academic_year_id`, `grade_id`, `subject_id`),
  CONSTRAINT `fk_tests_year`    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tests_grade`   FOREIGN KEY (`grade_id`)         REFERENCES `grades` (`id`)         ON DELETE RESTRICT,
  CONSTRAINT `fk_tests_subject` FOREIGN KEY (`subject_id`)       REFERENCES `subjects` (`id`)       ON DELETE RESTRICT,
  CONSTRAINT `fk_tests_creator` FOREIGN KEY (`created_by`)       REFERENCES `users` (`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- نسخة مجمّدة من السؤال وقت إضافته للاختبار (لا تتأثر بتعديل البنك لاحقاً)
CREATE TABLE IF NOT EXISTS `test_questions` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id`          INT UNSIGNED NOT NULL,
  `question_id`      INT UNSIGNED NULL,
  `question_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `subject_id`       INT UNSIGNED NOT NULL,
  `domain_id`        INT UNSIGNED NULL,
  `skill_id`         INT UNSIGNED NULL,
  `type`             VARCHAR(20)  NOT NULL,
  `snapshot`         LONGTEXT     NOT NULL,         -- JSON: النص، الخيارات، الإجابة، الوسائط، النص القرائي
  `points`           DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `sort_order`       INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tq` (`test_id`, `question_id`),
  KEY `idx_tq_skill` (`skill_id`),
  CONSTRAINT `fk_tq_test`     FOREIGN KEY (`test_id`)     REFERENCES `tests` (`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_tq_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tq_subject`  FOREIGN KEY (`subject_id`)  REFERENCES `subjects` (`id`)  ON DELETE RESTRICT,
  CONSTRAINT `fk_tq_domain`   FOREIGN KEY (`domain_id`)   REFERENCES `domains` (`id`)   ON DELETE SET NULL,
  CONSTRAINT `fk_tq_skill`    FOREIGN KEY (`skill_id`)    REFERENCES `skills` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إسناد الاختبار لفصل أو لطالب
CREATE TABLE IF NOT EXISTS `test_assignments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id`     INT UNSIGNED NOT NULL,
  `class_id`    INT UNSIGNED NULL,
  `student_id`  INT UNSIGNED NULL,
  `assigned_by` INT UNSIGNED NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ta_class` (`test_id`, `class_id`),
  UNIQUE KEY `uq_ta_student` (`test_id`, `student_id`),
  KEY `idx_tas_class` (`class_id`),
  KEY `idx_tas_student` (`student_id`),
  CONSTRAINT `fk_tas_test`    FOREIGN KEY (`test_id`)     REFERENCES `tests` (`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_tas_class`   FOREIGN KEY (`class_id`)    REFERENCES `classes` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_tas_student` FOREIGN KEY (`student_id`)  REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tas_user`    FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `test_attempts` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id`          INT UNSIGNED NOT NULL,
  `student_id`       INT UNSIGNED NOT NULL,
  `class_id`         INT UNSIGNED NULL,             -- فصل الطالب وقت الاختبار
  `academic_year_id` INT UNSIGNED NOT NULL,
  `attempt_no`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `status`           ENUM('in_progress','submitted','needs_grading','graded') NOT NULL DEFAULT 'in_progress',
  `started_at`       DATETIME     NOT NULL,
  `deadline_at`      DATETIME     NULL,
  `submitted_at`     DATETIME     NULL,
  `auto_submitted`   TINYINT(1)   NOT NULL DEFAULT 0,
  `duration_seconds` INT UNSIGNED NULL,
  `last_activity_at` DATETIME     NULL,
  `ip`               VARCHAR(45)  NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attempt_no` (`test_id`, `student_id`, `attempt_no`),
  KEY `idx_att_student` (`student_id`, `status`),
  KEY `idx_att_status` (`status`, `deadline_at`),
  CONSTRAINT `fk_att_test`    FOREIGN KEY (`test_id`)          REFERENCES `tests` (`id`)          ON DELETE CASCADE,
  CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`)       REFERENCES `students` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_att_class`   FOREIGN KEY (`class_id`)         REFERENCES `classes` (`id`)        ON DELETE SET NULL,
  CONSTRAINT `fk_att_year`    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attempt_answers` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`       INT UNSIGNED NOT NULL,
  `test_question_id` INT UNSIGNED NOT NULL,
  `sort_order`       INT          NOT NULL DEFAULT 0,
  `option_order`     TEXT         NULL,             -- JSON ترتيب البدائل المعروض للطالب
  `answer`           TEXT         NULL,             -- JSON إجابة الطالب
  `is_correct`       TINYINT(1)   NULL,
  `score`            DECIMAL(5,2) NULL,
  `needs_manual`     TINYINT(1)   NOT NULL DEFAULT 0,
  `grader_note`      VARCHAR(500) NULL,
  `graded_by`        INT UNSIGNED NULL,
  `graded_at`        DATETIME     NULL,
  `flagged`          TINYINT(1)   NOT NULL DEFAULT 0,
  `answered_at`      DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aa` (`attempt_id`, `test_question_id`),
  KEY `idx_aa_tq` (`test_question_id`),
  KEY `idx_aa_manual` (`needs_manual`),
  CONSTRAINT `fk_aa_attempt` FOREIGN KEY (`attempt_id`)       REFERENCES `test_attempts` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_aa_tq`      FOREIGN KEY (`test_question_id`) REFERENCES `test_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aa_grader`  FOREIGN KEY (`graded_by`)        REFERENCES `users` (`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- نتيجة واحدة لكل محاولة (يمنع الاحتساب المزدوج بالقيد الفريد)
CREATE TABLE IF NOT EXISTS `test_results` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`       INT UNSIGNED NOT NULL,
  `test_id`          INT UNSIGNED NOT NULL,
  `student_id`       INT UNSIGNED NOT NULL,
  `class_id`         INT UNSIGNED NULL,
  `academic_year_id` INT UNSIGNED NOT NULL,
  `score`            DECIMAL(7,2) NOT NULL DEFAULT 0,
  `max_score`        DECIMAL(7,2) NOT NULL DEFAULT 0,
  `percent`          DECIMAL(5,2) NOT NULL DEFAULT 0,
  `correct_count`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `wrong_count`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `blank_count`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `level`            ENUM('advanced','proficient','basic','below') NOT NULL,
  `is_final`         TINYINT(1)   NOT NULL DEFAULT 1,   -- 0 إن بقيت أسئلة بانتظار التصحيح اليدوي
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_result_attempt` (`attempt_id`),
  KEY `idx_res_test` (`test_id`),
  KEY `idx_res_student` (`student_id`, `created_at`),
  KEY `idx_res_class` (`class_id`),
  CONSTRAINT `fk_res_attempt` FOREIGN KEY (`attempt_id`)       REFERENCES `test_attempts` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_res_test`    FOREIGN KEY (`test_id`)          REFERENCES `tests` (`id`)          ON DELETE CASCADE,
  CONSTRAINT `fk_res_student` FOREIGN KEY (`student_id`)       REFERENCES `students` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_res_class`   FOREIGN KEY (`class_id`)         REFERENCES `classes` (`id`)        ON DELETE SET NULL,
  CONSTRAINT `fk_res_year`    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- نتيجة المحاولة لكل مهارة (أساس التحليلات والخطط العلاجية)
CREATE TABLE IF NOT EXISTS `result_skill_scores` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `result_id`  INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `domain_id`  INT UNSIGNED NULL,
  `skill_id`   INT UNSIGNED NULL,
  `score`      DECIMAL(7,2) NOT NULL DEFAULT 0,
  `max_score`  DECIMAL(7,2) NOT NULL DEFAULT 0,
  `percent`    DECIMAL(5,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rss_result` (`result_id`),
  KEY `idx_rss_student_skill` (`student_id`, `skill_id`),
  KEY `idx_rss_skill` (`skill_id`),
  CONSTRAINT `fk_rss_result`  FOREIGN KEY (`result_id`)  REFERENCES `test_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rss_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_rss_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_rss_domain`  FOREIGN KEY (`domain_id`)  REFERENCES `domains` (`id`)      ON DELETE SET NULL,
  CONSTRAINT `fk_rss_skill`   FOREIGN KEY (`skill_id`)   REFERENCES `skills` (`id`)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الخطط العلاجية والإثرائية
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `remedial_plans` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind`             ENUM('remedial','enrichment') NOT NULL DEFAULT 'remedial',
  `scope`            ENUM('individual','group') NOT NULL DEFAULT 'individual',
  `title`            VARCHAR(200) NOT NULL,
  `academic_year_id` INT UNSIGNED NOT NULL,
  `class_id`         INT UNSIGNED NULL,
  `subject_id`       INT UNSIGNED NOT NULL,
  `teacher_user_id`  INT UNSIGNED NULL,             -- المعلم المسؤول
  `goals`            TEXT         NULL,
  `target_percent`   DECIMAL(5,2) NOT NULL DEFAULT 65,
  `starts_on`        DATE         NOT NULL,
  `ends_on`          DATE         NOT NULL,
  `status`           ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `post_test_id`     INT UNSIGNED NULL,
  `created_by`       INT UNSIGNED NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rp_status` (`status`, `kind`),
  CONSTRAINT `fk_rp_year`     FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rp_class`    FOREIGN KEY (`class_id`)         REFERENCES `classes` (`id`)        ON DELETE SET NULL,
  CONSTRAINT `fk_rp_subject`  FOREIGN KEY (`subject_id`)       REFERENCES `subjects` (`id`)       ON DELETE RESTRICT,
  CONSTRAINT `fk_rp_teacher`  FOREIGN KEY (`teacher_user_id`)  REFERENCES `users` (`id`)          ON DELETE SET NULL,
  CONSTRAINT `fk_rp_posttest` FOREIGN KEY (`post_test_id`)     REFERENCES `tests` (`id`)          ON DELETE SET NULL,
  CONSTRAINT `fk_rp_creator`  FOREIGN KEY (`created_by`)       REFERENCES `users` (`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remedial_plan_students` (
  `plan_id`          INT UNSIGNED NOT NULL,
  `student_id`       INT UNSIGNED NOT NULL,
  `baseline_percent` DECIMAL(5,2) NULL,             -- إتقان مهارات الخطة قبلها
  `post_percent`     DECIMAL(5,2) NULL,             -- بعد الاختبار البعدي
  PRIMARY KEY (`plan_id`, `student_id`),
  KEY `idx_rps_student` (`student_id`),
  CONSTRAINT `fk_rps_plan`    FOREIGN KEY (`plan_id`)    REFERENCES `remedial_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rps_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- عناصر الخطة: مهارة مستهدفة، تدريب مسند، نشاط، أو ملاحظة/إجراء
CREATE TABLE IF NOT EXISTS `remedial_plan_items` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_id`      INT UNSIGNED NOT NULL,
  `item_type`    ENUM('skill','practice','activity','note') NOT NULL,
  `skill_id`     INT UNSIGNED NULL,
  `test_id`      INT UNSIGNED NULL,
  `description`  TEXT         NULL,
  `due_on`       DATE         NULL,
  `status`       ENUM('pending','done') NOT NULL DEFAULT 'pending',
  `created_by`   INT UNSIGNED NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rpi_plan` (`plan_id`, `item_type`),
  CONSTRAINT `fk_rpi_plan`  FOREIGN KEY (`plan_id`)    REFERENCES `remedial_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rpi_skill` FOREIGN KEY (`skill_id`)   REFERENCES `skills` (`id`)         ON DELETE SET NULL,
  CONSTRAINT `fk_rpi_test`  FOREIGN KEY (`test_id`)    REFERENCES `tests` (`id`)          ON DELETE SET NULL,
  CONSTRAINT `fk_rpi_user`  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- التحفيز
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `badges` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(40)  NOT NULL,
  `name`           VARCHAR(100) NOT NULL,
  `description`    VARCHAR(255) NOT NULL,
  `icon`           VARCHAR(40)  NOT NULL DEFAULT 'award',
  `color`          VARCHAR(9)   NOT NULL DEFAULT '#0f6cbd',
  `criteria_type`  VARCHAR(40)  NOT NULL,           -- tests_completed, practice_count, perfect_score, improvement, skills_mastered, weekly_goal
  `criteria_value` INT          NOT NULL DEFAULT 1,
  `points`         INT          NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_badges_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_badges` (
  `student_id` INT UNSIGNED NOT NULL,
  `badge_id`   INT UNSIGNED NOT NULL,
  `awarded_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`, `badge_id`),
  CONSTRAINT `fk_sb_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sb_badge`   FOREIGN KEY (`badge_id`)   REFERENCES `badges` (`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_points` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `points`     INT          NOT NULL,
  `reason`     VARCHAR(150) NOT NULL,
  `ref_type`   VARCHAR(30)  NULL,
  `ref_id`     INT UNSIGNED NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_points_ref` (`student_id`, `ref_type`, `ref_id`, `reason`),
  KEY `idx_points_student` (`student_id`, `created_at`),
  CONSTRAINT `fk_sp_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تحديات بين الفصول ينشئها المعلم (قائمة على التحسن والالتزام)
CREATE TABLE IF NOT EXISTS `challenges` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200) NOT NULL,
  `description` TEXT         NULL,
  `metric`      ENUM('practice_questions','average_improvement','participation','mastery') NOT NULL DEFAULT 'participation',
  `subject_id`  INT UNSIGNED NULL,
  `starts_on`   DATE         NOT NULL,
  `ends_on`     DATE         NOT NULL,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ch_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ch_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `challenge_classes` (
  `challenge_id` INT UNSIGNED NOT NULL,
  `class_id`     INT UNSIGNED NOT NULL,
  PRIMARY KEY (`challenge_id`, `class_id`),
  CONSTRAINT `fk_cc_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `challenges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cc_class`     FOREIGN KEY (`class_id`)     REFERENCES `classes` (`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الإشعارات والسجلات والنسخ الاحتياطي
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       VARCHAR(40)  NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `body`       VARCHAR(500) NULL,
  `link`       VARCHAR(255) NULL,
  `dedupe_key` VARCHAR(120) NULL,                   -- يمنع تكرار الإشعار نفسه
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notif_dedupe` (`user_id`, `dedupe_key`),
  KEY `idx_notif_user` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NULL,
  `action`     VARCHAR(60)  NOT NULL,
  `entity`     VARCHAR(60)  NULL,
  `entity_id`  INT UNSIGNED NULL,
  `details`    TEXT         NULL,
  `ip`         VARCHAR(45)  NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`, `created_at`),
  KEY `idx_audit_action` (`action`, `created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `backup_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action`     ENUM('backup','restore') NOT NULL DEFAULT 'backup',
  `filename`   VARCHAR(200) NOT NULL,
  `size`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `status`     ENUM('success','failed') NOT NULL,
  `message`    VARCHAR(500) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_backup_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
