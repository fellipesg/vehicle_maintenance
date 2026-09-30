<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * Validação ao vivo de resources/js/form-ux.js (máscara de dígitos e critérios da senha): data-state
 * e aria-invalid no campo, borda pelos papéis semânticos, e a dica só é reescrita quando é a dica
 * antiga ([data-field-hint]). O erro só aparece depois do primeiro blur (ou de uma tentativa de
 * envio, ou quando o servidor já marcou o campo); o válido aparece na hora (AUTH-10). O módulo roda
 * no Node com um DOM mínimo.
 */
class FormUxFieldStateTest extends TestCase
{
    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_invalid_value_marks_the_field_and_the_legacy_hint_after_blur(): void
    {
        $result = $this->type('123', withLegacyHint: true, blur: true);

        $this->assertSame('invalid', $result['state']);
        $this->assertSame('true', $result['ariaInvalid']);
        $this->assertSame(['border-danger'], $result['stateBorders']);
        $this->assertSame('Informe 11 dígitos (3/11)', $result['hintText']);
        $this->assertSame('mt-1 text-sm text-danger', $result['hintClass']);
        $this->assertSame('true', $result['touched']);
    }

    public function test_invalid_value_stays_neutral_while_the_field_is_first_typed(): void
    {
        $result = $this->type('123', withLegacyHint: true);

        $this->assertSame('idle', $result['state'], 'Nada de erro na primeira tecla.');
        $this->assertNull($result['ariaInvalid']);
        $this->assertSame([], $result['stateBorders']);
        $this->assertSame('11 dígitos', $result['hintText']);
        $this->assertNull($result['touched']);
    }

    public function test_after_blur_the_field_follows_each_keystroke(): void
    {
        $result = $this->type('12345678901', then: '123', withLegacyHint: true, blur: true);

        $this->assertSame('invalid', $result['state']);
        $this->assertSame('true', $result['ariaInvalid']);
    }

    public function test_native_invalid_event_counts_as_a_submit_attempt(): void
    {
        $result = $this->type('123', withLegacyHint: false, event: 'invalid');

        $this->assertSame('invalid', $result['state']);
        $this->assertSame('true', $result['ariaInvalid']);
    }

    public function test_valid_value_shows_at_once_and_clears_the_invalid_state_it_set(): void
    {
        $result = $this->type('123', then: '12345678901', withLegacyHint: true, blur: true);

        $this->assertSame('valid', $result['state']);
        $this->assertNull($result['ariaInvalid']);
        $this->assertSame(['border-success'], $result['stateBorders']);
        $this->assertSame('mt-1 text-sm text-success', $result['hintClass']);

        $this->assertSame('valid', $this->type('12345678901', withLegacyHint: true)['state'], 'O válido não espera o blur.');
    }

    public function test_empty_value_goes_back_to_idle_with_the_default_hint(): void
    {
        $result = $this->type('123', then: '', withLegacyHint: true, blur: true);

        $this->assertSame('idle', $result['state']);
        $this->assertNull($result['ariaInvalid']);
        $this->assertSame([], $result['stateBorders']);
        $this->assertSame('11 dígitos', $result['hintText']);
        $this->assertSame('mt-1 text-sm text-muted-foreground', $result['hintClass']);
    }

    public function test_server_error_is_kept_while_the_value_is_valid(): void
    {
        $result = $this->type('12345678901', withLegacyHint: false, serverInvalid: true);

        $this->assertSame('valid', $result['state']);
        $this->assertSame('true', $result['ariaInvalid'], 'aria-invalid do servidor não é tirado pelo script.');
    }

    public function test_field_marked_by_the_server_validates_without_waiting_for_blur(): void
    {
        $result = $this->type('123', withLegacyHint: false, serverInvalid: true);

        $this->assertSame('invalid', $result['state']);
        $this->assertSame('true', $result['touched']);
    }

    public function test_hint_of_the_field_component_is_not_rewritten(): void
    {
        $result = $this->type('123', withLegacyHint: false, blur: true);

        $this->assertSame('invalid', $result['state']);
        $this->assertSame('true', $result['ariaInvalid']);
        $this->assertNull($result['hintText']);
    }

    public function test_digit_mask_keeps_the_autocomplete_of_the_view(): void
    {
        $this->assertSame('tel-national', $this->type('11', withLegacyHint: false, autocomplete: 'tel-national')['autocomplete']);
        $this->assertSame('off', $this->type('11', withLegacyHint: false)['autocomplete'], 'Sem autocomplete na view, o campo de dígitos desliga o preenchimento.');
    }

