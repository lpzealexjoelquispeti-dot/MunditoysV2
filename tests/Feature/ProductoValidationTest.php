<?php

use App\Console\Commands\ServeCommand;
use App\Rules\ArchivoGlb;
use App\Rules\DimensionesProducto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

test('acepta tres dimensiones positivas con separadores y decimales', function (string $dimensiones) {
    expect(Validator::make(['dimensiones' => $dimensiones], [
        'dimensiones' => ['required', new DimensionesProducto],
    ])->passes())->toBeTrue();
})->with(['30 x 20 x 15', '30x20x15', '30 × 20 × 15 cm', '30.5 x 20,25 x 0.01', '30 X 20 X 15']);

test('rechaza dimensiones incompletas o con texto arbitrario', function (string $dimensiones) {
    $validador = Validator::make(['dimensiones' => $dimensiones], [
        'dimensiones' => ['required', new DimensionesProducto],
    ]);
    expect($validador->errors()->first('dimensiones'))
        ->toBe('Escribe largo x ancho x alto en centímetros. Ejemplo: 30 x 20 x 15.');
})->with(['xx', '30 xxx30', '30 x 20', '30 x x 15', 'a x b x c', '-1 x 2 x 3', '1e2 x 2 x 3', '1.234 x 2 x 3', "1 x 2 x 3\notro"]);

test('rechaza dimensiones de valor cero', function () {
    expect(Validator::make(['dimensiones' => '30 x 0 x 15'], [
        'dimensiones' => [new DimensionesProducto],
    ])->errors()->first('dimensiones'))->toBe('Las tres dimensiones deben ser mayores que cero.');
});

test('artisan utiliza el servidor de desarrollo con límites de carga de MundiToys', function () {
    expect(Artisan::all()['serve'])->toBeInstanceOf(ServeCommand::class);
});

function contenidoGlbDePrueba(): string
{
    $json = '{"asset":{"version":"2.0"}}';
    $json = str_pad($json, (int) (ceil(strlen($json) / 4) * 4), ' ');

    return 'glTF'.pack('VV', 2, 20 + strlen($json)).pack('VV', strlen($json), 0x4E4F534A).$json;
}

test('acepta un GLB por su contenido aunque el sistema reporte un MIME genérico', function () {
    $archivo = UploadedFile::fake()->createWithContent('juguete.glb', contenidoGlbDePrueba());
    $archivo->mimeType('application/octet-stream');
    expect(Validator::make(['modeloGlb' => $archivo], ['modeloGlb' => [new ArchivoGlb]])->passes())->toBeTrue();
});

test('rechaza GLB renombrados truncados o con extensión diferente', function (string $nombre, string $contenido) {
    $archivo = UploadedFile::fake()->createWithContent($nombre, $contenido);
    expect(Validator::make(['modeloGlb' => $archivo], ['modeloGlb' => [new ArchivoGlb]])->errors()->first('modeloGlb'))
        ->toBe('El archivo debe ser un modelo GLB 2.0 válido, no un archivo renombrado o dañado.');
})->with([
    ['falso.glb', 'Este texto no es un modelo tridimensional'],
    ['truncado.glb', 'glTF'.pack('VV', 2, 1000).'incompleto'],
    ['modelo.txt', contenidoGlbDePrueba()],
    ['version.glb', 'glTF'.pack('VV', 1, 24).str_repeat(' ', 12)],
]);

test('el endpoint temporal admite archivos superiores a 12 MB y rechaza más de 50 MB', function () {
    Storage::fake('tmp-for-tests');
    config(['livewire.temporary_file_upload.disk' => 'local']);
    $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), [], false);

    $this->postJson($url, ['files' => [UploadedFile::fake()->create('juguete.glb', 13000, 'application/octet-stream')]])
        ->assertOk()->assertJsonCount(1, 'paths');
    expect(Storage::disk('tmp-for-tests')->allFiles('livewire-tmp'))->not->toBeEmpty();

    $this->postJson($url, ['files' => [UploadedFile::fake()->create('grande.glb', 51201, 'application/octet-stream')]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
});
