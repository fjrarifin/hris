<?php

return [
    'admin_levels' => [],

    'hr_manager_levels' => [],

    'allowed_usernames' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('PAYROLL_ALLOWED_USERNAMES', 'hrd0002'))
    ))),
];
