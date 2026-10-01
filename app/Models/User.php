<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Portal;
use App\Notifications\ResetPasswordNotification;
use App\Support\AppStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'is_admin',
        'tenant_id',
        'phone',
        'document',
        'subscription_active',
        'postal_code',
        'street',
        'number',
        'complement',
        'city',
        'state',
        'country',
        'latitude',
        'longitude',
        'provider',
        'provider_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'document',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'document' => 'encrypted',
            'is_admin' => 'boolean',
            'subscription_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get all vehicles owned by this user
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'user_vehicles')
            ->using(UserVehicle::class)
            ->withPivot(
                'purchase_date',
                'sale_date',
                'is_current_owner',
                'tenant_id',
                'ownership_verified_at',
                'crlv_exercise_year',
                'owner_document',
                'ownership_type',
            )
            ->withTimestamps();
    }

    /**
     * Get current vehicles (where user is current owner) scoped to tenant
     */
    public function currentVehicles(): BelongsToMany
    {
        // Postgres rejects "boolean = 1"; use a SQL boolean literal.
        $query = $this->vehicles()->whereRaw('user_vehicles.is_current_owner = true');

        if ($this->tenant_id) {
            $query->wherePivot('tenant_id', $this->tenant_id);
        }

        return $query;
    }

    /**
     * Estoque do lojista: veículos de que ele é dono atual mais os que recebeu em consignação,
     * no tenant dele. A consignação é anexada com is_current_owner=false (o dono é outra pessoa),
     * então currentVehicles() não a enxerga. Aqui ela entra em qualquer estado do acesso ao
     * histórico (sem pedido, em análise, aprovado ou recusado), com a consignação deste usuário
     * já carregada em activeConsignment, para o estoque mostrar o status.
     */
    public function stockVehicles(): BelongsToMany
    {
        $query = $this->vehicles()
            ->where(function (Builder $query): void {
                // Postgres rejects "boolean = 1"; use a SQL boolean literal.
                $query->whereRaw('user_vehicles.is_current_owner = true')
                    ->orWhere('user_vehicles.ownership_type', 'consignment');
            })
            ->with([
                'activeConsignment' => fn (HasOne $query) => $query->where('garage_user_id', $this->id),
            ]);

        if ($this->tenant_id) {
            $query->wherePivot('tenant_id', $this->tenant_id);
        }

        return $query;
    }

    /**
     * O veículo está no estoque em consignação: o lojista o vende, mas o dono é outra pessoa.
     */
    public function holdsOnConsignment(Vehicle $vehicle): bool
    {
        $pivot = $this->stockPivotFor($vehicle);

        return $pivot !== null
            && $pivot->ownership_type === 'consignment'
            && ! $pivot->is_current_owner;
    }

    /**
     * Procuração de consignação que este usuário enviou para o veículo, se houver.
     */
    public function consignmentFor(Vehicle $vehicle): ?VehicleConsignment
    {
        if ($vehicle->relationLoaded('activeConsignment')) {
            $consignment = $vehicle->activeConsignment;

            return $consignment !== null && (int) $consignment->garage_user_id === (int) $this->id
                ? $consignment
                : null;
        }

        return VehicleConsignment::query()
            ->active()
            ->where('garage_user_id', $this->id)
            ->where('vehicle_id', $vehicle->id)
            ->first();
    }

    /**
     * A loja abre a ficha de qualquer veículo do estoque, inclusive a consignação sem liberação do
     * histórico: é nela que ela registra as manutenções e acompanha o que já registrou.
     */
    public function canOpenStockVehicle(Vehicle $vehicle): bool
    {
        return $this->stockPivotFor($vehicle) !== null;
    }

    /**
     * Espelha VehiclePolicy::viewFullHistory nas listas do estoque, sem uma consulta por veículo: o
     * histórico anterior abre para o dono atual e para a consignação que o proprietário liberou (ou
     * cuja procuração a equipe aprovou). Em consignação sem essa liberação o lojista continua
     * entrando no veículo e vendo o que ele mesmo registrou.
     */
    public function canViewStockVehicleHistory(Vehicle $vehicle): bool
    {
        $pivot = $this->stockPivotFor($vehicle);

        if ($pivot === null) {
            return false;
        }

        if ($pivot->is_current_owner) {
            return true;
        }

        return $this->consignmentFor($vehicle)?->grantsHistoryAccess() ?? false;
    }

    /**
     * IDs dos veículos do estoque cujo histórico o lojista pode ver.
     *
     * @return list<int>
     */
    public function stockVehicleIdsWithVisibleHistory(): array
    {
        return $this->stockVehicles()
            ->get()
            ->filter(fn (Vehicle $vehicle): bool => $this->canViewStockVehicleHistory($vehicle))
            ->values()
            ->modelKeys();
    }

    /**
     * Vínculo do veículo com o estoque deste usuário: o pivot já carregado quando o veículo veio de
     * stockVehicles(), ou uma consulta quando veio de outro lugar (ex.: route model binding).
     */
    private function stockPivotFor(Vehicle $vehicle): ?UserVehicle
    {
        if ($vehicle->relationLoaded('pivot')) {
            $pivot = $vehicle->getRelation('pivot');

            $isOwnStockPivot = $pivot instanceof UserVehicle
                && (int) $pivot->user_id === (int) $this->id
                && ($this->tenant_id === null || (int) $pivot->tenant_id === (int) $this->tenant_id);

            if ($isOwnStockPivot) {
                return $pivot;
            }
        }

        $stockVehicle = $this->stockVehicles()->where('vehicles.id', $vehicle->id)->first();

        return $stockVehicle?->getRelation('pivot');
    }

    /**
     * Consignações que este usuário mantém como lojista.
     */
    public function consignments(): HasMany
    {
        return $this->hasMany(VehicleConsignment::class, 'garage_user_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function garage()
    {
        return $this->hasOne(Garage::class);
    }

    /**
     * Get FCM tokens for push notifications
     */
    public function fcmTokens()
    {
        return $this->hasMany(UserFcmToken::class);
    }

    /**
     * Check if user is a common user
     */
    public function isUser(): bool
    {
        return $this->user_type === 'user';
    }

    /**
     * Check if user is a garage (dealership)
     */
    public function isGarage(): bool
    {
        return $this->user_type === 'garage';
    }

    /**
     * Check if user is a workshop
     */
    public function isWorkshop(): bool
    {
        return $this->user_type === 'workshop';
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function canViewVehicleMaintenances(?Vehicle $vehicle = null): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isGarage()) {
            return true;
        }

        return (bool) $this->subscription_active;
    }

    public function normalizedDocument(): ?string
    {
        if ($this->document === null) {
            return null;
        }

        return preg_replace('/\D/', '', $this->document) ?: null;
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Portal (área) da conta pelo user_type. O Painel admin é uma área extra de quem tem is_admin:
     * ver Portal::current() e Portal::accessibleBy().
     */
    public function portal(): Portal
    {
        return Portal::forUserType($this->user_type);
    }

    public function typeLabel(): string
    {
        return $this->portal()->label();
    }

    /**
     * Get workshop associated with this user (if user_type is workshop)
     */
    public function workshop()
    {
        return $this->hasOne(Workshop::class, 'user_id');
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->resolveAvatarUrl());
    }

    public function resolveAvatarUrl(): ?string
    {
        if ($this->avatar === null || $this->avatar === '') {
            return null;
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        return AppStorage::url($this->avatar);
    }

    /**
     * Link de redefinição de senha em pt-BR, no chrome de e-mail do projeto.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function hasPendingTwoFactorSetup(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at === null;
    }

    /**
     * @return list<string>
     */
    public function twoFactorRecoveryCodeHashes(): array
    {
        if ($this->two_factor_recovery_codes === null || $this->two_factor_recovery_codes === '') {
            return [];
        }

        $decoded = json_decode($this->two_factor_recovery_codes, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
