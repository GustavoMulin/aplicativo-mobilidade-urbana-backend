<?php

namespace App\Http\Controllers\Ajuda;

use App\Enums\StatusTicket;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Usuario\ChamadosAjudaController;
use App\Http\Resources\TicketResource;
use App\Models\ChamadoAjuda;
use App\Models\Gestor;
use App\Models\User;
use App\Services\TicketsAjudaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketsController extends Controller
{
    public function __construct(private TicketsAjudaService $service) {}

    private function acesso(Request $request, ?ChamadoAjuda $ticket = null): User|Gestor
    {
        $actor = $request->user();
        if ($actor instanceof Gestor) {
            abort_unless($actor->pode('ajuda.gerenciar'), 403);
        } else {
            abort_unless($actor instanceof User, 403);
            if ($ticket) {
                abort_unless($ticket->user_id === $actor->id, 404);
            }
        }

        return $actor;
    }

    public function index(Request $request)
    {
        $actor = $this->acesso($request);
        $dados = $request->validate([
            'status' => ['nullable', Rule::enum(StatusTicket::class)],
            'solicitante_tipo' => 'nullable|in:passageiro,motorista',
            'search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ]);
        $query = ChamadoAjuda::with(['solicitante', 'responsavel', 'corrida']);
        if ($actor instanceof User) {
            $query->where('user_id', $actor->id);
        }
        if (! empty($dados['status'])) {
            $query->where('status', $dados['status']);
        }
        if (! empty($dados['solicitante_tipo'])) {
            $query->where('solicitante_tipo', $dados['solicitante_tipo']);
        }
        if ($search = trim($dados['search'] ?? '')) {
            $query->where(function ($q) use ($search) {
                $q->where('motivo', 'like', '%'.$search.'%')->orWhereHas('solicitante', fn ($user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('cpf', 'like', '%'.$search.'%'));
                if (preg_match('/^(?:AJ-)?(\d+)$/i', $search, $match)) {
                    $q->orWhere('id', (int) $match[1]);
                }
            });
        }

        return TicketResource::collection($query->orderByDesc('ultima_mensagem_em')->orderByDesc('id')->paginate($dados['per_page'] ?? 20));
    }

    public function solicitantes(Request $request)
    {
        abort_unless($this->acesso($request) instanceof Gestor, 403);
        $dados = $request->validate(['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $query = User::with(['passageiro', 'motorista'])->where(fn ($q) => $q->whereHas('passageiro')->orWhereHas('motorista'));
        if ($search = trim($dados['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('cpf', 'like', '%'.$search.'%'));
        }
        $page = $query->orderBy('name')->paginate(20);
        $page->setCollection($page->getCollection()->map(fn (User $user) => $user->only(['id', 'name', 'cpf']) + ['perfis' => array_values(array_filter([$user->passageiro ? 'passageiro' : null, $user->motorista ? 'motorista' : null]))]));

        return response()->json($page);
    }

    public function store(Request $request)
    {
        $actor = $this->acesso($request);
        $dados = $request->validate([
            'user_id' => $actor instanceof Gestor ? 'required|integer' : 'prohibited',
            'solicitante_tipo' => $actor instanceof Gestor ? 'required|in:passageiro,motorista' : 'nullable|in:passageiro,motorista',
            'motivo' => ['required', Rule::in(ChamadosAjudaController::MOTIVOS)],
            'descricao' => 'required|string|min:10|max:1000', 'corrida_id' => 'nullable|integer|min:1',
        ]);
        $user = $actor instanceof Gestor ? User::findOrFail($dados['user_id']) : $actor;
        $ticket = $this->service->criar($user, $dados, $actor instanceof Gestor ? $actor : null);

        return (new TicketResource($ticket->load(['solicitante', 'responsavel', 'corrida'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, ChamadoAjuda $ticket)
    {
        $this->acesso($request, $ticket);

        return new TicketResource($ticket->load(['solicitante', 'responsavel', 'corrida']));
    }

    public function mensagens(Request $request, ChamadoAjuda $ticket)
    {
        $this->acesso($request, $ticket);
        $dados = $request->validate(['before_id' => 'nullable|integer|min:1', 'after_id' => 'nullable|integer|min:0', 'limit' => 'nullable|integer|min:1|max:100']);
        abort_if(isset($dados['before_id'], $dados['after_id']), 422, 'Use apenas um cursor.');
        $limit = $dados['limit'] ?? 50;
        $query = $ticket->mensagens();
        $after = isset($dados['after_id']);
        if ($after) {
            $query->where('id', '>', $dados['after_id']);
        }
        if (isset($dados['before_id'])) {
            $query->where('id', '<', $dados['before_id']);
        }
        $items = $after ? $query->orderBy('id')->limit($limit + 1)->get() : $query->orderByDesc('id')->limit($limit + 1)->get();
        $more = $items->count() > $limit;
        $items = $items->take($limit)->sortBy('id')->values();

        return response()->json(['data' => $items->map->dados(), 'has_more' => $more, 'next_before_id' => $items->first()?->id, 'next_after_id' => $items->last()?->id]);
    }

    public function enviar(Request $request, ChamadoAjuda $ticket)
    {
        $actor = $this->acesso($request, $ticket);
        $dados = $request->validate(['conteudo' => 'required|string|max:4000', 'client_message_id' => 'required|uuid', 'autor_tipo' => 'prohibited', 'user_id' => 'prohibited', 'gestor_id' => 'prohibited']);
        $texto = trim($dados['conteudo']);
        abort_if($texto === '', 422, 'Escreva uma mensagem.');
        $message = $this->service->enviar($ticket, $actor, $texto, $dados['client_message_id']);

        return response()->json(['data' => $message->dados()], 201);
    }

    public function assumir(Request $request, ChamadoAjuda $ticket)
    {
        $gestor = $this->acesso($request, $ticket);
        abort_unless($gestor instanceof Gestor, 403);

        return new TicketResource($this->service->assumir($ticket, $gestor)->load(['solicitante', 'responsavel', 'corrida']));
    }

    public function status(Request $request, ChamadoAjuda $ticket)
    {
        $gestor = $this->acesso($request, $ticket);
        abort_unless($gestor instanceof Gestor, 403);
        $dados = $request->validate(['status' => ['required', Rule::enum(StatusTicket::class)]]);

        return new TicketResource($this->service->status($ticket, $gestor, StatusTicket::from($dados['status']))->load(['solicitante', 'responsavel', 'corrida']));
    }
}
