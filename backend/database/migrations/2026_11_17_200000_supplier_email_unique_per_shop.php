<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each shop keeps its own supplier list, and two shops can buy from the same
 * supplier. A supplier's email is now unique within one shop only (and a
 * contact's email within its supplier), not across every shop.
 * Only the supplier tables change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('suppliers', 'suppliers_email_unique')) {
            Schema::table('suppliers', fn (Blueprint $t) => $t->dropUnique('suppliers_email_unique'));
        }
        if (! Schema::hasIndex('suppliers', 'suppliers_owner_id_email_unique')) {
            Schema::table('suppliers', fn (Blueprint $t) => $t->unique(['owner_id', 'email'], 'suppliers_owner_id_email_unique'));
        }

        if (Schema::hasTable('supplier_contacts')) {
            if (Schema::hasIndex('supplier_contacts', 'supplier_contacts_email_unique')) {
                Schema::table('supplier_contacts', fn (Blueprint $t) => $t->dropUnique('supplier_contacts_email_unique'));
            }
            if (! Schema::hasIndex('supplier_contacts', 'supplier_contacts_supplier_id_email_unique')) {
                Schema::table('supplier_contacts', fn (Blueprint $t) => $t->unique(['supplier_id', 'email'], 'supplier_contacts_supplier_id_email_unique'));
            }
        }
    }

    public function down(): void
    {
        // Not put back: shops may now share a supplier's email
    }
};
