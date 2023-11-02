<?php

namespace Abd\Larahelpers\Traits;

use App\Models\Attachment;

trait HasManyAttachment
{
    use GetFirstAttachment;
    
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function getAttachmentsInfoAttribute()
    {
        $attachments = [];
        foreach ($this->attachments as $attachment) {
            $attachments[] = $attachment->fileInfo;
        }

        return $attachments;
    }
}
