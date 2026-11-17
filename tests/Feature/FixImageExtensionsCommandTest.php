<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `storage:fix-image-extensions` conserta o que foi gravado quando a extensão ainda saía do nome
 * enviado pelo cliente: um PNG de verdade chamado `logo.html` virava `workshop-logos/1_123.html` e
 * continua sendo servido como `text/html` pelo servidor web ou pelo CDN, porque é a extensão que
 * decide o Content-Type. A correção na gravação (`App\Support\UploadedImageExtension`) não alcança o
 * que já está no storage.
 */
class FixImageExtensionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '<script>alert(document.domain)</script>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeCoversDisk('covers');
        Storage::fake('public');
    }

    /** PNG real com o payload depois do IEND: para o finfo continua um PNG. */
    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(12, 12);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes.self::PAYLOAD;
    }

    private function workshopWithLogoAt(string $path): Workshop
    {
        $user = User::factory()->asWorkshop()->create();
        Storage::disk('covers')->put($path, $this->pngBytes());
        $user->workshop->forceFill(['logo_path' => $path])->save();

        return $user->workshop->fresh();
    }

    public function test_a_logo_stored_as_html_is_renamed_to_png(): void
    {
        $workshop = $this->workshopWithLogoAt(AppStorage::WORKSHOP_LOGOS_PREFIX.'7_1762800000.html');

        $this->artisan('storage:fix-image-extensions')
            ->expectsOutputToContain('Corrigidos: 1')
            ->assertSuccessful();

        $path = $workshop->fresh()->logo_path;

        $this->assertSame(AppStorage::WORKSHOP_LOGOS_PREFIX.'7_1762800000.png', $path);
        Storage::disk('covers')->assertExists($path);
        Storage::disk('covers')->assertMissing(AppStorage::WORKSHOP_LOGOS_PREFIX.'7_1762800000.html');
        // O arquivo foi movido como estava: o que muda é a extensão do caminho.
        $this->assertStringContainsString(self::PAYLOAD, (string) Storage::disk('covers')->get($path));
    }

    public function test_the_dry_run_changes_nothing(): void
    {
        $original = AppStorage::WORKSHOP_LOGOS_PREFIX.'7_1762800000.svg';
        $workshop = $this->workshopWithLogoAt($original);

        $this->artisan('storage:fix-image-extensions', ['--dry-run' => true])
            ->expectsOutputToContain('A corrigir: 1')
            ->assertSuccessful();

        $this->assertSame($original, $workshop->fresh()->logo_path);
        Storage::disk('covers')->assertExists($original);
        Storage::disk('covers')->assertMissing(AppStorage::WORKSHOP_LOGOS_PREFIX.'7_1762800000.png');
    }

    public function test_a_path_that_already_matches_its_content_is_left_alone(): void
    {
        $original = AppStorage::WORKSHOP_LOGOS_PREFIX.'7_1762800000.png';
        $workshop = $this->workshopWithLogoAt($original);

        $this->artisan('storage:fix-image-extensions')
            ->expectsOutputToContain('Corrigidos: 0')
            ->expectsOutputToContain('Já certos: 1')
            ->assertSuccessful();

        $this->assertSame($original, $workshop->fresh()->logo_path);
        Storage::disk('covers')->assertExists($original);
    }

    public function test_running_twice_leaves_the_second_run_with_nothing_to_do(): void
    {
        $workshop = $this->workshopWithLogoAt(AppStorage::WORKSHOP_LOGOS_PREFIX.'7_1762800000.html');

        $this->artisan('storage:fix-image-extensions')->assertSuccessful();
        $fixedPath = $workshop->fresh()->logo_path;

        $this->artisan('storage:fix-image-extensions')
            ->expectsOutputToContain('Corrigidos: 0')
            ->expectsOutputToContain('Já certos: 1')
            ->assertSuccessful();

        $this->assertSame($fixedPath, $workshop->fresh()->logo_path);
    }

    /**
     * URL de provedor (Google/Apple, ver `User::resolveAvatarUrl`) é ignorada pelo recorte de
     * prefixo, não por falhar a leitura: sem a guarda ela seria lida do storage, não acharia nada e
     * entraria em "Sem leitura" — ruído num relatório que o operador precisa conseguir ler.
     */
    public function test_an_avatar_that_is_a_provider_url_is_skipped_without_even_being_read(): void
    {
        $user = User::factory()->asUser()->create([
            'avatar' => 'https://lh3.googleusercontent.com/a/conta.html',
        ]);

        $this->artisan('storage:fix-image-extensions')
            ->expectsOutputToContain('Corrigidos: 0')
            ->doesntExpectOutputToContain('Sem leitura')
            ->assertSuccessful();

        $this->assertSame('https://lh3.googleusercontent.com/a/conta.html', $user->fresh()->avatar);
    }

    /**
     * Objeto legível, mas fora do prefixo da família: o comando não é dono dele e não mexe.
     */
    public function test_a_readable_path_outside_the_expected_prefix_is_left_alone(): void
    {
        $outside = 'legado/avatares/9.html';
        $user = User::factory()->asUser()->create();
        Storage::disk('public')->put($outside, $this->pngBytes());
        $user->forceFill(['avatar' => $outside])->save();

        $this->artisan('storage:fix-image-extensions')
            ->expectsOutputToContain('Corrigidos: 0')
            ->assertSuccessful();

        $this->assertSame($outside, $user->fresh()->avatar);
        Storage::disk('public')->assertExists($outside);
        Storage::disk('public')->assertMissing('legado/avatares/9.png');
    }

    public function test_an_avatar_on_the_app_disk_is_corrected(): void
    {
        $user = User::factory()->asUser()->create();
        Storage::disk('public')->put('avatars/'.$user->id.'_1.html', $this->pngBytes());
        $user->forceFill(['avatar' => 'avatars/'.$user->id.'_1.html'])->save();

        $this->artisan('storage:fix-image-extensions')->assertSuccessful();

        $this->assertSame('avatars/'.$user->id.'_1.png', $user->fresh()->avatar);
        Storage::disk('public')->assertExists('avatars/'.$user->id.'_1.png');
        Storage::disk('public')->assertMissing('avatars/'.$user->id.'_1.html');
    }

    public function test_a_vehicle_cover_and_a_blog_cover_are_corrected_too(): void
    {
        $vehicle = Vehicle::factory()->create();
        $coverPath = AppStorage::COVERS_PREFIX.$vehicle->id.'_landscape_1.html';
        Storage::disk('covers')->put($coverPath, $this->pngBytes());
        $vehicle->forceFill(['cover_photo_path' => $coverPath])->save();

        $post = BlogPost::factory()->create();
        $postPath = AppStorage::BLOG_COVERS_PREFIX.$post->id.'_1.svg';
        Storage::disk('covers')->put($postPath, $this->pngBytes());
        $post->forceFill(['cover_photo_path' => $postPath])->save();

        $this->artisan('storage:fix-image-extensions')
            ->expectsOutputToContain('Corrigidos: 2')
            ->assertSuccessful();

        $this->assertSame(AppStorage::COVERS_PREFIX.$vehicle->id.'_landscape_1.png', $vehicle->fresh()->cover_photo_path);
        $this->assertSame(AppStorage::BLOG_COVERS_PREFIX.$post->id.'_1.png', $post->fresh()->cover_photo_path);
    }

    public function test_content_that_is_not_one_of_the_four_types_is_reported_and_left_alone(): void
    {
        $original = AppStorage::WORKSHOP_LOGOS_PREFIX.'7_1762800000.png';
        $user = User::factory()->asWorkshop()->create();
        Storage::disk('covers')->put($original, 'nome,valor'."\n".'a,1');
        $user->workshop->forceFill(['logo_path' => $original])->save();

        $this->artisan('storage:fix-image-extensions')
            ->expectsOutputToContain('Conteúdo não identificado: 1')
            ->assertSuccessful();

        $this->assertSame($original, $user->workshop->fresh()->logo_path);
        Storage::disk('covers')->assertExists($original);
    }

    public function test_a_missing_object_is_reported_and_the_column_is_left_alone(): void
    {
        $original = AppStorage::WORKSHOP_LOGOS_PREFIX.'7_sumiu.html';
        $user = User::factory()->asWorkshop()->create();
        $user->workshop->forceFill(['logo_path' => $original])->save();

        $this->artisan('storage:fix-image-extensions')
            ->expectsOutputToContain('Sem leitura no storage: 1')
            ->assertSuccessful();

        $this->assertSame($original, $user->workshop->fresh()->logo_path);
    }

    /**
     * Dois objetos que diferiam só na extensão: o comando não escolhe por conta própria qual fica.
     */
    public function test_a_taken_destination_is_reported_and_nothing_is_lost(): void
    {
        $original = AppStorage::WORKSHOP_LOGOS_PREFIX.'7_1762800000.html';
        $taken = AppStorage::WORKSHOP_LOGOS_PREFIX.'7_1762800000.png';
        $workshop = $this->workshopWithLogoAt($original);
        Storage::disk('covers')->put($taken, 'outro conteúdo');

        $this->artisan('storage:fix-image-extensions')
            ->expectsOutputToContain('Caminho de destino já ocupado: 1')
            ->assertSuccessful();

        $this->assertSame($original, $workshop->fresh()->logo_path);
        Storage::disk('covers')->assertExists($original);
        $this->assertSame('outro conteúdo', (string) Storage::disk('covers')->get($taken));
    }

    public function test_the_limit_stops_after_the_given_number_and_says_so_once(): void
    {
        foreach ([1, 2, 3] as $n) {
            $user = User::factory()->asWorkshop()->create();
            $path = AppStorage::WORKSHOP_LOGOS_PREFIX.$n.'_1.html';
            Storage::disk('covers')->put($path, $this->pngBytes());
            $user->workshop->forceFill(['logo_path' => $path])->save();
        }

        // Uma tabela depois das oficinas na lista de alvos: o limite tem de parar o laço de fora
        // também, senão cada tabela restante repete o aviso.
        $vehicle = Vehicle::factory()->create();
        $coverPath = AppStorage::COVERS_PREFIX.$vehicle->id.'_landscape_1.html';
        Storage::disk('covers')->put($coverPath, $this->pngBytes());
        $vehicle->forceFill(['cover_photo_path' => $coverPath])->save();

        Artisan::call('storage:fix-image-extensions', ['--limit' => 2]);
        $output = Artisan::output();

        $this->assertStringContainsString('Corrigidos: 2', $output);
        $this->assertSame(1, substr_count($output, 'Limite de 2 alcançado'), 'O aviso de limite sai uma vez.');
        $this->assertStringNotContainsString('vehicles.cover_photo_path', $output, 'Parou antes de abrir a tabela seguinte.');

        $this->assertSame(
            1,
            Workshop::query()->where('logo_path', 'like', '%.html')->count(),
            'Um dos três deve ter ficado para a próxima rodada.',
        );
        $this->assertSame($coverPath, $vehicle->fresh()->cover_photo_path);
    }
}
