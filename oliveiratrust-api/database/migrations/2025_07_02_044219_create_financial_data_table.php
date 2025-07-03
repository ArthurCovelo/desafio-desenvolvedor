<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('financial_data', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('ticker_symbol');
            $table->string('isin')->nullable();
            $table->string('corporate_name')->nullable();
            $table->string('market_name')->nullable();
            $table->string('security_category')->nullable();
            $table->foreignId('file_upload_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('financial_data');
    }
};
