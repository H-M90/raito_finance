<?php
namespace App\Models\Concerns;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
trait HasAttachments
{
    public static function bootHasAttachments(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) return;
            $model->attachments()->get()->each(function (Attachment $attachment) {
                Storage::disk('local')->delete($attachment->path);
                $attachment->delete();
            });
        });
    }
    public function attachments(): MorphMany { return $this->morphMany(Attachment::class,'attachable')->latest(); }
}
