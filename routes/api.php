<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ReferenciaController;
use App\Http\Controllers\LibroController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\CobroController;
use App\Http\Controllers\ColportorDashboardController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\ApiMovilController;
use App\Http\Controllers\MapaController;

// --- GESTIÓN DE EQUIPOS (PASTORES) ---
Route::get('/equipos', [EquipoController::class, 'index']);
Route::post('/equipos', [EquipoController::class, 'store']);
Route::put('/equipos/{id}', [EquipoController::class, 'update']);
Route::get('/equipos/{id}/personal', [EquipoController::class, 'getPersonal']);

// --- AUTENTICACIÓN ---
Route::post('/login', [UserController::class, 'login']);

// --- USUARIOS Y COLPORTORES ---
Route::get('/colportores', [UserController::class, 'index']);
Route::post('/colportores/nuevo', [UserController::class, 'store']);
Route::patch('/colportores/{id}/estado', [UserController::class, 'toggleActive']);
Route::get('/admins', [UserController::class, 'admins']);
Route::put('/colportores/{id}', [UserController::class, 'update']);
Route::delete('/colportores/{id}', [UserController::class, 'destroy']);

// --- LIBROS E INVENTARIO WEB/APP ---
Route::get('/libros', [LibroController::class, 'index']); // Para la App Móvil (solo activos)
Route::get('/web/libros', [LibroController::class, 'indexWeb']); // Para la Web (Contadora)
Route::post('/libros/nuevo', [LibroController::class, 'store']);
Route::put('/libros/{id}', [LibroController::class, 'update']);
Route::delete('/libros/{id}', [LibroController::class, 'destroy']);
Route::patch('/libros/{id}/estado', [LibroController::class, 'toggleActive']);
// Agrega esta línea en el bloque de libros
Route::patch('/libros/{id}/aumentar-stock', [LibroController::class, 'aumentarStock']);
Route::post('/libros/{id}/gestion-aces', [LibroController::class, 'gestionAces']);

// --- CONFIGURACIONES (SEDES Y METAS) ---
Route::get('/configuraciones', [ConfiguracionController::class, 'index']);
Route::post('/configuraciones', [ConfiguracionController::class, 'store']);
Route::put('/configuraciones/{id}', [ConfiguracionController::class, 'update']);
Route::delete('/configuraciones/{id}', [ConfiguracionController::class, 'destroy']);


// --- DASHBOARD ESPECÍFICO DEL COLPORTOR ---
Route::get('/colportores/{id}/detalle', [ColportorDashboardController::class, 'getDetalle']);
Route::post('/colportores/{id}/asignar', [ColportorDashboardController::class, 'asignarLibros']);
Route::post('/colportores/{id}/devolver', [ColportorDashboardController::class, 'devolverLibros']);
Route::post('/colportores/{id}/abono', [ColportorDashboardController::class, 'registrarAbono']);

// --- MOVIMIENTOS DE ALMACÉN ---
Route::post('/inventario/asignar', [LibroController::class, 'asignar']);
Route::post('/inventario/devolver', [LibroController::class, 'devolver']);


// --- VISITAS Y VENTAS ---
Route::post('/ventas/nueva', [VentaController::class, 'store']);
Route::get('/colportores/{user_id}/ventas', [VentaController::class, 'misVentas']);

// --- MAPAS Y RUTAS ---
Route::post('/referencias/nueva', [ReferenciaController::class, 'store']);
Route::get('/referencias', [ReferenciaController::class, 'index']);

// --- CONFIGURACIONES Y LOGS ---
Route::get('/configuraciones', [ConfiguracionController::class, 'index']);
Route::post('/configuraciones/guardar', [ConfiguracionController::class, 'store']);
Route::get('/historial', [HistorialController::class, 'index']);

// --- COBRANZAS Y CUOTAS ---
// Registrar un pago (cuota)
Route::post('/cobros/nuevo', [CobroController::class, 'store']);
Route::get('/dashboard', [DashboardController::class, 'index']);

// Ver clientes que aún le deben dinero a un colportor específico (útil para que la app pinte la ruta del día)
Route::get('/colportores/{user_id}/cobros-pendientes', [CobroController::class, 'pendientesPorColportor']);


// ==========================================
// RUTAS EXCLUSIVAS PARA LA APP MÓVIL (FLUTTER)
// ==========================================
Route::post('/movil/login', [ApiMovilController::class, 'login']);
Route::post('/movil/ventas', [ApiMovilController::class, 'registrarVenta']);
Route::get('/movil/mis-ventas', [ApiMovilController::class, 'misVentas']);
Route::post('/movil/cuotas/{id}/pagar', [ApiMovilController::class, 'cobrarCuota']);


// ... (tus rutas anteriores)

// Rutas para el Mapa y GPS
Route::post('/movil/recorridos/iniciar', [MapaController::class, 'iniciarRecorrido']);
Route::put('/movil/recorridos/{id}', [MapaController::class, 'actualizarRecorrido']);
Route::post('/movil/recorridos/{id}/finalizar', [MapaController::class, 'finalizarRecorrido']);

Route::post('/movil/referencias', [MapaController::class, 'crearReferencia']);

// Rutas para consultar el historial en el mapa
Route::get('/recorridos', [MapaController::class, 'obtenerRecorridos']);
Route::get('/referencias', [MapaController::class, 'obtenerReferencias']);
