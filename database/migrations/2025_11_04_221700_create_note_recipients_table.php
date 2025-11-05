<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_recipients', function (Blueprint $table) {
            $table->id();
            $table->uuid('note_id');
            $table->string('email');
            $table->uuid('token')->unique();
            $table->timestamp('send_date')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('hearted_at')->nullable();
            $table->timestamps();

            $table->foreign('note_id')->references('id')->on('notes')->onDelete('cascade');
            $table->index(['note_id']);
            $table->index(['email']);
            $table->unique(['note_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_recipients');
    }
};


