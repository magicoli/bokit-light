<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable("coupons")) {
            Schema::create("coupons", function (Blueprint $table) {
                $table->id();
                $table->string("code")->unique();
                $table->string("name");
                $table
                    ->foreignId("property_id")
                    ->constrained()
                    ->onDelete("cascade");
                $table->decimal("discount_amount", 10, 2);
                $table->string("discount_type")->default("percentage"); // percentage or fixed
                $table->json("conditions")->nullable(); // Future conditions
                $table->boolean("is_active")->default(true);
                $table->timestamps();

                $table->index(["property_id", "is_active"]);
            });
        }

        // Add reference_rate_id to rates table
        Schema::table("rates", function (Blueprint $table) {
            $table
                ->foreignId("reference_rate_id")
                ->nullable()
                ->constrained("rates")
                ->onDelete("set null");
            $table->string("priority")->nullable();
            $table->date("booking_from")->nullable();
            $table->date("booking_to")->nullable();
            $table->date("stay_from")->nullable();
            $table->date("stay_to")->nullable();
            $table->json("conditions")->nullable(); // Future conditions

            // Modify base_amount to base_rate for consistency
            $table->renameColumn("base_amount", "base_rate");
        });
    }

    public function down(): void
    {
        Schema::table("rates", function (Blueprint $table) {
            $table->dropForeign(["reference_rate_id"]);
            $table->dropColumn([
                "reference_rate_id",
                "priority",
                "booking_from",
                "booking_to",
                "stay_from",
                "stay_to",
                "conditions",
            ]);
            $table->renameColumn("base_rate", "base_amount");
        });

        Schema::dropIfExists("coupons");
    }
};
