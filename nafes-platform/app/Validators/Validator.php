<?php
declare(strict_types=1);

namespace App\Validators;

/**
 * تحقق بسيط من المدخلات:
 *   Validator::make($data, ['name' => 'required|max:150', 'email' => 'nullable|email'], ['name' => 'الاسم'])
 */
final class Validator
{
    public static function make(array $data, array $rules, array $labels = []): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $label = $labels[$field] ?? $field;
            $rulesList = explode('|', $ruleString);
            $empty = $value === null || $value === '' || $value === [];

            if ($empty) {
                if (in_array('required', $rulesList, true)) {
                    $errors[$field] = "حقل «{$label}» مطلوب.";
                }
                continue;
            }
            foreach ($rulesList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $message = match ($name) {
                    'max'      => mb_strlen((string) $value) > (int) $arg ? "«{$label}» يجب ألا يتجاوز $arg حرفاً." : null,
                    'min'      => mb_strlen((string) $value) < (int) $arg ? "«{$label}» يجب ألا يقل عن $arg أحرف." : null,
                    'int'      => filter_var($value, FILTER_VALIDATE_INT) === false ? "«{$label}» يجب أن يكون رقماً صحيحاً." : null,
                    'between'  => (static function () use ($value, $arg, $label) {
                        [$lo, $hi] = explode(',', (string) $arg);
                        return !is_numeric($value) || $value < $lo || $value > $hi ? "«{$label}» يجب أن يكون بين $lo و$hi." : null;
                    })(),
                    'in'       => !in_array((string) $value, explode(',', (string) $arg), true) ? "قيمة «{$label}» غير صالحة." : null,
                    'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? "«{$label}» بريد إلكتروني غير صحيح." : null,
                    'username' => !preg_match('/^[a-z0-9_.\-]{3,64}$/', mb_strtolower((string) $value)) ? "«{$label}» يجب أن يكون بأحرف إنجليزية أو أرقام (3–64)." : null,
                    'code'     => !preg_match('/^[\p{L}\p{N}_.\-\/]{1,30}$/u', (string) $value) ? "«{$label}» يحتوي على رموز غير مسموحة." : null,
                    'date'     => (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) || !strtotime((string) $value)) ? "«{$label}» تاريخ غير صحيح." : null,
                    'datetime' => !strtotime((string) $value) ? "«{$label}» تاريخ ووقت غير صحيحين." : null,
                    'hex'      => !preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? "«{$label}» لون غير صالح." : null,
                    default    => null,
                };
                if ($message !== null) {
                    $errors[$field] = $message;
                    break;
                }
            }
        }
        return $errors;
    }
}
