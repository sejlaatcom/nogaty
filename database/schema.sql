-- =====================================================================
--  نظام الأرشفة الإلكترونية للمدارس  (School Electronic Archiving System)
--  قاعدة البيانات : MySQL 5.7+ / MariaDB 10.3+
--  الترميز        : utf8mb4
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- الأدوار والصلاحيات
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100)  NOT NULL COMMENT 'اسم الدور',
  slug          VARCHAR(60)   NOT NULL COMMENT 'المعرف البرمجي',
  description   VARCHAR(255)  NULL,
  is_system     TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'دور نظام لا يمكن حذفه',
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code          VARCHAR(80)  NOT NULL COMMENT 'كود الصلاحية مثل transactions.create',
  name          VARCHAR(150) NOT NULL,
  group_name    VARCHAR(80)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id       INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الهيكل التنظيمي للمدرسة (شجرة إدارات / أقسام)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS departments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id     INT UNSIGNED NULL COMMENT 'الإدارة الأم',
  name          VARCHAR(150) NOT NULL,
  code          VARCHAR(40)  NULL COMMENT 'الرمز المستخدم في ترقيم المعاملات',
  manager_id    INT UNSIGNED NULL COMMENT 'مدير الإدارة',
  description   VARCHAR(500) NULL,
  sort_order    INT          NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_departments_parent (parent_id),
  CONSTRAINT fk_dept_parent FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- المستخدمون
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username             VARCHAR(60)  NOT NULL,
  password_hash        VARCHAR(255) NOT NULL,
  full_name            VARCHAR(150) NOT NULL,
  email                VARCHAR(150) NULL,
  phone                VARCHAR(40)  NULL,
  job_title            VARCHAR(120) NULL COMMENT 'المسمى الوظيفي',
  national_id          VARCHAR(30)  NULL,
  department_id        INT UNSIGNED NULL,
  role_id              INT UNSIGNED NOT NULL,
  avatar               VARCHAR(255) NULL,
  is_active            TINYINT(1)   NOT NULL DEFAULT 1,
  must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
  last_login_at        DATETIME     NULL,
  last_login_ip        VARCHAR(45)  NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_dept (department_id),
  KEY idx_users_role (role_id),
  CONSTRAINT fk_users_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE departments
  ADD CONSTRAINT fk_dept_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- شجرة الأرشفة (مستويات غير محدودة)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS folders (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id     INT UNSIGNED NULL,
  name          VARCHAR(200) NOT NULL,
  code          VARCHAR(40)  NULL,
  path          VARCHAR(1000) NOT NULL DEFAULT '/' COMMENT 'مسار المعرفات 1/5/9/',
  depth         SMALLINT     NOT NULL DEFAULT 0 COMMENT 'مستوى المجلد في الشجرة',
  description   VARCHAR(500) NULL,
  department_id INT UNSIGNED NULL COMMENT 'الإدارة المالكة للمجلد',
  icon          VARCHAR(40)  NULL,
  color         VARCHAR(20)  NULL,
  sort_order    INT          NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_folders_parent (parent_id),
  KEY idx_folders_path (path(255)),
  CONSTRAINT fk_folders_parent FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE,
  CONSTRAINT fk_folders_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_folders_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- صلاحيات المجلدات (لمستخدم أو دور أو إدارة)
CREATE TABLE IF NOT EXISTS folder_permissions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  folder_id     INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NULL,
  role_id       INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  can_view      TINYINT(1) NOT NULL DEFAULT 1,
  can_add       TINYINT(1) NOT NULL DEFAULT 0,
  can_edit      TINYINT(1) NOT NULL DEFAULT 0,
  can_delete    TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_fp_folder (folder_id),
  CONSTRAINT fk_fp_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
  CONSTRAINT fk_fp_user   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fp_role   FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_fp_dept   FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الجهات المراسلة (خارجية / داخلية)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS correspondents (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(200) NOT NULL,
  type        ENUM('government','education','company','school','individual','internal','other') NOT NULL DEFAULT 'government',
  contact_person VARCHAR(150) NULL,
  phone       VARCHAR(40)  NULL,
  email       VARCHAR(150) NULL,
  address     VARCHAR(300) NULL,
  notes       VARCHAR(500) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_corr_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الأعوام الدراسية
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS academic_years (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(60) NOT NULL COMMENT 'مثال 1447 هـ / 2025-2026',
  start_date  DATE NULL,
  end_date    DATE NULL,
  is_current  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- تصنيفات المعاملات
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name      VARCHAR(150) NOT NULL,
  color     VARCHAR(20)  NULL,
  is_active TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- المعاملات (الصادر / الوارد / الداخلي)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  transaction_no      VARCHAR(60)  NOT NULL COMMENT 'الرقم الداخلي التسلسلي',
  direction           ENUM('incoming','outgoing','internal') NOT NULL COMMENT 'وارد / صادر / داخلي',
  reference_no        VARCHAR(80)  NULL COMMENT 'رقم المعاملة لدى الجهة',
  subject             VARCHAR(500) NOT NULL,
  body                TEXT         NULL COMMENT 'ملخص / نص المعاملة',
  transaction_date    DATE         NOT NULL,
  hijri_date          VARCHAR(20)  NULL,
  received_date       DATE         NULL COMMENT 'تاريخ الاستلام للوارد',
  correspondent_id    INT UNSIGNED NULL COMMENT 'الجهة الخارجية',
  from_department_id  INT UNSIGNED NULL,
  to_department_id    INT UNSIGNED NULL,
  folder_id           INT UNSIGNED NULL COMMENT 'موقعها في شجرة الأرشفة',
  category_id         INT UNSIGNED NULL,
  academic_year_id    INT UNSIGNED NULL,
  priority            ENUM('normal','urgent','very_urgent') NOT NULL DEFAULT 'normal',
  confidentiality     ENUM('normal','confidential','top_secret') NOT NULL DEFAULT 'normal',
  status              ENUM('draft','registered','in_progress','completed','archived','closed') NOT NULL DEFAULT 'registered',
  due_date            DATE         NULL,
  keywords            VARCHAR(500) NULL,
  notes               TEXT         NULL,
  linked_transaction_id INT UNSIGNED NULL COMMENT 'ربط الصادر بالوارد',
  attachments_count   INT UNSIGNED NOT NULL DEFAULT 0,
  created_by          INT UNSIGNED NULL,
  updated_by          INT UNSIGNED NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NULL,
  deleted_at          DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_trx_no (transaction_no, direction),
  KEY idx_trx_direction (direction),
  KEY idx_trx_status (status),
  KEY idx_trx_date (transaction_date),
  KEY idx_trx_folder (folder_id),
  KEY idx_trx_corr (correspondent_id),
  KEY idx_trx_deleted (deleted_at),
  FULLTEXT KEY ft_trx (subject, body, keywords),
  CONSTRAINT fk_trx_corr   FOREIGN KEY (correspondent_id) REFERENCES correspondents(id) ON DELETE SET NULL,
  CONSTRAINT fk_trx_fdept  FOREIGN KEY (from_department_id) REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_trx_tdept  FOREIGN KEY (to_department_id) REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_trx_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL,
  CONSTRAINT fk_trx_cat    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_trx_year   FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL,
  CONSTRAINT fk_trx_user   FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- إحالات وتحويل المعاملات بين الموظفين والإدارات
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transaction_routings (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  transaction_id   INT UNSIGNED NOT NULL,
  from_user_id     INT UNSIGNED NULL,
  to_user_id       INT UNSIGNED NULL,
  to_department_id INT UNSIGNED NULL,
  action           ENUM('for_action','for_info','for_review','for_approval','for_signature','for_archive') NOT NULL DEFAULT 'for_action',
  instructions     VARCHAR(1000) NULL COMMENT 'التأشيرة / التعليمات',
  response         VARCHAR(1000) NULL COMMENT 'رد المحال إليه',
  status           ENUM('pending','seen','done','returned') NOT NULL DEFAULT 'pending',
  due_date         DATE     NULL,
  seen_at          DATETIME NULL,
  completed_at     DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rt_trx (transaction_id),
  KEY idx_rt_to (to_user_id, status),
  CONSTRAINT fk_rt_trx  FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
  CONSTRAINT fk_rt_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_rt_to   FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_rt_dept FOREIGN KEY (to_department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الوثائق العامة داخل شجرة الأرشفة (غير المعاملات)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  folder_id       INT UNSIGNED NOT NULL,
  title           VARCHAR(500) NOT NULL,
  doc_no          VARCHAR(80)  NULL,
  doc_date        DATE         NULL,
  hijri_date      VARCHAR(20)  NULL,
  doc_type        VARCHAR(80)  NULL COMMENT 'تعميم / لائحة / محضر / شهادة ...',
  description     TEXT         NULL,
  confidentiality ENUM('normal','confidential','top_secret') NOT NULL DEFAULT 'normal',
  keywords        VARCHAR(500) NULL,
  academic_year_id INT UNSIGNED NULL,
  attachments_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by      INT UNSIGNED NULL,
  updated_by      INT UNSIGNED NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NULL,
  deleted_at      DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_doc_folder (folder_id),
  KEY idx_doc_deleted (deleted_at),
  FULLTEXT KEY ft_doc (title, description, keywords),
  CONSTRAINT fk_doc_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_year   FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL,
  CONSTRAINT fk_doc_user   FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- المرفقات : ملفات مرفوعة أو روابط قوقل درايف
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attachments (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  attachable_type ENUM('transaction','document') NOT NULL,
  attachable_id   INT UNSIGNED NOT NULL,
  kind            ENUM('file','drive') NOT NULL DEFAULT 'file' COMMENT 'ملف مرفوع أو رابط قوقل درايف',
  title           VARCHAR(300) NULL,
  file_name       VARCHAR(300) NULL COMMENT 'الاسم الأصلي',
  stored_name     VARCHAR(300) NULL COMMENT 'الاسم على القرص',
  file_path       VARCHAR(500) NULL,
  ext             VARCHAR(20)  NULL,
  mime_type       VARCHAR(150) NULL,
  media_group     ENUM('document','image','video','audio','archive','other') NOT NULL DEFAULT 'document',
  file_size       BIGINT UNSIGNED NULL,
  drive_url       VARCHAR(800) NULL COMMENT 'رابط قوقل درايف كما أدخله المستخدم',
  drive_file_id   VARCHAR(120) NULL COMMENT 'معرف الملف المستخرج من الرابط',
  checksum        VARCHAR(64)  NULL,
  downloads       INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by     INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_att_owner (attachable_type, attachable_id),
  CONSTRAINT fk_att_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الوسوم
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS taggables (
  tag_id        INT UNSIGNED NOT NULL,
  taggable_type ENUM('transaction','document') NOT NULL,
  taggable_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (tag_id, taggable_type, taggable_id),
  KEY idx_tg_owner (taggable_type, taggable_id),
  CONSTRAINT fk_tg_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- المشاركة عبر روابط
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shares (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  token          VARCHAR(64) NOT NULL,
  shareable_type ENUM('transaction','document') NOT NULL,
  shareable_id   INT UNSIGNED NOT NULL,
  password_hash  VARCHAR(255) NULL,
  allow_download TINYINT(1) NOT NULL DEFAULT 1,
  expires_at     DATETIME NULL,
  views          INT UNSIGNED NOT NULL DEFAULT 0,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shares_token (token),
  CONSTRAINT fk_sh_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الإشعارات
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(200) NOT NULL,
  message    VARCHAR(500) NULL,
  url        VARCHAR(300) NULL,
  icon       VARCHAR(40)  NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_nt_user (user_id, is_read),
  CONSTRAINT fk_nt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- سجل الأحداث
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(60)  NOT NULL COMMENT 'login/create/update/delete/view/download/share',
  entity_type VARCHAR(60)  NULL,
  entity_id   INT UNSIGNED NULL,
  description VARCHAR(500) NULL,
  ip_address  VARCHAR(45)  NULL,
  user_agent  VARCHAR(300) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_al_user (user_id),
  KEY idx_al_entity (entity_type, entity_id),
  KEY idx_al_date (created_at),
  CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- محاولات الدخول (حماية من التخمين)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username   VARCHAR(60) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  success    TINYINT(1)  NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_la_lookup (ip_address, created_at),
  KEY idx_la_user (username, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الإعدادات العامة
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(80)  NOT NULL,
  setting_value TEXT         NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- عدادات الترقيم التلقائي للمعاملات
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sequences (
  name       VARCHAR(40) NOT NULL COMMENT 'incoming / outgoing / internal',
  year       SMALLINT    NOT NULL,
  current_no INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (name, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
