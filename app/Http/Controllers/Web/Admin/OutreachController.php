<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\WorkshopProspectStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ImportWorkshopProspectsCsv;
use App\Models\EmailSuppression;
use App\Models\WorkshopProspect;
use App\Services\Outreach\OutreachDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutreachController extends Controller
{
    /**
     * Error bag do formulário de importação (a página tem mais de um formulário).
     */
    public const IMPORT_BAG = 'importProspects';

    public function index(Request $request, OutreachDispatcher $dispatcher): View
    {
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $status = WorkshopProspectStatus::tryFrom((string) $request->query('situacao'));

        $prospects = WorkshopProspect::query()
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';
                $digits = preg_replace('/\D/', '', $search) ?? '';
                $query->where(function (Builder $terms) use ($like, $digits): void {
                    $terms->whereRaw('lower(trade_name) like ?', [$like])
                        ->orWhereRaw('lower(legal_name) like ?', [$like])
                        ->orWhereRaw('lower(email) like ?', [$like]);
                    if ($digits !== '') {
                        $terms->orWhere('cnpj', 'like', "%{$digits}%");
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $byStatus = fn (WorkshopProspectStatus $case): int => WorkshopProspect::query()->where('status', $case)->count();

        return view('admin.outreach.index', [
            'prospects' => $prospects,
            'search' => $search,
            'status' => $status,
            'statuses' => collect(WorkshopProspectStatus::cases())->mapWithKeys(fn (WorkshopProspectStatus $case) => [$case->value => $case->label()])->all(),
            'funnel' => [
                'Pendentes' => $byStatus(WorkshopProspectStatus::Pending),
                'Enviados' => WorkshopProspect::query()->whereNotNull('first_sent_at')->count(),
                'Follow-up' => WorkshopProspect::query()->whereNotNull('follow_up_sent_at')->count(),
                'Clicaram' => WorkshopProspect::query()->whereNotNull('clicked_at')->count(),
                'Responderam' => WorkshopProspect::query()->whereNotNull('replied_at')->count(),
                'Convertidas' => WorkshopProspect::query()->whereNotNull('converted_at')->count(),
                'Descadastradas' => $byStatus(WorkshopProspectStatus::Unsubscribed),
                'Devolvidas' => $byStatus(WorkshopProspectStatus::Bounced),
                'Falhas' => $byStatus(WorkshopProspectStatus::Failed),
            ],
            'sentToday' => $dispatcher->sentToday(),
            'dailyLimit' => (int) config('outreach.daily_limit'),
            'enabled' => (bool) config('outreach.enabled'),
            'paused' => $dispatcher->isPaused(),
            'active' => $dispatcher->isActive(),
        ]);
    }

    /**
     * O CSV vai para a fila (ImportWorkshopProspectsCsv) e quem enviou recebe o resultado por e-mail:
     * importar na requisição estourava o tempo do Cloudflare.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validateWithBag(self::IMPORT_BAG, [
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('csv');

        ImportWorkshopProspectsCsv::dispatch(
            (string) $file->get(),
            $file->getClientOriginalName(),
            (int) $request->user()->id,
        );

        return redirect()->route('admin.outreach.index')->with(
            'success',
            "Importação na fila. Você recebe o resultado em {$request->user()->email} quando terminar.",
        );
    }

    public function pause(OutreachDispatcher $dispatcher): RedirectResponse
    {
        $dispatcher->pause();

        return redirect()->route('admin.outreach.index')->with('success', 'Envios pausados.');
    }

    public function resume(OutreachDispatcher $dispatcher): RedirectResponse
    {
        $dispatcher->resume();

        return redirect()->route('admin.outreach.index')->with('success', 'Envios retomados.');
    }

    public function markReplied(WorkshopProspect $prospect): RedirectResponse
    {
        $prospect->markReplied();

        return $this->back("{$prospect->adminLabel()} marcada como respondeu.");
    }

    public function markConverted(WorkshopProspect $prospect): RedirectResponse
    {
        $prospect->markConverted();

        return $this->back("{$prospect->adminLabel()} marcada como convertida.");
    }

    public function markBounced(WorkshopProspect $prospect): RedirectResponse
    {
        EmailSuppression::suppress($prospect->email, EmailSuppression::REASON_BOUNCED);
        $prospect->update(['status' => WorkshopProspectStatus::Bounced]);

        return $this->back("{$prospect->adminLabel()} marcada como devolvida e o e-mail não recebe mais mensagens.");
    }

    public function unsubscribe(WorkshopProspect $prospect): RedirectResponse
    {
        EmailSuppression::suppress($prospect->email, EmailSuppression::REASON_MANUAL);
        $prospect->update([
            'status' => WorkshopProspectStatus::Unsubscribed,
            'unsubscribed_at' => $prospect->unsubscribed_at ?? now(),
        ]);

        return $this->back("{$prospect->adminLabel()} descadastrada.");
    }

    private function back(string $message): RedirectResponse
    {
        return back()->with('success', $message);
    }
}
