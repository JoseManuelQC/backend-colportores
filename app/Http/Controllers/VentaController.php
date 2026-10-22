<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\User;
use App\Models\Libro;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'estado_visita' => 'required|in:venta,interesado,no_atendio',
            'nombre_cliente' => 'nullable|string',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'productos' => 'required_if:estado_visita,venta|array',
            'productos.*.libro_id' => 'required_if:estado_visita,venta|exists:libros,id',
            'productos.*.cantidad' => 'required_if:estado_visita,venta|integer|min:1',
            'productos.*.precio_unitario' => 'required_if:estado_visita,venta|numeric|min:0',
            'fechas_cobro' => 'nullable|array',
            'estado_pago' => 'nullable|in:pagado,credito'
        ]);

        try {
            DB::beginTransaction();

            $precioTotalVenta = 0;
            $estadoPago = $request->estado_pago ?? 'pagado';

            // 1. Guardar la cabecera (Visita/Venta) con datos GPS
            $venta = Venta::create([
                'user_id' => $request->user_id,
                'nombre_cliente' => $request->nombre_cliente,
                'estado_visita' => $request->estado_visita,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'fechas_cobro' => $request->fechas_cobro,
                'estado_pago' => $estadoPago,
                'precio_final' => 0 // Se actualiza luego si hay productos
            ]);

            // 2. Procesar productos si es una venta concretada
            if ($request->estado_visita === 'venta' && $request->has('productos')) {
                $totalLibrosVendidosCantidad = 0;

                foreach ($request->productos as $item) {
                    $subtotal = $item['cantidad'] * $item['precio_unitario'];
                    $precioTotalVenta += $subtotal;

                    // Insertar detalle
                    VentaDetalle::create([
                        'venta_id' => $venta->id,
                        'libro_id' => $item['libro_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio_unitario'],
                    ]);

                    // Actualizar inventario global del libro
                    $libro = Libro::find($item['libro_id']);
                    $libro->decrement('stock_reserva', $item['cantidad']);
                    $libro->increment('total_entregados', $item['cantidad']);

                    $totalLibrosVendidosCantidad += $item['cantidad'];
                }

                // Actualizar total de la venta
                $venta->update(['precio_final' => $precioTotalVenta]);

                // 3. Actualizar métricas del colportor
                $colportor = User::find($request->user_id);
                if ($estadoPago === 'credito') {
                    $colportor->increment('deuda_actual', $precioTotalVenta);
                }
                $colportor->increment('libros_vendidos', $totalLibrosVendidosCantidad);
                $colportor->decrement('stock_actual', $totalLibrosVendidosCantidad);
            }

            DB::commit();

            return response()->json([
                'mensaje' => 'Registro procesado exitosamente',
                'data' => $venta->load('detalles.libro')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al guardar el registro', 'detalle' => $e->getMessage()], 500);
        }
    }

    public function misVentas($user_id)
    {
        $ventas = Venta::where('user_id', $user_id)
            ->with('detalles.libro')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($ventas, 200);
    }
}