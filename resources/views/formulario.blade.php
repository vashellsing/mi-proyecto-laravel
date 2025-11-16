<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validador de Turnos</title>
    <!-- Cargamos Tailwind CSS desde el CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Usamos una fuente más limpia */
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl p-8 md:p-12">
        <h1 class="text-3xl md:text-4xl font-bold text-center text-blue-600 mb-2">Validador de Turnos</h1>
        <p class="text-center text-gray-600 mb-8">Sube el PDF de historias clínicas para validar los turnos de
            enfermería.</p>

        <!--
          Formulario de Carga
          - Apunta a la ruta 'turno.procesar' que definimos.
          - Usa método POST y enctype para permitir subida de archivos.
        -->
        <form action="{{ route('turno.procesar') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf <!-- Directiva de Blade para protección CSRF (¡Muy importante!) -->

            <!-- Sección de Archivo -->
            <div>
                <label for="pdf_historias" class="block text-sm font-medium text-gray-700 mb-2">1. Archivo PDF del
                    Mes</label>
                <input type="file" name="pdf_historias" id="pdf_historias" required accept=".pdf"
                    class="block w-full text-sm text-gray-500
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-lg file:border-0
                              file:text-sm file:font-semibold
                              file:bg-blue-50 file:text-blue-700
                              hover:file:bg-blue-100
                              border border-gray-300 rounded-lg cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Sección de Datos -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="nombre_enfermera" class="block text-sm font-medium text-gray-700">2. Nombre de
                        Enfermera</label>
                    <input type="text" name="nombre_enfermera" id="nombre_enfermera" required
                        placeholder="Ej: Ana Pérez"
                        class="mt-1 block w-full px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label for="tarifa_dia" class="block text-sm font-medium text-gray-700">3. Tarifa Turno Día</label>
                    <input type="number" name="tarifa_dia" id="tarifa_dia" required placeholder="Ej: 100"
                        min="0" step="any"
                        class="mt-1 block w-full px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label for="tarifa_noche" class="block text-sm font-medium text-gray-700">4. Tarifa Turno
                        Noche</label>
                    <input type="number" name="tarifa_noche" id="tarifa_noche" required placeholder="Ej: 120"
                        min="0" step="any"
                        class="mt-1 block w-full px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="mb-4">
                    <label for="bono" class="block text-sm font-medium text-gray-700">Bono adicional
                        (opcional)</label>
                    <input type="number" name="bono" id="bono"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        placeholder="Ej: 50000">
                </div>



            </div>

            <!-- Botón de Envío -->
            <div>
                <button type="submit"
                    class="w-full flex justify-center py-3 px-6 border border-transparent
                               rounded-lg shadow-lg text-base font-medium text-white
                               bg-blue-600 hover:bg-blue-700
                               focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500
                               transition-all duration-200 ease-in-out transform hover:scale-105">
                    Validar y Calcular Turnos
                </button>
            </div>
        </form>
    </div>
</body>

</html>
