<?php

namespace App\Http\Controllers\Admin;

// Creamos un controlador para manejar los roles de usuario
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Models\Usuario\User;

class RoleController
{

    
    // Obtener todos los roles
    public function listarRoles()
    {
        // Obtener todos los roles
        $roles = Role::all();
        return response()->json($roles);
    }

    // // Crear un nuevo rol
    // public function crearRol(Request $request)
    // {
    //     // Validar los datos de entrada
    //     $request->validate([
    //         'name' => 'required|string|unique:roles',
    //     ]);

    //     // Crear el rol
    //     $rol = Role::create(['name' => $request->name]);

    //     // Devolver respuesta con el rol creado
    //     return response()->json(['message' => 'Rol creado con éxito', 'rol' => $rol]);
    // }

    // Asignar un rol a un usuario
    public function asignarRol(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|exists:roles,name',
        ]);

        // Buscar el usuario
        $usuario = User::findOrFail($request->user_id);
        $usuario->assignRole($request->role);

        // Devolver respuesta
        return response()->json(['message' => 'Rol asignado con éxito']);
    }

    // Remover un rol de un usuario
    public function removerRol(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|exists:roles,name',
        ]);

        // Buscar el usuario
        $usuario = User::findOrFail($request->user_id);
        $usuario->removeRole($request->role);

        // Devolver respuesta
        return response()->json(['message' => 'Rol eliminado con éxito']);
    }

    // Editar un rol (cambiar nombre)
    public function actualizarRol(Request $request, Role $rol, $id)
    {
        // Buscar el rol
        $existingRole = Role::find($id);
        if (!$existingRole) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }
        // Validar los datos de entrada
        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $existingRole->id,
        ]);
        // Actualizar el rol
        $existingRole->update(['name' => $request->name]);

        // Devolver respuesta
        return response()->json(['message' => 'Rol actualizado con éxito']);
    }

    // // Eliminar un rol
    // public function eliminarRol(Role $rol)
    // {
    //     // Eliminar el rol
    //     $rol->delete();
    //     return response()->json(['message' => 'Rol eliminado con éxito']);
    // }
}
