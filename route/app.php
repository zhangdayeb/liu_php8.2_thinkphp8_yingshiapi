<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});
Route::get('hello/:name', 'index/hello');

// 计划模块
Route::rule('movie/type_list$', 'movie.Type/getList');        // 分类列表
Route::rule('movie/list$', 'movie.Movie/getList');            // 影片列表（含搜索、排序）
Route::rule('movie/info$', 'movie.Movie/getInfo');            // 影片详情
Route::rule('movie/play_log$', 'movie.Movie/addPlayLog');     // 播放日志
