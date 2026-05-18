<?php

return [
    'admin_levels' => [0, 1],

    'hr_manager_levels' => [2],

    'allowed_usernames' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('PAYROLL_ALLOWED_USERNAMES', 'hrd0001'))
    ))),
];
