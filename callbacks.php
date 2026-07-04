<?php

return [
    App\Events\PluginWasEnabled::class => function () {
        if (!Schema::hasTable('user_bans')) {
            Schema::create('user_bans', function ($table) {
                $table->increments('id');
                $table->integer('user_id')->unsigned();
                $table->string('reason', 500)->default('');
                $table->dateTime('banned_at');
                $table->dateTime('expires_at')->nullable();
                $table->boolean('is_permanent')->default(false);
                $table->integer('banned_by')->unsigned();
                $table->timestamps();

                $table->foreign('user_id')->references('uid')->on('users')->onDelete('cascade');
                $table->foreign('banned_by')->references('uid')->on('users')->onDelete('cascade');
            });
        }
    },

    App\Events\PluginWasDeleted::class => function () {
        Schema::dropIfExists('user_bans');
    },
];