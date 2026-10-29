<?php

namespace App\Platform\Operations\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Decides what the base-schema consolidation may do to a given database.
 *
 * Every installation reaching 3.0 arrives from one of three places: a brand new
 * database, a 2.x database whose history is complete, or something in between
 * that cannot be repaired automatically. The consolidation migration replaces
 * 150 historical files, so a database that stopped part-way through that chain
 * has a schema no single migration can reason about — it must finish the 2.x
 * line first.
 *
 * The verdict is derived from reads alone, so an ABORT leaves the database
 * exactly as it was found. The same verdict backs both the migration guard and
 * the self-updater preflight, so the two can never disagree about whether an
 * upgrade is safe to start.
 */
final class SchemaConsolidationGuard
{
    /**
     * Empty database: create the consolidated base schema and seed it.
     */
    public const BUILD = 'build';

    /**
     * The 150 replaced migrations already ran: keep the schema, drop the rows.
     */
    public const SKIP = 'skip';

    /**
     * Some of the replaced migrations ran. The 2.x line has to finish first.
     */
    public const ABORT_FLOOR = 'abort-floor';

    /**
     * The recorded history and the schema tell different stories.
     */
    public const ABORT_INCONSISTENT = 'abort-inconsistent';

    /**
     * The table whose presence stands in for "this database has a schema".
     *
     * It is the oldest table in the product and has never been renamed, so it
     * is present in every database the consolidation can legitimately meet.
     */
    public const SENTINEL_TABLE = 'companies';

    /**
     * The recorded name of the migration this guard protects.
     *
     * Once that name is in the history the consolidation is spent — the
     * framework will never run it again — so a preflight has nothing left to
     * warn about, however the database got there.
     */
    public const CONSOLIDATION_MIGRATION = '2026_01_01_000000_consolidate_base_schema';

    /**
     * The one name a 2.4.x history carries that this codebase never shipped.
     *
     * The 2.x line widened the tax percent column after the point this
     * consolidation reproduces, in a file the 3.x tree has no copy of. Every
     * database upgraded from 2.4.0 or later therefore records a name whose
     * migration cannot exist here, now or ever.
     *
     * It is deliberately not part of {@see self::REPLACED_MIGRATIONS}: the
     * decision counts those against a floor of exactly 150, and a name this
     * file does not reproduce has no business in that count. It is stale all
     * the same, so the consolidation prunes it alongside them.
     */
    public const SUPERSEDED_MIGRATION = '2026_04_07_000001_increase_tax_percent_precision_to_three_decimals';

