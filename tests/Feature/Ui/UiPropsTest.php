<?php

namespace Tests\Feature\Ui;

use App\Enums\WarrantyScope;
use App\Support\UiProps;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\View\ComponentSlot;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * UiProps valida as props dos componentes x-ui.*: erro sobe em local/testing e só é reportado nos
 * demais ambientes, com o valor padrão no lugar.
 */
class UiPropsTest extends TestCase
{
    public function test_one_of_returns_an_allowed_value(): void
    {
        $this->assertSame('danger', UiProps::oneOf('x-ui.button', 'variant', 'danger', ['primary', 'danger'], 'primary'));
    }

    public function test_one_of_accepts_backed_enums(): void
    {
        $this->assertSame('item', UiProps::oneOf('x-ui.badge', 'variant', WarrantyScope::Item, ['order', 'item'], 'order'));
    }

    public function test_one_of_rejects_unknown_values_in_testing_with_the_allowed_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('x-ui.button: variant "primario" não existe. Use primary, danger.');

        UiProps::oneOf('x-ui.button', 'variant', 'primario', ['primary', 'danger'], 'primary');
    }

    public function test_one_of_reports_and_falls_back_outside_local_and_testing(): void
    {
        Exceptions::fake();
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->assertSame('primary', UiProps::oneOf('x-ui.button', 'variant', 'primario', ['primary', 'danger'], 'primary'));

        Exceptions::assertReported(fn (InvalidArgumentException $exception): bool => str_contains($exception->getMessage(), 'variant "primario"'));
    }

    public function test_required_fails_for_blank_values_and_empty_slots(): void
    {
        UiProps::required('x-ui.stat', 'value', 0);
        UiProps::required('x-ui.stat', 'value', '0');
        UiProps::required('x-ui.card', 'title', new ComponentSlot('Título'));

        $this->assertTrue(UiProps::isBlank(null));
        $this->assertTrue(UiProps::isBlank('   '));
        $this->assertTrue(UiProps::isBlank(new ComponentSlot("  <!-- nada -->\n ")));
        $this->assertFalse(UiProps::isBlank(0));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('x-ui.icon-button precisa de label. O label vira o aria-label.');

        UiProps::required('x-ui.icon-button', 'label', '', 'O label vira o aria-label.');
    }
}
