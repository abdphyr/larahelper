<?php

namespace Abd\Larahelpers\Traits;

trait SerializationFields
{
    /**
     * Databasega yozilishidan oldin JSON ga o'tkaziladigan fieldlar ro'yxati
     *
     * @var array
     */
    public array $serializingToJsonFields = [];

    protected function serializeToJson($data = [])
    {
        foreach ($this->serializingToJsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }
        return $data;
    }
}
