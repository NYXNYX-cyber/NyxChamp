<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')
                ->constrained('chat_messages')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('disk', 32)->default('chat');
            $table->string('file_path', 512);
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index('chat_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_attachments');
    }
};
