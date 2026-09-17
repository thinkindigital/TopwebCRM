<?php

use Webkul\Admin\DataGrids\Activity\ActivityDataGrid;
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

it('scopes activities by the lead owner for individual users', function () {
    $user = new User([
        'view_permission' => 'individual',
    ]);
    $user->id = 7;

    auth()->guard('user')->setUser($user);

    $dataGrid = app(ActivityDataGrid::class);
    $query = $dataGrid->prepareQueryBuilder();

    expect($query->toSql())
        ->toContain('"leads"."user_id" in (?)')
        ->and($query->getBindings())->toContain(7);
});