    /**
     * The migration names this version consolidates, byte for byte.
     *
     * Matching against the recorded history is exact: no trimming, no case
     * folding, no normalising. One of these names really does contain a space
     * (`2018_11_02_133825_create_ expense_categories_table`) and is matched
     * with it. Names recorded by anything outside this list — module
     * migrations, migrations from releases this codebase never shipped, or
     * anything a future release adds — are ignored entirely.
     *
     * @var list<string>
     */
    public const REPLACED_MIGRATIONS = [
        '2014_10_11_071840_create_companies_table',
        '2014_10_11_125754_create_currencies_table',
        '2014_10_12_000000_create_users_table',
        '2014_10_12_100000_create_password_resets_table',
        '2016_05_13_060834_create_settings_table',
        '2017_04_11_064308_create_units_table',
        '2017_04_11_081227_create_items_table',
        '2017_04_12_090759_create_invoices_table',
        '2017_04_12_091015_create_invoice_items_table',
        '2017_05_05_055609_create_estimates_table',
        '2017_05_05_073927_create_notifications_table',
        '2017_05_06_173745_create_countries_table',
        '2017_10_02_123501_create_estimate_items_table',
        '2018_11_02_133825_create_ expense_categories_table',
        '2018_11_02_133956_create_expenses_table',
        '2019_08_30_072639_create_addresses_table',
        '2019_09_02_053155_create_payment_methods_table',
        '2019_09_03_135234_create_payments_table',
        '2019_09_14_120124_create_media_table',
        '2019_09_21_052540_create_tax_types_table',
        '2019_09_21_052548_create_taxes_table',
        '2019_09_26_145012_create_company_settings_table',
        '2019_12_14_000001_create_personal_access_tokens_table',
        '2020_02_01_063235_create_custom_fields_table',
        '2020_02_01_063509_create_custom_field_values_table',
        '2020_05_12_154129_add_user_id_to_expenses_table',
        '2020_09_07_103054_create_file_disks_table',
        '2020_09_22_153617_add_columns_to_media_table',
        '2020_09_26_100951_create_user_settings_table',
        '2020_10_01_102913_add_company_to_addresses_table',
        '2020_10_17_074745_create_notes_table',
        '2020_10_24_091934_change_value_column_to_text_on_company_settings_table',
        '2020_11_23_050206_add_creator_in_invoices_table',
        '2020_11_23_050252_add_creator_in_estimates_table',
        '2020_11_23_050316_add_creator_in_payments_table',
        '2020_11_23_050333_add_creator_in_expenses_table',
        '2020_11_23_050406_add_creator_in_items_table',
        '2020_11_23_065815_add_creator_in_users_table',
        '2020_11_23_074154_create_email_logs_table',
        '2020_12_02_064933_update_crater_version_320',
        '2020_12_02_090527_update_crater_version_400',
        '2020_12_08_065715_change_description_and_notes_column_type',
        '2020_12_08_133131_update_crater_version_401',
        '2020_12_14_044717_add_template_name_to_invoices_table',
        '2020_12_14_045310_add_template_name_to_estimates_table',
        '2020_12_14_051450_remove_template_id_from_invoices_and_estimates_table',
        '2020_12_23_061302_update_crater_version_402',
        '2020_12_31_100816_update_crater_version_403',
        '2021_01_22_085644_update_crater_version_404',
        '2021_03_03_155223_add_unit_name_to_pdf',
        '2021_03_23_145012_add_number_length_setting',
        '2021_05_05_063533_update_crater_version_410',
        '2021_06_19_121939_update_crater_version_420',
        '2021_06_28_105334_create_bouncer_tables',
        '2021_06_28_111647_create_customers_table',
        '2021_06_28_120010_add_customer_id_to_estimates_table',
        '2021_06_28_120133_add_customer_id_to_expenses_table',
        '2021_06_28_120208_add_customer_id_to_invoices_table',
        '2021_06_28_120231_add_customer_id_to_payments_table',
        '2021_06_29_052745_add_customer_id_to_addresses_table',
        '2021_06_30_062411_update_customer_id_in_all_tables',
        '2021_07_01_060700_create_user_company_table',
        '2021_07_05_100256_change_relationship_of_company',
        '2021_07_06_070204_add_owner_id_to_companies_table',
        '2021_07_08_110940_add_company_to_notes_table',
        '2021_07_09_063502_create_recurring_invoices_table',
        '2021_07_09_063712_add_recurring_invoice_id_to_invoices_table',
        '2021_07_09_063755_add_recurring_invoice_id_to_invoice_items_table',
        '2021_07_15_054753_make_due_date_optional_in_invoices_table',
        '2021_07_15_054929_make_expiry_date_optional_estimates_table',
        '2021_07_16_072458_add_base_columns_into_invoices_table',
        '2021_07_16_072925_add_base_columns_into_invoice_items_table',
        '2021_07_16_073040_add_base_columns_into_estimates_table',
        '2021_07_16_073441_add_base_columns_into_estimate_items_table',
        '2021_07_16_074810_add_base_column_into_payments_table',
        '2021_07_16_075100_add_base_values_into_taxes_table',
        '2021_07_16_080253_add_currency_id_into_invoices_table',
        '2021_07_16_080508_add_currency_id_into_payments_table',
        '2021_07_16_080611_add_currency_id_into_items_table',
        '2021_07_16_080702_add_currency_id_into_taxes_table',
        '2021_07_16_112429_add_currency_id_into_estimates_table',
        '2021_08_05_103535_create_exchange_rate_logs_table',
        '2021_08_16_091413_add_tax_per_item_into_items_table',
        '2021_08_19_063244_add_base_columns_to_expense_table',
        '2021_09_28_081543_create_exchange_rate_providers_table',
        '2021_09_28_130822_add_sequence_column',
        '2021_10_06_100539_add_recurring_invoice_id_to_taxes_table',
        '2021_11_13_051127_add_payment_method_to_expense_table',
        '2021_11_13_114808_calculate_base_values_for_existing_data',
        '2021_11_23_092111_add_new_company_settings',
        '2021_11_23_093811_update_crater_version_500',
        '2021_12_01_120956_update_crater_version_501',
        '2021_12_02_063005_calculate_base_due_amount',
        '2021_12_02_074516_migrate_templates_from_version_4',
        '2021_12_02_123007_update_crater_version_502',
        '2021_12_03_154423_update_crater_version_503',
        '2021_12_04_122255_create_transactions_table',
        '2021_12_04_123315_add_transaction_id_to_payments_table',
        '2021_12_04_123415_add_type_to_payment_methods_table',
        '2021_12_06_131201_update_crater_version_504',
        '2021_12_09_054033_calculate_base_values_for_expenses',
        '2021_12_09_062434_update_crater_version_505',
        '2021_12_09_065718_drop_unique_email_on_customers_table',
        '2021_12_10_121739_update_creater_version_506',
        '2021_12_13_055813_calculate_base_amount_of_payments_table',
        '2021_12_13_093701_add_fields_to_email_logs_table',
        '2021_12_15_053223_create_modules_table',
        '2021_12_21_102521_change_enable_portal_field_of_customers_table',
        '2021_12_31_042453_add_type_to_tax_types_table',
        '2022_01_05_101841_add_sales_tax_fields_to_invoices_table',
        '2022_01_05_102538_add_sales_tax_fields_to_estimates_table',
        '2022_01_05_103607_add_sales_tax_fields_to_recurring_invoices_table',
        '2022_01_05_115423_update_crater_version_600',
        '2022_01_06_103536_add_slug_to_companies',
        '2022_01_12_132859_update_crater_version_601',
        '2022_01_13_123829_update_crater_version_602',
        '2022_02_15_113648_update_crater_version_603',
        '2022_02_17_081723_update_crater_version_604',
        '2022_02_23_130108_update_value_column_to_nullable_on_settings_table',
        '2022_03_02_120210_add_overdue_to_invoices_table',
        '2022_03_03_060121_crater_version_605',
        '2022_03_03_063237_change_over_due_status_to_sent',
        '2022_03_04_051438_calculate_base_values_for_invoice_items',
        '2022_03_06_070829_update_crater_version_606',
        '2024_01_28_114715_add_generated_conversions_to_media_table',
        '2024_02_04_005632_update_version_100',
        '2024_02_08_181804_taxes_amount_as_signed',
        '2024_02_11_075831_update_version_110',
        '2024_02_17_211900_add_expires_at_to_personal_access_tokens',
        '2024_04_12_162703_add_tax_ids_to_companies',
        '2024_04_14_173940_replace_crater_type',
        '2024_05_04_110000_update_version_121',
        '2024_05_04_110000_update_version_122',
        '2024_07_12_235756_update_version_130',
        '2024_07_13_000000_rename_password_resets_table',
        '2024_07_13_000001_update_stored_namespace',
        '2024_07_17_113642_create_cache_table',
        '2024_07_17_113702_create_jobs_table',
        '2024_08_08_173226_update_invoice_date_to_datetime_on_invoices_table',
        '2024_10_04_093723_add_tax_id_field_to_customers_table',
        '2024_10_09_103306_modify_invoices_to_allow_negative_values',
        '2024_12_11_102055_update_taxes_to_handle_fixed_amount',
        '2025_01_05_211404_add_is_default_to_notes_table',
        '2025_03_15_160016_allow_unsigned_item_price',
        '2025_05_04_152240_add_tax_included_to_invoices',
        '2025_05_04_152522_add_tax_included_to_estimates',
        '2025_05_04_152833_add_tax_included_to_recurring_invoices',
        '2025_08_18_101343_add_new_currencies_to_currencies_table',
        '2025_09_017_add_qar_currency_to_currencies_table',
        '2025_09_02_add_expense_number_to_expenses_table',
    ];

