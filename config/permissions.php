<?php

return [
    'roles' => [
        'admin' => [
            'label' => 'Admin',
            'permissions' => ['*'],
        ],
        'manager' => [
            'label' => 'Manager',
            'permissions' => [
                'countries.view',
                'tenants.view',
                'tenants.create',
                'tenants.update',
                'tenants.delete',
                'projects.view',
                'projects.create',
                'projects.update',
                'projects.delete',
            ],
        ],
    ],
];
