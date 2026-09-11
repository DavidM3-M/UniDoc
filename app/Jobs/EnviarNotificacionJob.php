<?php

namespace App\Jobs;

use App\Http\Controllers\TalentoHumano\NotificacionController;
use App\Models\MovimientoExpediente;
use App\Models\NotificacionEnviada;
use App\Models\Usuario\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnviarNotificacionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 60;
    private array $movimientoIds = [];

    public function __construct(
        private readonly string $clave,
        private readonly string $tipo,
        private readonly string $metodo,
        private readonly array $args,
        private readonly ?int $destinatarioId = null,
        array $movimientoIds = [],
    ) {
        $this->movimientoIds = $movimientoIds;
    }

    public function handle(): void
    {
        // El candado caduca después del timeout del job. También protege una reejecución manual.
        $lock = Cache::lock('notificacion:' . hash('sha256', $this->clave), 120);
        if (!$lock->get()) {
            $this->release(10);
            return;
        }

        try {
            $destinatario = $this->destinatarioId !== null ? User::find($this->destinatarioId) : null;
            $registro = NotificacionEnviada::firstOrCreate(
                ['clave' => $this->clave],
                ['tipo' => $this->tipo, 'user_id' => $destinatario?->id]
            );
            if ($registro->enviado_en !== null) {
                $this->marcarMovimientos();
                return;
            }

            $args = $this->args;
            if ($this->destinatarioId !== null) {
                if (!$destinatario) {
                    $registro->marcarFallida('El destinatario ya no existe.');
                    return;
                }
                array_unshift($args, $destinatario);
            }

            try {
                NotificacionController::{$this->metodo}(...$args, encolar: false);
                $registro->marcarEnviada();
            } catch (\Throwable $e) {
                $registro->marcarFallida($e->getMessage());
                throw $e;
            }
            $this->marcarMovimientos();
        } finally {
            $lock->release();
        }
    }

    private function marcarMovimientos(): void
    {
        if ($this->movimientoIds) {
            MovimientoExpediente::whereIn('id_movimiento', $this->movimientoIds)
                ->where('user_id', $this->destinatarioId)
                ->whereNull('notificado_en')
                ->update(['notificado_en' => now()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("La notificación {$this->clave} agotó sus reintentos: " . $e->getMessage());
    }
}
