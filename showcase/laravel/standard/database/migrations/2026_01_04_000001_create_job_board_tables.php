<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Companies table
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->string('industry')->nullable();
            $table->string('size')->nullable();
            $table->integer('founded_year')->nullable();
            $table->timestamp('enriched_at')->nullable();
            $table->timestamps();
        });

        // Job listings table
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->string('external_id');
            $table->string('source');
            $table->string('title');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_name');
            $table->string('company_logo')->nullable();
            $table->string('location')->nullable();
            $table->boolean('remote')->default(false);
            $table->string('job_type')->nullable();
            $table->integer('salary_min')->nullable();
            $table->integer('salary_max')->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->text('description')->nullable();
            $table->string('url');
            $table->json('tags')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['external_id', 'source']);
            $table->index(['remote', 'job_type']);
            $table->index('posted_at');
        });

        // Saved searches with webhooks
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('query')->nullable();
            $table->json('filters')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
        });

        // Webhook delivery log
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $table->json('job_ids');
            $table->json('payload');
            $table->string('status');
            $table->integer('attempts')->default(0);
            $table->integer('http_status')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('job_listings');
        Schema::dropIfExists('companies');
    }
};
