<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_contacts')) {
            Schema::table('tenant_contacts', function (Blueprint $table) {
                $table->string('name_bn', 200)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_contacts')) {
            Schema::table('tenant_contacts', function (Blueprint $table) {
                $table->string('name_bn', 200)->nullable(false)->change();
            });
        }
    }
};
