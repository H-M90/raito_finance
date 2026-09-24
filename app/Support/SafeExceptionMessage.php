<?php

namespace App\Support;

use DomainException;
use Illuminate\Validation\ValidationException;
use Throwable;

class SafeExceptionMessage
{
    public static function from(Throwable $exception, string $fallback = 'تعذر تنفيذ العملية بسبب خطأ غير متوقع. حاول مرة أخرى أو راجع سجل النظام.'): string
    {
        if ($exception instanceof DomainException || $exception instanceof ValidationException) {
            return $exception->getMessage();
        }

        report($exception);
        $reference = 'ERR-'.strtoupper(substr(sha1($exception::class.'|'.$exception->getMessage().'|'.$exception->getFile().'|'.$exception->getLine()), 0, 10));

        return $fallback.' رقم المرجع: '.$reference;
    }
}
