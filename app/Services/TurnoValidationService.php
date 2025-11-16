<?php

namespace App\Services;

use DateTime;
use DateInterval;
use Exception;

/**
 * Servicio para validar y calcular pagos de turnos basados en notas.
 * - Las tarifas ($tarifaDia, $tarifaNoche) representan VALOR POR HORA.
 * - Aplica reglas de redondeo / normalización acordadas.
 */
class TurnoValidationService
{
    const TIPO_RECIBO = 'nota de recibo';
    const TIPO_INTERMEDIA = 'nota intermedia';
    const TIPO_ENTREGA = 'nota de entrega';

    private float $tarifaDia;   // valor por hora diurna
    private float $tarifaNoche; // valor por hora nocturna

    public function __construct(float $tarifaDia = 0.0, float $tarifaNoche = 0.0)
    {
        $this->tarifaDia = $tarifaDia;
        $this->tarifaNoche = $tarifaNoche;
    }

    /**
     * Procesa las notas y arma los turnos completos, inconsistencias y totales.
     *
     * @param array $notasUnsorted
     * @return array
     */
    public function procesarTurnos(array $notasUnsorted): array
    {
        $notas = $this->sortNotasByTimestamp($notasUnsorted);

        $turnosCompletos = [];
        $inconsistencias = [
            'turnos_incompletos' => [],
            'notas_huerfanas' => [],
            'notas_duplicadas_seguidas' => [],
        ];

        $notasProcesadas = [];
        $totalAPagar = 0.0;
        $subtotalDia = 0.0;
        $subtotalNoche = 0.0;

        $recibos = array_values(array_filter($notas, fn($n) => $n['tipo'] === self::TIPO_RECIBO));
        $intermedias = array_values(array_filter($notas, fn($n) => $n['tipo'] === self::TIPO_INTERMEDIA));
        $entregas = array_values(array_filter($notas, fn($n) => $n['tipo'] === self::TIPO_ENTREGA));

        // Tolerancia en segundos para emparejar notas (evita errores por OCR/minutos)
        $toleranceSeconds = 90;

        foreach ($recibos as $recibo) {
            $idRecibo = $this->getNotaId($recibo);
            if (in_array($idRecibo, $notasProcesadas, true)) continue;

            $timestampRecibo = $recibo['timestamp'];

            // Buscar intermedia y entrega en rangos esperados con tolerancia
            $intermediaEncontrada = $this->findNotaEnRango($intermedias, $timestampRecibo, 'PT5H', 'PT7H', $notasProcesadas, $toleranceSeconds);
            $entregaEncontrada = $this->findNotaEnRango($entregas, $timestampRecibo, 'PT11H', 'PT13H', $notasProcesadas, $toleranceSeconds);

            if ($intermediaEncontrada && $entregaEncontrada) {
                $start = $timestampRecibo instanceof DateTime ? $timestampRecibo : new DateTime((string)$timestampRecibo);
                $end = $entregaEncontrada['timestamp'] instanceof DateTime ? $entregaEncontrada['timestamp'] : new DateTime((string)$entregaEncontrada['timestamp']);

                if ($end <= $start) {
                    // Entrega anterior o igual -> inconsistencia
                    $inconsistencias['turnos_incompletos'][] = [
                        'recibo' => $recibo,
                        'intermedia_encontrada' => $intermediaEncontrada,
                        'entrega_encontrada' => $entregaEncontrada,
                        'mensaje' => 'Entrega anterior o igual a recibo.'
                    ];
                    $this->marcarNotaProcesada($notasProcesadas, $idRecibo);
                    continue;
                }

                // Duración real en segundos
                $totalSec = $end->getTimestamp() - $start->getTimestamp();
                $horasExactas = round($totalSec / 3600, 4); // alta precisión antes de reglas

                // === Reglas de redondeo/normalización (las confirmadas por ti) ===
                // 12h: si 11.5 <= horas <= 12.1 => pagar 12
                // 6h:  if 5.5 <= horas <= 6.4  => pagar 6
                if ($horasExactas >= 11.5 && $horasExactas <= 12.1) {
                    $horasPagadasTotal = 12.0;
                } elseif ($horasExactas >= 5.5 && $horasExactas <= 6.4) {
                    $horasPagadasTotal = 6.0;
                } else {
                    $horasPagadasTotal = round($horasExactas, 2);
                }

                $tipoInicio = $this->clasificarTurno($start); // 'diurno' o 'nocturno'

                if ($tipoInicio === 'diurno') {
                    $horasPagadasDiurno = round($horasPagadasTotal, 2);
                    $horasPagadasNocturno = 0.00;
                } else {
                    $horasPagadasDiurno = 0.00;
                    $horasPagadasNocturno = round($horasPagadasTotal, 2);
                }

                // Ajuste por suma (por si hay ligeras diferencias de redondeo)
                $sumaHoras = $horasPagadasDiurno + $horasPagadasNocturno;
                $diff = round($horasPagadasTotal - $sumaHoras, 2);
                if (abs($diff) >= 0.01) {
                    // aplicar diferencia al nocturno (o al diurno si prefieres)
                    if ($tipoInicio === 'diurno') {
                        $horasPagadasDiurno = round($horasPagadasDiurno + $diff, 2);
                    } else {
                        $horasPagadasNocturno = round($horasPagadasNocturno + $diff, 2);
                    }
                }

                // Cálculo pagos
                $pagoDiurno = round($horasPagadasDiurno * $this->tarifaDia, 2);
                $pagoNocturno = round($horasPagadasNocturno * $this->tarifaNoche, 2);
                $pagoTotal = round($pagoDiurno + $pagoNocturno, 2);

                // Agregar turno con detalle
                $turnosCompletos[] = [
                    'turno_tipo' => $this->clasificarTurno($start),
                    'horas_exactas' => round($horasExactas, 4),
                    'horas_pagadas_total' => $horasPagadasTotal,
                    'horas_pagadas_diurno' => $horasPagadasDiurno,
                    'horas_pagadas_nocturno' => $horasPagadasNocturno,
                    'pago_diurno' => $pagoDiurno,
                    'pago_nocturno' => $pagoNocturno,
                    'pago_total' => $pagoTotal,
                    'recibo' => $recibo,
                    'intermedia' => $intermediaEncontrada,
                    'entrega' => $entregaEncontrada,
                ];

                // Acumular subtotales
                $subtotalDia += $pagoDiurno;
                $subtotalNoche += $pagoNocturno;
                $totalAPagar += $pagoTotal;

                // Marcar notas procesadas
                $this->marcarNotaProcesada($notasProcesadas, $idRecibo);
                $this->marcarNotaProcesada($notasProcesadas, $this->getNotaId($intermediaEncontrada));
                $this->marcarNotaProcesada($notasProcesadas, $this->getNotaId($entregaEncontrada));
            } else {
                // Turno incompleto
                $inconsistencias['turnos_incompletos'][] = [
                    'recibo' => $recibo,
                    'intermedia_encontrada' => $intermediaEncontrada,
                    'entrega_encontrada' => $entregaEncontrada,
                    'mensaje' => 'Falta ' . (!$intermediaEncontrada ? 'intermedia' : '') . (!$entregaEncontrada ? ($intermediaEncontrada ? 'entrega' : ' / entrega') : '')
                ];
                $this->marcarNotaProcesada($notasProcesadas, $idRecibo);
            }
        }

        // Notas huérfanas
        foreach ($notas as $nota) {
            $idNota = $this->getNotaId($nota);
            if (!in_array($idNota, $notasProcesadas, true)) {
                $inconsistencias['notas_huerfanas'][] = $nota;
            }
        }

        // Duplicados seguidos (mantener)
        for ($i = 0; $i < count($notas) - 1; $i++) {
            if ($notas[$i]['tipo'] === $notas[$i + 1]['tipo']) {
                if (!($notas[$i]['timestamp'] instanceof DateTime) || !($notas[$i + 1]['timestamp'] instanceof DateTime)) {
                    continue;
                }
                $secDiff = abs($notas[$i + 1]['timestamp']->getTimestamp() - $notas[$i]['timestamp']->getTimestamp());
                $minutosDiff = intdiv($secDiff, 60);
                if ($minutosDiff < 60) {
                    $inconsistencias['notas_duplicadas_seguidas'][] = [
                        'nota_1' => $notas[$i],
                        'nota_2' => $notas[$i + 1],
                    ];
                    $this->marcarNotaProcesada($notasProcesadas, $this->getNotaId($notas[$i]));
                    $this->marcarNotaProcesada($notasProcesadas, $this->getNotaId($notas[$i + 1]));
                }
            }
        }

        // Re-filtrar huérfanas por si se marcaron duplicados
        $inconsistencias['notas_huerfanas'] = array_values(array_filter(
            $inconsistencias['notas_huerfanas'],
            fn($nota) => !in_array($this->getNotaId($nota), $notasProcesadas, true)
        ));

        // Resultado final
        return [
            'turnos_completos' => $turnosCompletos,
            'inconsistencias' => $inconsistencias,
            'total_a_pagar' => round($totalAPagar, 2),
            'subtotal_dia' => round($subtotalDia, 2),
            'subtotal_noche' => round($subtotalNoche, 2),
            'total_turnos' => count($turnosCompletos),
        ];
    }

