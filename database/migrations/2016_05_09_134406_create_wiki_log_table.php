<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWikiLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wiki_log', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('wiki_id');
            $table->integer('user_id');
            $table->string('title');
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP(0)'));
            $table->text('excerpt')->nullable();
            $table->text('text')->nullable();
            $table->text('comment')->nullable();
            $table->integer('length')->default(0);
            $table->integer('diff')->default(0);
            $table->tinyInteger('is_restored')->default(0);
            $table->string('ip', 45)->nullable();
            $table->string('browser')->nullable();
            $table->string('host')->nullable();

            $table->index('wiki_id');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('no action');
            $table->foreign('wiki_id')->references('id')->on('wiki_pages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('wiki_log');
    }
}
