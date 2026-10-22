<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : match ($status) {
            403     => 'ليس لديك صلاحية للوصول إلى هذه الصفحة.',
            404     => 'الصفحة المطلوبة غير موجودة.',
            419     => 'انتهت صلاحية الصفحة، يرجى تحديثها والمحاولة مرة أخرى.',
            422     => 'البيانات المدخلة غير صحيحة.',
            default => 'حدث خطأ غير متوقع.',
        });
    }
}
