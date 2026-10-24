<?php

/*
|--------------------------------------------------------------------------
| Roles aur unki permissions (YAHIN SE badlein)
|--------------------------------------------------------------------------
| '*' ka matlab: sab kuch. Kisi role ko koi kaam dena ho to uski list me permission likh dein.
|
| Permissions:
|   users.manage        users banana/badalna/delete karna
|   academics.manage    classes, sections, subjects, teachers, exams
|   students.view       students dekhna (teacher ko sirf apni classes ke)
|   students.manage     student add/edit
|   students.delete     student delete
|   attendance.manage   attendance dekhna aur lagana (teacher ko sirf apni classes ki)
|   marks.manage        marks enter karna, results, result card (teacher ko sirf apni classes/subjects)
|   fees.view           vouchers/receipts dekhna, print/PDF
|   fees.manage         voucher banana/badalna, bulk, payment receive karna
|   fees.delete         voucher delete aur payment cancel karna
|   fee-types.manage    fee types
|   reports.fees        fee, daily collection, monthly collection reports
|   reports.academic    student, attendance, result reports (teacher ko sirf apni classes ke)
|   settings.manage     school settings        (aane wale step me)
*/

return [

    'labels' => [
        'admin'        => 'Admin',
        'teacher'      => 'Teacher',
        'accountant'   => 'Accountant',
        'receptionist' => 'Receptionist',
    ],

    'permissions' => [

        'admin' => ['*'],

        'teacher' => [
            'students.view',
            'attendance.manage',
            'marks.manage',
            'reports.academic',
        ],

        'accountant' => [
            'students.view',
            'fees.view',
            'fees.manage',
            'fee-types.manage',
            'reports.fees',
        ],

        'receptionist' => [
            'students.view',
            'students.manage',
        ],
    ],
];
