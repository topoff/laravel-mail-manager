<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('mail-manager.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('message_types', function (Blueprint $table): void {
            $table->string('ses_configuration_set', 80)->nullable()->after('bulk_message_line');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('message_types', function (Blueprint $table): void {
            $table->dropColumn('ses_configuration_set');
        });
    }
};
