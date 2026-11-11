<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $forms = Form::query()
            ->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $forms]);
    }

    public function store(Request $request): JsonResponse
    {
        $form = Form::create($request->validate($this->rules()));

        return response()->json(['form' => $form->refresh()], 201);
    }

    public function update(Request $request, Form $form): JsonResponse
    {
        $form->update($request->validate($this->rules($form, partial: true)));

        return response()->json(['form' => $form]);
    }

    /** @return array<string, mixed> */
    private function rules(?Form $form = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'key' => [$required, 'string', 'max:64', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', Rule::unique('forms', 'key')->ignore($form?->id)],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'fields' => ['nullable', 'array', 'max:200'],
            'fields.*.name' => ['required', 'string', 'max:64', 'distinct'],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.type' => ['nullable', 'string', 'max:32'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
