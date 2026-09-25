<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('icon', 16)->nullable();
            $table->string('title');
            $table->text('desc')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('level', 16)->default('Mid');
            $table->unsignedTinyInteger('years')->default(0);
            $table->string('avatar')->nullable();
            $table->boolean('available')->default(true);
            $table->string('location')->nullable();
            $table->string('education')->nullable();
            $table->string('languages')->nullable();
            $table->text('bio')->nullable();
            $table->json('skills')->nullable();
            $table->json('links')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable();
            $table->string('year', 16)->nullable();
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->text('desc')->nullable();
            $table->json('tech')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('member_project', function (Blueprint $table) {
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->primary(['member_id', 'project_id']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('ip', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('member_project');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('members');
        Schema::dropIfExists('services');
        Schema::dropIfExists('settings');
    }
};
