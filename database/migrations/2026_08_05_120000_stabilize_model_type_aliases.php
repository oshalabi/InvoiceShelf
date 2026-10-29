<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Silber\Bouncer\Database\Ability;
use Silber\Bouncer\Database\Role;

return new class extends Migration
{
    /**
     * Every database column that stores an Eloquent morph identity.
     *
     * @var array<string, list<string>>
     */
    public const COLUMNS = [
        'media' => ['model_type'],
        'email_logs' => ['mailable_type'],
        'notifications' => ['notifiable_type'],
        'personal_access_tokens' => ['tokenable_type'],
        'custom_field_values' => ['custom_field_valuable_type'],
        'abilities' => ['entity_type'],
        'assigned_roles' => ['entity_type', 'restricted_to_type'],
        'permissions' => ['entity_type'],
    ];

    /**
     * Stable alias => legacy model basename.
     *
     * @var array<string, string>
     */
    public const FIRST_PARTY_ALIASES = [
        'address' => 'Address',
        'company' => 'Company',
        'company_invitation' => 'CompanyInvitation',
        'company_setting' => 'CompanySetting',
        'country' => 'Country',
        'currency' => 'Currency',
        'custom_field' => 'CustomField',
        'custom_field_value' => 'CustomFieldValue',
        'customer' => 'Customer',
        'email_log' => 'EmailLog',
        'estimate' => 'Estimate',
        'estimate_item' => 'EstimateItem',
        'exchange_rate_log' => 'ExchangeRateLog',
        'exchange_rate_provider' => 'ExchangeRateProvider',
        'expense' => 'Expense',
        'expense_category' => 'ExpenseCategory',
        'file_disk' => 'FileDisk',
        'impersonation_log' => 'ImpersonationLog',
        'invoice' => 'Invoice',
        'invoice_item' => 'InvoiceItem',
        'item' => 'Item',
        'marketplace_credential' => 'MarketplaceCredential',
        'marketplace_operation' => 'MarketplaceOperation',
        'module' => 'Module',
        'note' => 'Note',
        'payment' => 'Payment',
        'payment_allocation' => 'PaymentAllocation',
        'payment_method' => 'PaymentMethod',
        'recurring_invoice' => 'RecurringInvoice',
        'setting' => 'Setting',
        'tax' => 'Tax',
        'tax_type' => 'TaxType',
        'transaction' => 'Transaction',
        'unit' => 'Unit',
        'user' => 'User',
        'user_setting' => 'UserSetting',
    ];

    /** @var array<string, array{legacy: string, types: list<string>}> */
    public const VENDOR_ALIASES = [
        'bouncer_ability' => [
            'legacy' => 'abilities',
            'types' => ['abilities', Ability::class],
        ],
        'bouncer_role' => [
            'legacy' => 'roles',
            'types' => ['roles', Role::class],
        ],
    ];

    public function up(): void
    {
        $this->replaceTypes(true);
    }

    public function down(): void
    {
        $this->replaceTypes(false);
    }

    private function replaceTypes(bool $up): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach (self::FIRST_PARTY_ALIASES as $alias => $basename) {
                    $legacyTypes = $up
                        ? [
                            $alias,
                            'App\\Models\\'.$basename,
                            'App\\'.$basename,
                            'InvoiceShelf\\Models\\'.$basename,
                            'InvoiceShelf\\'.$basename,
                            'Crater\\Models\\'.$basename,
                            'Crater\\'.$basename,
                        ]
                        : [$alias];

                    DB::table($table)
                        ->whereIn($column, $legacyTypes)
                        ->update([$column => $up ? $alias : 'App\\Models\\'.$basename]);
                }

                foreach (self::VENDOR_ALIASES as $alias => $mapping) {
                    DB::table($table)
                        ->whereIn($column, $up ? [$alias, ...$mapping['types']] : [$alias])
                        ->update([$column => $up ? $alias : $mapping['legacy']]);
                }
            }
        }
    }
};
