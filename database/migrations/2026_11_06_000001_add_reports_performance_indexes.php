<?php

// PROPOSED — not yet applied.
// Review each index against EXPLAIN ANALYSE on a production-sized dataset before running.
// Run with: php artisan migrate --path=database/migrations/2026_11_06_000001_add_reports_performance_indexes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------------------------
        // invoices
        // -------------------------------------------------------------------------

        // Sales Summary / VAT Summary
        // Query: WHERE status IN ('issued','partially_paid','paid')
        //          AND issue_date BETWEEN :from AND :to
        //          [AND location_id = :loc]
        //        GROUP BY DATE_TRUNC('day', issue_date)
        //
        // Partial predicate eliminates draft/void rows from the index.
        DB::statement("
            CREATE INDEX IF NOT EXISTS invoices_posted_issue_date_location_idx
            ON invoices (issue_date, location_id)
            WHERE status IN ('issued', 'partially_paid', 'paid')
        ");

        // Receivables Aging
        // Query: WHERE status IN ('issued','partially_paid')
        //        SELECT customer_id, due_date, total, amount_paid  (CASE WHEN buckets)
        //        GROUP BY customer_id
        //
        // Partial index + INCLUDE turns the aggregation into an index-only scan.
        DB::statement("
            CREATE INDEX IF NOT EXISTS invoices_open_receivables_covering_idx
            ON invoices (customer_id, due_date)
            INCLUDE (total, amount_paid)
            WHERE status IN ('issued', 'partially_paid')
        ");

        // Voided Invoices Audit
        // Query: WHERE status = 'void'
        //          AND voided_at >= :start AND voided_at < :end
        //          [AND location_id = :loc]
        DB::statement("
            CREATE INDEX IF NOT EXISTS invoices_voided_at_location_idx
            ON invoices (voided_at, location_id)
            WHERE status = 'void'
        ");

        // -------------------------------------------------------------------------
        // payments
        // -------------------------------------------------------------------------

        // Payments by Method
        // Query: WHERE status = 'active' AND received_at >= :start AND received_at < :end
        //        GROUP BY method
        //
        // Partial predicate keeps reversed payments out of the index.
        DB::statement("
            CREATE INDEX IF NOT EXISTS payments_active_received_at_idx
            ON payments (received_at, method)
            WHERE status = 'active'
        ");

        // -------------------------------------------------------------------------
        // pos_sessions
        // -------------------------------------------------------------------------

        // POS Sessions report
        // Query: [WHERE location_id = :loc]
        //          AND opened_at >= :start AND opened_at < :end
        //        ORDER BY opened_at DESC
        DB::statement('
            CREATE INDEX IF NOT EXISTS pos_sessions_location_opened_at_idx
            ON pos_sessions (location_id, opened_at DESC)
        ');

        // -------------------------------------------------------------------------
        // products (Inventory Report)
        // -------------------------------------------------------------------------

        // Inventory Report
        // Query: WHERE products.deleted_at IS NULL
        //          [AND products.category_id = :cat]
        //        JOIN stock_levels ON products.id = stock_levels.product_id
        //
        // Partial index excludes soft-deleted products.
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_active_category_idx
            ON products (category_id, id)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invoices_posted_issue_date_location_idx');
        DB::statement('DROP INDEX IF EXISTS invoices_open_receivables_covering_idx');
        DB::statement('DROP INDEX IF EXISTS invoices_voided_at_location_idx');
        DB::statement('DROP INDEX IF EXISTS payments_active_received_at_idx');
        DB::statement('DROP INDEX IF EXISTS pos_sessions_location_opened_at_idx');
        DB::statement('DROP INDEX IF EXISTS products_active_category_idx');
    }
};
