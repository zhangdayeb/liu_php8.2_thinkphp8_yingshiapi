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
Route::rule('movie/type_list$', 'movie.TypeList/get_type_list'); // 获取电影类型列表
Route::rule('movie/movie_list$', 'movie.MovieList/get_movie_list'); // 获取电影列表
Route::rule('movie/movie_info$', 'movie.MovieInfo/get_movie_info'); // 获取电影详情
Route::rule('movie/movie_search$', 'movie.MovieSearch/get_movie_search'); // 搜索电影
Route::rule('movie/movie_rank$', 'movie.MovieRank/get_movie_rank'); // 获取电影排行榜
