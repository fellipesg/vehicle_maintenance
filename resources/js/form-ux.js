/**
 * Form UX: máscaras, critérios de senha e validação enquanto digita.
 *
 * Erro só depois de sair do campo: enquanto a pessoa digita pela primeira vez, o campo fica neutro
 * (nada de vermelho na primeira tecla). Depois do primeiro blur, de uma tentativa de envio (submit
 * ou o evento invalid da validação nativa) ou quando o servidor já marcou o campo com aria-invalid,
 * a validação passa a acompanhar cada tecla (data-touched="true"). O estado válido aparece na hora.
 * A lista de critérios da senha continua ao vivo.
 */

/** Pedido para um campo validar já, mesmo sem blur (disparado no envio do formulário). */
const TOUCH_EVENT = 'form-ux:touch';

function digitsOnly(value) {
    return String(value ?? '').replace(/\D/g, '');
}

/**
 * Classes de cor lidas do próprio elemento (data-ok-class, data-error-class, data-idle-class), para
 * a mesma validação servir a fundo claro e escuro. Sem o atributo, vale o padrão de fundo claro.
 */
function stateClasses(element, state, fallback) {
    const value = element.dataset[`${state}Class`];

    return (value ?? fallback).split(/\s+/).filter(Boolean);
}

/** Bordas de estado pelos papéis semânticos (success 6,81:1 e danger 6,42:1 no branco). */
const STATE_BORDER_CLASSES = { valid: 'border-success', invalid: 'border-danger' };

/**
 * Estado da validação enquanto a pessoa digita: data-state (idle | valid | invalid) no campo,
 * aria-invalid="true" só enquanto o valor digitado é inválido e a borda pelos papéis semânticos.
 *
 * A mensagem só vai para a dica antiga, [data-field-hint] ao lado do campo. A dica de <x-ui.field>
 * ({id}-hint) não tem esse atributo e fica como está: ela e o erro do servidor já estão no
 * aria-describedby. aria-invalid vindo do servidor não é tirado aqui; só o que este script pôs.
 */
function setFieldState(input, ok, message) {
    const hint = input.parentElement?.querySelector('[data-field-hint]');
    let state = input.value.length === 0 ? 'idle' : (ok ? 'valid' : 'invalid');

    // Antes do primeiro blur (ou envio), o valor ainda está sendo digitado: sem erro por enquanto.
    if (state === 'invalid' && input.dataset.touched !== 'true') {
        state = 'idle';
    }

    input.dataset.state = state;
    input.classList.remove(...Object.values(STATE_BORDER_CLASSES));

    if (state === 'invalid') {
        input.setAttribute('aria-invalid', 'true');
        input.dataset.liveInvalid = 'true';
    } else if (input.dataset.liveInvalid) {
        input.removeAttribute('aria-invalid');
        delete input.dataset.liveInvalid;
    }

    if (state !== 'idle') {
        input.classList.add(STATE_BORDER_CLASSES[state]);
    }

    if (!hint) {
        return;
    }

    if (state === 'idle') {
        hint.textContent = hint.dataset.defaultHint || '';
        hint.className = stateClasses(hint, 'idle', 'mt-1 text-sm text-muted-foreground').join(' ');
    } else if (state === 'valid') {
        hint.textContent = message || hint.dataset.okHint || 'OK';
        hint.className = stateClasses(hint, 'ok', 'mt-1 text-sm text-success').join(' ');
    } else {
        hint.textContent = message || hint.dataset.errorHint || 'Valor inválido';
        hint.className = stateClasses(hint, 'error', 'mt-1 text-sm text-danger').join(' ');
    }
}

/**
 * No envio, marca os campos do formulário como tocados para o erro aparecer mesmo sem blur. Uma vez
 * por formulário.
 *
 * @param {HTMLFormElement|null|undefined} form
 */
function touchFieldsOnSubmit(form) {
    if (!form || form.dataset.formUxSubmitReady === 'true') {
        return;
    }

    form.dataset.formUxSubmitReady = 'true';
    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-form-ux-field]').forEach((field) => {
            field.dispatchEvent(new CustomEvent(TOUCH_EVENT));
        });
    });
}

/**
 * Liga a validação de um campo: apply() a cada tecla; o blur, o invalid nativo e o envio marcam o
 * campo como tocado antes de validar. Erro que veio do servidor (aria-invalid) já conta como tocado.
 *
 * @param {HTMLInputElement} input
 * @param {() => void} apply
 */
function watchField(input, apply) {
    const touch = () => {
        input.dataset.touched = 'true';
        apply();
    };

    if (input.getAttribute('aria-invalid') === 'true') {
        input.dataset.touched = 'true';
    }

    input.dataset.formUxField = '';
    input.addEventListener('input', apply);
    input.addEventListener('blur', touch);
    input.addEventListener('invalid', touch);
    input.addEventListener(TOUCH_EVENT, touch);
    touchFieldsOnSubmit(input.form);
}

function bindDigitMask(input) {
    const max = Number(input.dataset.maxDigits || input.maxLength || 20);
    const min = Number(input.dataset.minDigits || 0);

    const apply = () => {
        const digits = digitsOnly(input.value).slice(0, max);
        if (input.value !== digits) {
            input.value = digits;
        }
        if (digits.length === 0) {
            setFieldState(input, true, '');
            return;
        }
        const ok = digits.length >= min && digits.length <= max;
        const msg = ok
            ? `${digits.length}/${max} dígitos`
            : `Informe ${min === max ? max : `${min}–${max}`} dígitos (${digits.length}/${max})`;
        setFieldState(input, ok, msg);
    };

    input.setAttribute('inputmode', 'numeric');
    // Não passa por cima de um autocomplete da view (tel-national, postal-code...).
    if (input.getAttribute('autocomplete') === null) {
        input.setAttribute('autocomplete', 'off');
    }
    watchField(input, apply);
    apply();
}

