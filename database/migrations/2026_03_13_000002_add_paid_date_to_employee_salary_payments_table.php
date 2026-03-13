<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('employee_salary_payments', function (Blueprint $table) {
            $table->date('paid_date')->nullable()->after('paid_amount');
        });
    }

    public function down()
    {
        Schema::table('employee_salary_payments', function (Blueprint $table) {
            $table->dropColumn('paid_date');
        });
    }
};
