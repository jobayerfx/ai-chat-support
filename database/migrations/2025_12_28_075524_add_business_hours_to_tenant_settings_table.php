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
        Schema::table('tenant_settings', function (Blueprint $table) {
            // Business hours configuration
            $table->boolean('business_hours_enabled')->default(true);
            $table->string('timezone')->default('UTC');
            $table->time('business_start_time')->default('09:00');
            $table->time('business_end_time')->default('17:00');
            $table->json('business_days')->default('[1,2,3,4,5]'); // Monday to Friday
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'business_hours_enabled',
                'timezone',
                'business_start_time',
                'business_end_time',
                'business_days'
            ]);
        });
    }
};
