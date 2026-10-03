<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * resources/js/ui/submit-busy.js: depois do envio, o botão que enviou fica ocupado (disabled,
 * aria-busy, rótulo do data-loading-label e círculo no lugar do ícone), o segundo envio do mesmo
 * formulário é barrado e o bfcache devolve tudo. O módulo roda no Node com um DOM mínimo.
 */
class SubmitBusyTest extends TestCase
{
    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_submitter_becomes_busy_after_the_submit_event(): void
    {
        $result = $this->runScenario('busy');

        $this->assertFalse($result['duringDispatch']['disabled'], 'Desabilitar dentro do evento tiraria o botão dos dados enviados.');
        $this->assertTrue($result['after']['disabled']);
        $this->assertSame('true', $result['after']['ariaBusy']);
        $this->assertSame('Entrando…', $result['after']['label']);
        $this->assertTrue($result['after']['iconHidden']);
        $this->assertSame(['spinner', 'icon', 'label'], $result['after']['children']);
        $this->assertSame('size-4', $result['after']['spinnerSize'], 'O círculo copia o tamanho do ícone.');
        $this->assertSame('submitting', $result['after']['form']);
    }

    public function test_second_submit_of_the_same_form_is_blocked(): void
    {
        $result = $this->runScenario('double');

        $this->assertFalse($result['first'], 'O primeiro envio segue.');
        $this->assertTrue($result['second'], 'O segundo envio é cancelado.');
    }

    public function test_submit_cancelled_by_another_script_is_left_alone(): void
    {
        $result = $this->runScenario('prevented');

        $this->assertFalse($result['after']['disabled']);
        $this->assertNull($result['after']['ariaBusy']);
        $this->assertSame('Entrar', $result['after']['label']);
        $this->assertNull($result['after']['form']);
        $this->assertFalse($result['resubmitBlocked'], 'O formulário volta a aceitar envio.');
    }

    public function test_other_window_targets_and_opt_out_are_ignored(): void
    {
        $result = $this->runScenario('ignored');

        $this->assertSame([false, false, false], $result['disabled']);
    }

    public function test_back_forward_cache_releases_the_button(): void
    {
        $result = $this->runScenario('pageshow');

        $this->assertFalse($result['after']['disabled']);
        $this->assertNull($result['after']['ariaBusy']);
        $this->assertSame('Entrar', $result['after']['label']);
        $this->assertFalse($result['after']['iconHidden']);
        $this->assertSame(['icon', 'label'], $result['after']['children']);
        $this->assertNull($result['after']['form']);
    }

    public function test_module_is_started_by_the_bundle(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("import { initSubmitBusy } from './ui/submit-busy';", $app);
        $this->assertMatchesRegularExpression('/^\s+initSubmitBusy\(\);$/m', $app);
    }

