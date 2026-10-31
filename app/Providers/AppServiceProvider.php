<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Policies\InvoicePolicy;
use App\Policies\MaintenancePhotoPolicy;
use App\Policies\MaintenancePolicy;
use App\Policies\VehiclePolicy;
use App\Policies\WorkshopPolicy;
use App\Support\ApiResponse;
use App\Support\Maintenance\MaintenancePhotoUrls;
use App\Support\StorageEndpointResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    private const AUTH_PER_IP_PER_MINUTE = 30;

    public function register(): void
    {
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new PostgresConnection($connection, $database, $prefix, $config);
        });
    }

    public function boot(): void
    {
        $this->configureLocale();

        MaintenancePhotoUrls::registerLocalTemporaryUrls(Storage::disk('public'));

        Model::preventLazyLoading(! $this->app->isProduction());

        // Em produção, nenhum comando que apaga dados pode rodar: o deploy usa `migrate --force`,
        // e migrate:fresh/refresh/reset/rollback e db:wipe não fazem parte dele. Vale para quem
        // abrir um shell na instância com o .env de produção carregado.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        if (! $this->app->runningInConsole() && $this->requestIsHttps()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('auth', fn (Request $request) => $this->buildAuthRateLimit(
            $request,
            fn (Request $request, array $headers) => $this->tooManyAttemptsJson($headers),
        ));

        // O formulário web volta com aviso, mas um cliente que pede JSON (um XHR no mesmo
        // formulário) não lê o erro do flash: para ele o redirect esconderia o 429 e o Retry-After.
        RateLimiter::for('auth-web', fn (Request $request) => $this->buildAuthRateLimit(
            $request,
            fn (Request $request, array $headers) => $request->expectsJson()
                ? $this->tooManyAttemptsJson($headers)
                : redirect()
                    ->back()
                    ->withInput($request->only('email', 'name', 'phone', 'document', 'trade_name', 'cnpj', 'ref'))
                    ->withErrors([
                        'email' => $this->tooManyAttemptsMessage($headers),
                    ]),
        ));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by(
            (string) ($request->user()?->id ?: $request->ip()),
        ));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(20)->by(
            'search|'.$request->ip(),
        ));

        // Mesmos limites do pedido de link pelo portal (PasswordResetController): por e-mail e
        // aparelho a cada 15 minutos, mais um teto por aparelho para qualquer e-mail.
        RateLimiter::for('password-forgot', fn (Request $request) => [
            Limit::perMinutes(15, 5)->by(
                'password-forgot|'.Str::lower((string) $request->input('email', '')).'|'.$request->ip(),
            ),
            Limit::perHour(20)->by('password-forgot-ip|'.$request->ip()),
        ]);

        // Claiming a vehicle is guarded by plate + RENAVAM, so cap the guesses per account.
        RateLimiter::for('vehicle-link', fn (Request $request) => Limit::perMinute(10)->by(
            'vehicle-link|'.($request->user()?->id ?: $request->ip()),
        ));

        // Consulta por chassi da oficina: limitada para não virar sondagem de carros alheios.
        RateLimiter::for('workshop-chassis-lookup', fn (Request $request) => Limit::perMinute(20)->by(
            'workshop-chassis|'.($request->user()?->id ?: $request->ip()),
        ));

        // A etapa 1 da OS acha veículo por placa e por chassi, então é oráculo de enumeração como as
        // buscas públicas — mas é tela de conta logada, e no balde `search` (só por IP) a oficina
        // levava 429 por tráfego de terceiro: dois funcionários atrás do mesmo NAT se atrapalhavam, e
        // qualquer pessoa na mesma rede usando /verificar ou /v/{code} esgotava a cota da tela que
        // registra serviço. Balde próprio pela conta, como o da consulta por chassi: quem sonda
        // responde pela conta, que é o que a oficina tem e o visitante não.
        RateLimiter::for('workshop-vehicle-search', function (Request $request) {
            // Abrir o formulário vazio não acha veículo nenhum: só gasta ficha quem manda
            // identificador. (Depois de um erro de validação o old input repete uma placa que a
            // própria conta já enviou, então isso não abre caminho de sondagem.)
            if (! $request->filled('license_plate') && ! $request->filled('chassis')) {
                return Limit::none();
            }

            return Limit::perMinute(20)->by(
                'workshop-vehicle-search|'.($request->user()?->id ?: $request->ip()),
            );
        });

        RateLimiter::for('workshop-invites', fn (Request $request) => Limit::perMinute(20)->by(
            'workshop-invites|'.($request->user()?->id ?: $request->ip()),
        ));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by(
            'uploads|'.($request->user()?->id ?: $request->ip()),
        ));

        // The challenge payload carries challenge_token (not email): 5 guesses per
        // challenge, plus a per-IP cap across challenges.
        RateLimiter::for('two-factor', fn (Request $request) => [
            Limit::perMinute(5)->by('2fa|'.hash('sha256', (string) $request->input('challenge_token', '')).'|'.$request->ip()),
            Limit::perMinute(10)->by('2fa-ip|'.$request->ip()),
        ]);

        // O /contato só manda para o suporte: o e-mail digitado vira Reply-To, nunca destinatário,
        // então não há como usar o formulário para encher a caixa de outra pessoa. Teto por IP basta.
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by(
            'contact|'.$request->ip(),
        ));

        // O /excluir-conta manda o link de exclusão para o endereço que foi digitado, então um teto
        // só por IP não protege o destinatário: trocar de IP libera quantos e-mails o atacante
        // quiser para a mesma caixa. Daí o balde por destinatário, que soma todos os IPs. A chave é
        // o e-mail digitado e não a conta encontrada, para o 429 não revelar quem tem cadastro —
        // mesma regra da resposta única do AccountDeletionController e do PasswordResetController.
        // O preço conhecido é poder gastar a cota de alguém; quem precisa excluir a conta ainda tem
        // o caminho pelo app e o e-mail do suporte, os dois citados na própria página.
        RateLimiter::for('account-deletion', fn (Request $request) => [
            Limit::perMinute(5)->by('account-deletion-ip|'.$request->ip()),
            Limit::perHour(20)->by('account-deletion-ip-hourly|'.$request->ip()),
            Limit::perDay(5)->by('account-deletion-email|'.hash('sha256', Str::lower((string) $request->input('email', '')))),
        ]);

        if ($this->app->runningInConsole()) {
            $this->describeAbuseGuards();
        }

        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Maintenance::class, MaintenancePolicy::class);
        Gate::policy(MaintenancePhoto::class, MaintenancePhotoPolicy::class);
        Gate::policy(Workshop::class, WorkshopPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        Storage::extend('s3', fn ($app, array $config) => $app['filesystem']->createS3Driver(
            StorageEndpointResolver::apply($config)
        ));
    }

    /**
     * `php artisan about` responde se o captcha do /contato está de pé, porque a chave vem do
     * ambiente e some sem avisar. Em produção o formulário recusa tudo sem ela
     * (App\Http\Requests\Web\StoreContactRequest), então vale poder conferir sem abrir o .env.
     *
     * Só no console: AboutCommand::add guarda o resolvedor num estático, e registrar a cada
     * requisição só empilharia cópias de algo que apenas o comando lê.
     */
    private function describeAbuseGuards(): void
    {
        AboutCommand::add('RevisaLog', fn () => [
            'Captcha do /contato (Turnstile)' => filled(config('services.turnstile.secret_key'))
                ? '<fg=green;options=bold>configurado</>'
                : '<fg=red;options=bold>SEM CHAVE</>',
        ]);
    }

    /**
     * Carbon's package provider already follows app.locale; setting it here keeps
     * diffForHumans()/translatedFormat() and Number helpers in pt_BR even if that
     * provider stops being discovered.
     */
    private function configureLocale(): void
    {
        $locale = $this->app->getLocale();

        Carbon::setLocale($locale);
        Number::useLocale($locale);
    }

    /**
     * Mesma resposta para a API e para um cliente JSON na rota web: envelope com a mensagem em
     * pt-BR e os cabeçalhos do limiter, Retry-After incluído.
     *
     * @param  array<string, mixed>  $headers  Rate limiter headers, including Retry-After.
     */
    private function tooManyAttemptsJson(array $headers): JsonResponse
    {
        return ApiResponse::error($this->tooManyAttemptsMessage($headers), 429)->withHeaders($headers);
    }

    /**
     * @param  array<string, mixed>  $headers  Rate limiter headers, including Retry-After.
     */
    private function tooManyAttemptsMessage(array $headers): string
    {
        $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));

        return trans_choice('auth.too_many_attempts', $seconds, ['seconds' => $seconds]);
    }

    /**
     * Login is keyed on email+IP, so it also gets a per-IP cap to stop one IP
     * spraying many emails. Register and OAuth buckets are already per-IP.
     *
     * @return list<Limit>
     */
    private function buildAuthRateLimit(Request $request, callable $response): array
    {
        $limits = [
            Limit::perMinute(5)
                ->by($this->authRateLimitKey($request))
                ->response($response),
        ];

        if ($this->isLoginRequest($request)) {
            $limits[] = Limit::perMinute(self::AUTH_PER_IP_PER_MINUTE)
                ->by('login-ip|'.$request->ip())
                ->response($response);
        }

        return $limits;
    }

    private function isLoginRequest(Request $request): bool
    {
        return ! $request->is('api/v1/register', 'register', 'para-oficinas/cadastro', 'api/v1/auth/*/callback', 'api/v1/auth/apple');
    }

    private function authRateLimitKey(Request $request): string
    {
        if ($request->is('api/v1/register', 'register', 'para-oficinas/cadastro')) {
            return 'register|'.$request->ip();
        }

        if ($request->is('api/v1/auth/apple')) {
            return 'oauth:apple|'.$request->ip();
        }

        if ($request->is('api/v1/auth/*/callback')) {
            $provider = (string) $request->route('provider', 'unknown');

            return 'oauth:'.$provider.'|'.$request->ip();
        }

        $email = strtolower((string) $request->input('email', ''));

        return 'login:'.$email.'|'.$request->ip();
    }

    private function requestIsHttps(): bool
    {
        $request = request();

        if ($request->isSecure()) {
            return true;
        }

        $forwardedProto = $request->header('X-Forwarded-Proto', '');

        return str_contains(strtolower((string) $forwardedProto), 'https');
    }
}
