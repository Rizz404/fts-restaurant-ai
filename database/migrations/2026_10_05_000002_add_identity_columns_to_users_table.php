<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff accounts are provisioned by admins only. There is no department:
     * access comes from roles (admin/editor/staff) and staff team membership.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_locale', 5)->default('id')->after('password');
            $table->boolean('is_active')->default(true)->after('preferred_locale');
            $table->boolean('must_change_password')->default(true)->after('is_active');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['preferred_locale', 'is_active', 'must_change_password']);
        });
    }
};
