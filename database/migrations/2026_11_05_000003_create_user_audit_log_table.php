<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User audit log — records important permission and account changes.
 * Append-only: records are never updated or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('target_user_id');   // User being changed
            $table->unsignedBigInteger('performed_by');     // Admin who made the change
            $table->string('action');                       // e.g. created, role_changed, deactivated
            $table->json('changes')->nullable();            // Before/after values
            $table->text('notes')->nullable();
            $table->timestamp('performed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_audit_log');
    }
};
