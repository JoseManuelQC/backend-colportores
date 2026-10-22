<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cobro;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class CobroController extends Controller
{
    // 1. Registrar un nuevo pago/cuota
    public function store(Request $request)
    {
        $request->validate([
            'venta_id' => 'required|exists:ventas,id',
            'monto' => 'required|numeric|min:0.1',
            'fecha_pago' => 'required|date',
            'metodo_pago' => 'required|in:efectivo,yape,transferencia,otro',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            $venta = Venta::findOrFail($request->venta_id);

            // Evitar que se pague más del saldo pendiente
            $saldoPendiente = $venta->saldo_pendiente;
            if ($request->monto > $saldoPendiente) {
                return response()->json([
                    'error' => 'El monto supera el saldo pendiente',
                    'saldo_pendiente' => $saldoPendiente
                ], 400);
            }

            // Registrar el cobro
            $cobro = Cobro::create([
                'venta_id' => $venta->id,
                'monto' => $request->monto,
                'fecha_pago' => $request->fecha_pago,
                'metodo_pago' => $request->metodo_pago,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
            ]);

            // Verificar si con este pago la deuda queda saldada
            if (($saldoPendiente - $request->monto) <= 0) {
                $venta->update(['estado_pago' => 'pagado']);
            }

            DB::commit();

            return response()->json([
                'mensaje' => 'Cobro registrado correctamente',
                'data' => $cobro,
                'saldo_restante' => $venta->saldo_pendiente // Usa el atributo calculado
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al registrar el cobro', 'detalle' => $e->getMessage()], 500);
        }
    }

    // 2. Obtener la lista de clientes que le deben a un colportor específico (Cartera por cobrar)
    public function pendientesPorColportor($user_id)
    {
        $ventasPendientes = Venta::where('user_id', $user_id)
            ->where('estado_pago', 'credito')
            ->with(['detalles.libro', 'cobros']) // Traemos detalles y pagos previos
            ->orderBy('created_at', 'asc')
            ->get();

        // Mapeamos la respuesta para enviar datos limpios a la app móvil
        $cartera = $ventasPendientes->map(function ($venta) {
            return [
                'venta_id' => $venta->id,
                'cliente' => $venta->nombre_cliente,
                'fecha_venta' => $venta->created_at->format('Y-m-d'),
                'fechas_cobro_acordadas' => $venta->fechas_cobro,
                'precio_total' => $venta->precio_final,
                'monto_pagado' => $venta->monto_pagado,
                'saldo_pendiente' => $venta->saldo_pendiente,
                'latitud_venta' => $venta->latitud,
                'longitud_venta' => $venta->longitud
            ];
        });

        return response()->json($cartera, 200);
    }
}