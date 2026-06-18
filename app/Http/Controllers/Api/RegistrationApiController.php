<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Services\ApiResponse;
use App\Services\RegistrationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegistrationApiController extends Controller
{
    public function storeForEvent(int $eventId, Request $request, RegistrationService $registrations): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }

        $event = Event::query()->with('customFields.options')->findOrFail($eventId);
        $payload = $this->flattenRegistrationPayload($request->all());
        if ($payload === null) {
            return ApiResponse::json('Il blocco custom_answers deve essere un oggetto JSON.', false, [], [], 400);
        }

        $validator = Validator::make($payload, ['attendee_note' => ['nullable', 'string', 'max:500']]);
        if ($validator->fails()) {
            return ApiResponse::json('Controlla i dati inviati.', false, [], $validator->errors()->toArray(), 400);
        }

        try {
            $customAnswers = $registrations->customAnswersFromPayload($event, $payload);
            $registration = $registrations->createOrReactivate(
                $event,
                $request->user(),
                trim((string) ($payload['attendee_note'] ?? '')),
                $customAnswers,
            );
        } catch (ValidationException $exception) {
            return $this->jsonValidationError($exception);
        } catch (AuthorizationException $exception) {
            return $this->jsonAuthorizationError($exception);
        }

        return ApiResponse::json(
            $registration->status === Registration::STATUS_ACTIVE
                ? 'Iscrizione salvata con successo.'
                : "Richiesta registrata in lista d'attesa.",
            true,
            ['item' => ApiResponse::registrationToArray($registration)],
            [],
            201,
        );
    }

    public function mine(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }

        $items = Registration::query()
            ->where('user_id', $request->user()->id)
            ->with('event', 'customAnswers.field', 'customAnswers.selectedOption')
            ->latest('created_at')
            ->get();

        return ApiResponse::json('Lista iscrizioni recuperata con successo.', true, [
            'items' => $items->map(fn (Registration $registration) => ApiResponse::registrationToArray($registration))->all(),
        ]);
    }

    public function showMine(int $registrationId, Request $request): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }

        $registration = Registration::query()
            ->where('user_id', $request->user()->id)
            ->with('event', 'customAnswers.field', 'customAnswers.selectedOption')
            ->find($registrationId);

        if (! $registration) {
            return ApiResponse::json('Iscrizione non trovata.', false, [], [], 404);
        }

        return ApiResponse::json('Dettaglio iscrizione recuperato.', true, [
            'item' => ApiResponse::registrationToArray($registration),
        ]);
    }

    public function updateMine(int $registrationId, Request $request, RegistrationService $registrations): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }

        $registration = Registration::query()->where('user_id', $request->user()->id)->with('event')->find($registrationId);
        if (! $registration) {
            return ApiResponse::json('Iscrizione non trovata.', false, [], [], 404);
        }

        $validator = Validator::make($request->all(), ['attendee_note' => ['nullable', 'string', 'max:500']]);
        if ($validator->fails()) {
            return ApiResponse::json('Controlla i dati inviati.', false, [], $validator->errors()->toArray(), 400);
        }

        try {
            $updated = $registrations->updateNote($registration, $request->user(), trim((string) $request->input('attendee_note', '')));
        } catch (ValidationException $exception) {
            return $this->jsonValidationError($exception);
        } catch (AuthorizationException $exception) {
            return $this->jsonAuthorizationError($exception);
        }

        return ApiResponse::json('Iscrizione aggiornata.', true, [
            'item' => ApiResponse::registrationToArray($updated),
        ]);
    }

    public function deleteMine(int $registrationId, Request $request, RegistrationService $registrations): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }

        $registration = Registration::query()->where('user_id', $request->user()->id)->with('event')->find($registrationId);
        if (! $registration) {
            return ApiResponse::json('Iscrizione non trovata.', false, [], [], 404);
        }

        try {
            $cancelled = $registrations->cancelForUser($registration, $request->user());
        } catch (ValidationException $exception) {
            return $this->jsonValidationError($exception);
        } catch (AuthorizationException $exception) {
            return $this->jsonAuthorizationError($exception);
        }

        return ApiResponse::json('Iscrizione annullata.', true, [
            'item' => ApiResponse::registrationToArray($cancelled),
        ]);
    }

    private function flattenRegistrationPayload(array $payload): ?array
    {
        $normalized = ['attendee_note' => $payload['attendee_note'] ?? ''];
        $customAnswers = $payload['custom_answers'] ?? [];
        if ($customAnswers === null) {
            $customAnswers = [];
        }
        if (! is_array($customAnswers)) {
            return null;
        }

        foreach ($customAnswers as $fieldId => $value) {
            $normalized['custom_field_'.$fieldId] = $value;
        }

        return $normalized;
    }
}
