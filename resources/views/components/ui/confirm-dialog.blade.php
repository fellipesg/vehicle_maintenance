{{--
    Diálogo de confirmação único da página, usado por resources/js/ui/confirm.js no lugar da caixa
    de confirmação nativa do navegador. Vai uma vez em cada layout: <x-ui.confirm-dialog />.

    Quem pede confirmação é o próprio elemento, com data-attributes (os textos passam pelo Blade,
    então saem escapados):
    - data-confirm="Mensagem": obrigatório. Num <form>, vale para qualquer envio dele; num botão de
      envio, só para aquele botão; num link ou num botão comum, para o clique.
    - data-confirm-title: título (padrão "Confirmar ação").
    - data-confirm-action-label: rótulo do botão que confirma (padrão "Confirmar").
    - data-confirm-cancel-label: rótulo do botão que desiste (padrão "Cancelar").
    - data-confirm-variant="danger": botão de confirmar em vermelho, para excluir ou desfazer.

    O foco abre em "Cancelar". Esc e "Cancelar" desistem; só "Confirmar" deixa o envio seguir. Sem
    JavaScript, o formulário envia direto, sem confirmação. Os dois botões são <x-ui.button>; com
    data-confirm-variant="danger" o confirm.js troca o "Confirmar" para a variante danger
    (setButtonVariant de resources/js/ui/button.js).

    Ex.:
    <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}"
          data-confirm="A marca e os modelos dela serão excluídos. Não é possível desfazer."
          data-confirm-title="Excluir a marca {{ $brand->name }}?"
          data-confirm-action-label="Excluir marca"
          data-confirm-variant="danger">
        @csrf @method('DELETE')
        <x-ui.button type="submit" variant="danger" icon="trash">Excluir marca</x-ui.button>
    </form>

    Pelo JS: const confirmed = await window.revisalogConfirm({ title, message, confirmLabel, variant: 'danger' }).

    Prop id: só para ter mais de um na página (raro). O padrão é "confirmacao".
--}}
@props([
    'id' => 'confirmacao',
])
<x-ui.dialog
    :id="$id"
    title="Confirmar ação"
    description="Deseja continuar?"
    size="sm"
    role="alertdialog"
    :close-button="false"
    :close-on-backdrop="false"
    data-ui-confirm-dialog
    {{ $attributes }}
>
    <x-slot:footer>
        <x-ui.button variant="secondary" data-confirm-cancel data-dialog-close="cancelar" data-dialog-initial-focus>Cancelar</x-ui.button>
        <x-ui.button data-confirm-accept data-dialog-close="confirmar">Confirmar</x-ui.button>
    </x-slot:footer>
</x-ui.dialog>
