<?php
/**
 * خدمة المرفقات المشتركة بين المعاملات والوثائق
 * تدعم : الملفات المرفوعة (Office / PDF / صور / فيديو / صوت) وروابط قوقل درايف
 */
class AttachmentService
{
    /**
     * حفظ المرفقات القادمة من نموذج (ملفات + روابط درايف)
     * @return array{saved:int, errors:string[]}
     */
    public static function saveFromRequest(string $type, int $ownerId, string $subDir): array
    {
        $saved  = 0;
        $errors = [];

        // ------------------- الملفات المرفوعة -------------------
        if (!empty($_FILES['attachments']['name'][0] ?? null)) {
            $files  = Uploader::normalizeMultiple($_FILES['attachments']);
            $titles = Request::array('attachment_titles');

            foreach ($files as $i => $file) {
                $res = Uploader::store($file, $subDir);
                if (!$res['ok']) {
                    $errors[] = Uploader::sanitizeName($file['name']) . ' : ' . $res['message'];
                    continue;
                }
                $data = $res['data'];
                Database::insert('attachments', [
                    'attachable_type' => $type,
                    'attachable_id'   => $ownerId,
                    'kind'            => 'file',
                    'title'           => trim((string)($titles[$i] ?? '')) ?: $data['file_name'],
                    'file_name'       => $data['file_name'],
                    'stored_name'     => $data['stored_name'],
                    'file_path'       => $data['file_path'],
                    'ext'             => $data['ext'],
                    'mime_type'       => $data['mime_type'],
                    'media_group'     => $data['media_group'],
                    'file_size'       => $data['file_size'],
                    'checksum'        => $data['checksum'],
                    'uploaded_by'     => Auth::id(),
                ]);
                $saved++;
            }
        }

        // ------------------- روابط قوقل درايف -------------------
        $driveUrls   = Request::array('drive_urls');
        $driveTitles = Request::array('drive_titles');
        $driveGroups = Request::array('drive_groups');

        foreach ($driveUrls as $i => $rawUrl) {
            $rawUrl = trim((string) $rawUrl);
            if ($rawUrl === '') {
                continue;
            }
            if (!GDrive::isDriveUrl($rawUrl) && !preg_match('/^[a-zA-Z0-9_-]{20,}$/', $rawUrl)) {
                $errors[] = 'الرابط ليس رابط قوقل درايف صالح : ' . mb_substr($rawUrl, 0, 60);
                continue;
            }
            $fileId = GDrive::extractId($rawUrl);
            if (!$fileId) {
                $errors[] = 'تعذر استخراج معرف الملف من الرابط : ' . mb_substr($rawUrl, 0, 60);
                continue;
            }
            $driveType = GDrive::detectType($rawUrl);
            $group     = pick($driveGroups[$i] ?? 'document',
                ['document','image','video','audio','archive','other'], 'document');

            Database::insert('attachments', [
                'attachable_type' => $type,
                'attachable_id'   => $ownerId,
                'kind'            => 'drive',
                'title'           => trim((string)($driveTitles[$i] ?? '')) ?: 'ملف على قوقل درايف',
                'ext'             => $driveType,
                'media_group'     => $group,
                'drive_url'       => mb_substr($rawUrl, 0, 800),
                'drive_file_id'   => $fileId,
                'uploaded_by'     => Auth::id(),
            ]);
            $saved++;
        }

        if ($saved > 0) {
            self::syncCount($type, $ownerId);
        }
        return ['saved' => $saved, 'errors' => $errors];
    }

    /** جلب مرفقات عنصر */
    public static function listFor(string $type, int $ownerId): array
    {
        return Database::all(
            'SELECT a.*, u.full_name AS uploader_name
               FROM attachments a
               LEFT JOIN users u ON u.id = a.uploaded_by
              WHERE a.attachable_type = ? AND a.attachable_id = ?
              ORDER BY a.id',
            [$type, $ownerId]
        );
    }

    /** حذف مرفق مع ملفه */
    public static function remove(int $attachmentId): bool
    {
        $att = Database::first('SELECT * FROM attachments WHERE id = ?', [$attachmentId]);
        if (!$att) {
            return false;
        }
        if ($att['kind'] === 'file') {
            Uploader::deleteFile($att['file_path']);
        }
        Database::delete('attachments', 'id = ?', [$attachmentId]);
        self::syncCount($att['attachable_type'], (int) $att['attachable_id']);
        Audit::log('delete', 'attachment', $attachmentId, 'حذف مرفق : ' . ($att['title'] ?? ''));
        return true;
    }

    /** حذف كل مرفقات عنصر */
    public static function removeAllFor(string $type, int $ownerId): void
    {
        foreach (self::listFor($type, $ownerId) as $a) {
            if ($a['kind'] === 'file') {
                Uploader::deleteFile($a['file_path']);
            }
        }
        Database::delete('attachments', 'attachable_type = ? AND attachable_id = ?', [$type, $ownerId]);
    }

    /** تحديث عداد المرفقات في الجدول الأصل */
    public static function syncCount(string $type, int $ownerId): void
    {
        $table = $type === 'transaction' ? 'transactions' : 'documents';
        Database::run(
            "UPDATE `$table` SET attachments_count =
                (SELECT COUNT(*) FROM attachments WHERE attachable_type = ? AND attachable_id = ?)
              WHERE id = ?",
            [$type, $ownerId, $ownerId]
        );
    }

    /** روابط العرض والتحميل لمرفق واحد */
    public static function links(array $att): array
    {
        if ($att['kind'] === 'drive') {
            $t = $att['ext'] ?: 'file';
            return [
                'preview'  => GDrive::previewUrl($att['drive_file_id'], $t),
                'download' => GDrive::downloadUrl($att['drive_file_id'], $t),
                'open'     => GDrive::openUrl($att['drive_file_id'], $t),
                'thumb'    => GDrive::thumbnailUrl($att['drive_file_id']),
                'external' => true,
            ];
        }
        return [
            'preview'  => url('attachments/preview', ['id' => $att['id']]),
            'download' => url('attachments/download', ['id' => $att['id']]),
            'open'     => url('attachments/preview', ['id' => $att['id']]),
            'thumb'    => '',
            'external' => false,
        ];
    }
}