    /**
     * Genera ID único para nota (usa fecha+hora).
     */
    private function getNotaId(array $nota): string
    {
        $timestamp = $nota['timestamp'] instanceof DateTime ? $nota['timestamp'] : new DateTime((string)$nota['timestamp']);
        return $timestamp->format('YmdHis') . '-' . $nota['tipo'];
    }

    /**
     * Ordena notas por timestamp (robusto con strings o DateTime).
     */
    private function sortNotasByTimestamp(array $notas): array
    {
        usort($notas, function ($a, $b) {
            $tA = $a['timestamp'] instanceof DateTime ? $a['timestamp']->getTimestamp() : (new DateTime((string)$a['timestamp']))->getTimestamp();
            $tB = $b['timestamp'] instanceof DateTime ? $b['timestamp']->getTimestamp() : (new DateTime((string)$b['timestamp']))->getTimestamp();
            return $tA <=> $tB;
        });
        return $notas;
    }

    /**
     * Clasifica por el momento de inicio (recibo).
     */
    private function clasificarTurno(DateTime $timestampRecibo): string
    {
        $hora = (int) $timestampRecibo->format('H');
        if ($hora >= 6 && $hora < 18) {
            return 'diurno';
        }
        return 'nocturno';
    }

    /**
     * Busca una nota dentro de un rango de tiempo desde $inicio (PT... strings).
     * Añadimos $toleranceSeconds para tolerar errores pequeños (ej: 18:58 vs 18:59).
     */
    private function findNotaEnRango(array $notasArray, DateTime $inicio, string $rangoInicioStr, string $rangoFinStr, array &$notasProcesadas, int $toleranceSeconds = 90): ?array
    {
        $inicioRango = (clone $inicio)->add(new DateInterval($rangoInicioStr));
        $finRango = (clone $inicio)->add(new DateInterval($rangoFinStr));

        $inicioRangoTS = $inicioRango->getTimestamp() - $toleranceSeconds;
        $finRangoTS = $finRango->getTimestamp() + $toleranceSeconds;

        foreach ($notasArray as $nota) {
            $idNota = $this->getNotaId($nota);
            if (in_array($idNota, $notasProcesadas, true)) {
                continue;
            }

            $ts = $nota['timestamp'] instanceof DateTime ? $nota['timestamp']->getTimestamp() : (new DateTime((string)$nota['timestamp']))->getTimestamp();

            if ($ts >= $inicioRangoTS && $ts <= $finRangoTS) {
                if (!($nota['timestamp'] instanceof DateTime)) {
                    $nota['timestamp'] = new DateTime((string)$nota['timestamp']);
                }
                return $nota;
            }
        }
        return null;
    }

