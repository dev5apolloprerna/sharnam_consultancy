<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('employee_salary_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedTinyInteger('salary_month');
            $table->unsignedSmallInteger('salary_year');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('deduct_amount', 12, 2)->default(200);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'salary_month', 'salary_year'], 'emp_month_year_unique');
            $table->index(['salary_year', 'salary_month'], 'salary_period_index');
            $table->foreign('employee_id')->references('employee_id')->on('employee_master')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('employee_salary_payments');
    }
};
