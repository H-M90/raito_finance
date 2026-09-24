<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    private const ALLOWED_MIMES = [
        'application/pdf','image/jpeg','image/png','image/webp',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel','text/csv',
    ];

    public function storePath(UploadedFile $file, string $folder): string
    {
        $this->assertSafe($file);
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $safeName = Str::uuid().'.'.$extension;
        return $file->storeAs('private-documents/'.$folder, $safeName, 'local');
    }

    public function attach(Model $model, UploadedFile $file, string $label): void
    {
        if (! method_exists($model, 'attachments')) {
            throw new \LogicException('هذا السجل لا يدعم المرفقات.');
        }
        $path = $this->storePath($file, $model->getTable().'/'.$model->getKey());
        $model->attachments()->create([
            'label'=>$label,
            'path'=>$path,
            'original_name'=>basename($file->getClientOriginalName()),
            'mime_type'=>$file->getMimeType(),
            'size'=>$file->getSize() ?: 0,
            'uploaded_by'=>auth()->id(),
        ]);
    }

    private function assertSafe(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['attachment'=>'فشل رفع الملف.']);
        }
        if (($file->getSize() ?: 0) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['attachment'=>'الحد الأقصى لحجم الملف 10 ميجابايت.']);
        }
        if (! in_array((string) $file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['attachment'=>'نوع الملف غير مسموح.']);
        }
    }
}
