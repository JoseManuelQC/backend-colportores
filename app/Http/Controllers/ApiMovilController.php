<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Libro;
use App\Models\Historial;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Cuota;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ApiMovilController extends Controller
{
    // 1. Login actualizado para enviar métricas al Perfil (Flutter)
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $colportor = User::with(['equipo', 'inventario'])->where('email', $request->email)->first();

        if (!$colportor || !Hash::check($request->password, $colportor->password)) {
            return response()->json(['error' => 'Credenciales incorrectas.'], 401);
        }

        if ($colportor->role !== 'colportor') {
            return response()->json(['error' => 'Acceso denegado. App exclusiva para colportores.'], 403);
        }

        // Calculamos ventas totales realizadas por este colportor
        $ventasTotales = Venta::where('user_id', $colportor->id)->count();

        return response()->json([
            'mensaje' => 'Bienvenido',
            'colportor' => [
                'id' => $colportor->id,
                'nombre' => $colportor->name,
                'equipo' => $colportor->equipo ? $colportor->equipo->nombre : 'Sin equipo',
                'deuda_actual' => $colportor->deuda_actual,
                'ganancia_actual' => $colportor->ganancia_actual,
                'total_recaudado' => $colportor->total_recaudado,
                'ventas_totales' => $ventasTotales,
                'libros_en_maleta' => $colportor->stock_actual,
                'inventario' => $colportor->inventario
            ]
        ], 200);
    }

    // 2. Motor de Ventas (Mini-CRM)
    public function registrarVenta(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');
        $colportor = User::find($userId);

        if (!$colportor || $colportor->role !== 'colportor') {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // Validamos la estructura compleja que enviará Flutter
        $request->validate([
            'cliente.nombre_completo' => 'required|string|max:255',
            'cliente.dni' => 'nullable|string',
            'cliente.telefono' => 'nullable|string',
            'cliente.direccion' => 'nullable|string',

            'libros' => 'required|array|min:1',
            'libros.*.libro_id' => 'required|exists:libros,id',
            'libros.*.cantidad' => 'required|integer|min:1',
            'libros.*.precio_unitario' => 'required|numeric|min:0',

            'pago.tipo_pago' => 'required|in:contado,cuotas',
            'pago.cuotas' => 'required|array|min:1',
            'pago.cuotas.*.numero_cuota' => 'required|integer|min:1',
            'pago.cuotas.*.monto_esperado' => 'required|numeric|min:0',
            'pago.cuotas.*.fecha_vencimiento' => 'required|date',
            'pago.cuotas.*.estado' => 'required|in:pendiente,pagado',
            'pago.cuotas.*.monto_pagado' => 'nullable|numeric|min:0',

            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            // A. Gestionar Cliente (Lo busca por DNI en el mismo equipo, si no, lo crea)
            $clienteData = $request->cliente;
            $cliente = null;

            if (!empty($clienteData['dni'])) {
                $cliente = Cliente::where('dni', $clienteData['dni'])
                    ->where('equipo_id', $colportor->equipo_id)
                    ->first();
            }

            if (!$cliente) {
                $cliente = Cliente::create([
                    'equipo_id' => $colportor->equipo_id,
                    'nombre_completo' => $clienteData['nombre_completo'],
                    'dni' => $clienteData['dni'] ?? null,
                    'telefono' => $clienteData['telefono'] ?? null,
                    'direccion' => $clienteData['direccion'] ?? null,
                ]);
            }

            // B. Crear Cabecera de Venta
            $venta = Venta::create([
                'user_id' => $colportor->id,
                'cliente_id' => $cliente->id,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'precio_final' => 0, // Se recalcula en el paso C
                'tipo_pago' => $request->pago['tipo_pago'],
                'estado_pago' => 'pendiente'
            ]);

            // C. Procesar Libros (Detalles) e Inventario
            $precioTotal = 0;
            $cantidadTotalLibros = 0;

            foreach ($request->libros as $item) {
                $libro = Libro::findOrFail($item['libro_id']);
                $cantidadVendida = $item['cantidad'];

                // Verificar stock en maleta pivote
                $registroPivot = $colportor->inventario()->where('libro_id', $libro->id)->first();
                if (!$registroPivot || $registroPivot->pivot->cantidad < $cantidadVendida) {
                    throw new \Exception("Stock insuficiente para el libro: " . $libro->titulo);
                }

                // Descontar maleta
                $nuevaCantidad = $registroPivot->pivot->cantidad - $cantidadVendida;
                if ($nuevaCantidad == 0) {
                    $colportor->inventario()->detach($libro->id);
                } else {
                    $colportor->inventario()->updateExistingPivot($libro->id, ['cantidad' => $nuevaCantidad]);
                }

                // Crear detalle
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'libro_id' => $libro->id,
                    'cantidad' => $cantidadVendida,
                    'precio_unitario' => $item['precio_unitario']
                ]);

                $precioTotal += ($cantidadVendida * $item['precio_unitario']);
                $cantidadTotalLibros += $cantidadVendida;
            }

            // Actualizar total venta y métricas colportor
            $venta->update(['precio_final' => $precioTotal]);
            $colportor->decrement('stock_actual', $cantidadTotalLibros);

            // D. Procesar Plan de Pagos (Cuotas / Contado)
            $todasPagadas = true;
            foreach ($request->pago['cuotas'] as $cuotaReq) {
                Cuota::create([
                    'venta_id' => $venta->id,
                    'numero_cuota' => $cuotaReq['numero_cuota'],
                    'monto_esperado' => $cuotaReq['monto_esperado'],
                    'fecha_vencimiento' => $cuotaReq['fecha_vencimiento'],
                    'estado' => $cuotaReq['estado'],
                    'monto_pagado' => $cuotaReq['estado'] === 'pagado' ? ($cuotaReq['monto_pagado'] ?? $cuotaReq['monto_esperado']) : null,
                    'fecha_pago_real' => $cuotaReq['estado'] === 'pagado' ? date('Y-m-d') : null,
                    'latitud' => $cuotaReq['estado'] === 'pagado' ? $request->latitud : null,
                    'longitud' => $cuotaReq['estado'] === 'pagado' ? $request->longitud : null,
                ]);

                if ($cuotaReq['estado'] === 'pendiente') {
                    $todasPagadas = false;
                }
            }

            if ($todasPagadas) {
                $venta->update(['estado_pago' => 'pagado']);
            }

            // E. Historial
            Historial::create([
                'user_id' => $colportor->id,
                'equipo_id' => $colportor->equipo_id,
                'accion' => 'Venta Realizada (App)',
                'detalles' => "Vendió {$cantidadTotalLibros} libros a {$cliente->nombre_completo} por S/ {$precioTotal}."
            ]);

            DB::commit();
            return response()->json(['mensaje' => 'Venta estructurada registrada con éxito.', 'venta_id' => $venta->id], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al registrar venta.', 'detalle' => $e->getMessage()], 500);
        }
    }

    // 3. Ver historial de ventas propio detallado
    public function misVentas(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');

        $ventas = Venta::where('user_id', $userId)
            ->with(['cliente', 'detalles.libro', 'cuotas'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($ventas, 200);
    }

    // 4. Registrar el pago de una cuota en la calle
    public function cobrarCuota(Request $request, $id)
    {
        $userId = $request->header('X-Usuario-Id');
        $colportor = User::find($userId);

        $request->validate([
            'metodo_pago' => 'required|in:efectivo,yape,plin,transferencia,otro',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            $cuota = Cuota::findOrFail($id);
            $venta = Venta::findOrFail($cuota->venta_id);

            // Verificamos que esta venta pertenezca a este colportor
            if ($venta->user_id !== $colportor->id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            if ($cuota->estado === 'pagado') {
                return response()->json(['error' => 'Esta cuota ya ha sido pagada.'], 400);
            }

            // 1. Actualizamos la cuota
            $cuota->update([
                'estado' => 'pagado',
                'monto_pagado' => $cuota->monto_esperado,
                'fecha_pago_real' => date('Y-m-d'),
                'metodo_pago' => $request->metodo_pago,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
            ]);

            // 2. Revisamos si con este pago la venta completa ya se canceló
            $cuotasPendientes = Cuota::where('venta_id', $venta->id)->where('estado', 'pendiente')->count();
            if ($cuotasPendientes === 0) {
                $venta->update(['estado_pago' => 'pagado']);
            }

            // 3. Dejamos rastro en el historial para el Contador
            Historial::create([
                'user_id' => $colportor->id,
                'equipo_id' => $colportor->equipo_id,
                'accion' => 'Cobro en Calle (App)',
                'detalles' => "Cobró S/ {$cuota->monto_esperado} de la Cuota #{$cuota->numero_cuota} (Venta #{$venta->id}) vía {$request->metodo_pago}."
            ]);

            DB::commit();
            return response()->json(['mensaje' => 'Cobro registrado correctamente.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al registrar cobro.', 'detalle' => $e->getMessage()], 500);
        }
    }


}
