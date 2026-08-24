-- =====================================================================
--  البيانات الأولية لنظام الأرشفة الإلكترونية للمدارس
--  كلمة مرور المدير الافتراضية : Admin@123
-- =====================================================================
SET NAMES utf8mb4;

-- ----------------------------- الأدوار -------------------------------
INSERT INTO roles (id, name, slug, description, is_system) VALUES
 (1,'مدير النظام','admin','صلاحيات كاملة على النظام',1),
 (2,'مدير المدرسة','principal','الاطلاع على كافة المعاملات والاعتماد والإحالة',1),
 (3,'وكيل المدرسة','vice_principal','متابعة المعاملات والإحالات',0),
 (4,'مسؤول الأرشفة','archivist','إدخال وأرشفة المعاملات والوثائق والمرفقات',1),
 (5,'موظف','employee','الاطلاع على المعاملات المحالة إليه والرد عليها',1),
 (6,'مطلع','viewer','اطلاع فقط دون تعديل',1);

-- --------------------------- الصلاحيات -------------------------------
INSERT INTO permissions (code, name, group_name) VALUES
 ('dashboard.view','عرض لوحة المعلومات','عام'),
 ('transactions.view','عرض المعاملات','المعاملات'),
 ('transactions.view_all','عرض كل المعاملات (لا المحالة له فقط)','المعاملات'),
 ('transactions.create','إضافة معاملة','المعاملات'),
 ('transactions.edit','تعديل معاملة','المعاملات'),
 ('transactions.delete','حذف معاملة','المعاملات'),
 ('transactions.route','إحالة وتحويل المعاملات','المعاملات'),
 ('transactions.export','تصدير وطباعة المعاملات','المعاملات'),
 ('documents.view','عرض الوثائق','الوثائق'),
 ('documents.create','إضافة وثيقة','الوثائق'),
 ('documents.edit','تعديل وثيقة','الوثائق'),
 ('documents.delete','حذف وثيقة','الوثائق'),
 ('folders.view','عرض شجرة الأرشفة','شجرة الأرشفة'),
 ('folders.manage','إدارة شجرة الأرشفة','شجرة الأرشفة'),
 ('attachments.upload','رفع المرفقات وإضافة روابط درايف','المرفقات'),
 ('attachments.delete','حذف المرفقات','المرفقات'),
 ('shares.manage','مشاركة الوثائق عبر روابط','المشاركة'),
 ('correspondents.manage','إدارة الجهات','البيانات الأساسية'),
 ('departments.manage','إدارة الهيكل التنظيمي','البيانات الأساسية'),
 ('categories.manage','إدارة التصنيفات والأعوام الدراسية','البيانات الأساسية'),
 ('users.manage','إدارة المستخدمين','النظام'),
 ('roles.manage','إدارة الأدوار والصلاحيات','النظام'),
 ('reports.view','عرض التقارير والإحصائيات','التقارير'),
 ('audit.view','عرض سجل الأحداث','النظام'),
 ('settings.manage','إدارة إعدادات النظام','النظام');

-- مدير النظام : كل الصلاحيات
INSERT INTO role_permissions (role_id, permission_id) SELECT 1, id FROM permissions;

-- مدير المدرسة
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE code IN
 ('dashboard.view','transactions.view','transactions.view_all','transactions.create','transactions.edit',
  'transactions.route','transactions.export','documents.view','documents.create','documents.edit',
  'folders.view','attachments.upload','shares.manage','reports.view','audit.view');

-- وكيل المدرسة
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE code IN
 ('dashboard.view','transactions.view','transactions.view_all','transactions.create','transactions.edit',
  'transactions.route','transactions.export','documents.view','documents.create','documents.edit',
  'folders.view','attachments.upload','shares.manage','reports.view');

-- مسؤول الأرشفة
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE code IN
 ('dashboard.view','transactions.view','transactions.view_all','transactions.create','transactions.edit',
  'transactions.delete','transactions.route','transactions.export','documents.view','documents.create',
  'documents.edit','documents.delete','folders.view','folders.manage','attachments.upload','attachments.delete',
  'shares.manage','correspondents.manage','categories.manage','reports.view');

-- موظف
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE code IN
 ('dashboard.view','transactions.view','transactions.create','transactions.export',
  'documents.view','folders.view','attachments.upload');

-- مطلع
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE code IN
 ('dashboard.view','transactions.view','documents.view','folders.view');

-- ---------------------- الهيكل التنظيمي للمدرسة ----------------------
INSERT INTO departments (id, parent_id, name, code, sort_order) VALUES
 (1,  NULL, 'إدارة المدرسة',            'ADM', 1),
 (2,  1,    'مكتب مدير المدرسة',        'DIR', 1),
 (3,  1,    'وكالة الشؤون التعليمية',   'EDU', 2),
 (4,  1,    'وكالة شؤون الطلاب',        'STD', 3),
 (5,  1,    'الشؤون الإدارية والمالية', 'FIN', 4),
 (6,  3,    'الأقسام العلمية',          'SCI', 1),
 (7,  3,    'التوجيه والإرشاد الطلابي', 'GUD', 2),
 (8,  3,    'مصادر التعلم والمكتبة',    'LIB', 3),
 (9,  4,    'شؤون الطلاب والقبول',      'REG', 1),
 (10, 4,    'النشاط الطلابي',           'ACT', 2),
 (11, 4,    'الصحة المدرسية',           'HLT', 3),
 (12, 5,    'السكرتارية والصادر والوارد','SEC', 1),
 (13, 5,    'المستودع والعهد',          'STK', 2),
 (14, 5,    'شؤون الموظفين',            'HR',  3),
 (15, 5,    'الأمن والسلامة',           'SAF', 4);

