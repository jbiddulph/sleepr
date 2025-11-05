<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            if (!Schema::hasColumn('notes', 'template_id')) {
                $table->uuid('template_id')->nullable()->after('is_published');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            if (Schema::hasColumn('notes', 'template_id')) {
                $table->dropColumn('template_id');
            }
        });
    }
};


