<?php

namespace App\Support;

/**
 * Staff roles and permissions. Policies check permissions, never role names,
 * so roles can be re-shaped from Admin → Staff without code changes.
 */
final class Permissions
{
    public const SUPER_ADMIN = 'super_admin';

    public const AFFILIATE = 'affiliate';

    public const STAFF_ROLES = ['super_admin', 'manager', 'sales', 'fulfilment', 'finance', 'support', 'content'];

    public const ALL = [
        'catalogue.manage' => 'Manage categories, products, variants and images',
        'inventory.manage' => 'Adjust stock and view movements',
        'orders.view' => 'View orders',
        'orders.manage' => 'Create, edit, confirm and cancel orders',
        'fulfilment.manage' => 'Dispatch, deliver and return units',
        'payments.manage' => 'Record payments',
        'refunds.manage' => 'Record refunds and approve restocking',
        'customers.view' => 'View customers',
        'customers.manage' => 'Edit customers',
        'affiliates.manage' => 'Approve and manage affiliates',
        'affiliates.payout_details' => 'View affiliate payout bank/UPI details',
        'commissions.manage' => 'Review commission entries and attribution',
        'payouts.manage' => 'Create and record affiliate payouts',
        'warranty.manage' => 'Manage warranty registrations and corrections',
        'claims.manage' => 'Review and decide warranty claims',
        'content.manage' => 'Edit website content and policies',
        'reports.view' => 'View reports',
        'settings.manage' => 'Edit business settings',
        'staff.manage' => 'Manage staff and roles',
        'audit.view' => 'View activity history',
    ];

    /** Default permission sets for seeded roles. */
    public const ROLE_DEFAULTS = [
        'manager' => ['catalogue.manage', 'inventory.manage', 'orders.view', 'orders.manage', 'fulfilment.manage', 'payments.manage', 'refunds.manage', 'customers.view', 'customers.manage', 'affiliates.manage', 'commissions.manage', 'warranty.manage', 'claims.manage', 'content.manage', 'reports.view', 'audit.view'],
        'sales' => ['orders.view', 'orders.manage', 'payments.manage', 'customers.view'],
        'fulfilment' => ['orders.view', 'fulfilment.manage', 'inventory.manage'],
        'finance' => ['orders.view', 'payments.manage', 'refunds.manage', 'commissions.manage', 'payouts.manage', 'affiliates.payout_details', 'reports.view'],
        'support' => ['orders.view', 'customers.view', 'warranty.manage', 'claims.manage'],
        'content' => ['catalogue.manage', 'content.manage'],
    ];
}
