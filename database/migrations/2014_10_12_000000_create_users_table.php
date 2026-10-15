<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
//        Schema::create('registros', function (Blueprint $table) {
//            $table->id();
//            $table->timestamps();
//            $table->softDeletes();
//        });
//
//        Schema::create('registro_logs', function(Blueprint $table){
//            $table->id();
//            $table->unsignedBigInteger('registro_id');
//            $table->dateTime('date_log');
//            $table->enum('type',['create', 'update', 'delete']);
//            $table->text('old_data');
//            $table->text('new_data');
//            $table->timestamps();
//            $table->foreign('registro_id')->references('id')->on('registros')->onDelete('cascade')->onUpdate('cascade');
//        });


        Schema::create('users', function (Blueprint $table) {
            $table->id();
//            $table->unsignedBigInteger('registro_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('cpf')->nullable();
            $table->string('telefone')->nullable();
            $table->string('photo')->nullable();
            $table->string('photo_url')->nullable();
            $table->dateTime('photo_url_expire')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
//            $table->foreign('registro_id')->references('id')->on('registros')->onDelete('cascade')->onUpdate('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
//        Schema::dropIfExists('registro_logs');
//        Schema::dropIfExists('registros');
    }
}
