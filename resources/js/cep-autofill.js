/**
 * Preenche rua, bairro, cidade e UF a partir do CEP, nos formulários de endereço (Minha loja e
 * Minha oficina). Age só onde encontra [data-cep-autofill].
 *
 * Contrato no Blade:
 *   - o container do formulário tem data-cep-autofill
 *   - o campo de CEP tem data-cep-input (a máscara data-mask="digits" do form-ux.js continua valendo)
 *   - cada destino tem data-cep-target="street|neighborhood|city|state"
 *   - o campo do número pode ter data-cep-focus, para receber o foco depois do preenchimento
 *   - um elemento com data-cep-status recebe a mensagem (aria-live no Blade)
 *
 * A consulta é feita pelo navegador no ViaCEP, sem chave. Se o serviço cair ou o CEP não existir, a
 * tela avisa e o preenchimento manual continua valendo: nada aqui bloqueia o envio do formulário.
 */

const LOOKUP_URL = (cep) => `https://viacep.com.br/ws/${cep}/json/`;

/** Campos do ViaCEP que alimentam cada data-cep-target. */
const FIELD_MAP = {
    street: 'logradouro',
    neighborhood: 'bairro',
    city: 'localidade',
    state: 'uf',
};

function digitsOnly(value) {
    return String(value ?? '').replace(/\D/g, '');
}

/**
 * Verdadeiro só enquanto este script escreve num campo, para distinguir os eventos que ele mesmo
 * dispara dos que vêm de quem está digitando.
 */
let filling = false;

/**
 * Só sobrescreve o que está vazio ou o que este script mesmo preencheu e ninguém editou depois: o
 * que a pessoa digitou à mão fica, mesmo que ela troque o CEP em seguida.
 */
function fillField(field, value) {
    if (!field) {
        return;
    }

    const typedByPerson = field.value.trim() !== '' && field.dataset.cepFilled !== 'true';

    if (typedByPerson || value === '') {
        return;
    }

    filling = true;
    field.value = value;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
    filling = false;

    field.dataset.cepFilled = 'true';
}

/**
 * Edição à mão num campo preenchido pelo CEP devolve ele para a pessoa: a próxima busca não o toca.
 */
function releaseOnManualEdit(field) {
    field?.addEventListener('input', () => {
        if (!filling) {
            field.dataset.cepFilled = 'false';
        }
    });
}

function setStatus(status, message) {
    if (status) {
        status.textContent = message;
    }
}

function initRoot(root) {
    if (root.dataset.cepAutofillReady === 'true') {
        return;
    }

    const input = root.querySelector('[data-cep-input]');

    if (!input) {
        return;
    }

    root.dataset.cepAutofillReady = 'true';

    const status = root.querySelector('[data-cep-status]');
    const targets = {};

    for (const key of Object.keys(FIELD_MAP)) {
        targets[key] = root.querySelector(`[data-cep-target="${key}"]`);
        releaseOnManualEdit(targets[key]);
    }

    let lastLookup = '';
    let pending = null;

    const lookup = async () => {
        const cep = digitsOnly(input.value);

        if (cep.length !== 8) {
            lastLookup = '';

            return;
        }

        if (cep === lastLookup) {
            return;
        }

        lastLookup = cep;
        pending?.abort();
        pending = new AbortController();
        setStatus(status, 'Buscando endereço…');

        try {
            const response = await fetch(LOOKUP_URL(cep), { signal: pending.signal });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const address = await response.json();

            if (address.erro) {
                setStatus(status, 'CEP não encontrado. Preencha o endereço à mão.');

                return;
            }

            for (const [key, source] of Object.entries(FIELD_MAP)) {
                fillField(targets[key], String(address[source] ?? '').trim());
            }

            setStatus(status, 'Endereço preenchido pelo CEP. Confira antes de salvar.');
            root.querySelector('[data-cep-focus]')?.focus();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            // Uma busca que falha nunca pode atrapalhar quem está digitando o endereço.
            lastLookup = '';
            setStatus(status, 'Não foi possível buscar o CEP agora. Preencha o endereço à mão.');
        }
    };

    input.addEventListener('input', lookup);
    input.addEventListener('change', lookup);
    input.addEventListener('blur', lookup);
}

export function initCepAutofill(root = document) {
    root.querySelectorAll('[data-cep-autofill]').forEach(initRoot);
}
