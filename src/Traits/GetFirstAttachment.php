<?php

namespace Abd\Larahelpers\Traits;

trait GetFirstAttachment
{
    public function getFirstAttachmentUrl($model = null)
    {
        $attachments = $model != null ? $model->getAttachmentsInfoAttribute() : $this->getAttachmentsInfoAttribute();
        if (isset($attachments[0])) {
            $name = $attachments[0]['filename'];
            $folder = $attachments[0]['folder'];
            $extension = $attachments[0]['extension'];
            return asset("storage/" . $folder . "/" . $name . "." . $extension);
        } else {
            return null;
        }
    }
}
