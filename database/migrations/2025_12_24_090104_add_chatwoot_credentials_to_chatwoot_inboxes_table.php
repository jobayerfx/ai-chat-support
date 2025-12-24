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
        Schema::table('chatwoot_inboxes', function (Blueprint $table) {
            $table->string('base_url');
            $table->string('api_key');
            $table->unsignedInteger('account_id');
            $table->string('webhook_secret')->nullable();
            $table->string('name')->nullable(); // inbox name
            $table->boolean('is_connected')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chatwoot_inboxes', function (Blueprint $table) {
            $table->dropColumn(['base_url', 'api_key', 'account_id', 'webhook_secret', 'name', 'is_connected']);
        });
    }
};
