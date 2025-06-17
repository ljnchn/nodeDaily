<?php

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;

Route::fallback(function(){
    // 处理跨域 options 请求
    response()->withHeaders([
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Origin' => request()->header('Origin', '*'),
        'Access-Control-Allow-Methods' => '*',
        'Access-Control-Allow-Headers' => '*',
    ]);

    if (request()->method() == 'OPTIONS') {
        return response();
    }

    return Response('Not Found', 404);
});

// Telegram Bot Webhook
Route::post('/telegram/webhook', [app\controller\TelegramBotController::class, 'webhook']);
Route::get('/telegram/setWebhook', [app\controller\TelegramBotController::class, 'setWebhook']);

// TgPost 数据接收接口
Route::post('/telegram/receive-data', [app\controller\TelegramBotController::class, 'receiveData']);

Route::group('/search', function () {
    Route::post('/list', [app\controller\SearchController::class, 'search']);
    Route::get('/categoryList', [app\controller\SearchController::class, 'categoryList']);
})->middleware([
    app\middleware\Cors::class,
]);

# 关闭自动路由
Route::disableDefaultRoute();
