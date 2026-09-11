<?php

use App\Http\Controllers\TalentoHumano\NotificacionController;
use Illuminate\Support\Facades\Route;

// Rutas de notificaciones accesibles por cualquier usuario autenticado
// (Aspirante, Docente, Talento Humano, etc.)
Route::middleware(['api', 'auth:api'])->group(function () {
    // Listado paginado de notificaciones del usuario autenticado.
    // Acepta ?pagina, ?limite y ?solo_no_leidas.
    Route::get('/notificaciones', [NotificacionController::class, 'obtenerNotificaciones']);

    // Solo el número de no leídas: es lo que la campana consulta periódicamente en toda la
    // plataforma, y traer el listado entero para contarlo saldría caro sin ganar nada.
    Route::get('/notificaciones/conteo', [NotificacionController::class, 'conteoNoLeidas']);

    // Marcar una notificación específica como leída
    Route::put('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarComoLeida']);

    // Marcar todas las notificaciones como leídas
    Route::put('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasComoLeidas']);
});
