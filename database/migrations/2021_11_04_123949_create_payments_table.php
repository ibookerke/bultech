<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->smallInteger("month");
            $table->smallInteger("year");
            $table->smallInteger("days_norm");
            $table->smallInteger("days_worked");
            $table->smallInteger("is_mzp");
            $table->double("salary");
            $table->double("after_taxes");
            $table->double("ipn");
            $table->double("opv");
            $table->double("osms");
            $table->double("vosms");
            $table->double("so");


            $table->foreignId("employee_id")->constrained("employees")->onUpdate('NO ACTION')->onDelete("NO ACTION");
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
        Schema::dropIfExists('payments');
    }
}
