<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Resultados de Validación</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none; }
            .print-friendly { box-shadow: none !important; border: 1px solid #ccc; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-7xl mx-auto">

        <!-- Header -->
        <div class="no-print flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Reporte de Validación de Turnos</h1>
                <p class="text-lg text-gray-600">Enfermera: <span class="font-semibold">{{ $enfermera }}</span></p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('turno.formulario') }}" class="px-5 py-2 bg-gray-600 text-white rounded-lg shadow hover:bg-gray-700 transition">
                    &larr; Volver al formulario
                </a>
                <button onclick="window.print()" class="px-5 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition">
                    Imprimir Reporte
                </button>
            </div>
        </div>

        <!-- Resumen: counts + subtotals + total -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">

            <!-- Diurno -->
            <div class="print-friendly bg-white p-6 rounded-2xl shadow-xl border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-500">Turnos Diurnos</h3>
                <p class="text-3xl font-bold text-yellow-600">
                    {{ count($resultados['turnos_dia'] ?? []) }}
                </p>
                <p class="text-sm text-gray-500 mt-1">Subtotal (día, por horas):</p>
                <p class="text-xl font-semibold text-gray-800">${{ number_format($resultados['subtotal_dia'] ?? 0, 2) }}</p>
            </div>

            <!-- Nocturno -->
            <div class="print-friendly bg-white p-6 rounded-2xl shadow-xl border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-500">Turnos Nocturnos</h3>
                <p class="text-3xl font-bold text-blue-600">
                    {{ count($resultados['turnos_noche'] ?? []) }}
                </p>
                <p class="text-sm text-gray-500 mt-1">Subtotal (noche, por horas):</p>
                <p class="text-xl font-semibold text-gray-800">${{ number_format($resultados['subtotal_noche'] ?? 0, 2) }}</p>
            </div>

            <!-- Total a pagar -->
            <div class="print-friendly bg-white p-6 rounded-2xl shadow-xl border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-500">Total a Pagar</h3>
                <p class="text-4xl font-bold text-green-600">
                    ${{ number_format(($resultados['total_a_pagar'] ?? 0) + ($bono ?? 0), 2) }}
                </p>
                @if(!empty($bono))
                    <p class="text-sm text-gray-500 mt-1">Incluye bono de ${{ number_format($bono, 2) }}</p>
                @endif
            </div>

            <!-- Total turnos -->
            <div class="print-friendly bg-white p-6 rounded-2xl shadow-xl border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-500">Turnos Totales</h3>
                <p class="text-4xl font-bold text-purple-600">{{ $resultados['total_turnos'] ?? count($resultados['turnos_completos'] ?? []) }}</p>
                <p class="text-sm text-gray-500 mt-1">Horas totales (suma):
                    {{ number_format(array_sum(array_column($resultados['turnos_completos'] ?? [], 'horas')) ?? 0, 2) }}
                </p>
            </div>
        </div>

        <!-- Detalle y inconsistencias header -->
        <div class="mb-6 border-b border-gray-300">
            <h2 class="text-2xl font-semibold text-gray-700 pb-2">Detalles del Reporte</h2>
        </div>

        <!-- Inconsistencias -->
        @if(!empty($totalInconsistencias) && $totalInconsistencias > 0)
            <div class="print-friendly bg-red-50 border border-red-300 p-6 rounded-2xl shadow-lg mb-8">
                <h3 class="text-2xl font-semibold text-red-700 mb-4">Detalle de Inconsistencias ({{ $totalInconsistencias }})</h3>

                @if(!empty($resultados['inconsistencias']['turnos_incompletos']))
                    <div class="mb-6">
                        <h4 class="text-xl font-bold text-red-600">Turnos Incompletos ({{ count($resultados['inconsistencias']['turnos_incompletos']) }})</h4>
                        <p class="text-sm text-gray-600 mb-3">Turnos con recibo pero faltó intermedia o entrega.</p>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white rounded-lg overflow-hidden">
                                <thead class="bg-red-100 text-red-800">
                                    <tr>
                                        <th class="py-3 px-4 text-left">Recibo</th>
                                        <th class="py-3 px-4 text-left">Estado</th>
                                        <th class="py-3 px-4 text-left">Mensaje</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($resultados['inconsistencias']['turnos_incompletos'] as $item)
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4">{{ $item['recibo']['timestamp']->format('d/m/Y H:i') }}</td>
                                            <td class="py-3 px-4">
                                                @if($item['intermedia_encontrada']) <span class="text-green-600">Intermedia OK</span> @else <span class="text-red-600">Falta Intermedia</span> @endif
                                                /
                                                @if($item['entrega_encontrada']) <span class="text-green-600">Entrega OK</span> @else <span class="text-red-600">Falta Entrega</span> @endif
                                            </td>
                                            <td class="py-3 px-4 font-semibold text-red-600">{{ $item['mensaje'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if(!empty($resultados['inconsistencias']['notas_huerfanas']))
                    <div class="mb-6">
                        <h4 class="text-xl font-bold text-yellow-600">Notas Huérfanas ({{ count($resultados['inconsistencias']['notas_huerfanas']) }})</h4>
                        <p class="text-sm text-gray-600 mb-3">Notas intermedias/entrega no asociadas.</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($resultados['inconsistencias']['notas_huerfanas'] as $nota)
                                <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-3 py-1 rounded-full">
                                    {{ $nota['tipo'] }} - {{ $nota['timestamp']->format('d/m/Y H:i') }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($resultados['inconsistencias']['notas_duplicadas_seguidas']))
                    <div class="mb-6">
                        <h4 class="text-xl font-bold text-purple-600">Notas Duplicadas ({{ count($resultados['inconsistencias']['notas_duplicadas_seguidas']) }})</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white rounded-lg overflow-hidden">
                                <thead class="bg-purple-100 text-purple-800">
                                    <tr>
                                        <th class="py-3 px-4 text-left">Tipo</th>
                                        <th class="py-3 px-4 text-left">Nota 1</th>
                                        <th class="py-3 px-4 text-left">Nota 2</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($resultados['inconsistencias']['notas_duplicadas_seguidas'] as $item)
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4">{{ ucwords($item['nota_1']['tipo']) }}</td>
                                            <td class="py-3 px-4">{{ $item['nota_1']['timestamp']->format('d/m/Y H:i') }}</td>
                                            <td class="py-3 px-4">{{ $item['nota_2']['timestamp']->format('d/m/Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

            </div>
        @endif

        <!-- Turnos Completos (detalle por turno) -->
        <div class="print-friendly bg-white p-6 rounded-2xl shadow-lg border border-gray-200">
            <h3 class="text-2xl font-semibold text-gray-800 mb-4">Detalle de Turnos Completos ({{ $resultados['total_turnos'] ?? count($resultados['turnos_completos'] ?? []) }})</h3>

            @if(empty($resultados['turnos_completos'] ?? []))
                <p class="text-gray-500">No se encontraron turnos completos.</p>
            @else
                <div class="overflow-x-auto">
                   <table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">#</th>
            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Recibo</th>
            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Entrega</th>
            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Horas pagadas</th>
            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Valor hora (ef.)</th>
            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
        </tr>
    </thead>

    <tbody class="bg-white divide-y divide-gray-200">
        @foreach($resultados['turnos_completos'] as $i => $turno)
            <tr class="hover:bg-gray-50">
                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-500">{{ $i + 1 }}</td>

                <td class="py-3 px-4 whitespace-nowrap text-sm">
                    @if(($turno['turno_tipo'] ?? '') == 'diurno')
                        <span class="px-3 py-1 inline-flex text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Diurno</span>
                    @else
                        <span class="px-3 py-1 inline-flex text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Nocturno</span>
                    @endif
                </td>

                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-900">
                    {{ $turno['recibo']['timestamp']->format('d/m/Y H:i') }}
                </td>

                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-500">
                    {{ $turno['entrega']['timestamp']->format('d/m/Y H:i') }}
                </td>

                <td class="py-3 px-4 whitespace-nowrap text-sm">
                    {{ number_format($turno['horas'] ?? 0, 2) }} h
                </td>

                {{-- Valor hora efectivo: pago / horas (fallback a 0.00 si horas = 0) --}}
                <td class="py-3 px-4 whitespace-nowrap text-sm">
                    @php
                        $horas = (float) ($turno['horas'] ?? 0);
                        $pago = (float) ($turno['pago'] ?? $turno['pago_total'] ?? 0);
                        $valorHora = $horas > 0 ? round($pago / $horas, 2) : 0.00;
                    @endphp
                    ${{ number_format($valorHora, 2) }} /h
                </td>

                <td class="py-3 px-4 whitespace-nowrap text-sm font-semibold text-green-600">
                    ${{ number_format($pago, 2) }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

                </div>
            @endif
        </div>

        <!-- Bono -->
        @if(!empty($bono))
            <div class="print-friendly bg-green-50 border border-green-300 p-6 rounded-2xl shadow-lg mt-8">
                <h3 class="text-2xl font-semibold text-green-700 mb-2">💰 Bono Aplicado</h3>
                <p class="text-gray-700 text-lg">Se otorgó un bono adicional de <span class="font-bold text-green-800">${{ number_format($bono, 2) }}</span>.</p>
            </div>
        @endif

    </div>
</body>
</html>
