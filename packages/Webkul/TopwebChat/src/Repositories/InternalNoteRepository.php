<?php

namespace Webkul\TopwebChat\Repositories;

use Webkul\Core\Eloquent\Repository;

class InternalNoteRepository extends Repository
{
    public function model(): string
    {
        return 'Webkul\TopwebChat\Contracts\InternalNote';
    }
}
