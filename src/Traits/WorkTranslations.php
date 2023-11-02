<?php

namespace Abd\Larahelpers\Traits;

trait WorkTranslations
{
    /**
     * Model Translation class
     * @var mixed
     */
    protected $translation;

    public function popTranslations($data)
    {
        if (isset($data['translations'])) {
            $translations = $data['translations'];
            unset($data['translations']);
            return [$data, $translations];
        } else return [$data, []];
    }

    protected function createTranslations($model, $translations)
    {
        foreach ($translations as $translation) {
            $this->translation::create($translation + ['object_id' => $model->id]);
        }
    }

    protected function updateTranslations($model, $translations)
    {
        foreach ($translations as $translation) {
            if (isset($translation['id']) && !empty($translation['id'])) {
                if ($trModel = $this->translation::find($translation['id'])) $trModel->update($translation);
            } else {
                $isExists = $this->translation::firstWhere([
                    'object_id' => $model->id,
                    'language_code' => $translation['language_code'],
                ]);
                if (!$isExists) $this->translation::create($translation + ['object_id' => $model->id]);
            }
        }
    }
}
