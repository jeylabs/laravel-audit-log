<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAuditLogTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('log_name')->nullable();
            $table->string('description');
            $table->integer('subject_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->integer('causer_id')->nullable();
            $table->string('causer_type')->nullable();
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
    public function down()
    {
        Schema::drop('audit_logs');
    }
}
