<?php

/*
|--------------------------------------------------------------------------
| Permission Catalogue
|--------------------------------------------------------------------------
|
| Single source of truth for every permission used in the application.
| Grouped by module for the Roles & Permissions screen.
|
| system_role_defaults defines the default permission set assigned when a
| system role is first created. EnsureSystemRoles uses this config instead
| of hardcoded arrays so that adding a permission here automatically reaches
| Admin (always) and uncustomised system roles (additively on next deploy).
|
*/

return [

    /*
     * All permissions, grouped by module.
     * Each entry: ['name' => ..., 'label' => ..., 'description' => ...]
     */
    'modules' => [

        'Dashboard' => [
            'permissions' => [
                ['name' => 'dashboard.view', 'label' => 'View Dashboard', 'description' => 'Access the main dashboard and KPIs.'],
            ],
        ],

        'Products' => [
            'permissions' => [
                ['name' => 'products.view',       'label' => 'View Products',       'description' => 'List and search products in the catalogue.'],
                ['name' => 'products.create',     'label' => 'Create Products',     'description' => 'Add new products to the catalogue.'],
                ['name' => 'products.update',     'label' => 'Edit Products',       'description' => 'Modify existing product details and pricing.'],
                ['name' => 'products.delete',     'label' => 'Delete Products',     'description' => 'Remove products from the catalogue.'],
                ['name' => 'categories.view',     'label' => 'View Categories',     'description' => 'List product categories.'],
                ['name' => 'categories.create',   'label' => 'Create Categories',   'description' => 'Add new product categories.'],
                ['name' => 'categories.update',   'label' => 'Edit Categories',     'description' => 'Rename or modify categories.'],
                ['name' => 'categories.delete',   'label' => 'Delete Categories',   'description' => 'Remove product categories.'],
                ['name' => 'units.view',          'label' => 'View Units',          'description' => 'List units of measure.'],
                ['name' => 'units.create',        'label' => 'Create Units',        'description' => 'Add new units of measure.'],
                ['name' => 'units.update',        'label' => 'Edit Units',          'description' => 'Modify units of measure.'],
                ['name' => 'units.delete',        'label' => 'Delete Units',        'description' => 'Remove units of measure.'],
                ['name' => 'locations.view',      'label' => 'View Locations',      'description' => 'List warehouse and store locations.'],
                ['name' => 'locations.create',    'label' => 'Create Locations',    'description' => 'Add new storage locations.'],
                ['name' => 'locations.update',    'label' => 'Edit Locations',      'description' => 'Modify location details.'],
                ['name' => 'locations.delete',    'label' => 'Delete Locations',    'description' => 'Remove storage locations.'],
            ],
        ],

        'Inventory' => [
            'permissions' => [
                ['name' => 'inventory.view',     'label' => 'View Inventory',    'description' => 'View stock on hand and the stock ledger.'],
                ['name' => 'inventory.receive',  'label' => 'Receive Stock',     'description' => 'Record incoming stock from suppliers.'],
                ['name' => 'inventory.adjust',   'label' => 'Adjust Stock',      'description' => 'Make manual stock adjustments (write-off, corrections).'],
                ['name' => 'inventory.transfer', 'label' => 'Transfer Stock',    'description' => 'Move stock between locations.'],
            ],
        ],

        'Customers' => [
            'permissions' => [
                ['name' => 'customers.view',   'label' => 'View Customers',   'description' => 'List and search customer accounts.'],
                ['name' => 'customers.create', 'label' => 'Create Customers', 'description' => 'Add new customer records.'],
                ['name' => 'customers.update', 'label' => 'Edit Customers',   'description' => 'Modify customer details and credit terms.'],
                ['name' => 'customers.delete', 'label' => 'Delete Customers', 'description' => 'Remove customer records.'],
            ],
        ],

        'Quotations' => [
            'permissions' => [
                ['name' => 'quotations.view',             'label' => 'View Quotations',       'description' => 'List and view quotations.'],
                ['name' => 'quotations.create',           'label' => 'Create Quotations',     'description' => 'Draft new quotations for customers.'],
                ['name' => 'quotations.update',           'label' => 'Edit Quotations',       'description' => 'Modify draft quotations before sending.'],
                ['name' => 'quotations.send',             'label' => 'Send Quotations',       'description' => 'Send quotations to customers via email.'],
                ['name' => 'quotations.convert',          'label' => 'Convert Quotations',    'description' => 'Convert accepted quotations into invoices.'],
                ['name' => 'quotations.delete',           'label' => 'Delete Quotations',     'description' => 'Remove draft or declined quotations.'],
                ['name' => 'quotations.print',            'label' => 'Print Quotations',      'description' => 'Download or print quotation PDFs.'],
                ['name' => 'quotations.approve_discount', 'label' => 'Approve Discounts',     'description' => 'Authorise discounts above the standard threshold.'],
            ],
        ],

        'Invoices' => [
            'permissions' => [
                ['name' => 'invoices.view',   'label' => 'View Invoices',        'description' => 'List and view all invoices.'],
                ['name' => 'invoices.create', 'label' => 'Create Invoices',      'description' => 'Draft new invoices.'],
                ['name' => 'invoices.update', 'label' => 'Edit Draft Invoices',  'description' => 'Modify invoices before they are issued.'],
                ['name' => 'invoices.issue',  'label' => 'Issue Invoices',       'description' => 'Finalise and issue invoices to customers.'],
                ['name' => 'invoices.void',   'label' => 'Void Invoices',        'description' => 'Void issued invoices with a reason.'],
                ['name' => 'invoices.print',  'label' => 'Print Invoices',       'description' => 'Download or print invoice PDFs.'],
            ],
        ],

        'Payments' => [
            'permissions' => [
                ['name' => 'payments.view',    'label' => 'View Payments',    'description' => 'List and view recorded payments.'],
                ['name' => 'payments.create',  'label' => 'Record Payments',  'description' => 'Record incoming customer payments.'],
                ['name' => 'payments.reverse', 'label' => 'Reverse Payments', 'description' => 'Reverse incorrectly recorded payments.'],
            ],
        ],

        'POS' => [
            'permissions' => [
                ['name' => 'pos.use',              'label' => 'Use POS',                 'description' => 'Access the Point of Sale screen.'],
                ['name' => 'pos.open_session',     'label' => 'Open POS Session',        'description' => 'Open a new POS cash session.'],
                ['name' => 'pos.close_session',    'label' => 'Close Own POS Session',   'description' => "Close the cashier's own open session."],
                ['name' => 'pos.close_any_session', 'label' => 'Close Any POS Session',   'description' => "Force-close another cashier's open session."],
                ['name' => 'pos.discount',         'label' => 'Apply POS Discounts',     'description' => 'Apply item or order discounts at the POS.'],
                ['name' => 'pos.credit_sale',      'label' => 'POS Credit Sales',        'description' => 'Complete POS sales on credit (unpaid invoice).'],
            ],
        ],

        'Reports' => [
            'permissions' => [
                ['name' => 'reports.view',   'label' => 'View Reports',    'description' => 'Access all reporting screens.'],
                ['name' => 'reports.export', 'label' => 'Export Reports',  'description' => 'Download reports as PDF or Excel.'],
            ],
        ],

        'Users' => [
            'permissions' => [
                ['name' => 'users.view',           'label' => 'View Users',              'description' => 'List and view user accounts.'],
                ['name' => 'users.create',         'label' => 'Create Users',            'description' => 'Invite new users to the system.'],
                ['name' => 'users.update',         'label' => 'Edit Users',              'description' => 'Modify user names, emails and role assignments.'],
                ['name' => 'users.deactivate',     'label' => 'Deactivate Users',        'description' => 'Suspend user access without deleting the account.'],
                ['name' => 'users.reset_password', 'label' => 'Reset Passwords',         'description' => 'Send a new temporary password to a user.'],
                ['name' => 'users.delete',         'label' => 'Delete / Anonymize Users', 'description' => 'Permanently delete or anonymize user records.'],
            ],
        ],

        'Roles' => [
            'permissions' => [
                ['name' => 'roles.view',   'label' => 'View Roles',    'description' => 'List roles and view their permission assignments.'],
                ['name' => 'roles.create', 'label' => 'Create Roles',  'description' => 'Add new custom roles.'],
                ['name' => 'roles.update', 'label' => 'Edit Roles',    'description' => 'Modify role permissions and names.'],
                ['name' => 'roles.delete', 'label' => 'Delete Roles',  'description' => 'Remove custom roles that have no assigned users.'],
            ],
        ],

        'Settings' => [
            'permissions' => [
                ['name' => 'settings.view',   'label' => 'View Settings', 'description' => 'View business settings and configuration.'],
                ['name' => 'settings.update', 'label' => 'Edit Settings', 'description' => 'Modify business name, logo and other settings.'],
            ],
        ],

        'Audit' => [
            'permissions' => [
                ['name' => 'audit.view', 'label' => 'View Audit Log', 'description' => 'Access the administration audit trail.'],
            ],
        ],

    ],

    /*
     * Default permission sets per system role.
     * '*' means all catalogue permissions.
     * EnsureSystemRoles grants these on first creation; for uncustomised roles
     * it additively grants any that are missing (never removes).
     */
    'system_role_defaults' => [

        'Admin' => ['*'],

        'Storekeeper' => [
            'dashboard.view',
            'products.view', 'products.create', 'products.update',
            'categories.view', 'categories.create', 'categories.update',
            'units.view', 'units.create', 'units.update',
            'locations.view', 'locations.create', 'locations.update',
            'inventory.view', 'inventory.receive', 'inventory.adjust', 'inventory.transfer',
            'invoices.view', 'invoices.print',
            'reports.view',
            'quotations.view',
        ],

        'Cashier' => [
            'dashboard.view',
            'products.view', 'categories.view', 'units.view', 'locations.view',
            'customers.view', 'customers.create', 'customers.update',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.issue', 'invoices.print',
            'payments.view', 'payments.create',
            'pos.use', 'pos.open_session', 'pos.close_session', 'pos.discount', 'pos.credit_sale',
            'inventory.view',
            'quotations.view', 'quotations.create', 'quotations.update',
            'quotations.send', 'quotations.convert', 'quotations.print',
        ],

        'Viewer' => [
            'dashboard.view',
            'products.view', 'categories.view', 'units.view', 'locations.view',
            'inventory.view',
            'invoices.view',
            'payments.view',
            'reports.view',
            'quotations.view',
        ],

    ],

];
