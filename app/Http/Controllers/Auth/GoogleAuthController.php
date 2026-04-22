<?php

namespace App\Http\Controllers\Auth;

use App\Models\Usuario\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class GoogleAuthController
{
    /**
     * Resuelve un municipio válido para creación de usuarios OAuth.
     */
    private function resolveMunicipioId(): int
    {
        if (DB::table('municipios')->where('id_municipio', 703)->exists()) {
            return 703;
        }

        return (int) (DB::table('municipios')->min('id_municipio') ?? 1);
    }

    /**
     * Obtiene una URL de frontend válida incluso si FRONTEND_URL viene como lista CSV.
     */
    private function resolveFrontendUrl(): string
    {
        $raw = (string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        $first = trim(explode(',', $raw)[0] ?? 'http://localhost:5173');

        return rtrim($first !== '' ? $first : 'http://localhost:5173', '/');
    }

    /**
     * Redirige al usuario a la pantalla de autenticación de Google.
     */
    public function redirect()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    /**
     * Maneja el callback de Google, crea o recupera el usuario y devuelve un JWT.
     * Redirige al frontend con el token como query param.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $googleId = (string) $googleUser->getId();
            $email = (string) $googleUser->getEmail();
            $givenName = trim((string) ($googleUser->user['given_name'] ?? ''));
            $familyName = trim((string) ($googleUser->user['family_name'] ?? ''));
            $fullName = trim((string) ($googleUser->getName() ?? 'Usuario'));

            $firstName = $givenName !== '' ? $givenName : (explode(' ', $fullName)[0] ?? 'Usuario');
            $lastName = $familyName !== '' ? $familyName : 'Google';

            $user = User::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if (! $user) {
                $user = User::create([
                    'municipio_id' => $this->resolveMunicipioId(),
                    'tipo_identificacion' => 'Pasaporte',
                    'numero_identificacion' => 'GOOGLE-' . $googleId,
                    'genero' => null,
                    'primer_nombre' => Str::limit($firstName, 255, ''),
                    'segundo_nombre' => null,
                    'primer_apellido' => Str::limit($lastName, 255, ''),
                    'segundo_apellido' => null,
                    'fecha_nacimiento' => '1990-01-01',
                    'estado_civil' => null,
                    'email' => $email,
                    'password' => bcrypt(Str::random(24)),
                    'google_id' => $googleId,
                ]);
            } elseif (! $user->google_id) {
                $user->update(['google_id' => $googleId]);
            }

            // Asignar rol Aspirante si no tiene ninguno
            if ($user->getRoleNames()->isEmpty()) {
                $user->assignRole('Aspirante');
            }

            $token = JWTAuth::fromUser($user);

            $frontendUrl = $this->resolveFrontendUrl();

            // Redirige al frontend con el token; el frontend lo captura y guarda en cookie
            return redirect("{$frontendUrl}/auth/google/callback?token={$token}");
        } catch (\Exception $e) {
            Log::error('Google OAuth error', ['error' => $e->getMessage()]);
            $frontendUrl = $this->resolveFrontendUrl();
            return redirect("{$frontendUrl}/inicio-sesion?error=google_auth_failed");
        }
    }
}