-- ---------------------------- المستخدمون ------------------------------
-- كلمة المرور : Admin@123
INSERT INTO users (id, username, password_hash, full_name, email, job_title, department_id, role_id, is_active) VALUES
 (1,'admin','$2y$12$AAKSRY.ZtIHOGYb8QAz2BOPtuZgZwbaJJW/dyiHYvyJo1D5ECyC5y','مدير النظام','admin@school.sa','مدير النظام',1,1,1);

UPDATE departments SET manager_id = 1 WHERE id = 1;

-- -------------------------- الأعوام الدراسية --------------------------
INSERT INTO academic_years (id, name, start_date, end_date, is_current) VALUES
 (1,'1446 هـ / 2024-2025','2024-08-18','2025-06-20',0),
 (2,'1447 هـ / 2025-2026','2025-08-24','2026-06-25',1);

-- --------------------------- تصنيفات المعاملات ------------------------
INSERT INTO categories (id, name, color) VALUES
 (1,'تعاميم ولوائح','#2563eb'),
 (2,'شؤون الطلاب','#059669'),
 (3,'شؤون المعلمين والموظفين','#7c3aed'),
 (4,'الاختبارات والنتائج','#dc2626'),
 (5,'الشؤون المالية والمستودعات','#d97706'),
 (6,'الأنشطة والبرامج','#0891b2'),
 (7,'الصيانة والمباني','#4b5563'),
 (8,'خطابات ومخاطبات عامة','#be185d');

-- ------------------------------ الجهات --------------------------------
INSERT INTO correspondents (id, name, type, phone) VALUES
 (1,'وزارة التعليم','government',NULL),
 (2,'إدارة التعليم بالمنطقة','education',NULL),
 (3,'مكتب التعليم','education',NULL),
 (4,'إدارة شؤون المعلمين','education',NULL),
 (5,'إدارة الاختبارات والقبول','education',NULL),
 (6,'إدارة النشاط الطلابي','education',NULL),
 (7,'أمانة المنطقة / البلدية','government',NULL),
 (8,'الدفاع المدني','government',NULL),
 (9,'أولياء الأمور','individual',NULL),
 (10,'مدارس أخرى','school',NULL);

-- --------------------- شجرة الأرشفة الافتراضية ------------------------
INSERT INTO folders (id, parent_id, name, code, path, depth, sort_order, created_by) VALUES
 (1,  NULL,'أرشيف المدرسة',              'ROOT','/1/',0,1,1),
 (2,  1,   'المعاملات الواردة',           'IN',  '/1/2/',1,1,1),
 (3,  1,   'المعاملات الصادرة',           'OUT', '/1/3/',1,2,1),
 (4,  1,   'المذكرات الداخلية',           'INT', '/1/4/',1,3,1),
 (5,  1,   'التعاميم واللوائح',           'CIR', '/1/5/',1,4,1),
 (6,  1,   'شؤون الطلاب',                 'STD', '/1/6/',1,5,1),
 (7,  6,   'ملفات الطلاب',                'STF', '/1/6/7/',2,1,1),
 (8,  6,   'الاختبارات والنتائج',         'EXM', '/1/6/8/',2,2,1),
 (9,  6,   'الغياب والسلوك',              'ABS', '/1/6/9/',2,3,1),
 (10, 6,   'القبول والتسجيل والنقل',      'REG', '/1/6/10/',2,4,1),
 (11, 1,   'شؤون المعلمين والموظفين',     'HR',  '/1/11/',1,6,1),
 (12, 11,  'ملفات الموظفين',              'EMP', '/1/11/12/',2,1,1),
 (13, 11,  'الإجازات والمباشرة',          'LVE', '/1/11/13/',2,2,1),
 (14, 11,  'التقارير والأداء الوظيفي',    'PRF', '/1/11/14/',2,3,1),
 (15, 1,   'الشؤون المالية والمستودعات',  'FIN', '/1/15/',1,7,1),
 (16, 15,  'العهد والمستودع',             'STK', '/1/15/16/',2,1,1),
 (17, 15,  'الميزانية والمصروفات',        'BDG', '/1/15/17/',2,2,1),
 (18, 1,   'الأنشطة والبرامج',            'ACT', '/1/18/',1,8,1),
 (19, 1,   'المباني والصيانة والسلامة',   'SAF', '/1/19/',1,9,1),
 (20, 1,   'محاضر الاجتماعات',            'MIN', '/1/20/',1,10,1);

-- ---------------------------- الإعدادات -------------------------------
INSERT INTO settings (setting_key, setting_value) VALUES
 ('school_name','مدرسة ...'),
 ('school_stage','التعليم العام'),
 ('education_admin','إدارة التعليم بالمنطقة'),
 ('school_logo',''),
 ('app_name','نظام الأرشفة الإلكترونية'),
 ('items_per_page','20'),
 ('max_upload_mb','50'),
 ('allowed_extensions','pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,csv,jpg,jpeg,png,gif,bmp,webp,mp4,avi,mkv,mov,wmv,mp3,wav,ogg,m4a,zip,rar,7z'),
 ('transaction_no_format','{TYPE}-{YEAR}-{SEQ}'),
 ('seq_padding','5'),
 ('date_type','both'),
 ('enable_audit','1'),
 ('login_max_attempts','5'),
 ('login_lockout_minutes','15'),
 ('theme_color','#0f766e');
