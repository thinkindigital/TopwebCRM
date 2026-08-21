<?php

use Webkul\Admin\DataGrids\Contact\PersonDataGrid;
use Webkul\User\Models\User;

it('qualifies the default person id sorting when organizations are joined', function () {
    auth()->guard('user')->setUser(new User(['view_permission' => 'global']));

    $dataGrid = app(PersonDataGrid::class);
    $dataGrid->prepareColumns();
    $dataGrid->setQueryBuilder();

    $method = new ReflectionMethod($dataGrid, 'processRequestedSorting');
    $method->setAccessible(true);

    $query = $method->invoke($dataGrid, []);

    expect($query->toSql())->toContain('order by "persons"."id" desc');
});
