<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('confidence_threshold', 3, 2)->default(0.7);
            $table->boolean('human_override_enabled')->default(true);
            $table->decimal('auto_escalate_threshold', 3, 2)->default(0.4);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['confidence_threshold', 'human_override_enabled', 'auto_escalate_threshold']);
        });
    }
};
