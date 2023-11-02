<?php

namespace Abd\Larahelpers\Traits;


trait AuthorizeModel
{
    protected array $defaultAuthorizeMethods = ['getList', 'show', 'create', 'edit', 'delete', 'softDelete'];
}
