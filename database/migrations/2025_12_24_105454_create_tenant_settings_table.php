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
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->unique('tenant_id');

            // Onboarding status
            $table->boolean('onboarding_completed')->default(false);
            $table->json('onboarding_steps')->nullable(); // Track completion of each step

            // Chatwoot integration settings
            $table->string('chatwoot_base_url')->nullable();
            $table->text('chatwoot_api_token')->nullable(); // Will be encrypted
            $table->boolean('chatwoot_connected')->default(false);
            $table->json('chatwoot_inboxes')->nullable();

            // Knowledge base settings
            $table->boolean('knowledge_base_setup')->default(false);
            $table->integer('knowledge_documents_count')->default(0);
            $table->timestamp('last_knowledge_update')->nullable();

            // AI settings
            $table->boolean('ai_configured')->default(false);
            $table->decimal('confidence_threshold', 3, 2)->default(0.7);
            $table->boolean('human_override_enabled')->default(true);
            $table->decimal('auto_escalate_threshold', 3, 2)->default(0.4);

            // Notification preferences
            $table->boolean('email_notifications')->default(true);
            $table->boolean('ai_response_notifications')->default(true);

            // Branding settings
            $table->string('company_logo_url')->nullable();
            $table->string('primary_color')->default('#4F46E5');
            $table->string('secondary_color')->default('#6B7280');

            // Usage tracking
            $table->integer('monthly_active_users')->default(0);
            $table->integer('total_conversations')->default(0);
            $table->integer('ai_responses_count')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tenant_settings');
    }
};
