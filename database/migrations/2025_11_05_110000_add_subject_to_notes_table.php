<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            if (!Schema::hasColumn('notes', 'subject')) {
                $table->string('subject')->nullable()->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            if (Schema::hasColumn('notes', 'subject')) {
                $table->dropColumn('subject');
            }
        });
    }
};


