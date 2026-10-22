<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Libro;
use App\Models\User;
use App\Models\Historial;
use Illuminate\Support\Facades\DB;

class LibroController extends Controller
{
    // Filtrar libros por equipo para la WEB
    public function indexWeb(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $query = Libro::query();
        
        if (in_array($userAuth->role, ['contador', 'lider', 'co-lider'])) {
            $query->where('equipo_id', $userAuth->equipo_id);
        } elseif ($userAuth->role === 'pastor') {
            $equiposIds = \App\Models\Equipo::where('campo', $userAuth->campo)->pluck('id');
            $query->whereIn('equipo_id', $equiposIds);
        }

        $libros = $query->get();
        return response()->json($libros, 200);
    }

    // Registrar nuevo libro
    public function store(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $datosValidados = $request->validate([
            'titulo' => 'required|string|max:255',
            'imagen_referencial' => 'nullable|string',
            'precio_costo' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'total_recibidos' => 'nullable|integer|min:0',
        ]);

        $datosValidados['total_recibidos'] = $datosValidados['total_recibidos'] ?? 0;
        $datosValidados['stock_reserva'] = $datosValidados['total_recibidos'];
        $datosValidados['total_entregados'] = 0;
        $datosValidados['equipo_id'] = $userAuth->equipo_id;

        $libro = Libro::create($datosValidados);

        // PASAMOS EL USUARIO A LA FUNCIÓN
        $this->registrarHistorial($userAuth, 'Creación', "Se registró el libro: {$libro->titulo}");

        return response()->json(['mensaje' => 'Libro registrado exitosamente.', 'data' => $libro], 201);
    }

    // Editar libro
    public function update(Request $request, $id)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        $libro = Libro::findOrFail($id);

        $datosValidados = $request->validate([
            'titulo' => 'required|string|max:255',
            'imagen_referencial' => 'nullable|string',
            'precio_costo' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
        ]);

        $libro->update($datosValidados);

        // PASAMOS EL USUARIO A LA FUNCIÓN
        $this->registrarHistorial($userAuth, 'Edición', "Se editó el libro: {$libro->titulo}");

        return response()->json(['mensaje' => 'Libro actualizado correctamente.', 'data' => $libro], 200);
    }

    // Aumentar inventario de forma segura
    public function aumentarStock(Request $request, $id)
    {   
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        $request->validate([
            'cantidad' => 'required|integer|min:1'
        ]);

        $libro = Libro::findOrFail($id);
        $libro->increment('total_recibidos', $request->cantidad);
        $libro->increment('stock_reserva', $request->cantidad);

        // PASAMOS EL USUARIO A LA FUNCIÓN
        $this->registrarHistorial($userAuth, 'Ingreso de Inventario', "Se agregaron {$request->cantidad} ejemplares al libro: {$libro->titulo}");

        return response()->json(['mensaje' => 'Stock aumentado correctamente.', 'data' => $libro], 200);
    }

    // Activar/Desactivar libro
    public function toggleActive(Request $request, $id) // AÑADIDO Request $request A LA FIRMA
    {   
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        $libro = Libro::findOrFail($id);
        $libro->is_active = !$libro->is_active;
        $libro->save();

        $estado = $libro->is_active ? 'Activado' : 'Desactivado';
        
        // PASAMOS EL USUARIO A LA FUNCIÓN
        $this->registrarHistorial($userAuth, 'Cambio de Estado', "El libro {$libro->titulo} fue {$estado}.");

        return response()->json(['mensaje' => "Libro {$estado} correctamente.", 'data' => $libro], 200);
    }

    public function asignar(Request $request)
    {
        return $this->procesarMovimiento($request, 'asignacion');
    }

    public function devolver(Request $request)
    {
        return $this->procesarMovimiento($request, 'devolucion');
    }

    private function procesarMovimiento(Request $request, $tipo)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'libro_id' => 'required|exists:libros,id',
            'cantidad' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            $libro = Libro::findOrFail($request->libro_id);
            $colportor = User::findOrFail($request->user_id);
            $cantidad = $request->cantidad;

            if ($tipo === 'asignacion') {
                if ($libro->stock_reserva < $cantidad) {
                    return response()->json(['error' => 'Stock insuficiente en almacén.'], 400);
                }
                $libro->decrement('stock_reserva', $cantidad);
                $libro->increment('total_entregados', $cantidad);
                $colportor->increment('stock_actual', $cantidad); 
                $accion = 'Asignación';
                $detalle = "Se asignaron {$cantidad} ejemplares de '{$libro->titulo}' a {$colportor->name}.";
            } else {
                $libro->increment('stock_reserva', $cantidad);
                $libro->decrement('total_entregados', $cantidad);
                $colportor->decrement('stock_actual', $cantidad);
                $accion = 'Devolución';
                $detalle = "El colportor {$colportor->name} devolvió {$cantidad} ejemplares de '{$libro->titulo}'.";
            }

            // PASAMOS EL USUARIO A LA FUNCIÓN
            $this->registrarHistorial($userAuth, $accion, $detalle);

            DB::commit();
            return response()->json(['mensaje' => 'Movimiento registrado correctamente.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al procesar el movimiento', 'detalle' => $e->getMessage()], 500);
        }
    }

    public function gestionAces(Request $request, $id)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        $request->validate([
            'cantidad' => 'required|integer|min:1',
            'tipo_operacion' => 'required|in:ingreso,devolucion'
        ]);

        try {
            DB::beginTransaction(); 

            $libro = Libro::findOrFail($id);
            $cantidad = $request->cantidad;

            if ($request->tipo_operacion === 'ingreso') {
                $libro->increment('total_recibidos', $cantidad);
                $libro->increment('stock_reserva', $cantidad);
                $this->registrarHistorial($userAuth, 'Ingreso de ACES', "Se recibieron {$cantidad} ejemplares de: {$libro->titulo}");
            } else {
                if ($libro->stock_reserva < $cantidad) {
                    return response()->json(['error' => "No puede devolver a ACES más libros de los que tiene en reserva ({$libro->stock_reserva})."], 400);
                }
                $libro->decrement('total_recibidos', $cantidad);
                $libro->decrement('stock_reserva', $cantidad);
                $this->registrarHistorial($userAuth, 'Devolución a ACES', "Se devolvieron {$cantidad} ejemplares de: {$libro->titulo} a la editorial.");
            }

            DB::commit(); 
            return response()->json(['mensaje' => 'Inventario actualizado correctamente.', 'data' => $libro], 200);

        } catch (\Exception $e) {
            DB::rollBack(); 
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500); 
        }
    }

    // --- FUNCIÓN AÑADIDA ---
    private function registrarHistorial($user, $accion, $texto)
    {
        if ($user) {
            Historial::create([
                'user_id' => $user->id, 
                'equipo_id' => $user->equipo_id, 
                'accion' => $accion,
                'detalles' => $texto, 
            ]);
        }
    }
}