<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_audits', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->string('final_url', 2048)->nullable();
            $table->string('status', 20)->default('pending'); // pending|running|done|failed
            $table->unsignedInteger('http_status')->nullable();
            $table->unsignedInteger('render_ms')->nullable();
            $table->unsignedInteger('llm_ms')->nullable();

            $table->json('page_data')->nullable();   // what we extracted
            $table->json('result')->nullable();      // llm report JSON
            $table->text('error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_audits');
    }
};
