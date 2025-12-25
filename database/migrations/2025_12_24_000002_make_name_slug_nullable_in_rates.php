<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            // name will be auto-generated from property/unit/coupon
            $table->string('name')->nullable()->change();
            
            // slug is auto-generated
            $table->string('slug')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('slug')->nullable(false)->change();
        });
    }
};
