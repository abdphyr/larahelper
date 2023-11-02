<?php

namespace Abd\Larahelpers\Traits;

use App\Models\Attachment;

trait HasOneAttachment
{
    use GetFirstAttachment;
    
    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }

    public function getAttachmentUrl()
    {
        if (isset($this->attachment)) {
            $info = $this->attachment->fileInfo;
            $name = $info['filename'];
            $folder = $info['folder'];
            $extension = $info['extension'];
            return asset("storage/" . $folder . "/" . $name . "." . $extension);
        } else {
            return null;
        }
    }
}
