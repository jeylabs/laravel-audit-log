<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->string('description');
            $table->nullableMorphs('subject');
            $table->nullableMorphs('causer');
            $table->text('properties')->nullable();
            $table->string('ip')->nullable();
            $table->string('latitude')->nullable();
            $table->string('original_latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('original_longitude')->nullable();
            $table->timestamps();

            $table->index('log_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('audit_logs');
    }
};
