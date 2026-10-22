<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Configuracion;
use App\Models\User; // Añadido para poder buscar al usuario

class ConfiguracionController extends Controller
{
    // Ver todas las sedes creadas filtradas por equipo/campo
    public function index(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $query = Configuracion::query();
        
        if (in_array($userAuth->role, ['contador', 'lider', 'co-lider'])) {
            $query->where('equipo_id', $userAuth->equipo_id);
        } elseif ($userAuth->role === 'pastor') {
            $equiposIds = \App\Models\Equipo::where('campo', $userAuth->campo)->pluck('id');
            $query->whereIn('equipo_id', $equiposIds);
        }

        $configuraciones = $query->get();
        return response()->json($configuraciones, 200);
    }

    // Crear una NUEVA sede para este equipo específico
    public function store(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $datosValidados = $request->validate([
            // IMPORTANTE: Quitamos unique:configuraciones,universidad
            'universidad' => 'required|string', 
            'meta_media_beca' => 'required|numeric|min:0',
            'meta_beca_completa' => 'required|numeric|min:0'
        ]);

        // Validación manual: Evitar que ESTE equipo duplique sedes, 
        // pero permitir que JADHAI e ISMAEL tengan la misma sede por separado.
        $existe = Configuracion::where('universidad', $datosValidados['universidad'])
                               ->where('equipo_id', $userAuth->equipo_id)->exists();
        if($existe) {
             return response()->json(['error' => 'Esta sede ya está registrada para su equipo.'], 400);
        }

        // Si es pastor creando una sede, quizás quieras obligarlo a elegir a qué equipo va,
        // pero por ahora asignaremos al equipo_id del usuario (si un pastor no tiene equipo, esto será null. Asegúrate de que los pastores asignen configuración desde la vista de un equipo específico si lo requieren).
        $datosValidados['equipo_id'] = $userAuth->equipo_id;
        
        $configuracion = Configuracion::create($datosValidados);

        return response()->json([
            'mensaje' => "Sede '{$configuracion->universidad}' creada exitosamente.",
            'data' => $configuracion
        ], 201);
    }

    // Actualizar una sede EXISTENTE
    public function update(Request $request, $id)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        $configuracion = Configuracion::findOrFail($id);

        // Verificamos que no esté editando un registro de otro equipo (Seguridad)
        if ($configuracion->equipo_id !== $userAuth->equipo_id && $userAuth->role !== 'pastor') {
            return response()->json(['error' => 'No tienes permiso para editar esta configuración.'], 403);
        }

        $datosValidados = $request->validate([
            'universidad' => 'required|string',
            'meta_media_beca' => 'required|numeric|min:0',
            'meta_beca_completa' => 'required|numeric|min:0'
        ]);

        // Validar que el nuevo nombre no choque con otra sede del MISMO equipo
        $existeOtra = Configuracion::where('universidad', $datosValidados['universidad'])
                                   ->where('equipo_id', $configuracion->equipo_id)
                                   ->where('id', '!=', $id)
                                   ->exists();
                                   
        if($existeOtra) {
             return response()->json(['error' => 'Ya existe otra sede con ese nombre en tu equipo.'], 400);
        }

        $configuracion->update($datosValidados);

        return response()->json([
            'mensaje' => "Metas de '{$configuracion->universidad}' actualizadas correctamente.",
            'data' => $configuracion
        ], 200);
    }

    // Eliminar una sede
    public function destroy(Request $request, $id)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        $configuracion = Configuracion::findOrFail($id);

        if ($configuracion->equipo_id !== $userAuth->equipo_id && $userAuth->role !== 'pastor') {
            return response()->json(['error' => 'No tienes permiso para eliminar esto.'], 403);
        }

        $nombre = $configuracion->universidad;
        $configuracion->delete();

        return response()->json([
            'mensaje' => "La sede '{$nombre}' ha sido eliminada."
        ], 200);
    }
}