    /**
     * Agrega ID a notas procesadas si no está.
     */
    private function marcarNotaProcesada(array &$notasProcesadas, string $idNota): void
    {
        if (!in_array($idNota, $notasProcesadas, true)) {
            $notasProcesadas[] = $idNota;
        }
    }

    /**
     * Para un intervalo [start, end), calcula cuántos segundos corresponden
     * a diurno ([06:00,18:00) cada día) y nocturno (el resto).
     * Devuelve array con segundos: ['diurno'=>int, 'nocturno'=>int].
     */
    private function splitSecondsDiurnoNocturno(DateTime $start, DateTime $end): array
    {
        $startTs = $start->getTimestamp();
        $endTs = $end->getTimestamp();
        $totalSec = max(0, $endTs - $startTs);
        $diurnoSec = 0;

        $currentDay = (clone $start)->setTime(0, 0, 0);
        $endDay = (clone $end)->setTime(0, 0, 0);

        while ($currentDay <= $endDay) {
            $diurnoStart = (clone $currentDay)->setTime(6, 0, 0);
            $diurnoEnd = (clone $currentDay)->setTime(18, 0, 0);

            $intervalStart = $start > $diurnoStart ? $start : $diurnoStart;
            $intervalEnd = $end < $diurnoEnd ? $end : $diurnoEnd;

            if ($intervalEnd > $intervalStart) {
                $diurnoSec += $intervalEnd->getTimestamp() - $intervalStart->getTimestamp();
            }

            $currentDay->add(new DateInterval('P1D'));
        }

        $nocturnoSec = max(0, $totalSec - $diurnoSec);

        return ['diurno' => $diurnoSec, 'nocturno' => $nocturnoSec];
    }
}
