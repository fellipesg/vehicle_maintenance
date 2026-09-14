<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkshopMessageTemplateRequest;
use App\Http\Requests\Api\V1\UpdateWorkshopMessageTemplateRequest;
use App\Http\Resources\Api\V1\WorkshopMessageTemplateResource;
use App\Models\Workshop;
use App\Models\WorkshopMessageTemplate;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

#[Group('Workshop message templates', weight: 21)]
class WorkshopMessageTemplateController extends Controller
{
    use ResolvesPagination;

    public function index(Request $request, Workshop $workshop): JsonResponse
    {
        Gate::authorize('update', $workshop);

        $templates = $workshop->messageTemplates()
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($templates, WorkshopMessageTemplateResource::class);
    }

    public function store(StoreWorkshopMessageTemplateRequest $request, Workshop $workshop): JsonResponse
    {
        $template = $workshop->messageTemplates()->create([
            'tenant_id' => $workshop->tenant_id,
            'trigger' => $request->validated('trigger'),
            'service_category' => $request->validated('service_category'),
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'lead_kilometers' => $request->validated('lead_kilometers')
                ?? (int) config('maintenance_intervals.notify_before_kilometers', 2_000),
            'min_days_since_service' => $request->validated('min_days_since_service') ?? 60,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return ApiResponse::created(new WorkshopMessageTemplateResource($template), 'Message template created successfully');
    }

    public function show(Workshop $workshop, WorkshopMessageTemplate $messageTemplate): JsonResponse
    {
        Gate::authorize('update', $workshop);
        $this->ensureTemplateBelongsToWorkshop($workshop, $messageTemplate);

        return ApiResponse::success(new WorkshopMessageTemplateResource($messageTemplate));
    }

    public function update(
        UpdateWorkshopMessageTemplateRequest $request,
        Workshop $workshop,
        WorkshopMessageTemplate $messageTemplate,
    ): JsonResponse {
        $this->ensureTemplateBelongsToWorkshop($workshop, $messageTemplate);

        $messageTemplate->update($request->validated());

        return ApiResponse::success(
            new WorkshopMessageTemplateResource($messageTemplate->fresh()),
            'Message template updated successfully',
        );
    }

    public function destroy(Workshop $workshop, WorkshopMessageTemplate $messageTemplate): JsonResponse
    {
        Gate::authorize('update', $workshop);
        $this->ensureTemplateBelongsToWorkshop($workshop, $messageTemplate);

        $messageTemplate->delete();

        return ApiResponse::success(message: 'Message template deleted successfully');
    }

    private function ensureTemplateBelongsToWorkshop(Workshop $workshop, WorkshopMessageTemplate $messageTemplate): void
    {
        if ($messageTemplate->workshop_id !== $workshop->id) {
            abort(404);
        }
    }
}
