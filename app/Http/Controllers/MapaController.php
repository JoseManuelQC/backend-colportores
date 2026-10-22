<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Referencia;
use App\Models\Recorrido;
use Illuminate\Support\Facades\Storage;

class MapaController extends Controller
{
    // --- 1. RUTAS Y TRACKING GPS ---

    // A. Iniciar el recorrido (Crea la fila en la BD)
    public function iniciarRecorrido(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');
        $colportor = User::find($userId);

        $recorrido = Recorrido::create([
            'user_id' => $colportor->id,
            'equipo_id' => $colportor->equipo_id,
            'fecha_inicio' => now(),
            'coordenadas' => [] // Arreglo JSON vacío al inicio
        ]);

        return response()->json(['recorrido_id' => $recorrido->id], 201);
    }

    // B. Actualizar recorrido (Flutter enviará paquetes de coordenadas cada ciertos metros)
    public function actualizarRecorrido(Request $request, $id)
    {
        $request->validate([
            'coordenadas' => 'required|array' // Ej: [{"lat": -15.49, "lng": -70.13}, ...]
        ]);

        $recorrido = Recorrido::findOrFail($id);

        // Unimos las coordenadas anteriores con las nuevas que acaban de llegar
        $coordsActuales = $recorrido->coordenadas ?? [];
        $nuevasCoords = array_merge($coordsActuales, $request->coordenadas);

        $recorrido->update(['coordenadas' => $nuevasCoords]);

        return response()->json(['mensaje' => 'Coordenadas sincronizadas']);
    }

    // C. Finalizar recorrido (Cierra el cronómetro)
    public function finalizarRecorrido($id)
    {
        $recorrido = Recorrido::findOrFail($id);
        $recorrido->update(['fecha_fin' => now()]);

        return response()->json(['mensaje' => 'Recorrido finalizado']);
    }


    // --- 2. REFERENCIAS Y PUNTOS DE INTERÉS ---

    // Crear una referencia (Soporta imágenes)
    public function crearReferencia(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');
        $colportor = User::find($userId);

        $request->validate([
            'tipo' => 'required|in:hogar_adventista,ayuda_misionera,iglesia_adventista',
            'descripcion' => 'nullable|string',
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
            // La foto es opcional, debe ser imagen y pesar máximo 2MB para no saturar el servidor
            'foto' => 'nullable|image|max:2048'
        ]);

        $path = null;
        if ($request->hasFile('foto')) {
            // Guarda la foto en la carpeta "storage/app/public/referencias"
            $path = $request->file('foto')->store('referencias', 'public');
        }

        $referencia = Referencia::create([
            'user_id' => $colportor->id,
            'equipo_id' => $colportor->equipo_id,
            'tipo' => $request->tipo,
            'descripcion' => $request->descripcion,
            'latitud' => $request->latitud,
            'longitud' => $request->longitud,
            'foto_path' => $path
        ]);

        return response()->json(['mensaje' => 'Referencia guardada', 'data' => $referencia], 201);
    }

    // Obtener todas las referencias (casitas, iglesias, etc.)
    public function obtenerReferencias()
    {
        try {
            // Traemos todas las referencias.
            // Si deseas filtrar por el usuario logueado: Referencia::where('colportor_id', auth()->id())->get();
            $referencias = \App\Models\Referencia::all();

            return response()->json(['data' => $referencias], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener referencias: ' . $e->getMessage()], 500);
        }
    }

    // Obtener todo el historial de rutas
    // Obtener todo el historial de rutas
    public function obtenerRecorridos()
    {
        try {
            // Como 'coordenadas' es un arreglo JSON en la misma tabla,
            // no usamos with(), simplemente traemos todos los registros.
            $recorridos = \App\Models\Recorrido::all();

            return response()->json(['data' => $recorridos], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener recorridos: ' . $e->getMessage()], 500);
        }
    }


}
