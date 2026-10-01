import { elementsWithin } from './ui/dom';

/**
 * Editor de artigo do admin (form[data-admin-blog-editor], resources/views/admin/blog/_form.blade.php):
 *
 * - contadores "42/160" dos campos com [data-char-count] (o elemento
 *   [data-char-counter-for="{id}"] ao lado do rótulo), em text-warning perto do limite;
 * - prévia do endereço público ([data-admin-blog-slug-value]): o slug digitado ou, em branco, o
 *   gerado a partir do título, como Str::slug faz no servidor;
 * - bloco Publicação: o campo de data (data-admin-blog-schedule) só aparece e só é obrigatório em
 *   "Agendar" (publish_mode=schedule);
 * - aba "Pré-visualizar": envia o Markdown do editor para data-preview-url (POST, com o token CSRF)
 *   e mostra o HTML que o servidor devolve, o mesmo de /blog/{slug}. Nada é gravado;
 * - aviso do navegador ao sair da página com alterações não salvas (beforeunload).
 *
 * Idempotente (dataset.adminBlogEditorReady). Sem este script, o formulário funciona do mesmo jeito.
 */

const PREVIEW_PANEL_ID = 'conteudo-previa';
const PREVIEW_ERROR_MESSAGE = 'Não foi possível carregar a pré-visualização. Confira a conexão e abra a aba de novo.';
const PREVIEW_LOADING_MESSAGE = 'Carregando a pré-visualização…';
const NEAR_LIMIT_RATIO = 0.9;

/**
 * Aproximação de Str::slug: sem acento, minúsculas, hífen entre as palavras.
 *
 * @param {string} text
 * @returns {string}
 */
export function slugify(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/@/g, '-at-')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function bindCounters(form) {
    elementsWithin(form, '[data-char-count]').forEach((field) => {
        const counter = field.id ? form.querySelector(`[data-char-counter-for="${CSS.escape(field.id)}"]`) : null;
        const max = Number(field.getAttribute('maxlength'));

        if (!counter || !Number.isFinite(max) || max <= 0) {
            return;
        }

        const update = () => {
            const length = field.value.length;
            counter.textContent = `${length}/${max}`;
            counter.classList.toggle('text-warning', length >= max * NEAR_LIMIT_RATIO);
            counter.classList.toggle('text-subtle-foreground', length < max * NEAR_LIMIT_RATIO);
        };

        field.addEventListener('input', update);
        update();
    });
}

function bindSlugPreview(form) {
    const output = form.querySelector('[data-admin-blog-slug-value]');
    const slugField = form.querySelector('input[name="slug"]');
    const titleField = form.querySelector('input[name="title"]');

    if (!output || !slugField) {
        return;
    }

    const update = () => {
        const slug = slugify(slugField.value.trim() !== '' ? slugField.value : (titleField?.value ?? ''));
        output.textContent = slug !== '' ? slug : '…';
    };

    slugField.addEventListener('input', update);
    titleField?.addEventListener('input', update);
    update();
}

function bindPublishMode(form) {
    const block = form.querySelector('[data-admin-blog-schedule]');
    const dateField = block?.querySelector('input[name="published_at"]');
    const radios = Array.from(form.querySelectorAll('input[type="radio"][name="publish_mode"]'));

    if (!block || !dateField || radios.length === 0) {
        return;
    }

    const update = () => {
        const isScheduling = radios.some((radio) => radio.checked && radio.value === 'schedule');
        block.hidden = !isScheduling;
        dateField.disabled = !isScheduling;
        dateField.required = isScheduling;
    };

    radios.forEach((radio) => radio.addEventListener('change', update));
    update();
}

function bindPreview(form) {
    const url = form.dataset.previewUrl;
    const output = form.querySelector('[data-admin-blog-preview]');
    const source = form.querySelector('textarea[name="content"]');

    if (!url || !output || !source) {
        return;
    }

    let lastRendered = null;
    let activeRequest = null;

    const showMessage = (message) => {
        const paragraph = document.createElement('p');
        paragraph.className = 'text-sm text-muted-foreground';
        paragraph.textContent = message;
        output.replaceChildren(paragraph);
    };

    const render = async () => {
        const content = source.value;

        if (content === lastRendered) {
            return;
        }

        activeRequest?.abort();
        const controller = new AbortController();
        activeRequest = controller;
        output.setAttribute('aria-busy', 'true');
        showMessage(PREVIEW_LOADING_MESSAGE);

        try {
            const body = new FormData();
            body.append('content', content);

            const response = await fetch(url, {
                method: 'POST',
                body,
                headers: {
                    Accept: 'text/html',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(`Blog preview failed with status ${response.status}`);
            }

            // HTML do servidor: o Markdown do próprio admin renderizado por Str::markdown, igual ao
            // artigo publicado. <template> não executa scripts ao interpretar o trecho.
            const template = document.createElement('template');
            template.innerHTML = await response.text();
            output.replaceChildren(template.content);
            lastRendered = content;
        } catch (error) {
            if (controller.signal.aborted) {
                return;
            }

            showMessage(PREVIEW_ERROR_MESSAGE);
        } finally {
            if (activeRequest === controller) {
                activeRequest = null;
                output.setAttribute('aria-busy', 'false');
            }
        }
    };

    form.addEventListener('ui:tab-change', (event) => {
        if (event.detail?.panelId === PREVIEW_PANEL_ID) {
            render();
        }
    });
}

function bindUnsavedWarning(form) {
    let isDirty = false;

    const markDirty = (event) => {
        // Trocar de aba ou de modo de publicação sozinho não é alteração de conteúdo digitado.
        if (event.target instanceof HTMLElement && event.target.matches('input, textarea, select')) {
            isDirty = true;
        }
    };

    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    form.addEventListener('submit', () => {
        isDirty = false;
    });

    window.addEventListener('beforeunload', (event) => {
        if (!isDirty) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
}

/**
 * @param {ParentNode} [root]
 */
export function initAdminBlogEditor(root = document) {
    elementsWithin(root, 'form[data-admin-blog-editor]').forEach((form) => {
        if (form.dataset.adminBlogEditorReady === 'true') {
            return;
        }

        form.dataset.adminBlogEditorReady = 'true';
        bindCounters(form);
        bindSlugPreview(form);
        bindPublishMode(form);
        bindPreview(form);
        bindUnsavedWarning(form);
    });
}