    private function __construct(
        private readonly string $mode,
        private readonly int $recorded,
    ) {}

    /**
     * Read the database and decide, without writing anything.
     *
     * @param  string|null  $connection  the connection to inspect, or null for
     *                                   the default one (which is what the
     *                                   framework points at a migration while
     *                                   its body runs)
     */
    public static function inspect(?string $connection = null): self
    {
        return self::decide(
            self::recordedMigrations($connection),
            Schema::connection($connection)->hasTable(self::SENTINEL_TABLE),
        );
    }

    /**
     * The verdict an upgrade would meet, or null when there is nothing to meet.
     *
     * This is the entry point for anything that wants to know *before* running
     * migrations whether the consolidation is going to refuse the database. A
     * database that has already recorded the consolidation — every 3.x
     * installation from its second upgrade onwards — gets null: the migration
     * is not pending, so it cannot abort, and the decision table (which would
     * read such a database as an inconsistency) does not apply to it.
     */
    public static function preflight(?string $connection = null): ?self
    {
        $recorded = self::recordedMigrations($connection);

        if (in_array(self::CONSOLIDATION_MIGRATION, $recorded, true)) {
            return null;
        }

        return self::decide(
            $recorded,
            Schema::connection($connection)->hasTable(self::SENTINEL_TABLE),
        );
    }

