<?php

use App\Http\Controllers\TalentoHumano\NotificacionController;
use Illuminate\Support\Facades\Route;

// Rutas de notificaciones accesibles por cualquier usuario autenticado
// (Aspirante, Docente, Talento Humano, etc.)
Route::middleware(['api', 'auth:api'])->group(function () {
    // Obtener todas las notificaciones del usuario autenticado
    Route::get('/notificaciones', [NotificacionController::class, 'obtenerNotificaciones']);

    // Marcar una notificación específica como leída
    Route::put('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarComoLeida']);

    // Marcar todas las notificaciones como leídas
    Route::put('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasComoLeidas']);
});