function bindChassisMask(input) {
    const max = Number(input.maxLength || 17);

    const apply = () => {
        let value = String(input.value || '')
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '')
            .slice(0, max);
        input.value = value;
        const counter = input.parentElement?.querySelector('[data-chassis-counter]');
        if (counter) {
            counter.textContent = `${value.length}/${max}`;
        }
        if (!value) {
            setFieldState(input, true, '');
            return;
        }
        const ok = value.length === max;
        setFieldState(
            input,
            ok,
            ok ? 'Chassi completo' : `${value.length}/${max} caracteres`,
        );
    };

    watchField(input, apply);
    apply();
}

function bindPlateMask(input) {
    const apply = () => {
        let value = String(input.value || '')
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '')
            .slice(0, 7);
        input.value = value;
        if (!value) {
            setFieldState(input, true, '');
            return;
        }
        const ok = /^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/.test(value);
        setFieldState(input, ok, ok ? 'Placa válida' : 'Use o padrão ABC1D23 ou ABC1234');
    };
    watchField(input, apply);
    apply();
}

function bindYearField(input) {
    const min = Number(input.min || 1900);
    const max = Number(input.max || new Date().getFullYear() + 1);
    const apply = () => {
        if (!input.value) {
            setFieldState(input, true, '');
            return;
        }
        const year = Number(input.value);
        const ok = Number.isInteger(year) && year >= min && year <= max;
        setFieldState(
            input,
            ok,
            ok ? '' : `Ano entre ${min} e ${max}`,
        );
    };
    watchField(input, apply);
    apply();
}

function bindDocumentMask(input) {
    const apply = () => {
        let digits = digitsOnly(input.value).slice(0, 14);
        let formatted = digits;
        if (digits.length <= 11) {
            formatted = digits
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        } else {
            formatted = digits
                .replace(/^(\d{2})(\d)/, '$1.$2')
                .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1/$2')
                .replace(/(\d{4})(\d)/, '$1-$2');
        }
        input.value = formatted;
        if (!digits) {
            setFieldState(input, true, '');
            return;
        }
        const ok = digits.length === 11 || digits.length === 14;
        setFieldState(input, ok, ok ? 'Documento OK' : 'CPF (11) ou CNPJ (14) dígitos');
    };
    input.setAttribute('inputmode', 'numeric');
    watchField(input, apply);
    apply();
}

function bindPasswordCriteria(form) {
    const password = form.querySelector('[data-password-field]');
    const confirmation = form.querySelector('[data-password-confirmation]');
    const criteria = form.querySelector('[data-password-criteria]');
    if (!password || !criteria) {
        return;
    }

    const items = {
        length: criteria.querySelector('[data-rule="length"]'),
        match: criteria.querySelector('[data-rule="match"]'),
    };

    const okClasses = stateClasses(criteria, 'ok', 'text-success');
    const idleClasses = stateClasses(criteria, 'idle', 'text-muted-foreground');
    const errorClasses = stateClasses(criteria, 'error', 'text-danger');

    /**
     * @param {Element|null} el item da lista ([data-rule])
     * @param {boolean} ok critério atendido
     * @param {boolean} typed já há o que conferir (sem isso o item fica neutro, não vermelho)
     */
    const mark = (el, ok, typed) => {
        if (!el) return;
        const state = ok ? 'ok' : (typed ? 'error' : 'idle');
        el.classList.remove(...okClasses, ...idleClasses, ...errorClasses);
        el.classList.add(...({ ok: okClasses, error: errorClasses, idle: idleClasses })[state]);
        const icon = el.querySelector('[data-rule-icon]');
        if (icon) {
            icon.textContent = ok ? '✓' : '○';
        }
        // O glifo é aria-hidden: o leitor de tela ouve "atendido" ou "pendente" (a lista é aria-live).
        const status = el.querySelector('[data-rule-status]');
        const statusText = ok ? ', atendido' : ', pendente';
        if (status && status.textContent !== statusText) {
            status.textContent = statusText;
        }
    };

    const apply = () => {
        const lengthOk = password.value.length >= 8;
        const matchOk = confirmation
            ? confirmation.value.length > 0 && confirmation.value === password.value
            : true;
        mark(items.length, lengthOk, password.value.length > 0);
        // "Confirmação igual à senha" só é conferida quando a confirmação começa a ser digitada.
        mark(items.match, matchOk, (confirmation?.value.length ?? 0) > 0);
        setFieldState(password, password.value.length === 0 || lengthOk, lengthOk ? '' : 'Mínimo de 8 caracteres');
        if (confirmation) {
            setFieldState(
                confirmation,
                confirmation.value.length === 0 || matchOk,
                matchOk ? 'Senhas iguais' : 'As senhas não coincidem',
            );
        }
    };

    watchField(password, apply);
    if (confirmation) {
        watchField(confirmation, apply);
    }
    criteria.classList.remove('hidden');
    apply();
}

function initFormUx(root = document) {
    root.querySelectorAll('[data-mask="digits"]').forEach(bindDigitMask);
    root.querySelectorAll('[data-mask="plate"]').forEach(bindPlateMask);
    root.querySelectorAll('[data-mask="chassis"]').forEach(bindChassisMask);
    root.querySelectorAll('[data-mask="year"]').forEach(bindYearField);
    root.querySelectorAll('[data-mask="document"]').forEach(bindDocumentMask);
    root.querySelectorAll('form[data-password-form]').forEach(bindPasswordCriteria);
}

export { initFormUx };
