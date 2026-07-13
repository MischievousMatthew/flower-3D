<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            // Kept separate from the legacy status enum so existing records and APIs remain valid.
            $table->string('resubmission_status')->nullable()->after('status')->index();
        });

        Schema::create('vendor_application_resubmission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_application_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status')->default('requested')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['vendor_application_id', 'status']);
        });

        Schema::create('vendor_application_resubmissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resubmission_request_id')
                ->constrained('vendor_application_resubmission_requests')->cascadeOnDelete();
            $table->foreignId('vendor_application_id')->constrained()->cascadeOnDelete();
            $table->string('field_name');
            $table->text('rejection_reason')->nullable();
            $table->longText('original_value')->nullable();
            $table->longText('resubmitted_value')->nullable();
            $table->string('status')->default('pending_resubmission')->index();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('resubmitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['resubmission_request_id', 'field_name'], 'vendor_resubmission_request_field_unique');
            $table->index(['vendor_application_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_application_resubmissions');
        Schema::dropIfExists('vendor_application_resubmission_requests');

        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->dropIndex(['resubmission_status']);
            $table->dropColumn('resubmission_status');
        });
    }
};
