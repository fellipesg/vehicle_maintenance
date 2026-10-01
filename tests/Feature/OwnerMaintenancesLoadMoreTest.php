<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * "Carregar mais" da lista de manutenções do proprietário: o contador fica num role="status" que
 * sobrevive entre as páginas e o foco volta ao botão (ou ao primeiro item novo na última página)
 * depois que o botão desabilitado perde o foco. O módulo roda no Node com um DOM mínimo.
 */
class OwnerMaintenancesLoadMoreTest extends TestCase
{
    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_status_is_a_persistent_live_region_and_focus_returns_to_the_button(): void
    {
        $output = $this->runScenario();

        $this->assertSame('status', $output['firstRender']['role']);
        $this->assertSame('Mostrando 15 de 40', $output['firstRender']['status']);
        $this->assertSame('Carregar mais', $output['firstRender']['button']);

        $this->assertTrue($output['whileLoading']['disabled']);
        $this->assertSame('Carregando...', $output['whileLoading']['button']);
        $this->assertSame(1, $output['whileLoading']['loadCalls']);
        $this->assertSame('body', $output['whileLoading']['focused']);

        $this->assertTrue($output['secondRender']['sameStatusNode'], 'O role="status" precisa continuar no DOM para o leitor anunciar a nova contagem.');
        $this->assertSame('Mostrando 30 de 40', $output['secondRender']['status']);
        $this->assertFalse($output['secondRender']['disabled']);
        $this->assertSame('Carregar mais', $output['secondRender']['button']);
        $this->assertSame('load-more', $output['secondRender']['focused']);
    }

    public function test_last_page_removes_the_button_and_focuses_the_first_new_row(): void
    {
        $output = $this->runScenario();

        $this->assertSame(0, $output['lastRender']['pagerChildren']);
        $this->assertSame('row-30', $output['lastRender']['focused']);
    }

    public function test_pager_is_rebuilt_after_an_error_replaced_its_content(): void
    {
        $output = $this->runScenario();

        $this->assertSame('Mostrando 30 de 45', $output['afterError']['status']);
        $this->assertSame('load-more', $output['afterError']['focused']);
    }

    public function test_owner_maintenances_list_is_paginated_on_the_server_now(): void
    {
        $portal = File::get(resource_path('js/user-portal.js'));

        // A lista do proprietário passou a ser renderizada no servidor, com a paginação do
        // x-maintenance.list: o portal não carrega mais páginas por JS.
        $this->assertStringNotContainsString('createLoadMorePager', $portal);
        $this->assertStringNotContainsString('data-maintenances-more', File::get(resource_path('views/user/maintenances/index.blade.php')));
        $this->assertStringContainsString('->paginate(self::PER_PAGE)', File::get(app_path('Http/Controllers/Web/User/MaintenanceController.php')));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function runScenario(): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('Node.js is required to run the load more module.');
        }

        $this->sandbox = storage_path('framework/testing/load-more-'.Str::random(12));
        File::ensureDirectoryExists("{$this->sandbox}/utils");
        File::ensureDirectoryExists("{$this->sandbox}/ui");
        File::put("{$this->sandbox}/package.json", '{"type": "module"}');
        // O Vite resolve o import sem extensão; o Node precisa do ".js".
        File::put("{$this->sandbox}/utils/load-more.js", str_replace("from '../ui/button';", "from '../ui/button.js';", File::get(resource_path('js/utils/load-more.js'))));
        File::put("{$this->sandbox}/ui/button.js", File::get(resource_path('js/ui/button.js')));
        File::put("{$this->sandbox}/runner.js", <<<'JS'
            class FakeElement {
                constructor(name) {
                    this.name = name;
                    this.attributes = {};
                    this.children = [];
                    this.parent = null;
                    this.listeners = {};
                    this.textContent = '';
                    this.isDisabled = false;
                }

                get isConnected() {
                    return this.parent === page || (this.parent !== null && this.parent.isConnected);
                }

                get disabled() {
                    return this.isDisabled;
                }

                set disabled(value) {
                    this.isDisabled = value;

                    // Como no navegador: o controle desabilitado perde o foco.
                    if (value && document.activeElement === this) {
                        document.activeElement = body;
                    }
                }

                setAttribute(attribute, value) {
                    this.attributes[attribute] = String(value);

                    if (attribute === 'data-load-more') {
                        this.name = 'load-more';
                    }
                }

                getAttribute(attribute) {
                    return this.attributes[attribute] ?? null;
                }

                addEventListener(type, listener) {
                    (this.listeners[type] ??= []).push(listener);
                }

                click() {
                    if (!this.disabled) {
                        (this.listeners.click ?? []).forEach((listener) => listener({ currentTarget: this }));
                    }
                }

                replaceChildren(...nodes) {
                    this.children.forEach((child) => { child.parent = null; });
                    this.children = [];
                    nodes.forEach((node) => {
                        node.parent = this;
                        this.children.push(node);
                    });
                }

                focus() {
                    document.activeElement = this;
                }
            }

            const page = new FakeElement('page');
            const body = new FakeElement('body');
            globalThis.document = {
                activeElement: body,
                createElement: (tag) => new FakeElement(tag),
            };

            const { createLoadMorePager } = await import('./utils/load-more.js');

            const pager = new FakeElement('pager');
            pager.parent = page;
            const list = new FakeElement('list');
            list.parent = page;
            list.replaceChildren(...Array.from({ length: 15 }, (_, index) => new FakeElement(`row-${index}`)));

            let loadCalls = 0;
            const control = createLoadMorePager(pager, () => { loadCalls += 1; });
            const snapshot = () => {
                const [status, button] = pager.children;

                return {
                    role: status?.getAttribute('role') ?? null,
                    status: status?.textContent ?? null,
                    button: button?.textContent ?? null,
                    disabled: button?.disabled ?? null,
                    pagerChildren: pager.children.length,
                    focused: document.activeElement.name,
                    loadCalls,
                };
            };
            const appendRows = (from, to) => {
                list.replaceChildren(
                    ...list.children,
                    ...Array.from({ length: to - from }, (_, index) => new FakeElement(`row-${from + index}`)),
                );
            };

            const output = {};

            control.render({ shown: 15, total: 40, hasMore: true });
            output.firstRender = snapshot();
            const firstStatus = pager.children[0];

            pager.children[1].focus();
            pager.children[1].click();
            output.whileLoading = snapshot();

            appendRows(15, 30);
            control.render({ shown: 30, total: 40, hasMore: true });
            control.focusAfterAppend(list, 15);
            output.secondRender = { ...snapshot(), sameStatusNode: pager.children[0] === firstStatus };

            pager.children[1].click();
            appendRows(30, 40);
            control.render({ shown: 40, total: 40, hasMore: false });
            control.focusAfterAppend(list, 30);
            output.lastRender = snapshot();

            const retryPager = new FakeElement('pager');
            retryPager.parent = page;
            const retryControl = createLoadMorePager(retryPager, () => {});
            retryControl.render({ shown: 15, total: 45, hasMore: true });
            retryPager.replaceChildren(new FakeElement('error'));
            document.activeElement = body;
            retryControl.render({ shown: 30, total: 45, hasMore: true });
            retryControl.focusAfterAppend(list, 15);
            output.afterError = {
                status: retryPager.children[0].textContent,
                focused: document.activeElement.name,
            };

            console.log(JSON.stringify(output));
            JS);

        $result = Process::path($this->sandbox)->run(['node', 'runner.js']);

        $this->assertTrue($result->successful(), $result->errorOutput());

        return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    }
}