    public function test_confirmation_rule_stays_neutral_until_the_confirmation_is_typed(): void
    {
        $result = $this->passwordCriteria(password: 'curta', confirmation: '');

        $this->assertSame('error', $result['length']['state'], 'O comprimento é conferido enquanto a senha é digitada.');
        $this->assertSame('idle', $result['match']['state'], 'Confirmação vazia não fica vermelha na primeira tecla da senha.');
        $this->assertSame(', pendente', $result['match']['status']);
        $this->assertSame('○', $result['match']['icon']);
    }

    public function test_criteria_announce_their_state_in_text(): void
    {
        $result = $this->passwordCriteria(password: 'senha-forte', confirmation: 'senha-forte');

        $this->assertSame('ok', $result['length']['state']);
        $this->assertSame(', atendido', $result['length']['status']);
        $this->assertSame('✓', $result['length']['icon']);
        $this->assertSame('ok', $result['match']['state']);
        $this->assertSame(', atendido', $result['match']['status']);

        $mismatch = $this->passwordCriteria(password: 'senha-forte', confirmation: 'outra');

        $this->assertSame('error', $mismatch['match']['state']);
        $this->assertSame(', pendente', $mismatch['match']['status']);
        $this->assertNull($mismatch['confirmationInvalid'], 'Sem blur, a confirmação ainda não é marcada como inválida.');
    }

    public function test_password_criteria_list_is_a_polite_live_region(): void
    {
        $html = (string) view('auth.partials.password-criteria');

        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertSame(2, substr_count($html, 'aria-atomic="true"'));
        $this->assertSame(2, substr_count($html, '<span class="sr-only" data-rule-status>, pendente</span>'));
        $this->assertSame(2, substr_count($html, '<span data-rule-icon aria-hidden="true">○</span>'));
    }

    /**
     * @return array{state: string, ariaInvalid: ?string, stateBorders: list<string>, hintText: ?string, hintClass: ?string, touched: ?string, autocomplete: ?string}
     */
    private function type(
        string $value,
        ?string $then = null,
        bool $withLegacyHint = true,
        bool $serverInvalid = false,
        bool $blur = false,
        ?string $event = null,
        ?string $autocomplete = null,
    ): array {
        $this->prepareSandbox();

        File::put("{$this->sandbox}/runner.js", <<<'JS'
            import { FakeElement } from './fake-dom.js';

            const [value, then, withLegacyHint, serverInvalid, afterValue, autocomplete] = process.argv.slice(2);

            const input = new FakeElement();
            input.value = '';
            input.maxLength = -1;
            input.dataset.minDigits = '11';
            input.dataset.maxDigits = '11';
            if (serverInvalid === '1') {
                input.setAttribute('aria-invalid', 'true');
            }
            if (autocomplete !== '__none__') {
                input.setAttribute('autocomplete', autocomplete);
            }

            const hint = withLegacyHint === '1' ? new FakeElement() : null;
            if (hint) {
                hint.dataset.defaultHint = '11 dígitos';
                hint.textContent = '11 dígitos';
            }
            input.parentElement = { querySelector: (selector) => (selector === '[data-field-hint]' ? hint : null) };

            const root = {
                querySelectorAll: (selector) => (selector === '[data-mask="digits"]' ? [input] : []),
            };

            const { initFormUx } = await import('./form-ux.js');
            initFormUx(root);

            input.value = value;
            input.dispatch('input');

            if (afterValue !== '__none__') {
                input.dispatch(afterValue);
            }

            if (then !== '__none__') {
                input.value = then;
                input.dispatch('input');
            }

            console.log(JSON.stringify({
                state: input.dataset.state,
                ariaInvalid: input.getAttribute('aria-invalid'),
                stateBorders: [...input.classes].filter((name) => ['border-success', 'border-danger'].includes(name)),
                hintText: hint ? hint.textContent : null,
                hintClass: hint ? hint.className : null,
                touched: input.dataset.touched ?? null,
                autocomplete: input.getAttribute('autocomplete'),
            }));
            JS);

        return $this->runNode([
            $value,
            $then ?? '__none__',
            $withLegacyHint ? '1' : '0',
            $serverInvalid ? '1' : '0',
            $event ?? ($blur ? 'blur' : '__none__'),
            $autocomplete ?? '__none__',
        ]);
    }

