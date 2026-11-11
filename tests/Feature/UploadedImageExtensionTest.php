<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O caminho público é decidido pelo conteúdo, não pelo nome que o cliente enviou.
 *
 * `File::image()` confere o conteúdo e deixa o nome passar, então um PNG de verdade chamado
 * `payload.html` era aceito e gravado como `workshop-logos/1_123.html`. Logos, capas e avatares são
 * servidos direto pelo servidor web ou pelo CDN do bucket, onde a extensão é que decide o
 * Content-Type: aquele arquivo saía como `text/html`, e um PNG carrega texto arbitrário depois do
 * IEND, então o navegador executava o que estivesse lá — na origem do app quando a disk é a local.
 * `.svg` tinha o mesmo caminho; só `.php` e parentes eram barrados, pelo `shouldBlockPhpUpload` do
 * próprio Laravel.
 *
 * Medido antes da correção, no PUT do perfil da oficina: `payload.html` virava
 * `workshop-logos/1_…​.html` e `payload.svg` virava `…​.svg`, os dois com 302 e sem erro de validação.
 */
class UploadedImageExtensionTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '<script>alert(document.domain)</script>';

    /**
     * PNG real com o payload depois do IEND: para o finfo (e para a validação) continua sendo um PNG,
     * porque o que conta é a assinatura. É o arquivo que um `.html` entregaria ao navegador.
     */
    private function pngNamed(string $name, string $mime): UploadedFile
    {
        $image = imagecreatetruecolor(20, 20);
        $path = (string) tempnam(sys_get_temp_dir(), 'upload_ext_');
        imagepng($image, $path);
        imagedestroy($image);
        file_put_contents($path, self::PAYLOAD, FILE_APPEND);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    public function test_workshop_logo_named_as_html_is_stored_as_png(): void
    {
        $this->fakeCoversDisk('r2');

        $user = User::factory()->asWorkshop()->create();
        $workshop = $user->workshop;

        $this->actingAs($user)
            ->put(route('workshop.profile.update'), [
                'name' => $workshop->name,
                'phone' => $workshop->phone,
                'cep' => $workshop->cep,
                'street' => $workshop->street,
                'number' => $workshop->number,
                'neighborhood' => $workshop->neighborhood,
                'city' => $workshop->city,
                'state' => $workshop->state,
                'logo' => $this->pngNamed('payload.html', 'text/html'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.profile.show'));

        $path = $workshop->fresh()->logo_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith(AppStorage::WORKSHOP_LOGOS_PREFIX, $path);
        $this->assertStringEndsWith('.png', $path);
        $this->assertStringNotContainsString('html', $path);
        Storage::disk('r2')->assertExists($path);

        // O arquivo foi gravado como veio: o que muda é só a extensão do caminho, e é ela que decide
        // o Content-Type de quem serve.
        $this->assertStringContainsString(self::PAYLOAD, (string) Storage::disk('r2')->get($path));
    }

    public function test_workshop_logo_named_as_svg_is_stored_as_png(): void
    {
        $this->fakeCoversDisk('r2');

        $user = User::factory()->asWorkshop()->create();
        $workshop = $user->workshop;

        $this->actingAs($user)
            ->put(route('workshop.profile.update'), [
                'name' => $workshop->name,
                'phone' => $workshop->phone,
                'cep' => $workshop->cep,
                'street' => $workshop->street,
                'number' => $workshop->number,
                'neighborhood' => $workshop->neighborhood,
                'city' => $workshop->city,
                'state' => $workshop->state,
                'logo' => $this->pngNamed('payload.svg', 'image/svg+xml'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.png', (string) $workshop->fresh()->logo_path);
    }

    public function test_an_uppercase_name_becomes_a_lowercase_extension(): void
    {
        $this->fakeCoversDisk('r2');

        $user = User::factory()->asWorkshop()->create();
        $workshop = $user->workshop;

        $this->actingAs($user)
            ->put(route('workshop.profile.update'), [
                'name' => $workshop->name,
                'phone' => $workshop->phone,
                'cep' => $workshop->cep,
                'street' => $workshop->street,
                'number' => $workshop->number,
                'neighborhood' => $workshop->neighborhood,
                'city' => $workshop->city,
                'state' => $workshop->state,
                'logo' => UploadedFile::fake()->image('LOGO.JPG', 40, 40),
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.jpg', (string) $workshop->fresh()->logo_path);
    }

    public function test_avatar_named_as_html_is_stored_as_png(): void
    {
        Storage::fake('public');

        $user = $this->actingAsApiUser();

        $this->post('/api/v1/me/avatar', [
            'avatar' => $this->pngNamed('payload.html', 'text/html'),
        ])->assertOk();

        $path = $user->fresh()->avatar;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('avatars/', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_vehicle_cover_named_as_html_is_stored_as_png(): void
    {
        $this->fakeCoversDisk('r2');

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $this->post("/api/v1/vehicles/{$vehicle->id}/cover", [
            'cover' => $this->pngNamed('payload.html', 'text/html'),
        ])->assertOk();

        $vehicle->refresh();

        $this->assertStringEndsWith('.png', (string) $vehicle->cover_photo_path);
        Storage::disk('r2')->assertExists($vehicle->cover_photo_path);
    }

    public function test_blog_cover_named_as_html_is_stored_as_png(): void
    {
        $this->fakeCoversDisk();

        $admin = User::factory()->asUser()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'title' => 'Post com capa',
            'content' => 'Texto.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'cover' => $this->pngNamed('payload.html', 'text/html'),
        ])->assertRedirect();

        $path = BlogPost::firstWhere('slug', 'post-com-capa')?->cover_photo_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith(AppStorage::BLOG_COVERS_PREFIX, $path);
        $this->assertStringEndsWith('.png', $path);
    }
}
