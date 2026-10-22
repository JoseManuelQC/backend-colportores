<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Libro;
use App\Models\Historial;
use Illuminate\Support\Facades\DB;

class ColportorDashboardController extends Controller
{
    // Función auxiliar para registrar en el historial con el equipo_id correcto
    private function registrarHistorial($request, $accion, $detalles)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if ($userAuth) {
            Historial::create([
                'user_id' => $userAuth->id,
                'equipo_id' => $userAuth->equipo_id,
                'accion' => $accion,
                'detalles' => $detalles,
            ]);
        }
    }

    // Obtener todo el resumen de un colportor específico para su página de detalle
    public function getDetalle($id)
    {
        $colportor = User::with([
            'inventario', // Trae los libros y las cantidades pivote
            'ventas.detalles.libro' // Trae sus ventas y qué libros vendió
        ])->findOrFail($id);

        return response()->json($colportor, 200);
    }

    // Asignar libros a la maleta de este colportor
    public function asignarLibros(Request $request, $id)
    {
        $request->validate([
            'libro_id' => 'required|exists:libros,id',
            'cantidad' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            $colportor = User::findOrFail($id);
            $libro = Libro::findOrFail($request->libro_id);
            $cantidad = $request->cantidad;

            if ($libro->stock_reserva < $cantidad) {
                return response()->json(['error' => 'No hay suficiente stock en reserva.'], 400);
            }

            // Forzamos conversión a float para evitar que PHP lo trate como texto y devuelva 0
            $valorMercaderia = $cantidad * (float) $libro->precio_costo;

            $libro->decrement('stock_reserva', $cantidad);
            $libro->increment('total_entregados', $cantidad);

            $colportor->increment('stock_actual', $cantidad);
            $colportor->increment('libros_asignados', $cantidad);
            $colportor->increment('deuda_actual', $valorMercaderia);

            $registroPivot = $colportor->inventario()->where('libro_id', $libro->id)->first();

            if ($registroPivot) {
                $nuevaCantidad = $registroPivot->pivot->cantidad + $cantidad;
                $colportor->inventario()->updateExistingPivot($libro->id, ['cantidad' => $nuevaCantidad]);
            } else {
                $colportor->inventario()->attach($libro->id, ['cantidad' => $cantidad]);
            }

            // Usamos la nueva función para registrar el historial de manera segura
            $this->registrarHistorial(
                $request,
                'Asignación a Colportor',
                "Se entregaron {$cantidad} ejemplares de '{$libro->titulo}' a {$colportor->name}."
            );

            DB::commit();
            return response()->json(['mensaje' => 'Libros asignados correctamente.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al asignar libros: ' . $e->getMessage()], 500);
        }
    }

    public function devolverLibros(Request $request, $id)
    {
        $request->validate([
            'libro_id' => 'required|exists:libros,id',
            'cantidad' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            $colportor = User::findOrFail($id);
            $libro = Libro::findOrFail($request->libro_id);
            $cantidadDevuelta = $request->cantidad;

            $registroPivot = $colportor->inventario()->where('libro_id', $libro->id)->first();

            if (!$registroPivot || $registroPivot->pivot->cantidad < $cantidadDevuelta) {
                return response()->json(['error' => 'El colportor no tiene suficientes ejemplares de este libro para devolver.'], 400);
            }

            $valorMercaderia = $cantidadDevuelta * (float) $libro->precio_costo;

            // 1. Ajustes del libro en almacén central
            $libro->increment('stock_reserva', $cantidadDevuelta);
            $libro->decrement('total_entregados', $cantidadDevuelta);

            // 2. Ajustes matemáticos del Colportor (Sin doble resta)
            $colportor->stock_actual = max(0, $colportor->stock_actual - $cantidadDevuelta);
            $colportor->libros_asignados = max(0, $colportor->libros_asignados - $cantidadDevuelta);

            // 3. Lógica de Bono / Saldo a Favor
            $nuevaDeuda = $colportor->deuda_actual - $valorMercaderia;

            if ($nuevaDeuda < 0) {
                // Devolvió mercadería que ya estaba pagada
                $saldoAFavor = abs($nuevaDeuda);
                $colportor->deuda_actual = 0; // La deuda nunca es negativa
                $colportor->ganancia_actual += $saldoAFavor; // Se convierte en su Bono
            } else {
                $colportor->deuda_actual = $nuevaDeuda;
            }

            $colportor->save(); // Guardamos todos los cálculos de una sola vez

            // 4. Ajuste de la maleta (Pivot)
            $nuevaCantidad = $registroPivot->pivot->cantidad - $cantidadDevuelta;
            if ($nuevaCantidad == 0) {
                $colportor->inventario()->detach($libro->id);
            } else {
                $colportor->inventario()->updateExistingPivot($libro->id, ['cantidad' => $nuevaCantidad]);
            }

            // Usamos la nueva función para registrar el historial
            $this->registrarHistorial(
                $request,
                'Devolución de Colportor',
                "{$colportor->name} devolvió {$cantidadDevuelta} ejemplares de '{$libro->titulo}' al almacén."
            );

            DB::commit();
            return response()->json(['mensaje' => 'Libros devueltos correctamente.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al devolver libros: ' . $e->getMessage()], 500);
        }
    }

    // Registrar un pago (abono) del colportor a la oficina
    public function registrarAbono(Request $request, $id)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.1',
            'metodo_pago' => 'required|in:Efectivo,Yape,Plin,Transferencia,Otro',
            'referencia' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $colportor = User::findOrFail($id);
            $montoAbono = (float) $request->monto;

            // 1. Aumentar el total de dinero recaudado por la oficina
            $colportor->increment('total_recaudado', $montoAbono);

            // 2. Lógica de reducción de deuda y cálculo de saldo a favor
            $nuevaDeuda = $colportor->deuda_actual - $montoAbono;

            if ($nuevaDeuda < 0) {
                // Pagó más de su deuda, el exceso es saldo a favor
                $saldoAFavor = abs($nuevaDeuda);
                $colportor->deuda_actual = 0; // La deuda se salda a 0
                $colportor->ganancia_actual += $saldoAFavor; // El exceso se vuelve bono
            } else {
                $colportor->deuda_actual = $nuevaDeuda;
            }

            $colportor->save();

            // Usamos la nueva función para registrar el historial
            $refTexto = $request->referencia ? " (Ref: {$request->referencia})" : "";
            $this->registrarHistorial(
                $request,
                'Abono Recibido',
                "Se recibió un abono de S/ {$montoAbono} de {$colportor->name} vía {$request->metodo_pago}{$refTexto}."
            );

            DB::commit();
            return response()->json(['mensaje' => 'Abono registrado correctamente.', 'data' => $colportor], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al registrar el abono: ' . $e->getMessage()], 500);
        }
    }
}