    /**
     * Digita a senha e depois a confirmação (só eventos input, sem blur).
     *
     * @return array{length: array{state: string, status: string, icon: string}, match: array{state: string, status: string, icon: string}, confirmationInvalid: ?string}
     */
    private function passwordCriteria(string $password, string $confirmation): array
    {
        $this->prepareSandbox();

        File::put("{$this->sandbox}/runner.js", <<<'JS'
            import { FakeElement } from './fake-dom.js';

            const [passwordValue, confirmationValue] = process.argv.slice(2);

            const rule = () => {
                const item = new FakeElement();
                item.icon = new FakeElement();
                item.icon.textContent = '○';
                item.status = new FakeElement();
                item.status.textContent = ', pendente';
                item.querySelector = (selector) => ({ '[data-rule-icon]': item.icon, '[data-rule-status]': item.status })[selector] ?? null;

                return item;
            };

            const items = { length: rule(), match: rule() };
            const criteria = new FakeElement();
            criteria.dataset = { okClass: 'text-success', idleClass: 'text-muted-foreground', errorClass: 'text-danger' };
            criteria.querySelector = (selector) => ({ '[data-rule="length"]': items.length, '[data-rule="match"]': items.match })[selector] ?? null;

            const password = new FakeElement();
            const confirmation = new FakeElement();
            password.value = '';
            confirmation.value = '';
            password.parentElement = { querySelector: () => null };
            confirmation.parentElement = { querySelector: () => null };

            const form = {
                querySelector: (selector) => ({
                    '[data-password-field]': password,
                    '[data-password-confirmation]': confirmation,
                    '[data-password-criteria]': criteria,
                })[selector] ?? null,
            };

            const root = {
                querySelectorAll: (selector) => (selector === 'form[data-password-form]' ? [form] : []),
            };

            const { initFormUx } = await import('./form-ux.js');
            initFormUx(root);

            password.value = passwordValue;
            password.dispatch('input');
            confirmation.value = confirmationValue;
            confirmation.dispatch('input');

            const stateOf = (item) => {
                if (item.classes.has('text-success')) return 'ok';
                if (item.classes.has('text-danger')) return 'error';
                if (item.classes.has('text-muted-foreground')) return 'idle';

                return 'none';
            };
            const describe = (item) => ({ state: stateOf(item), status: item.status.textContent, icon: item.icon.textContent });

            console.log(JSON.stringify({
                length: describe(items.length),
                match: describe(items.match),
                confirmationInvalid: confirmation.getAttribute('aria-invalid'),
            }));
            JS);

        return $this->runNode([$password, $confirmation]);
    }

    private function prepareSandbox(): void
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('Node.js is required to run form-ux.js.');
        }

        $this->sandbox ??= storage_path('framework/testing/form-ux-'.Str::random(12));
        File::ensureDirectoryExists($this->sandbox);
        File::put("{$this->sandbox}/package.json", '{"type": "module"}');
        File::copy(resource_path('js/form-ux.js'), "{$this->sandbox}/form-ux.js");
        File::put("{$this->sandbox}/fake-dom.js", <<<'JS'
            export class FakeElement {
                constructor() {
                    this.attributes = new Map();
                    this.dataset = {};
                    this.listeners = {};
                    this.classes = new Set();
                    this.className = '';
                    this.textContent = '';
                    const classes = this.classes;
                    this.classList = {
                        add: (...names) => names.forEach((name) => classes.add(name)),
                        remove: (...names) => names.forEach((name) => classes.delete(name)),
                        contains: (name) => classes.has(name),
                    };
                }

                setAttribute(name, attributeValue) { this.attributes.set(name, String(attributeValue)); }
                getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
                removeAttribute(name) { this.attributes.delete(name); }
                addEventListener(type, listener) { (this.listeners[type] ??= []).push(listener); }
                dispatch(type) { (this.listeners[type] ?? []).forEach((listener) => listener({ type })); }
            }
            JS);
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    private function runNode(array $arguments): array
    {
        $process = Process::path($this->sandbox)->run(['node', 'runner.js', ...$arguments]);

        $this->assertTrue($process->successful(), $process->errorOutput());

        return json_decode(trim($process->output()), true, 512, JSON_THROW_ON_ERROR);
    }
}
