<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Exceptions\MarketDataException;
use App\Exceptions\ResourceNotFoundException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\RobotResource;
use App\Models\Robot;
use App\Models\RobotUserSetting;
use App\Services\MarketData\MarketAssetRegistrar;
use App\Services\Robots\Contracts\RobotContract;
use App\Services\Robots\RobotManager;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Ferramentas do aluno: só aparecem as ligadas a uma turma em que ele tem acesso ativo
 * (compra de curso, assinatura ou liberação manual). Administradores veem todas.
 */
class RobotController extends ApiController
{
    public function __construct(private readonly RobotManager $robots) {}

    public function index(Request $request): JsonResponse
    {
        $robots = Robot::query()
            ->accessibleTo($request->user())
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Robot $r) => $this->robots->supports($r->slug))
            ->values();

        return ApiResponse::success(RobotResource::collection($robots));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $robot = $this->find($request, $slug);

        $implementation = $this->implementation($robot);

        return ApiResponse::success(
            (new RobotResource($robot))->resolve() + [
                'options' => $implementation->options(),
                'settings' => $this->settingsFor($request, $robot, $implementation),
                'default_settings' => $implementation->defaultSettings(),
            ],
        );
    }

    /** Salva as preferências do usuário nesta ferramenta (ex.: regras de ativos operáveis). */
    public function updateSettings(Request $request, string $slug): JsonResponse
    {
        $robot = $this->find($request, $slug);
        $implementation = $this->implementation($robot);

        $settings = Validator::make($request->all(), $implementation->settingsRules())->validate();

        // Regra desligada sem valor guarda o padrão, para reaparecer preenchida ao religar.
        $settings = $this->fillEmptyValues($settings, $implementation->defaultSettings());

        RobotUserSetting::query()->updateOrCreate(
            ['robot_id' => $robot->id, 'user_id' => $request->user()->id],
            ['company_id' => $robot->company_id, 'settings' => $settings],
        );

        return ApiResponse::success(['settings' => $this->settingsFor($request, $robot, $implementation)]);
    }

    /** Volta ao padrão da ferramenta apagando o que o usuário salvou. */
    public function resetSettings(Request $request, string $slug): JsonResponse
    {
        $robot = $this->find($request, $slug);

        RobotUserSetting::query()->where('robot_id', $robot->id)->where('user_id', $request->user()->id)->delete();

        return ApiResponse::success(['settings' => $this->implementation($robot)->defaultSettings()]);
    }

    /** O que o usuário salvou por cima do padrão (regras novas no código entram com o valor padrão). */
    private function settingsFor(Request $request, Robot $robot, RobotContract $implementation): array
    {
        $saved = RobotUserSetting::query()
            ->where('robot_id', $robot->id)
            ->where('user_id', $request->user()->id)
            ->value('settings');

        $saved = is_string($saved) ? json_decode($saved, true) : $saved;
        $defaults = $implementation->defaultSettings();

        return array_replace_recursive($defaults, $this->onlyKnownKeys(is_array($saved) ? $saved : [], $defaults));
    }

    /** Descarta chaves salvas que não existem mais no padrão (regra renomeada ou removida no código). */
    private function onlyKnownKeys(array $saved, array $defaults): array
    {
        $known = array_intersect_key($saved, $defaults);

        foreach ($known as $key => $value) {
            if (is_array($value) && is_array($defaults[$key])) {
                $known[$key] = $this->onlyKnownKeys($value, $defaults[$key]);
            }
        }

        return $known;
    }

    private function fillEmptyValues(array $settings, array $defaults): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value) && is_array($defaults[$key] ?? null)) {
                $settings[$key] = $this->fillEmptyValues($value, $defaults[$key]);
            } elseif ($value === null && array_key_exists($key, $defaults)) {
                $settings[$key] = $defaults[$key];
            }
        }

        return $settings;
    }

    public function run(Request $request, string $slug): JsonResponse
    {
        $implementation = $this->implementation($this->find($request, $slug));

        return ApiResponse::success($implementation->run($this->params($request, $implementation)));
    }

    public function detail(Request $request, string $slug): JsonResponse
    {
        $implementation = $this->implementation($this->find($request, $slug));

        $params = $this->params($request, $implementation);
        $symbol = (string) $request->validate(['symbol' => ['required', 'string', 'max:30']])['symbol'];

        return ApiResponse::success($implementation->detail($params, $symbol));
    }

    /** Aluno digita um ativo que ainda não está na base: valida na fonte e já baixa o histórico. */
    public function addAsset(Request $request, string $slug, MarketAssetRegistrar $registrar): JsonResponse
    {
        $this->find($request, $slug);

        $data = $request->validate([
            'market' => ['required', 'in:b3,us,crypto'],
            'symbol' => ['required', 'string', 'max:15', 'regex:/^[A-Za-z0-9.\-]+$/'],
        ]);

        try {
            $asset = $registrar->register($data['market'], strtoupper($data['symbol']));
        } catch (MarketDataException) {
            throw ValidationException::withMessages([
                'symbol' => 'Ativo não encontrado na fonte de dados. Confira o código (ex.: PETR4, AAPL, BTCUSDT).',
            ]);
        }

        return ApiResponse::success(['symbol' => $asset->symbol]);
    }

    private function params(Request $request, RobotContract $implementation): array
    {
        return Validator::make($request->all(), $implementation->rules())->validate();
    }

    private function implementation(Robot $robot): RobotContract
    {
        return $this->robots->make($robot->slug);
    }

    private function find(Request $request, string $slug): Robot
    {
        $robot = Robot::query()->accessibleTo($request->user())->where('slug', $slug)->where('status', 'active')->first();

        if (! $robot || ! $this->robots->supports($robot->slug)) {
            throw new ResourceNotFoundException('Robô não encontrado.');
        }

        return $robot;
    }
}
