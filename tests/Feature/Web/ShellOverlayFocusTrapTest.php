<?php

namespace Tests\Feature\Web;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * O HSOverlay do Preline 4.2 só prende o Tab. O script do shell prende o Shift+Tab no menu mobile e na
 * gaveta do admin: do painel ou do primeiro item, o foco volta para o último item visível.
 * O script roda no Node com um DOM mínimo, o suficiente para os listeners que ele registra.
 */
class ShellOverlayFocusTrapTest extends TestCase
{
    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_shift_tab_from_the_panel_goes_to_the_last_visible_item(): void
    {
        $result = $this->pressKey(['key' => 'Tab', 'shiftKey' => true], focused: 'overlay');

        $this->assertTrue($result['prevented']);
        $this->assertSame('last', $result['focused']);
    }

    public function test_shift_tab_from_the_first_item_wraps_to_the_last_visible_item(): void
    {
        $result = $this->pressKey(['key' => 'Tab', 'shiftKey' => true], focused: 'first');

        $this->assertTrue($result['prevented']);
        $this->assertSame('last', $result['focused']);
    }

    public function test_shift_tab_from_a_middle_item_keeps_the_native_order(): void
    {
        $result = $this->pressKey(['key' => 'Tab', 'shiftKey' => true], focused: 'middle');

        $this->assertFalse($result['prevented']);
        $this->assertSame('middle', $result['focused']);
    }

    public function test_closed_overlay_does_not_trap_focus(): void
    {
        $result = $this->pressKey(['key' => 'Tab', 'shiftKey' => true], focused: 'first', open: false);

        $this->assertFalse($result['prevented']);
        $this->assertSame('first', $result['focused']);
    }

    public function test_plain_tab_is_left_to_preline(): void
    {
        $result = $this->pressKey(['key' => 'Tab', 'shiftKey' => false], focused: 'overlay');

        $this->assertFalse($result['prevented']);
        $this->assertSame('overlay', $result['focused']);
    }

    public function test_enter_on_a_link_still_stops_preline_from_hijacking_it(): void
    {
        $result = $this->pressKey(['key' => 'Enter', 'shiftKey' => false, 'onLink' => true], focused: 'first');

        $this->assertTrue($result['stopped']);
        $this->assertFalse($result['prevented']);
    }

    /**
     * @param  array{key: string, shiftKey: bool, onLink?: bool}  $key
     * @return array{prevented: bool, stopped: bool, focused: string}
     */
    private function pressKey(array $key, string $focused, bool $open = true): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('Node.js is required to run the shell script.');
        }

        $partial = File::get(resource_path('views/layouts/partials/shell-script.blade.php'));
        $this->assertSame(1, preg_match('#<script>(.*)</script>#s', $partial, $matches));

        $this->sandbox = storage_path('framework/testing/shell-script-'.Str::random(12));
        File::ensureDirectoryExists($this->sandbox);
        File::put("{$this->sandbox}/package.json", '{"type": "module"}');
        File::put("{$this->sandbox}/shell.js", $matches[1]);
        File::put("{$this->sandbox}/runner.js", <<<'JS'
            const [keyJson, focusedName, isOpen] = process.argv.slice(2);
            const key = JSON.parse(keyJson);

            class FakeElement {
                constructor(name, { visible = true } = {}) {
                    this.name = name;
                    this.visible = visible;
                    this.hidden = false;
                    this.listeners = {};
                    this.classes = new Set();
                    this.dataset = {};
                    this.classList = { contains: (className) => this.classes.has(className) };
                }

                addEventListener(type, listener) {
                    (this.listeners[type] ??= []).push(listener);
                }

                getClientRects() {
                    return this.visible ? [{}] : [];
                }

                focus() {
                    globalThis.document.activeElement = this;
                }

                closest() {
                    return null;
                }
            }

            const items = {
                first: new FakeElement('first'),
                middle: new FakeElement('middle'),
                last: new FakeElement('last'),
                hiddenAtThisSize: new FakeElement('hiddenAtThisSize', { visible: false }),
            };

            const overlay = new FakeElement('overlay');
            overlay.dataset.shellOverlayCloseFrom = 'lg';
            overlay.querySelectorAll = () => Object.values(items);

            if (isOpen === '1') {
                overlay.classes.add('open');
            }

            globalThis.window = {
                matchMedia: () => ({ addEventListener() {} }),
                location: { origin: 'http://localhost', pathname: '/', search: '' },
            };
            globalThis.document = {
                activeElement: null,
                querySelectorAll: () => [overlay],
                addEventListener() {},
                getElementById: () => null,
            };

            await import('./shell.js');

            (items[focusedName] ?? overlay).focus();

            const link = new FakeElement('link');
            const event = {
                key: key.key,
                shiftKey: key.shiftKey,
                target: { closest: () => (key.onLink ? link : null) },
                prevented: false,
                stopped: false,
                preventDefault() { this.prevented = true; },
                stopPropagation() { this.stopped = true; },
            };

            overlay.listeners.keydown.forEach((listener) => listener(event));

            console.log(JSON.stringify({
                prevented: event.prevented,
                stopped: event.stopped,
                focused: globalThis.document.activeElement.name,
            }));
            JS);

        $result = Process::path($this->sandbox)->run([
            'node',
            'runner.js',
            json_encode($key, JSON_THROW_ON_ERROR),
            $focused,
            $open ? '1' : '0',
        ]);

        $this->assertTrue($result->successful(), $result->errorOutput());

        return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    }
}
