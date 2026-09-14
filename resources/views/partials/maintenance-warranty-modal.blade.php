<div id="warranty-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" aria-hidden="true">
    <div class="absolute inset-0 bg-automotive-900/50" data-warranty-modal-backdrop></div>
    <div class="relative z-10 w-full max-w-md rounded-xl border border-automotive-200 bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="warranty-modal-title">
        <h3 id="warranty-modal-title" class="text-lg font-semibold">Garantia do item</h3>
        <p class="mt-1 text-sm text-automotive-600">Informe o período de cobertura da garantia desta peça ou serviço.</p>
        <div class="mt-4 space-y-3">
            <div>
                <label class="form-label" for="warranty_starts_at_modal">Início da garantia</label>
                <input type="date" id="warranty_starts_at_modal" class="form-input">
            </div>
            <div>
                <label class="form-label" for="warranty_ends_at_modal">Fim da garantia</label>
                <input type="date" id="warranty_ends_at_modal" class="form-input">
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" id="warranty-modal-cancel" class="btn-secondary">Cancelar</button>
            <button type="button" id="warranty-modal-save" class="btn-primary">Salvar garantia</button>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const list = document.getElementById('maintenance-items-list');
                const template = document.getElementById('maintenance-item-row-template');
                const addButton = document.getElementById('add-maintenance-item');
                const modal = document.getElementById('warranty-modal');
                const startsModal = document.getElementById('warranty_starts_at_modal');
                const endsModal = document.getElementById('warranty_ends_at_modal');
                const saveWarranty = document.getElementById('warranty-modal-save');
                const cancelWarranty = document.getElementById('warranty-modal-cancel');
                const backdrop = modal?.querySelector('[data-warranty-modal-backdrop]');

                if (!list || !template || !addButton || !modal || !startsModal || !endsModal) {
                    return;
                }

                let activeWarrantyRow = null;

                const openWarrantyModal = (row) => {
                    activeWarrantyRow = row;
                    const startsInput = row.querySelector('[data-warranty-starts]');
                    const endsInput = row.querySelector('[data-warranty-ends]');
                    const warrantyCheckbox = row.querySelector('[data-warranty-checkbox]');

                    startsModal.value = startsInput?.value || '';
                    endsModal.value = endsInput?.value || '';
                    if (warrantyCheckbox) {
                        warrantyCheckbox.checked = true;
                    }

                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.setAttribute('aria-hidden', 'false');
                    startsModal.focus();
                };

                const closeWarrantyModal = (uncheckIfEmpty = true) => {
                    if (uncheckIfEmpty && activeWarrantyRow) {
                        const warrantyCheckbox = activeWarrantyRow.querySelector('[data-warranty-checkbox]');
                        const startsInput = activeWarrantyRow.querySelector('[data-warranty-starts]');
                        if (warrantyCheckbox && !startsInput?.value) {
                            warrantyCheckbox.checked = false;
                        }
                    }

                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    modal.setAttribute('aria-hidden', 'true');
                    activeWarrantyRow = null;
                };

                const reindexRows = () => {
                    list.querySelectorAll('[data-item-row]').forEach((row, index) => {
                        row.dataset.index = String(index);
                        row.querySelectorAll('[name]').forEach((input) => {
                            input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
                        });
                        const title = row.querySelector('[data-item-title]');
                        if (title) {
                            title.textContent = `Item ${index + 1}`;
                        }
                    });
                };

                list.addEventListener('click', (event) => {
                    const removeBtn = event.target.closest('[data-remove-item]');
                    if (removeBtn) {
                        const row = removeBtn.closest('[data-item-row]');
                        if (!row) {
                            return;
                        }

                        if (list.querySelectorAll('[data-item-row]').length <= 1) {
                            row.querySelectorAll('input:not([type=hidden]), textarea').forEach((el) => {
                                if (el.type === 'checkbox') {
                                    el.checked = false;
                                } else {
                                    el.value = '';
                                }
                            });
                            const warrantySummary = row.querySelector('[data-warranty-summary]');
                            warrantySummary?.classList.add('hidden');
                            if (warrantySummary) {
                                warrantySummary.textContent = '';
                            }
                            return;
                        }

                        row.remove();
                        reindexRows();
                        return;
                    }

                    const editBtn = event.target.closest('[data-edit-warranty]');
                    if (editBtn) {
                        const row = editBtn.closest('[data-item-row]');
                        if (row) {
                            openWarrantyModal(row);
                        }
                    }
                });

                list.addEventListener('change', (event) => {
                    const warrantyCheckbox = event.target.closest('[data-warranty-checkbox]');
                    if (!warrantyCheckbox) {
                        return;
                    }

                    const row = warrantyCheckbox.closest('[data-item-row]');
                    if (!row) {
                        return;
                    }

                    if (warrantyCheckbox.checked) {
                        openWarrantyModal(row);
                        return;
                    }

                    const startsInput = row.querySelector('[data-warranty-starts]');
                    const endsInput = row.querySelector('[data-warranty-ends]');
                    const warrantySummary = row.querySelector('[data-warranty-summary]');

                    if (startsInput) {
                        startsInput.value = '';
                    }
                    if (endsInput) {
                        endsInput.value = '';
                    }
                    warrantySummary?.classList.add('hidden');
                    if (warrantySummary) {
                        warrantySummary.textContent = '';
                    }
                });

                saveWarranty?.addEventListener('click', () => {
                    if (!activeWarrantyRow || !startsModal.value || !endsModal.value) {
                        alert('Informe a data inicial e final da garantia.');
                        return;
                    }

                    if (endsModal.value < startsModal.value) {
                        alert('A data final deve ser igual ou posterior à data inicial.');
                        return;
                    }

                    const startsInput = activeWarrantyRow.querySelector('[data-warranty-starts]');
                    const endsInput = activeWarrantyRow.querySelector('[data-warranty-ends]');
                    const warrantySummary = activeWarrantyRow.querySelector('[data-warranty-summary]');
                    const warrantyCheckbox = activeWarrantyRow.querySelector('[data-warranty-checkbox]');

                    if (startsInput) {
                        startsInput.value = startsModal.value;
                    }
                    if (endsInput) {
                        endsInput.value = endsModal.value;
                    }
                    if (warrantyCheckbox) {
                        warrantyCheckbox.checked = true;
                    }

                    if (warrantySummary) {
                        const start = new Date(startsModal.value + 'T00:00:00');
                        const end = new Date(endsModal.value + 'T00:00:00');
                        const fmt = (date) => date.toLocaleDateString('pt-BR');
                        warrantySummary.textContent = `Garantia: ${fmt(start)} — ${fmt(end)}`;
                        warrantySummary.classList.remove('hidden');
                    }

                    closeWarrantyModal(false);
                });

                cancelWarranty?.addEventListener('click', () => closeWarrantyModal(true));
                backdrop?.addEventListener('click', () => closeWarrantyModal(true));

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                        closeWarrantyModal(true);
                    }
                });

                addButton.addEventListener('click', () => {
                    const index = list.querySelectorAll('[data-item-row]').length;
                    const html = template.innerHTML.replaceAll('__INDEX__', String(index));
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html.trim();
                    const row = wrapper.firstElementChild;
                    if (row) {
                        list.appendChild(row);
                        reindexRows();
                    }
                });
            });
        </script>
    @endpush
@endonce
