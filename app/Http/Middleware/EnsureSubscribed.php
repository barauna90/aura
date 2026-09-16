<?php

namespace App\Http\Middleware;

use App\Services\Billing\AccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sem assinatura ativa (confirmada pelo webhook), bolsa ou perfil de equipe, o aluno só
 * acessa a escolha de plano/pagamento, o perfil, a ajuda e a saída. Todo o resto é
 * redirecionado para a tela de assinatura. Nunca confia no navegador: consulta AccessService.
 */
class EnsureSubscribed
{
    /** Rotas liberadas sem assinatura (nome ou prefixo com ponto). */
    public const OPEN_ROUTES = ['subscription.', 'profile.', 'help', 'help.ask', 'logout'];

    public function __construct(private readonly AccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || self::isOpen($request->route()?->getName()) || $this->access->isPremium($user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Assine um plano para liberar o acesso.'], 402);
        }

        return redirect()->route('subscription.index')->with('error', 'Escolha um plano e conclua o pagamento para liberar o acesso à plataforma.');
    }

    public static function isOpen(?string $route): bool
    {
        if (! $route) {
            return false;
        }
        foreach (self::OPEN_ROUTES as $open) {
            if ($route === $open || (str_ends_with($open, '.') && str_starts_with($route, $open))) {
                return true;
            }
        }

        return false;
    }
}
