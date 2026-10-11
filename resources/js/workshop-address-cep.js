import { toast } from './ui/toast';

/**
 * Perfil da oficina: o CEP preenche rua, bairro, cidade e UF (ViaCEP).
 *
 * Só age onde acha [data-cep-autofill] — hoje o campo de CEP do formulário da oficina. Os outros
 * campos são procurados pelo name dentro do mesmo formulário e o que não existir é ignorado, então
 * o módulo serve a qualquer formulário que ganhe o atributo.
 *
 * Idempotente (dataset.cepAutofillReady). Sem este script o formulário continua inteiro: o
 * preenchimento é conveniência e qualquer falha de rede passa em silêncio.
 */

const VIACEP_URL = 'https://viacep.com.br/ws';
const CEP_DIGITS = 8;

/** name do campo no formulário => chave correspondente na resposta do ViaCEP. */
const FIELDS = {
    street: 'logradouro',
    neighborhood: 'bairro',
    city: 'localidade',
    state: 'uf',
};

function digitsOnly(value) {
    return String(value ?? '').replace(/\D/g, '');
}

/**
 * Preenche o campo e avisa a validação ao vivo (form-ux.js) de que o valor mudou: sem o evento, um
 * campo obrigatório preenchido por aqui continuaria contando como vazio.
 */
function fillField(field, value) {
    if (!field || value === '') {
        return;
    }

    if (field.tagName === 'SELECT') {
        if (![...field.options].some((option) => option.value === value)) {
            return;
        }

        field.value = value;
        field.dispatchEvent(new Event('change', { bubbles: true }));

        return;
    }

    field.value = value;
    field.dispatchEvent(new Event('input', { bubbles: true }));
}

/** Endereço do ViaCEP, ou null quando o CEP não existe. Erro de rede/servidor sobe como exceção. */
async function fetchAddress(cep, signal) {
    const response = await fetch(`${VIACEP_URL}/${cep}/json/`, { signal });

    if (!response.ok) {
        throw new Error(`ViaCEP respondeu ${response.status}`);
    }

    const data = await response.json();

    // CEP inexistente volta como 200 com {"erro": true}.
    return data?.erro ? null : data;
}

function bindCepAutofill(input) {
    if (input.dataset.cepAutofillReady === 'true') {
        return;
    }

    input.dataset.cepAutofillReady = 'true';

    const form = input.form;

    if (!form) {
        return;
    }

    let lastQueried = null;
    let pending = null;

    input.addEventListener('input', async () => {
        const cep = digitsOnly(input.value);

        if (cep.length !== CEP_DIGITS) {
            lastQueried = null;

            return;
        }

        if (cep === lastQueried) {
            return;
        }

        lastQueried = cep;

        // Resposta atrasada não pode sobrescrever o endereço de um CEP digitado depois.
        pending?.abort();
        pending = new AbortController();

        try {
            const address = await fetchAddress(cep, pending.signal);

            if (address === null) {
                toast({
                    title: 'CEP não encontrado',
                    description: 'Confira o número ou preencha o endereço à mão.',
                    variant: 'warning',
                    duration: 5000,
                });

                return;
            }

            Object.entries(FIELDS).forEach(([name, key]) => {
                fillField(form.elements[name], String(address[key] ?? '').trim());
            });
        } catch (error) {
            // Cancelamento é esperado; falha de rede não pode atrapalhar quem está preenchendo.
        }
    });
}

export function initWorkshopAddressCep(root = document) {
    root.querySelectorAll('[data-cep-autofill]').forEach(bindCepAutofill);
}
