<?php

use Integration\Forum\Listener;

require __DIR__.'/src/helpers.php';

return function () {
    // 确保开启单角色限制
    if (! option('single_player')) {
        option(['single_player' => true]);
    }

    // 在 users 表上添加 salt 字段
    if (! Schema::hasColumn('users', 'salt')) {
        Schema::table('users', function ($table) {
            $table->string('salt', 6)->default('');
        });
    }

    // 绑定 Query Builder 至容器，方便之后直接调用
    App::instance('db.local', DB::connection()->table('users'));
    App::singleton('db.remote', function () {
        $config = @unserialize(option('forum_db_config', ''));

        config(['database.connections.remote' => array_merge(
            forum_get_default_db_config(), (array) $config
        )]);

        return DB::connection('remote')->table(array_get($config, 'table'));
    });

    try {
        app('db.remote')->getConnection()->getPdo();
    } catch (Exception $e) {
        // 目标数据库没配置好之前啥也不干
        return;
    }

    // 兼容动态 salt，以及监听事件同步用户数据
    Event::subscribe(Listener\HashAlgorithms::class);
    Event::subscribe(Listener\SynchronizeUser::class);
};
