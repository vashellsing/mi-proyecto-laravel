<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TurnoValidationService;
use Spatie\PdfToText\Pdf;
use Illuminate\Support\Facades\Log;
use DateTime;
use Exception;

class TurnoController extends Controller
{
    /**
     * Muestra el formulario principal para subir el PDF.
     */
    public function mostrarFormulario()
    {
        return view('formulario');
    }

    /**
     * Procesa el PDF subido.
     */
    public function procesarPdf(Request $request)
{
    // 1. Validar la entrada (bono opcional)
    $request->validate([
        'pdf_historias' => 'required|file|mimes:pdf',
        'nombre_enfermera' => 'required|string|min:3',
        'tarifa_dia' => 'required|numeric|min:0',    // valor POR HORA
        'tarifa_noche' => 'required|numeric|min:0',  // valor POR HORA
        'bono' => 'nullable|numeric|min:0',
    ], [
        'pdf_historias.required' => 'Debes subir un archivo PDF.',
        'nombre_enfermera.required' => 'El nombre de la enfermera es obligatorio.',
        'tarifa_dia.required' => 'La tarifa por hora (día) es obligatoria.',
        'tarifa_noche.required' => 'La tarifa por hora (noche) es obligatoria.',
    ]);

    try {
        // 2. Extraer texto del PDF
        $path = $request->file('pdf_historias')->path();
        $pdf = new Pdf(base_path('poppler/pdftotext.exe'));
        $pdf->setPdf($path);
        $textoCompleto = $pdf->text();

        if (empty(trim($textoCompleto))) {
            return back()->with('error', 'El PDF está vacío o no se pudo leer.');
        }

        // 3. Parseo del PDF (busca notas de la enfermera)
        $notasExtraidas = $this->parsearNotas(
            $textoCompleto,
            $request->input('nombre_enfermera')
        );

        if (empty($notasExtraidas)) {
            return back()->with(
                'error',
                'No se encontraron notas para la enfermera: "' . $request->input('nombre_enfermera') . '"'
            );
        }

        // 4. Procesar turnos usando el servicio (tarifa POR HORA)
        $servicioValidacion = new TurnoValidationService(
            (float) $request->input('tarifa_dia'),
            (float) $request->input('tarifa_noche')
        );

        $resultados = $servicioValidacion->procesarTurnos($notasExtraidas);

        // -------------------------------------------------------------
        // NORMALIZAR los campos de cada turno para que la vista funcione
        // -------------------------------------------------------------
        $turnosCompletos = $resultados['turnos_completos'] ?? [];

        // Recorremos y garantizamos que existan las claves que el Blade espera:
        foreach ($turnosCompletos as &$t) {
            // Horas: mostrar las HORAS PAGADAS (si existen) o las horas exactas.
            $t['horas'] = $t['horas_pagadas_total'] ?? $t['horas_exactas'] ?? ($t['horas'] ?? 0);

            // Horas por tramo (usamos las horas pagadas por tramo si existen)
            $t['horas_diurno'] = $t['horas_pagadas_diurno'] ?? ($t['horas_diurno'] ?? 0);
            $t['horas_nocturno'] = $t['horas_pagadas_nocturno'] ?? ($t['horas_nocturno'] ?? 0);

            // Pago total y por tramo (mapear nombres del servicio)
            $t['pago'] = $t['pago_total'] ?? ($t['pago'] ?? 0);
            $t['pago_diurno'] = $t['pago_diurno'] ?? 0;
            $t['pago_nocturno'] = $t['pago_nocturno'] ?? 0;

            // Asegurarnos que sean floats y redondearlos para la vista
            $t['horas'] = round((float)$t['horas'], 2);
            $t['horas_diurno'] = round((float)$t['horas_diurno'], 2);
            $t['horas_nocturno'] = round((float)$t['horas_nocturno'], 2);
            $t['pago'] = round((float)$t['pago'], 2);
            $t['pago_diurno'] = round((float)$t['pago_diurno'], 2);
            $t['pago_nocturno'] = round((float)$t['pago_nocturno'], 2);
        }
        unset($t); // buena práctica para referencias

        // Sobrescribimos en resultados la versión normalizada
        $resultados['turnos_completos'] = $turnosCompletos;

        // 5. Construir arrays filtrados por tipo (para las tarjetas)
        $resultados['turnos_dia'] = array_values(array_filter(
            $turnosCompletos,
            fn($x) => ($x['turno_tipo'] ?? '') === 'diurno'
        ));

        $resultados['turnos_noche'] = array_values(array_filter(
            $turnosCompletos,
            fn($x) => ($x['turno_tipo'] ?? '') === 'nocturno'
        ));

        // 6. Subtotales / totales: respetar lo que devolvió el servicio o recalcular
        $resultados['subtotal_dia'] = $resultados['subtotal_dia'] ?? array_sum(array_column($resultados['turnos_dia'], 'pago_diurno'));
        $resultados['subtotal_noche'] = $resultados['subtotal_noche'] ?? array_sum(array_column($resultados['turnos_noche'], 'pago_nocturno'));
        $resultados['total_a_pagar'] = $resultados['total_a_pagar'] ?? array_sum(array_column($turnosCompletos, 'pago'));
        $resultados['total_turnos'] = $resultados['total_turnos'] ?? count($turnosCompletos);

        // 7. Cálculo de inconsistencias total (las 3 categorías)
        $totalInconsistencias = 0;
        if (isset($resultados['inconsistencias']) && is_array($resultados['inconsistencias'])) {
            $totalInconsistencias =
                count($resultados['inconsistencias']['turnos_incompletos'] ?? []) +
                count($resultados['inconsistencias']['notas_huerfanas'] ?? []) +
                count($resultados['inconsistencias']['notas_duplicadas_seguidas'] ?? []);
        }

        // 8. Bono (opcional)
        $bono = $request->filled('bono') ? (float) $request->input('bono') : null;

        // 9. Pasar datos a la vista
        return view('resultados', [
            'resultados' => $resultados,
            'enfermera' => $request->input('nombre_enfermera'),
            'totalInconsistencias' => $totalInconsistencias,
            'bono' => $bono,
        ]);
    } catch (Exception $e) {
        Log::error('Error al procesar el PDF: ' . $e->getMessage());
        return back()->with('error', 'Ocurrió un error grave al procesar el archivo: ' . $e->getMessage());
    }
}


