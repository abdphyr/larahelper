<?php

namespace Abd\Larahelpers\Traits;


trait HasTranslation
{
    public function translations()
    {
        return $this->hasMany($this->translationClass,'object_id');
    }

    public function translation()
    {
        return $this->hasOne($this->translationClass, 'object_id', 'id')
            ->where('language_code', config('app.user_language'));
    }

    public function getTranslationByLang($lang)
    {
        return $this->translations->where('language_code', $lang)->first();
    }
}
