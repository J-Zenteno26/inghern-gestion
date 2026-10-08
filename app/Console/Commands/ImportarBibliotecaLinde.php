<?php



namespace App\Console\Commands;



use App\Models\Cotizacion;

use App\Models\Documento;

use App\Models\DocumentoVinculo;

use App\Models\Factura;

use App\Models\OrdenCompra;

use App\Models\Planta;

use App\Models\User;

use Illuminate\Console\Command;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\File;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;

use RuntimeException;

use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

use Throwable;

use ZipArchive;



class ImportarBibliotecaLinde extends Command

{

    protected $signature = 'inghern:importar-biblioteca-linde

        {zip : Ruta al archivo ZIP histórico de Linde}

        {--user= : ID del usuario creador}

        {--commit : Confirma la escritura de documentos y vínculos}';



    protected $description = 'Previsualiza o importa la documentación histórica de Linde a la Biblioteca';



    private int $importados = 0;



    private int $existentes = 0;



    private int $excluidos = 0;



    private int $revisar = 0;



    private int $errores = 0;



    public function handle(): int

    {

        $zipPath = $this->resolveZipPath((string) $this->argument('zip'));

        if ($zipPath === null) {

            return self::FAILURE;

        }



        $usuario = $this->resolveUser();

        if ($this->option('commit') && $usuario === null) {

            $this->error('Con --commit debes indicar --user= con el ID de un usuario existente.');



            return self::FAILURE;

        }



        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CHECKCONS) !== true) {

            $this->error('El archivo indicado no es un ZIP válido o está corrupto.');



            return self::FAILURE;

        }



        $temporal = null;



        try {

            $this->newLine();

            $this->info($this->option('commit') ? 'MODO COMMIT' : 'DRY-RUN · sin escrituras');

            $this->line('ZIP: '.$zipPath);

            $userOption = $this->option('user');

            $userReport = $usuario

                ? "#{$usuario->getKey()} · {$usuario->name}"

                : ($userOption ? "[REVISAR] usuario #{$userOption} inexistente" : '[REVISAR] no indicado');

            $this->line('Usuario creador: '.$userReport);

            $this->newLine();



            $archivos = $this->scanZip($zip);

            if ($archivos === []) {

                $this->error('No se encontraron archivos dentro de carpetas LINDE/COT-XX-...');



                return self::FAILURE;

            }



            $cotizaciones = $this->cotizacionesByNumber();

            if ($this->option('commit')) {

                $temporal = storage_path('app/tmp/importar-biblioteca-linde-'.Str::uuid());

                File::ensureDirectoryExists($temporal);

            }



            foreach ($archivos as $numero => $grupo) {

                $this->processFolder(

                    $zip,

                    $grupo['folder'],

                    $numero,

                    $grupo['files'],

                    $cotizaciones[$numero] ?? collect(),

                    $usuario,

                    $temporal,

                );

            }



            $this->newLine();

            $this->table(

                ['Resultado', 'Cantidad'],

                [

                    ['Importados', $this->importados],

                    ['Ya existentes', $this->existentes],

                    ['Excluidos', $this->excluidos],

                    ['Revisar', $this->revisar],

                    ['Errores', $this->errores],

                ],

            );



            if (! $this->option('commit')) {

                $this->info('Dry-run finalizado. No se crearon documentos, archivos ni vínculos.');

                $this->line('Cuando el reporte esté validado, repite el comando con --user=<ID> --commit.');

            }



            return $this->errores > 0 ? self::FAILURE : self::SUCCESS;

        } finally {

            $zip->close();



            if ($temporal !== null && File::isDirectory($temporal)) {

                File::deleteDirectory($temporal);

            }

        }

    }



    private function resolveZipPath(string $argument): ?string

    {

        $candidate = $this->isAbsolutePath($argument)

            ? $argument

            : base_path($argument);

        $resolved = realpath($candidate);



        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {

            $this->error('El ZIP no existe o no puede leerse: '.$candidate);



            return null;

        }



        return $resolved;

    }



    private function resolveUser(): ?User

    {

        $userId = $this->option('user');

        if ($userId === null || $userId === '' || ! ctype_digit((string) $userId)) {

            return null;

        }



        return User::query()->find((int) $userId);

    }



    /**

     * @return array<int, array{folder: string, files: array<int, array{path: string, name: string, size: int, extension: string, type: string}>}>

     */

    private function scanZip(ZipArchive $zip): array

    {

        $groups = [];

        $allowedExtensions = config('filesystems.document_uploads.allowed_extensions', []);



        for ($index = 0; $index < $zip->numFiles; $index++) {

            $stat = $zip->statIndex($index);

            if ($stat === false) {

                continue;

            }



            $path = str_replace('\\\\', '/', (string) $stat['name']);

            if (str_ends_with($path, '/')) {

                continue;

            }



            if (! $this->isSafeZipPath($path)) {

                $this->line('[EXCLUIDO] Ruta insegura: '.$path);

                $this->excluidos++;



                continue;

            }



            $name = basename($path);

            if (Str::lower($name) === 'autodesk_backup_code.txt') {

                $this->line('[EXCLUIDO] '.$path);

                $this->excluidos++;



                continue;

            }



            $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, $allowedExtensions, true)) {

                $this->line('[EXCLUIDO] Extensión no admitida: '.$path);

                $this->excluidos++;



                continue;

            }



            if (! preg_match('#^LINDE/(COT-(\d{2})(?:-[^/]+)?)/#i', $path, $matches)) {

                $this->line('[REVISAR] Fuera de la estructura LINDE/COT-XX-...: '.$path);

                $this->revisar++;



                continue;

            }



            $number = (int) $matches[2];

            if ($number < 4 || $number > 19) {

                $this->line('[REVISAR] Número de carpeta fuera de COT-04 a COT-19: '.$path);

                $this->revisar++;



                continue;

            }



            $groups[$number] ??= ['folder' => $matches[1], 'files' => []];

            $groups[$number]['files'][] = [

                'path' => $path,

                'name' => $name,

                'size' => (int) ($stat['size'] ?? 0),

                'extension' => $extension,

                'type' => $this->documentType($name),

            ];

        }



        ksort($groups);



        return $groups;

    }



    /**

     * @return array<int, Collection<int, Cotizacion>>

     */

    private function cotizacionesByNumber(): array

    {

        return Cotizacion::query()

            ->with(['cliente', 'planta', 'ordenCompra.facturas'])

            ->orderBy('codigo')

            ->get()

            ->filter(fn (Cotizacion $cotizacion) => preg_match('/(\d+)$/', $cotizacion->codigo) === 1)

            ->groupBy(fn (Cotizacion $cotizacion): int => (int) Str::afterLast($cotizacion->codigo, '-'))

            ->all();

    }



    /**

     * @param  array<int, array{path: string, name: string, size: int, extension: string, type: string}>  $files

     * @param  Collection<int, Cotizacion>  $matches

     */

    private function processFolder(

        ZipArchive $zip,

        string $folder,

        int $number,

        array $files,

        Collection $matches,

        ?User $usuario,

        ?string $temporal,

    ): void {

        $this->line('COT-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)." · {$folder}");



        if ($matches->count() !== 1) {

            $codes = $matches->pluck('codigo')->implode(', ');

            $reason = $matches->isEmpty()

                ? 'no se encontró una cotización cuyo código termine en este número'

                : 'coincidencia ambigua: '.$codes;

            $this->warn('  [REVISAR] '.$reason.'. No se importará esta carpeta.');

            $this->revisar += count($files);



            foreach ($files as $file) {

                $this->line("  [REVISAR] {$file['name']} · {$this->typeLabel($file['type'])} · {$file['size']} bytes");

            }



            return;

        }



        /** @var Cotizacion $cotizacion */

        $cotizacion = $matches->first();

        $plantas = $this->documentPlants($cotizacion);

        $expectedPlantIds = $this->multiPlantIds($cotizacion);

        $missingPlantIds = $expectedPlantIds === null

            ? []

            : array_values(array_diff($expectedPlantIds, $plantas->pluck('id')->all()));



        $this->info("  Cotización encontrada: #{$cotizacion->getKey()} · {$cotizacion->codigo}");

        $this->line("  Organización: #{$cotizacion->cliente->getKey()} · {$cotizacion->cliente->nombre_display}");



        if ($plantas->count() === 1) {

            /** @var Planta $planta */

            $planta = $plantas->first();

            $this->line("  Planta documental: #{$planta->getKey()} · {$planta->nombre}");

        } elseif ($plantas->count() > 1) {

            $this->line('  Plantas documentales:');

            foreach ($plantas as $planta) {

                $this->line("    - #{$planta->getKey()} · {$planta->nombre}");

            }

        } else {

            $this->warn('  [REVISAR] Sin planta documental asociada.');

            $this->revisar++;

        }



        if ($missingPlantIds !== []) {

            $this->warn('  [REVISAR] Faltan plantas esperadas para esta cotización multiplanta: '.implode(', ', $missingPlantIds));

            $this->revisar++;

        }



        foreach ($files as $file) {

            $this->processFile($zip, $file, $cotizacion, $plantas, $usuario, $temporal);

        }



        $this->newLine();

    }



    /**

     * @param  array{path: string, name: string, size: int, extension: string, type: string}  $file

     */

    private function processFile(

        ZipArchive $zip,

        array $file,

        Cotizacion $cotizacion,

        Collection $plantas,

        ?User $usuario,

        ?string $temporal,

    ): void {

        $existing = Documento::query()

            ->where('cliente_id', $cotizacion->cliente_id)

            ->where('nombre_original', Str::limit($file['name'], 255, ''))

            ->where('tamano', $file['size'])

            ->whereHas('vinculos', fn ($links) => $links

                ->where('vinculable_type', $cotizacion->getMorphClass())

                ->where('vinculable_id', $cotizacion->getKey()))

            ->exists();



        if ($existing) {

            $this->line("  [YA EXISTE] {$file['name']} · {$this->typeLabel($file['type'])} · {$file['size']} bytes");

            $this->existentes++;



            return;

        }



        $links = [$cotizacion->cliente, $cotizacion];

        foreach ($plantas as $planta) {

            $links[] = $planta;

        }



        $review = null;

        if ($file['type'] === 'orden_compra') {

            if ($cotizacion->ordenCompra) {

                $links[] = $cotizacion->ordenCompra;

            } else {

                $review = 'No existe una Orden de compra asociada; se conservará el vínculo a la Cotización.';

            }

        }



        if ($file['type'] === 'factura_respaldo') {

            $factura = $this->matchingInvoice($cotizacion, $file['name']);

            if ($factura) {

                $links[] = $factura;

            } else {

                $review = 'No fue posible identificar una Factura asociada de forma inequívoca; se conservará el vínculo a la Cotización.';

            }

        }



        $status = $review ? '[REVISAR]' : '[NUEVO]';

        $this->line("  {$status} {$file['name']} · {$this->typeLabel($file['type'])} · {$file['size']} bytes");

        if ($review) {

            $this->warn('    '.$review);

            $this->revisar++;

        }

        $this->line('    Vínculos: '.collect($links)->map(fn (Model $model) => $this->linkLabel($model))->implode(' | '));



        if (! $this->option('commit')) {

            return;

        }



        try {

            $this->importFile($zip, $file, $cotizacion, $links, $usuario, $temporal);

            $this->info('    [IMPORTADO]');

            $this->importados++;

        } catch (Throwable $throwable) {

            $this->error('    [ERROR] '.$throwable->getMessage());

            $this->errores++;

        }

    }



    /**

     * @param  array{path: string, name: string, size: int, extension: string, type: string}  $file

     * @param  array<int, Model>  $links

     */

    private function importFile(

        ZipArchive $zip,

        array $file,

        Cotizacion $cotizacion,

        array $links,

        User $usuario,

        string $temporal,

    ): void {

        $temporaryPath = $temporal.'/'.Str::uuid().'.'.$file['extension'];

        $source = $zip->getStream($file['path']);

        $target = fopen($temporaryPath, 'xb');



        if (! is_resource($source) || ! is_resource($target)) {

            if (is_resource($source)) {

                fclose($source);

            }

            if (is_resource($target)) {

                fclose($target);

            }



            throw new RuntimeException('No fue posible extraer el archivo en el directorio temporal controlado.');

        }



        try {

            if (stream_copy_to_stream($source, $target) === false) {

                throw new RuntimeException('No fue posible extraer el contenido del ZIP.');

            }

        } finally {

            fclose($source);

            fclose($target);

        }



        $actualSize = filesize($temporaryPath);

        if ($actualSize === false || $actualSize !== $file['size']) {

            File::delete($temporaryPath);



            throw new RuntimeException('El tamaño extraído no coincide con el informado por el ZIP.');

        }



        $physicalName = Str::uuid().'.'.$file['extension'];

        $storagePath = 'clientes/'.$cotizacion->cliente_id.'/'.$physicalName;

        $storageSource = fopen($temporaryPath, 'rb');

        if (! is_resource($storageSource)) {

            File::delete($temporaryPath);



            throw new RuntimeException('No fue posible volver a leer el archivo temporal.');

        }



        try {

            $stored = Storage::disk('documentos')->put($storagePath, $storageSource);

        } finally {

            fclose($storageSource);

        }



        if (! $stored) {

            File::delete($temporaryPath);



            throw new RuntimeException('No fue posible almacenar el documento en el disk privado.');

        }



        try {

            DB::transaction(function () use ($file, $cotizacion, $links, $usuario, $temporaryPath, $storagePath): void {

                $baseName = Str::limit(pathinfo($file['name'], PATHINFO_FILENAME), 180, '');

                $document = Documento::create([

                    'cliente_id' => $cotizacion->cliente_id,

                    'nombre' => $baseName !== '' ? $baseName : 'Documento',

                    'nombre_original' => Str::limit($file['name'], 255, ''),

                    'descripcion' => 'Importado desde el histórico documental de Linde.',

                    'tipo_documento' => $file['type'],

                    'mime_type' => (new SymfonyFile($temporaryPath))->getMimeType() ?: 'application/octet-stream',

                    'extension' => $file['extension'],

                    'tamano' => $file['size'],

                    'ruta_storage' => $storagePath,

                    'usuario_creador_id' => $usuario->getKey(),

                ]);



                foreach (collect($links)->unique(fn (Model $model) => $model->getMorphClass().':'.$model->getKey()) as $link) {

                    DocumentoVinculo::firstOrCreate([

                        'documento_id' => $document->getKey(),

                        'vinculable_type' => $link->getMorphClass(),

                        'vinculable_id' => $link->getKey(),

                    ]);

                }

            });

        } catch (Throwable $throwable) {

            Storage::disk('documentos')->delete($storagePath);



            throw $throwable;

        } finally {

            File::delete($temporaryPath);

        }

    }



    private function documentType(string $name): string

    {

        $normalized = Str::upper(Str::ascii(pathinfo($name, PATHINFO_FILENAME)));



        if (

            str_contains($normalized, 'COT-LINDE')

            || str_contains($normalized, 'ING-COT')

            || str_contains($normalized, 'COTIZACION DE SERVICIOS')

        ) {

            return 'cotizacion_respaldo_comercial';

        }



        if (preg_match('/^OC(?:[-\_\s]|$)/', $normalized) === 1) {

            return 'orden_compra';

        }



        if (preg_match('/^(?:FACT|FACTURA|ANULACION_FACTURA)(?:[-\_\s]|\d|$)/', $normalized) === 1) {

            return 'factura_respaldo';

        }



        return 'otro';

    }



    /**
     * @return Collection<int, Planta>
     */
    private function documentPlants(Cotizacion $cotizacion): Collection
    {
        $multiPlantIds = $this->multiPlantIds($cotizacion);

        if ($multiPlantIds === null) {
            return $cotizacion->planta
                ? collect([$cotizacion->planta])
                : collect();
        }

        $plantsById = Planta::query()
            ->where('cliente_id', $cotizacion->cliente_id)
            ->whereIn('id', $multiPlantIds)
            ->get()
            ->keyBy('id');

        return collect($multiPlantIds)
            ->map(fn (int $plantId) => $plantsById->get($plantId))
            ->filter()
            ->values();
    }

    /**
     * @return array<int, int>|null
     */
    private function multiPlantIds(Cotizacion $cotizacion): ?array
    {
        return match ($cotizacion->codigo) {
            'COT-LINDE-0010' => [2, 5, 7],
            'COT-LINDE-0013' => [2, 5],
            'COT-LINDE-0014' => [6, 7],
            default => null,
        };
    }



    private function matchingPlant(?Planta $planta, string $folder): ?Planta

    {

        if ($planta === null) {

            return null;

        }



        $folderWords = $this->words($folder);

        $plantWords = array_values(array_filter(

            $this->words($planta->nombre),

            fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, ['planta'], true),

        ));



        if ($plantWords === []) {

            return null;

        }



        return collect($plantWords)->contains(fn (string $word) => in_array($word, $folderWords, true))

            ? $planta

            : null;

    }



    private function matchingInvoice(Cotizacion $cotizacion, string $fileName): ?Factura

    {

        $invoices = $cotizacion->ordenCompra?->facturas ?? collect();

        $normalizedName = Str::upper(Str::ascii(pathinfo($fileName, PATHINFO_FILENAME)));

        $matches = $invoices->filter(function (Factura $invoice) use ($normalizedName): bool {

            $folio = Str::upper(Str::ascii(trim((string) $invoice->folio)));



            return $folio !== ''

                && preg_match('/(^|[^A-Z0-9])'.preg_quote($folio, '/').'([^A-Z0-9]|$)/', $normalizedName) === 1;

        });



        return $matches->count() === 1 ? $matches->first() : null;

    }



    /**

     * @return array<int, string>

     */

    private function words(string $value): array

    {

        return array_values(array_filter(preg_split(

            '/[^A-Z0-9]+/',

            Str::upper(Str::ascii($value)),

        ) ?: []));

    }



    private function isSafeZipPath(string $path): bool

    {

        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/')) {

            return false;

        }

        if (preg_match('#^[A-Z]:/#i', $path) === 1) {

            return false;

        }



        return ! collect(explode('/', $path))->contains('..');

    }



    private function isAbsolutePath(string $path): bool

    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('#^[A-Z]:[\\\\/]#i', $path) === 1;
    }



    private function linkLabel(Model $model): string

    {

        return match (true) {

            $model instanceof Cotizacion => 'Cotización '.$model->codigo,

            $model instanceof Planta => 'Planta '.$model->nombre,

            $model instanceof OrdenCompra => 'OC '.$model->numero,

            $model instanceof Factura => 'Factura '.$model->folio,

            default => 'Organización '.$model->nombre_display,

        };

    }



    private function typeLabel(string $type): string

    {

        return $type.' ('.(Documento::TIPOS[$type] ?? 'Tipo desconocido').')';

    }

}
