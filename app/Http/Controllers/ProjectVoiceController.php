<?php

namespace App\Http\Controllers;

use App\Models\PhoneCall;
use App\Models\Project;
use App\Services\AccessControlService;
use App\Services\Voice\TwilioNumberService;
use App\Services\Voice\VoiceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProjectVoiceController extends Controller
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly TwilioNumberService $numberService,
    ) {}

    public function show(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canAccessProject($request->user(), $project, 'projects.view'), 403);

        return response()->json([
            'data' => array_merge(VoiceSettings::serialize($project), [
                'twilio_configured' => $this->numberService->isConfigured(),
                'webhook_url' => $this->numberService->webhookUrl('incoming'),
            ]),
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canAccessProject($request->user(), $project, 'projects.update'), 403);

        $payload = $request->validate([
            'enabled' => ['required', 'boolean'],
            'greeting' => ['required', 'string', 'max:500'],
            'tts_voice' => ['required', 'string', 'max:60'],
            'language' => ['required', 'string', 'max:10'],
            'speech_timeout' => ['required', 'string', 'max:10'],
            'max_turns' => ['required', 'integer', 'min:1', 'max:100'],
            'thinking_message' => ['required', 'string', 'max:200'],
            'no_input_prompt' => ['required', 'string', 'max:300'],
            'fallback_message' => ['required', 'string', 'max:300'],
            'goodbye_message' => ['required', 'string', 'max:300'],
            'unavailable_message' => ['required', 'string', 'max:300'],
            'record_calls' => ['required', 'boolean'],
            'transfer_number' => ['nullable', 'string', 'max:25'],
        ]);

        // 'auto' is Twilio's adaptive end-of-speech detection; anything else must be a second count.
        abort_unless(
            $payload['speech_timeout'] === 'auto' || ctype_digit($payload['speech_timeout']),
            422,
            'Speech timeout must be "auto" or a number of seconds.'
        );

        if (! empty($payload['transfer_number'])) {
            try {
                $payload['transfer_number'] = $this->numberService->normalize($payload['transfer_number']);
            } catch (RuntimeException $exception) {
                abort(422, 'Transfer number is invalid: '.$exception->getMessage());
            }
        } else {
            $payload['transfer_number'] = null;
        }

        // Turning voice on without a number assigned would leave a project silently unreachable.
        abort_if(
            $payload['enabled'] && ! $project->phone_number,
            422,
            'Assign a phone number before enabling the voice channel.'
        );

        $project->update([
            'voice_settings' => array_merge(VoiceSettings::defaults(), $payload),
        ]);

        return response()->json(['data' => VoiceSettings::serialize($project->fresh())]);
    }

    public function assignNumber(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canAccessProject($request->user(), $project, 'projects.update'), 403);

        $payload = $request->validate([
            'phone_number' => ['required', 'string', 'max:25'],
        ]);

        try {
            $e164 = $this->numberService->normalize($payload['phone_number']);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        $conflict = Project::query()
            ->where('phone_number', $e164)
            ->whereKeyNot($project->id)
            ->exists();

        abort_if($conflict, 422, 'That number is already assigned to another project.');

        abort_unless($this->numberService->isConfigured(), 422, 'Twilio credentials are not configured.');

        try {
            $number = $this->numberService->findOwnedNumber($e164);
            $this->numberService->configureWebhooks($number['sid']);
        } catch (RuntimeException $exception) {
            // Expected, actionable problems: number not in the account, not voice capable, etc.
            abort(422, $exception->getMessage());
        } catch (Throwable $exception) {
            // Anything else is our bug or a Twilio outage. Log the detail; do not dress an
            // internal error up as if the user typed the number wrong.
            Log::error('Assigning a Twilio number failed unexpectedly.', [
                'project_id' => $project->id,
                'phone_number' => $e164,
                'exception' => $exception,
            ]);

            abort(500, 'Could not reach Twilio to configure that number. Please try again.');
        }

        $project->update([
            'phone_number' => $number['phone_number'],
            'twilio_phone_sid' => $number['sid'],
            // Materialise the defaults on first assignment so the settings form has concrete values.
            'voice_settings' => VoiceSettings::for($project),
        ]);

        return response()->json(['data' => VoiceSettings::serialize($project->fresh())]);
    }

    public function releaseNumber(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canAccessProject($request->user(), $project, 'projects.update'), 403);

        if ($project->twilio_phone_sid) {
            // Unbind our webhooks only; the number stays in the Twilio account.
            $this->numberService->clearWebhooks($project->twilio_phone_sid);
        }

        $project->update([
            'phone_number' => null,
            'twilio_phone_sid' => null,
            'voice_settings' => array_merge(VoiceSettings::for($project), ['enabled' => false]),
        ]);

        return response()->json(['data' => VoiceSettings::serialize($project->fresh())]);
    }

    /**
     * Paginated, unlike the other project listings: call volume grows without an upper bound.
     */
    public function calls(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canAccessProject($request->user(), $project, 'projects.view'), 403);

        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', 'max:30'],
            'from_number' => ['sometimes', 'string', 'max:25'],
        ]);

        $calls = PhoneCall::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('project_id', $project->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('from_number'), fn ($query) => $query->where('from_number', 'like', '%'.$request->string('from_number').'%'))
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json($calls);
    }

    /**
     * The caller directory: every phone number that has called this project, aggregated.
     */
    public function callers(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canAccessProject($request->user(), $project, 'projects.view'), 403);

        $callers = PhoneCall::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('project_id', $project->id)
            ->groupBy('from_number', 'from_country')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit(200)
            ->get([
                'from_number',
                'from_country',
                DB::raw('COUNT(*) as call_count'),
                DB::raw('SUM(COALESCE(duration_seconds, 0)) as total_seconds'),
                DB::raw('MIN(created_at) as first_call_at'),
                DB::raw('MAX(created_at) as last_call_at'),
            ])
            ->map(static fn (PhoneCall $row): array => [
                'from_number' => $row->from_number,
                'from_country' => $row->from_country,
                'call_count' => (int) $row->call_count,
                'total_seconds' => (int) $row->total_seconds,
                'first_call_at' => $row->first_call_at,
                'last_call_at' => $row->last_call_at,
            ])
            ->all();

        return response()->json(['data' => $callers]);
    }
}
