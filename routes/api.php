<?php

use App\Http\Controllers\Webhooks\WahaWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
 * Endpoint único: o tenant é resolvido pelo campo "session" do payload, o que é
 * mais simples de configurar no WAHA que uma URL por cliente e evita usar URL
 * secreta como se fosse segurança. A segurança é o HMAC.
 *
 * O throttle do grupo 'api' (60/min) é trocado por um teto compatível com
 * rajada de webhook.
 */
Route::post('/webhooks/waha', WahaWebhookController::class)
    ->withoutMiddleware(['throttle:api'])
    ->middleware(['waha.hmac', 'throttle:600,1'])
    ->name('webhooks.waha');
