<?php

namespace Webkul\TopwebChat\Repositories;

use Webkul\Core\Eloquent\Repository;

class MessageRepository extends Repository
{
    public function model(): string
    {
        return 'Webkul\TopwebChat\Contracts\Message';
    }
}
