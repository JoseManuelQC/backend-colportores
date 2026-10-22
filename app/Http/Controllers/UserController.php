<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $query = User::where('role', 'colportor');

        if ($userAuth->role === 'pastor') {
            $equiposIds = \App\Models\Equipo::where('campo', $userAuth->campo)->pluck('id');
            $query->whereIn('equipo_id', $equiposIds);
        } elseif (in_array($userAuth->role, ['lider', 'co-lider', 'contador'])) {
            $query->where('equipo_id', $userAuth->equipo_id);
        }

        $colportores = $query->get();
        return response()->json($colportores, 200);
    }

    public function store(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $datosValidados = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'dni' => 'required|string|unique:users,dni', // AÑADIDO: Obligatorio y único
            'telefono' => 'required|string|max:20',      // AÑADIDO: Obligatorio
            'role' => 'required|in:pastor,contador,lider,co-lider,colportor',
            'equipo_id' => 'nullable|exists:equipos,id',
            'universidad_destino' => 'nullable|string'
        ]);

        if (in_array($userAuth->role, ['lider', 'co-lider', 'contador'])) {
            $datosValidados['equipo_id'] = $userAuth->equipo_id;
        }

        if ($userAuth->role === 'pastor' && in_array($datosValidados['role'], ['lider', 'co-lider', 'contador'])) {
            $datosValidados['campo'] = $userAuth->campo;
        }

        $datosValidados['password'] = Hash::make($datosValidados['password']);
        $datosValidados['role'] = $datosValidados['role'] ?? 'colportor';

        $usuario = User::create($datosValidados);

        return response()->json([
            'mensaje' => 'Registro exitoso.',
            'data' => $usuario
        ], 201);
    }

    public function toggleActive(Request $request, $id)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $usuario = User::findOrFail($id);

        $usuario->is_active = !$usuario->is_active;
        $usuario->save();

        $estado = $usuario->is_active ? 'activado' : 'desactivado';

        return response()->json([
            'mensaje' => "El colportor ha sido $estado correctamente.",
            'data' => $usuario
        ], 200);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::with('equipo')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Credenciales incorrectas o usuario no registrado.'], 401);
        }

        if ($user->role === 'colportor') {
            return response()->json(['error' => 'Acceso denegado. Esta plataforma es exclusiva para administración. Utiliza la App Móvil.'], 403);
        }

        return response()->json([
            'mensaje' => 'Login exitoso',
            'usuario' => $user
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $colportor = User::findOrFail($id);

        $datosValidados = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'dni' => 'required|string|unique:users,dni,' . $id,
            'telefono' => 'required|string|max:20',
            'role' => 'required|in:pastor,contador,lider,co-lider,colportor',
            'equipo_id' => 'nullable|exists:equipos,id',
            'universidad_destino' => 'nullable|string'
        ]);

        if (!empty($datosValidados['password'])) {
            $datosValidados['password'] = Hash::make($datosValidados['password']);
        } else {
            unset($datosValidados['password']);
        }

        $colportor->update($datosValidados);

        return response()->json([
            'mensaje' => 'Datos del colportor actualizados correctamente.',
            'data' => $colportor
        ], 200);
    }

    // =====================================================================
    // 6. ELIMINAR UN USUARIO DEFINITIVAMENTE
    // =====================================================================
    public function destroy(Request $request, $id)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $usuario = User::findOrFail($id);

        // Elimina el usuario de la base de datos
        $usuario->delete();

        return response()->json([
            'mensaje' => 'El usuario ha sido eliminado correctamente.'
        ], 200);
    }

}
