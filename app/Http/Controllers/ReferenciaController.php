<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Referencia;

class ReferenciaController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validar los datos exactos que envía Flutter
        $datosValidados = $request->validate([
            'user_id' => 'required|exists:users,id', // Verifica que el colportor exista
            'latitud' => 'required|numeric', // Coordenadas del mapa[cite: 2]
            'longitud' => 'required|numeric',
            'tipo_referencia' => 'required|array', // Valida el arreglo (ej. ["Hogar Adventista"])[cite: 1, 2]
            'foto_fachada' => 'nullable|string', // Opcional por respeto a la privacidad[cite: 2]
            'descripcion_textual' => 'nullable|string' // Ej: Casa azul, portón verde[cite: 2]
        ]);

        // 2. Guardar en PostgreSQL
        $referencia = Referencia::create($datosValidados);

        // 3. Responder a la aplicación móvil
        return response()->json([
            'mensaje' => 'Referencia guardada exitosamente en el mapa.',
            'data' => $referencia
        ], 201);
    }

    public function index()
{
    // Retorna todas las referencias registradas para pintarlas en el mapa
    $referencias = Referencia::all();
    return response()->json($referencias, 200);
}

}