    /**
     * Every recorded name a consolidated database has no further use for.
     *
     * The 150 files this version replaces, plus the one 2.4.x-only name above.
     * A SKIP prunes exactly this list from the migration repository and
     * nothing else: matching is by name, never by date or pattern, so module
     * migrations — which share the repository table — and anything a future
     * release records survive by construction rather than by luck.
     *
     * @return list<string>
     */
    public static function staleRecordedMigrations(): array
    {
        return [...self::REPLACED_MIGRATIONS, self::SUPERSEDED_MIGRATION];
    }

    /**
     * The configured name of the framework's migration repository table.
     */
    public static function repositoryTable(): string
    {
        $configured = config('database.migrations');

        if (is_array($configured)) {
            return $configured['table'] ?? 'migrations';
        }

        return is_string($configured) && $configured !== '' ? $configured : 'migrations';
    }

    /**
     * The decision table itself, and the only place it is written down.
     *
     * @param  list<string>  $recordedMigrations  every name the framework has
     *                                            recorded as ran
     * @param  bool  $sentinel  whether the sentinel table exists
     */
    private static function decide(array $recordedMigrations, bool $sentinel): self
    {
        $recorded = count(array_intersect(self::REPLACED_MIGRATIONS, $recordedMigrations));

        if ($recorded === 0) {
            return new self($sentinel ? self::ABORT_INCONSISTENT : self::BUILD, $recorded);
        }

        if ($recorded < count(self::REPLACED_MIGRATIONS)) {
            return new self(self::ABORT_FLOOR, $recorded);
        }

        return new self($sentinel ? self::SKIP : self::ABORT_INCONSISTENT, $recorded);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * How many of the replaced migrations this database has run.
     */
    public function recordedCount(): int
    {
        return $this->recorded;
    }

    public function isBuild(): bool
    {
        return $this->mode === self::BUILD;
    }

    public function isSkip(): bool
    {
        return $this->mode === self::SKIP;
    }

    public function isAbort(): bool
    {
        return $this->mode === self::ABORT_FLOOR || $this->mode === self::ABORT_INCONSISTENT;
    }

    /**
     * What to tell the operator, or null when there is nothing wrong.
     */
    public function message(): ?string
    {
        return match ($this->mode) {
            self::ABORT_FLOOR => 'Upgrading to this version requires the database to be fully migrated on the 2.x line first. '
                .'This database has run '.$this->recorded.' of the '.count(self::REPLACED_MIGRATIONS)
                .' historical migrations this version consolidates. Install the latest InvoiceShelf 2.x release, '
                .'run its migrations to completion, then upgrade to this version again. No changes have been made.',
            self::ABORT_INCONSISTENT => 'The database schema and the migration history do not match (the recorded history '
                .'and the `companies` table disagree). This usually means a partial restore. Restore a consistent backup '
                .'— schema and migration history together — and run the upgrade again. No changes have been made.',
            default => null,
        };
    }

    /**
     * Stop the caller with this verdict's message.
     *
     * @throws RuntimeException always
     */
    public function fail(): never
    {
        throw new RuntimeException((string) $this->message());
    }

    /**
     * Every migration name the framework has recorded as ran.
     *
     * A database that has never been migrated has no repository table yet; that
     * is an empty history, not an error.
     *
     * @return list<string>
     */
    private static function recordedMigrations(?string $connection): array
    {
        $table = self::repositoryTable();

        if (! Schema::connection($connection)->hasTable($table)) {
            return [];
        }

        return DB::connection($connection)->table($table)->pluck('migration')->all();
    }
}
