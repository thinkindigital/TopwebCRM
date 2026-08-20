<?php

namespace Webkul\TopwebChat\Repositories;

use Webkul\Core\Eloquent\Repository;

class InstanceRepository extends Repository
{
    public function model(): string
    {
        return 'Webkul\TopwebChat\Contracts\Instance';
    }
}
