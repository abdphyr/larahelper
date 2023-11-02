<?php

namespace Abd\Larahelpers\Traits;

use App\Services\AttachmentService;
use Illuminate\Support\Facades\DB;

trait MakeAttachment
{
    protected AttachmentService $attachment;
    protected $dimensions = [];
    protected $attachmentFolder;

    public function attachFile($id, $data)
    {
        $model = $this->findById($id);
        if ($model) {
            try {
                if (method_exists($model, 'attachment')) {
                    $file = $this->getFiles($data);
                    if (count($file) == 1) {
                        $fileInfo = $this->attachment->upload($file[0], $this->attachmentFolder, $this->dimensions);
                        if (isset($model->attachment)) {
                            $this->attachment->update($model->attachment->id);
                            $model->attachment()->where('id', $model->attachment->id)->update([
                                'info' => json_encode($fileInfo)
                            ]);
                        } else {
                            $model->attachment()->create([
                                'info' => json_encode($fileInfo)
                            ]);
                        }
                    }
                } else if (method_exists($model, 'attachments')) {
                    $files = $this->getFiles($data);
                    foreach ($files as $file) {
                        $fileInfo = $this->attachment->upload($file, $this->attachmentFolder, $this->dimensions);
                        $model->attachments()->create([
                            'info' => json_encode($fileInfo)
                        ]);
                    }
                }

                $data['updated_by'] = auth()->id();
                $model->update($data);

                DB::connection($this->model->connection)->commit();
                $model->refresh();
                $this->setReturnData(data: $this->withResource($model));
            } catch (\Throwable $th) {
                DB::connection($this->model->connection)->rollBack();
                $this->setReturnData(status: 0, message: 'Not implemented. ' . $th->getMessage(), code: 501);
            }
        } else {
            $this->setReturnData(status: 0, message: 'Not found', code: 404);
        }

        return $this->return();
    }

    public function detachFile()
    {
        
    }

    protected function getFiles($data)
    {
        $files = [];
        if (request()->hasFile('files') && isset($data['files']) && is_array($data['files'])) {
            $files = array_merge($files, $data['files']);
        }
        if (request()->hasFile('file') && isset($data['file'])) {
            $files[] = $data['file'];
        }
        return $files;
    }
}
