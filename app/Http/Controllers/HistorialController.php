<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Historial;
use App\Models\User; // Añadido para poder buscar al usuario

class HistorialController extends Controller
{
    // Función para que el panel web (Angular) consulte el historial de auditoría
    public function index(Request $request)
    {
        // 1. Obtener quién hace la petición
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $query = Historial::with('user');
        
        // 2. Aislamiento por equipo
        if (in_array($userAuth->role, ['contador', 'lider', 'co-lider'])) {
            $query->where('equipo_id', $userAuth->equipo_id);
        } elseif ($userAuth->role === 'pastor') {
            $equiposIds = \App\Models\Equipo::where('campo', $userAuth->campo)->pluck('id');
            $query->whereIn('equipo_id', $equiposIds);
        }

        // Opcional: Permitir filtrar por colportor si se envía en la petición
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $historiales = $query->orderBy('created_at', 'desc')->get();

        return response()->json($historiales, 200);
    }

    // Función interna para registrar acciones de forma automática (ej. entregas, depósitos)
    // Se actualizó para requerir el equipo_id o buscarlo a través del user_id
    public static function registrar($user_id, $accion, $detalles)
    {
        $user = User::find($user_id);
        
        return Historial::create([
            'user_id' => $user_id,
            'equipo_id' => $user ? $user->equipo_id : null, // Guarda el equipo_id para el aislamiento
            'accion' => $accion,
            'detalles' => $detalles
        ]);
    }
}