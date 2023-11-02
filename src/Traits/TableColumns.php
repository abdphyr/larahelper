<?php

namespace Abd\Larahelpers\Traits;

trait TableColumns
{
    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    public function getTranslationColumns()
    {
        $allColumns = $this->getTableColumns();
        $keyColumns = ['id', 'object_id', 'language_code'];
        foreach ($keyColumns as $col) {
            if (($key = array_search($col, $allColumns)) !== false) {
                unset($allColumns[$key]);
            }
        }

        $columns = [];
        foreach ($allColumns as $col) {
            $columns[] = $col;
        }

        return $columns;
    }
}
