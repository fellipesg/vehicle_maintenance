<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Portal;
use App\Notifications\ResetPasswordNotification;
use App\Support\AppStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Conta: um papel (proprietário, lojista ou oficina) com o `tenant_id` dele. É o que o guard `web`
 * devolve e o que toda policy compara — nada disso mudou com a identidade compartilhada.
 *
 * O e-mail, a senha e o 2FA moram em App\Models\Identity, acima da conta: as linhas que
 * compartilham `identity_id` são contas irmãs da mesma pessoa, uma por papel. `users.email` fica
 * como cópia desnormalizada (o provider do guard e as buscas por endereço leem dela); quem troca o
 * e-mail usa Identity::changeEmailTo, que grava nas duas pontas.
 *
 * `$user->password` e os três `two_factor_*` continuam legíveis aqui: são leituras que delegam para
 * a identidade, para o resto da aplicação não precisar saber onde a senha mora. Já a **escrita** da
 * senha por `$user->password = ...` (ou `User::create([... 'password' => ...])`) vai para a
 * identidade — criando-a quando a conta ainda não tem uma. É o que mantém fábricas, seeders e os
 * cadastros funcionando sem edição em massa.
 */
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
     * Senha recebida por `$user->password = ...` e ainda não gravada na identidade.
     */
    private ?string $pendingIdentityPassword = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'identity_id',
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
        'export_include_photos',
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
            'document' => 'encrypted',
            'is_admin' => 'boolean',
            'subscription_active' => 'boolean',
            'export_include_photos' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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

    public function garage(): HasOne
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

    /**
     * Identidade da loja para quem está de fora — o proprietário de um veículo em consignação, antes
     * de tudo. Vale o dado da loja (garages) e, quando ele está vazio, o da conta: é comum a loja
     * não ter e-mail nem telefone próprios e usar os da pessoa, e nesse caso o cadastro da loja
     * nasce com uma cópia (App\Services\TenantService). Nunca mostre users.name para um terceiro:
     * o nome que ele conhece é o da loja.
     *
     * @return array{name: string, email: ?string, phone: ?string, address: ?string}
     */
    public function storeIdentity(): array
    {
        $garage = $this->garage;

        return [
            'name' => (string) ($garage?->name ?: $this->name),
            'email' => $garage?->email ?: $this->email,
            'phone' => $garage?->phone ?: $this->phone,
            'address' => $garage !== null && $garage->addressLine() !== '' ? $garage->addressLine() : null,
        ];
    }

    /**
     * Nome pelo qual a loja é conhecida fora da conta.
     */
    public function storeName(): string
    {
        return $this->storeIdentity()['name'];
    }

    /**
     * CPF/CNPJ da conta, só com dígitos. No lojista o documento é o da loja (garages.document,
     * mantido em "Minha loja"); users.document fica como reserva para as contas de garagem que
     * receberam o CNPJ antes de a loja existir. Ponto único de leitura: VehicleEntryFlow e
     * VehicleOwnershipService decidem estoque próprio x consignação por aqui.
     */
    public function normalizedDocument(): ?string
    {
        $document = $this->isGarage()
            ? ($this->garage?->document ?? $this->document)
            : $this->document;

        if ($document === null) {
            return null;
        }

        return preg_replace('/\D/', '', $document) ?: null;
    }

    /**
     * Comunicados únicos já enviados (users:announce-ios-app).
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(UserAnnouncement::class);
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
        return $this->identityRecord()?->hasTwoFactorEnabled() ?? false;
    }

    public function hasPendingTwoFactorSetup(): bool
    {
        return $this->identityRecord()?->hasPendingTwoFactorSetup() ?? false;
    }

    /**
     * @return list<string>
     */
    public function twoFactorRecoveryCodeHashes(): array
    {
        return $this->identityRecord()?->twoFactorRecoveryCodeHashes() ?? [];
    }

    /**
     * Identidade da conta: o e-mail, a senha e o 2FA.
     *
     * @return BelongsTo<Identity, $this>
     */
    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class);
    }

    /**
     * As outras contas da mesma pessoa, uma por papel. Vazio enquanto ela tem só um papel, que é o
     * caso de toda a base de hoje.
     *
     * @return EloquentCollection<int, self>
     */
    public function siblingAccounts(): EloquentCollection
    {
        return self::query()
            ->where('identity_id', $this->identity_id)
            ->whereKeyNot($this->getKey())
            ->get();
    }

    /**
     * A identidade já carregada, com `loadMissing` e não `$this->identity`: o
     * Model::preventLazyLoading está ligado fora de produção, e isto é lido na autenticação de
     * **toda** requisição — uma violação aqui derrubaria a sessão em vez de responder.
     */
    private function identityRecord(): ?Identity
    {
        if ($this->identity_id === null) {
            return null;
        }

        $identity = $this->loadMissing('identity')->getRelation('identity');

        return $identity instanceof Identity ? $identity : null;
    }

    /**
     * A senha mora na identidade. Esta leitura existe para o resto da aplicação continuar
     * perguntando `$user->password` (é o que o Hash::check das reautenticações faz).
     */
    public function getPasswordAttribute(): ?string
    {
        return $this->identityRecord()?->password;
    }

    /**
     * Gravar `password` na conta grava na identidade — e não numa coluna de `users`, que não existe
     * mais. O valor fica guardado aqui e é persistido no `save()`, junto com a conta, para
     * `User::create([... 'password' => ...])` seguir valendo em fábricas, seeders e cadastros.
     */
    public function setPasswordAttribute(#[\SensitiveParameter] ?string $password): void
    {
        $this->pendingIdentityPassword = $password;
    }

    public function getTwoFactorSecretAttribute(): ?string
    {
        return $this->identityRecord()?->two_factor_secret;
    }

    public function getTwoFactorConfirmedAtAttribute(): mixed
    {
        return $this->identityRecord()?->two_factor_confirmed_at;
    }

    public function getTwoFactorRecoveryCodesAttribute(): ?string
    {
        return $this->identityRecord()?->two_factor_recovery_codes;
    }

    /**
     * O guard compara a senha da identidade. O App\Http\Middleware\AuthenticateWebSession guarda
     * este mesmo hash na sessão, então trocar a senha derruba os outros aparelhos de **todas** as
     * contas irmãs — a senha é uma só.
     */
    public function getAuthPassword(): string
    {
        return (string) $this->identityRecord()?->password;
    }

    /**
     * A identidade é garantida aqui, no `save()`, e **não** num evento `saving`: o
     * Illuminate\Database\Console\Seeds\WithoutModelEvents (usado pelo
     * Database\Seeders\DatabaseSeeder) silencia os eventos do modelo, e a conta seria gravada sem
     * `identity_id`. Sobrescrever o método vale para `create`, `update`, `updateOrCreate`,
     * `forceFill(...)->save()`, `saveQuietly` e as fábricas — tudo passa por aqui.
     *
     * A identidade nova nasce na mesma transação da conta, para um erro na gravação não deixar uma
     * identidade órfã segurando o e-mail (que é único) e impedindo a próxima tentativa.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->identity_id !== null && $this->pendingIdentityPassword === null) {
            return parent::save($options);
        }

        return (bool) $this->getConnection()->transaction(function () use ($options): bool {
            $this->persistIdentity();

            return parent::save($options);
        });
    }

    /**
     * Garante a identidade da conta antes de gravá-la: a conta nova sem `identity_id` ganha a sua,
     * com o e-mail dela e a senha que veio no `password`. Conta que já tem identidade só repassa a
     * senha nova. Ligar uma conta a uma identidade existente (a conta irmã) é explícito, pelo
     * `identity_id` — nunca por adivinhação pelo e-mail.
     */
    private function persistIdentity(): void
    {
        $password = $this->pendingIdentityPassword;
        $this->pendingIdentityPassword = null;

        if ($this->identity_id === null) {
            $identity = Identity::create([
                'email' => (string) $this->email,
                // Conta criada sem senha (nunca acontece nos cadastros) não pode nascer com uma
                // senha vazia que qualquer string confira.
                'password' => $password ?? Str::password(40),
            ]);

            $this->identity()->associate($identity);

            return;
        }

        if ($password === null) {
            return;
        }

        $identity = $this->identityRecord();

        $identity?->forceFill(['password' => $password])->save();
    }
}
