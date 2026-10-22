<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipo;
use App\Models\User; 

class EquipoController extends Controller
{
   public function index(Request $request)
    {
        // CAMBIO: Leemos desde la Cabecera (Header), igual que en Libros y Configuraciones
        $userId = $request->header('X-Usuario-Id');
        
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado. Se requiere usuario_id.'], 401);
        }

        // Filtramos solo los equipos de su campo
        $equipos = Equipo::where('campo', $userAuth->campo)
            ->withCount(['usuarios as miembros_activos' => function ($query) {
                $query->where('is_active', true);
            }])->get();

        return response()->json($equipos, 200);
    }

    public function store(Request $request)
    {
        // CAMBIO: Leemos desde la Cabecera (Header)
        $userId = $request->header('X-Usuario-Id');
        
        $userAuth = User::find($userId);

        if (!$userAuth || $userAuth->role !== 'pastor') {
            return response()->json(['error' => 'No autorizado. Solo los pastores pueden crear equipos.'], 403);
        }

        $datosValidados = $request->validate([
            'nombre' => 'required|string|unique:equipos,nombre|max:255',
            'lugar' => 'required|string|max:255',
        ]);

        $datosValidados['campo'] = $userAuth->campo;

        $equipo = Equipo::create($datosValidados);

        return response()->json([
            'mensaje' => 'Equipo creado exitosamente.',
            'data' => $equipo
        ], 201);
    }

   public function update(Request $request, $id)
    {
        $equipo = Equipo::findOrFail($id);

        $datosValidados = $request->validate([
            'nombre' => 'required|string|max:255|unique:equipos,nombre,' . $id,
            'lugar' => 'required|string|max:255',
        ]);

        $equipo->update($datosValidados);

        return response()->json([
            'mensaje' => 'Equipo actualizado correctamente.',
            'data' => $equipo
        ], 200);
    }

    public function getPersonal(Request $request, $id)
    {
        $personal = User::where('equipo_id', $id)
            ->whereIn('role', ['lider', 'co-lider', 'contador'])
            ->get();
            
        return response()->json($personal, 200);
    }
}