    /**
     * Parsea el texto extraído del PDF y busca las notas de la enfermera.
     *
     * @param string $textoCompleto El string gigante del PDF.
     * @param string $nombreEnfermera El nombre a buscar.
     * @return array Array de notas encontradas.
     */
    private function parsearNotas(string $textoCompleto, string $nombreEnfermera): array
    {
        $notasExtraidas = [];
        $nombreEnfermeraLower = strtolower(trim($nombreEnfermera));

        // 1. Dividimos el documento entero por "Item:".
        $bloquesDeNotas = preg_split("/Item:\s*\d+\s+Hora/", $textoCompleto);

        if (count($bloquesDeNotas) > 0) {
            array_shift($bloquesDeNotas);
        }

        Log::info("PDF dividido en " . count($bloquesDeNotas) . " bloques.");

        foreach ($bloquesDeNotas as $index => $bloque) {

            // Patrón para la fecha (DD/MM/YYYY HH:MM)
            $patronFecha = "/(\d{2}\/\d{2}\/\d{4} \d{1,2}:\d{2})/";

            // ===== CORRECCIÓN 2 (Para el error "no hace nada") =====
            // Hacemos que "(Titulo\s+)?" sea opcional.
            // El grupo 1 será "Titulo " o nada.
            // El grupo 2 será el tipo de nota.
            $patronTipo = "/(Titulo\s+)?(NOTA DE RECIBO|NOTA INTERMEDIA|NOTA DE ENTREGA)/s";
            // ===== FIN CORRECCIÓN 2 =====

            // Patrón para el nombre
            $patronNombre = "/AUXILIAR DE ENFERMERIA: (.*?)\s+Tp:/s";

            // Buscamos las coincidencias
            $matchFecha = preg_match($patronFecha, $bloque, $matchesFecha);
            $matchTipo = preg_match($patronTipo, $bloque, $matchesTipo);
            $matchNombre = preg_match($patronNombre, $bloque, $matchesNombre);

            if ($matchFecha && $matchTipo && $matchNombre) {
                $fechaStr = trim($matchesFecha[1]);

                // ===== CORRECCIÓN 3 =====
                // El tipo de nota ahora está en el GRUPO 2
                $tipoNota = strtolower(trim($matchesTipo[2]));
                // ===== FIN CORRECCIÓN 3 =====

                $nombreEncontrado = strtolower(trim($matchesNombre[1]));

                if (str_contains($nombreEncontrado, $nombreEnfermeraLower)) {
                    try {
                        $tipoNormalizado = null;
                        if (str_contains($tipoNota, 'nota de recibo')) {
                            $tipoNormalizado = TurnoValidationService::TIPO_RECIBO;
                        } elseif (str_contains($tipoNota, 'nota intermedia')) {
                            $tipoNormalizado = TurnoValidationService::TIPO_INTERMEDIA;
                        } elseif (str_contains($tipoNota, 'nota de entrega')) {
                            $tipoNormalizado = TurnoValidationService::TIPO_ENTREGA;
                        }

                        $timestamp = DateTime::createFromFormat('!d/m/Y H:i', $fechaStr);

                        if ($tipoNormalizado && $timestamp) {
                            $notasExtraidas[] = [
                                'timestamp' => $timestamp,
                                'tipo' => $tipoNormalizado
                            ];
                        } else {
                            Log::warning("Nota saltada (bloque $index): Tipo O Timestamp inválido. Fecha: '$fechaStr', Tipo: '$tipoNota'");
                        }
                    } catch (Exception $e) {
                        Log::warning("Fecha inválida (bloque $index): $fechaStr. Error: " . $e->getMessage());
                    }
                }
            } else {
                 Log::warning("Bloque $index saltado: No se encontraron todas las piezas. Fecha: $matchFecha, Tipo: $matchTipo, Nombre: $matchNombre");
            }
        }

        Log::info("Se extrajeron " . count($notasExtraidas) . " notas para '$nombreEnfermera'.");
        return $notasExtraidas;
    }
}
