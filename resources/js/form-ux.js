/**
 * Form UX: máscaras, critérios de senha e validação enquanto digita.
 */
function digitsOnly(value) {
    return String(value ?? '').replace(/\D/g, '');
}

function setFieldState(input, ok, message) {
    const hint = input.parentElement?.querySelector('[data-field-hint]');
    input.classList.remove('border-red-500', 'border-green-500', 'ring-red-200', 'ring-green-200');
    if (input.value.length === 0) {
        if (hint) {
            hint.textContent = hint.dataset.defaultHint || '';
            hint.className = hint.dataset.idleClass || 'mt-1 text-sm text-automotive-500';
        }
        return;
    }
    if (ok) {
        input.classList.add('border-green-500');
        if (hint) {
            hint.textContent = message || hint.dataset.okHint || 'OK';
            hint.className = 'mt-1 text-sm text-green-600';
        }
    } else {
        input.classList.add('border-red-500');
        if (hint) {
            hint.textContent = message || hint.dataset.errorHint || 'Valor inválido';
            hint.className = 'mt-1 text-sm text-red-600';
        }
    }
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
    input.setAttribute('autocomplete', 'off');
    input.addEventListener('input', apply);
    input.addEventListener('blur', apply);
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
    input.addEventListener('input', apply);
    input.addEventListener('blur', apply);
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
    input.addEventListener('input', apply);
    input.addEventListener('blur', apply);
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
    input.addEventListener('input', apply);
    input.addEventListener('blur', apply);
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

    const mark = (el, ok) => {
        if (!el) return;
        el.classList.toggle('text-green-500', ok);
        el.classList.toggle('text-automotive-400', !ok);
        el.classList.toggle('text-red-400', !ok && (password.value.length > 0 || (confirmation?.value.length ?? 0) > 0));
        const icon = el.querySelector('[data-rule-icon]');
        if (icon) {
            icon.textContent = ok ? '✓' : '○';
        }
    };

    const apply = () => {
        const lengthOk = password.value.length >= 8;
        const matchOk = confirmation
            ? confirmation.value.length > 0 && confirmation.value === password.value
            : true;
        mark(items.length, lengthOk);
        mark(items.match, matchOk);
        setFieldState(password, password.value.length === 0 || lengthOk, lengthOk ? '' : 'Mínimo de 8 caracteres');
        if (confirmation) {
            setFieldState(
                confirmation,
                confirmation.value.length === 0 || matchOk,
                matchOk ? 'Senhas iguais' : 'As senhas não coincidem',
            );
        }
    };

    password.addEventListener('input', apply);
    confirmation?.addEventListener('input', apply);
    criteria.classList.remove('hidden');
    apply();
}

function initFormUx(root = document) {
    root.querySelectorAll('[data-mask="digits"]').forEach(bindDigitMask);
    root.querySelectorAll('[data-mask="plate"]').forEach(bindPlateMask);
    root.querySelectorAll('[data-mask="year"]').forEach(bindYearField);
    root.querySelectorAll('[data-mask="document"]').forEach(bindDocumentMask);
    root.querySelectorAll('form[data-password-form]').forEach(bindPasswordCriteria);
}

export { initFormUx };
