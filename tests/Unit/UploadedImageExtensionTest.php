<?php

namespace Tests\Unit;

use App\Support\UploadedImageExtension;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class UploadedImageExtensionTest extends TestCase
{
    private function file(string $name, string $contents): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ext_unit_');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    public function test_the_content_decides_the_extension_not_the_name(): void
    {
        $this->assertSame('png', UploadedImageExtension::for($this->file('payload.html', $this->png())));
        $this->assertSame('png', UploadedImageExtension::for($this->file('payload.svg', $this->png())));
        $this->assertSame('png', UploadedImageExtension::for($this->file('sem-extensao', $this->png())));
    }

    /**
     * Conteúdo fora da lista nunca chega aqui pela web (a validação só aceita jpg, jpeg, png e webp),
     * mas o fallback existe para não deixar o caminho sem extensão nem com a do cliente se alguém
     * chamar o serviço por fora.
     */
    public function test_content_outside_the_list_falls_back_instead_of_trusting_the_name(): void
    {
        $this->assertSame(
            UploadedImageExtension::FALLBACK,
            UploadedImageExtension::for($this->file('planilha.png', 'nome,valor'."\n".'a,1')),
        );
    }
}
