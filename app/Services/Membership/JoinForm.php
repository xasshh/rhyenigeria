<?php

namespace App\Services\Membership;

use App\Enums\AgeRange;
use App\Enums\CanvasserRole;
use App\Enums\Gender;
use App\Models\Lga;
use App\Models\Ward;
use App\Support\NigerianPhone;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

/**
 * Validation for the four sign-up steps. The wizard checks each step on the
 * server before moving on, and the final submission re-checks all of them.
 */
final class JoinForm
{
    public const STEPS = ['account', 'details', 'location', 'finish'];

    /** Clean up the raw input (phone numbers, identifiers, case) before validating. */
    public static function prepare(array $input): array
    {
        $trim = fn ($v) => is_string($v) ? trim($v) : $v;
        $input = array_map($trim, $input);

        foreach (['phone', 'whatsapp_phone'] as $field) {
            if (filled($input[$field] ?? null)) {
                $input[$field] = NigerianPhone::normalise($input[$field]) ?? $input[$field];
            }
        }

        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = mb_strtolower($input['email']);
        }

        foreach (['vin', 'nin'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = strtoupper(preg_replace('/[\s-]+/', '', $input[$field])) ?: null;
            }
        }

        if (isset($input['referrer']) && is_string($input['referrer'])) {
            $input['referrer'] = strtoupper(preg_replace('/\s+/', '', $input['referrer'])) ?: null;
        }

        foreach (['polling_unit_id', 'ward_id'] as $field) {
            if (($input[$field] ?? null) === 'unsure' || ($input[$field] ?? null) === '') {
                $input[$field] = null;
            }
        }

        return $input;
    }

    /** @return array<string, mixed> */
    public static function rules(string $step, array $input, bool $canvasser): array
    {
        return match ($step) {
            'account' => [
                'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
                'phone' => ['required', 'string', self::nigerianPhone(), Rule::unique('users', 'phone')],
                'password' => ['required', 'string', 'confirmed', Password::min(8)],
            ],
            'details' => [
                'name' => ['required', 'string', 'min:3', 'max:120', 'regex:/\S+\s+\S+/u'],
                'gender' => ['required', new Enum(Gender::class)],
                'age_range' => ['required', new Enum(AgeRange::class)],
                'occupation' => ['nullable', 'string', 'max:120'],
            ],
            'location' => [
                'state_id' => ['required', 'integer', Rule::exists('states', 'id')],
                'lga_id' => ['required', 'integer', Rule::exists('lgas', 'id')->where('state_id', (int) ($input['state_id'] ?? 0))],
                // A ward is only required once the LGA's wards have been imported.
                'ward_id' => [
                    Rule::requiredIf(fn () => filled($input['lga_id'] ?? null) && Ward::where('lga_id', (int) $input['lga_id'])->exists()),
                    'nullable', 'integer', Rule::exists('wards', 'id')->where('lga_id', (int) ($input['lga_id'] ?? 0)),
                ],
                'polling_unit_id' => ['nullable', 'integer', Rule::exists('polling_units', 'id')->where('ward_id', (int) ($input['ward_id'] ?? 0))],
                'has_pvc' => ['required', 'boolean'],
                'vin' => ['nullable', 'string', 'regex:/^[A-Z0-9]{19}$/'],
                'nin' => ['nullable', 'string', 'regex:/^\d{11}$/'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            ],
            'finish' => [
                'referrer' => ['nullable', 'string', 'max:32', Rule::exists('member_profiles', 'referral_code')],
                'consent' => ['accepted'],
                // Honeypot: a hidden field people never see or fill in.
                'website' => ['prohibited'],
            ] + ($canvasser ? [
                'canvasser_roles' => ['required', 'array', 'min:1'],
                'canvasser_roles.*' => ['distinct', new Enum(CanvasserRole::class)],
                'whatsapp_phone' => ['nullable', 'string', self::nigerianPhone()],
            ] : []),
            default => throw new \InvalidArgumentException("Unknown step {$step}."),
        };
    }

    /**
     * Validate the given steps and return the clean data.
     *
     * @param  list<string>  $steps
     * @return array<string, mixed>
     */
    public static function validate(array $input, array $steps, bool $canvasser): array
    {
        $input = self::prepare($input);
        $rules = [];

        foreach ($steps as $step) {
            $rules += self::rules($step, $input, $canvasser);
        }

        return Validator::make($input, $rules, self::messages(), self::attributes())->validate();
    }

    private static function nigerianPhone(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || NigerianPhone::normalise($value) !== $value) {
                $fail('Enter a Nigerian mobile number, for example 0803 000 0000.');
            }
        };
    }

    /** @return array<string, string> */
    private static function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered. Sign in instead.',
            'phone.unique' => 'This phone number is already registered. Sign in instead.',
            'name.regex' => 'Enter your first and last name.',
            'ward_id.required' => 'Choose your ward.',
            'vin.regex' => 'The VIN is the 19 letters and numbers printed on your PVC.',
            'nin.regex' => 'Your NIN is 11 digits.',
            'referrer.exists' => 'We could not find that member number. Check it, or leave it blank.',
            'consent.accepted' => 'Please tick the box to agree to how we use your data.',
            'website.prohibited' => 'Something went wrong. Please try again.',
            'canvasser_roles.required' => 'Choose at least one way you would like to help.',
        ];
    }

    /** @return array<string, string> */
    private static function attributes(): array
    {
        return [
            'state_id' => 'state', 'lga_id' => 'LGA', 'ward_id' => 'ward', 'polling_unit_id' => 'polling unit',
            'has_pvc' => 'PVC answer', 'age_range' => 'age range', 'name' => 'full name',
        ];
    }
}