    public function test_auth_and_account_buttons_carry_a_loading_label(): void
    {
        foreach (['auth/_login-form', 'auth/register', 'auth/forgot-password', 'auth/reset-password'] as $view) {
            $this->assertMatchesRegularExpression('/<x-ui\.button type="submit"[^>]*loading-label="[^"]+…"/u', file_get_contents(resource_path("views/{$view}.blade.php")), $view);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runScenario(string $scenario): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('Node.js is required to run submit-busy.js.');
        }

        $this->sandbox = storage_path('framework/testing/submit-busy-'.Str::random(12));
        File::ensureDirectoryExists($this->sandbox);
        File::put("{$this->sandbox}/package.json", '{"type": "module"}');
        File::copy(resource_path('js/ui/submit-busy.js'), "{$this->sandbox}/submit-busy.js");
        File::put("{$this->sandbox}/runner.js", <<<'JS'
            const scenario = process.argv[2];
            const timers = [];
            const listeners = { document: {}, window: {} };
            const all = [];

            class Element {
                constructor(tag) {
                    this.tagName = tag.toUpperCase();
                    this.attributes = new Map();
                    this.children = [];
                    this.parent = null;
                    this.dataset = {};
                    this.textContent = '';
                    this.disabled = false;
                    const classes = new Set();
                    this.classList = { add: (name) => classes.add(name), contains: (name) => classes.has(name), [Symbol.iterator]: () => classes.values() };
                    all.push(this);
                }
                get firstElementChild() { return this.children[0] ?? null; }
                set innerHTML(html) {
                    const svg = new Element('svg');
                    svg.parent = this;
                    this.children = [svg];
                }
                get isConnected() { return true; }
                setAttribute(name, value) { this.attributes.set(name, String(value)); }
                getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
                hasAttribute(name) { return this.attributes.has(name); }
                removeAttribute(name) { this.attributes.delete(name); }
                append(child) { child.parent = this; this.children.push(child); }
                prepend(child) { child.parent = this; this.children.unshift(child); }
                before(node) { const siblings = this.parent.children; node.parent = this.parent; siblings.splice(siblings.indexOf(this), 0, node); }
                remove() { const siblings = this.parent.children; siblings.splice(siblings.indexOf(this), 1); }
                descendants() { return this.children.flatMap((child) => [child, ...child.descendants()]); }
                slot() { return this.attributes.get('data-slot') ?? this.dataset.slot; }
                querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; }
                querySelectorAll(selector) {
                    if (selector === '[data-slot="label"]') return this.descendants().filter((el) => el.slot() === 'label');
                    if (selector === ':scope > [data-slot="icon"]') return this.children.filter((el) => el.slot() === 'icon');
                    if (selector === '[data-submit-busy-spinner]') return this.descendants().filter((el) => 'submitBusySpinner' in el.dataset);
                    throw new Error(`selector ${selector}`);
                }
            }
            class HTMLFormElement extends Element {
                get elements() { return this.descendants(); }
            }
            class HTMLButtonElement extends Element {
                get form() { let node = this.parent; while (node && !(node instanceof HTMLFormElement)) node = node.parent; return node; }
                matches() { return true; }
            }
            class HTMLInputElement extends Element {}

            globalThis.HTMLFormElement = HTMLFormElement;
            globalThis.HTMLButtonElement = HTMLButtonElement;
            globalThis.HTMLInputElement = HTMLInputElement;
            globalThis.window = {
                setTimeout: (callback) => timers.push(callback),
                addEventListener: (type, listener) => (listeners.window[type] ??= []).push(listener),
            };
            globalThis.document = {
                createElement: (tag) => new Element(tag),
                addEventListener: (type, listener) => (listeners.document[type] ??= []).push(listener),
                querySelectorAll: (selector) => {
                    if (selector === 'form[data-submit-busy="submitting"]') return all.filter((el) => el instanceof HTMLFormElement && el.getAttribute('data-submit-busy') === 'submitting');
                    if (selector === 'button[data-submit-busy], input[data-submit-busy]') return all.filter((el) => (el instanceof HTMLButtonElement || el instanceof HTMLInputElement) && el.hasAttribute('data-submit-busy'));
                    throw new Error(`selector ${selector}`);
                },
            };

            function makeForm(attributes = {}) {
                const form = new HTMLFormElement('form');
                Object.entries(attributes).forEach(([name, value]) => form.setAttribute(name, value));
                const button = new HTMLButtonElement('button');
                button.setAttribute('type', 'submit');
                button.setAttribute('data-loading-label', 'Entrando…');
                const icon = new Element('svg');
                icon.setAttribute('data-slot', 'icon');
                icon.classList.add('size-4');
                const label = new Element('span');
                label.setAttribute('data-slot', 'label');
                label.textContent = 'Entrar';
                button.append(icon);
                button.append(label);
                form.append(button);
                return { form, button, icon, label };
            }

            function submit(form, submitter, { preventByOther = false } = {}) {
                const event = {
                    target: form,
                    submitter,
                    defaultPrevented: false,
                    preventDefault() { this.defaultPrevented = true; },
                };
                (listeners.document.submit ?? []).forEach((listener) => listener(event));
                if (preventByOther) {
                    event.preventDefault();
                }
                return event;
            }

            const flush = () => { while (timers.length) timers.shift()(); };
            const snapshot = ({ form, button, icon, label }) => ({
                disabled: button.disabled,
                ariaBusy: button.getAttribute('aria-busy'),
                label: label.textContent,
                iconHidden: icon.hasAttribute('hidden'),
                children: button.children.map((child) => child.slot()),
                spinnerSize: [...(button.children.find((child) => child.slot() === 'spinner')?.firstElementChild?.classList ?? [])][0] ?? null,
                form: form.getAttribute('data-submit-busy'),
            });

            const { initSubmitBusy } = await import('./submit-busy.js');
            initSubmitBusy();
            initSubmitBusy();

            const output = {};
            const login = makeForm({ method: 'post' });

            if (scenario === 'busy') {
                submit(login.form, login.button);
                output.duringDispatch = snapshot(login);
                flush();
                output.after = snapshot(login);
            } else if (scenario === 'double') {
                output.first = submit(login.form, login.button).defaultPrevented;
                output.second = submit(login.form, null).defaultPrevented;
            } else if (scenario === 'prevented') {
                submit(login.form, login.button, { preventByOther: true });
                flush();
                output.after = snapshot(login);
                output.resubmitBlocked = submit(login.form, login.button).defaultPrevented;
            } else if (scenario === 'ignored') {
                const blank = makeForm({ method: 'post', target: '_blank' });
                const off = makeForm({ method: 'post', 'data-submit-busy': 'off' });
                const dialog = makeForm({ method: 'dialog' });
                [blank, off, dialog].forEach((item) => submit(item.form, item.button));
                flush();
                output.disabled = [blank, off, dialog].map((item) => item.button.disabled);
            } else if (scenario === 'pageshow') {
                submit(login.form, login.button);
                flush();
                (listeners.window.pageshow ?? []).forEach((listener) => listener({ persisted: true }));
                output.after = snapshot(login);
            }

            if ((listeners.document.submit ?? []).length !== 1) {
                throw new Error('initSubmitBusy() não é idempotente.');
            }

            console.log(JSON.stringify(output));
            JS);

        $process = Process::path($this->sandbox)->run(['node', 'runner.js', $scenario]);

        $this->assertTrue($process->successful(), $process->errorOutput());

        return json_decode(trim($process->output()), true, 512, JSON_THROW_ON_ERROR);
    }
}
