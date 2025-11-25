<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Usuario\User;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsuariosExport;


class UserController
{

    // de admin aun no hay nada hecho
    // esto es solo un ejemplo basico de como se haria

    //Listar todos los usuarios con sus roles
    public function listarUsuarios()
    {
        //cambie esto (Brayan Cuellar)
        try {
            $usuarios = User::with('roles')->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'primer_nombre' => $user->primer_nombre,
                    'segundo_nombre' => $user->segundo_nombre,
                    'primer_apellido' => $user->primer_apellido,
                    'segundo_apellido' => $user->segundo_apellido,
                    'numero_identificacion' => $user->numero_identificacion,
                    'email' => $user->email,
                    'telefono' => $user->telefono ?? 'N/A',
                    'rol' => $user->roles->first()?->name ?? 'Sin rol',
                    'created_at' => $user->created_at->format('Y-m-d'),
                ];
            });

            return response()->json([
                'usuarios' => $usuarios
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Cambiar rol de un usuario
    // agregue esto (Brayan Cuellar)
    public function cambiarRol(Request $request, $id)
    {
        try {
            $request->validate([
                'rol' => 'required|exists:roles,name',
            ]);

            $usuario = User::findOrFail($id);

            // Remover todos los roles anteriores y asignar el nuevo
            $usuario->syncRoles([$request->rol]);

            return response()->json([
                'message' => 'Rol actualizado con éxito',
                'usuario' => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->primer_nombre . ' ' . $usuario->primer_apellido,
                    'rol' => $request->rol
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar el rol',
                'error' => $e->getMessage()
            ], 500);
        }
    }
     // Exportar usuarios a Excel
     // agregue esto tambien (Brayan Cuellar)
    public function exportarUsuariosExcel()
    {
        try {
            return Excel::download(new UsuariosExport, 'usuarios_' . date('Y-m-d_H-i-s') . '.xlsx');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al exportar usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Editar un usuario
    public function editarUsuario(Request $request,$id){

        //Buscar el usuario por id
        $user = User::find($id);

        //Si el usuario no existe, devolver un mensaje de error
        if(!$user){
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        //Actualizar el usuario
        $user->update($request->all());

        //Devolver respuesta con el usuario actualizado
        return response()->json($user, 200);
    }

    //Eliminar un usuario
    public function eliminarUsuario($id){

        //Buscar el usuario por id
        $user = User::find($id);

        //Si el usuario no existe, devolver un mensaje de error
        if(!$user){
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        //Eliminar el usuario
        $user->delete();

        //Devolver respuesta con el usuario eliminado
        return response()->json(['message' => 'Usuario eliminado'], 200);
    }
}
