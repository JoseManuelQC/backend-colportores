<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Libro;
use App\Models\Equipo;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->header('X-Usuario-Id');
        $userAuth = User::find($userId);

        if (!$userAuth) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $equipoId = $userAuth->equipo_id;

        // 1. Datos del Equipo y Misión
        $equipo = Equipo::find($equipoId);

        // 2. KPIs (Tarjetas Principales)
        $colportores = User::where('equipo_id', $equipoId)->where('role', 'colportor')->get();
        $colportoresActivos = $colportores->where('is_active', true)->count();
        $totalRecaudado = $colportores->sum('total_recaudado');
        $dineroEnLaCalle = $colportores->sum('deuda_actual'); // Deuda total de los colportores

        $libros = Libro::where('equipo_id', $equipoId)->get();
        $librosTotales = $libros->sum('total_recibidos');
        $stockReserva = $libros->sum('stock_reserva');

        // Deuda ACES = (Total de libros recibidos * su precio de costo) - Total Recaudado en caja
        // (Esto asume que el dinero recaudado es para pagarle a ACES)
        $valorTotalMercaderia = $libros->sum(function($libro) {
            return $libro->total_recibidos * $libro->precio_costo;
        });
        $deudaAces = max(0, $valorTotalMercaderia - $totalRecaudado);

        // 3. Ranking: Top 3 Colportores con más depósitos
        $topColportores = User::where('equipo_id', $equipoId)
            ->where('role', 'colportor')
            ->orderByDesc('total_recaudado')
            ->take(3)
            ->get(['name', 'total_recaudado', 'ganancia_actual']);

        // 4. Libros más solicitados (Gráfico de barras nativo)
        $librosPopulares = Libro::where('equipo_id', $equipoId)
            ->orderByDesc('total_entregados')
            ->take(4)
            ->get(['titulo', 'total_entregados']);

        return response()->json([
            'mision' => $equipo ? $equipo->campo : 'Sin Misión',
            'equipo_nombre' => $equipo ? $equipo->nombre : 'Sin Equipo',
            'kpis' => [
                'colportores_activos' => $colportoresActivos,
                'total_recaudado' => $totalRecaudado,
                'dinero_en_calle' => $dineroEnLaCalle,
                'deuda_aces' => $deudaAces,
                'libros_totales' => $librosTotales,
                'stock_reserva' => $stockReserva
            ],
            'top_colportores' => $topColportores,
            'libros_populares' => $librosPopulares
        ], 200);
    }
}
