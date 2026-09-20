<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'mobile' => preg_replace('/\D+/', '', (string) $request->mobile),
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'country_code' => ['required', 'string', 'max:8', 'regex:/^\+\d{1,4}$/'],
            'mobile' => [
                'required',
                'string',
                'min:7',
                'max:15',
                'regex:/^\d+$/',
                Rule::unique('users')->where(
                    fn ($query) => $query->where('country_code', $request->country_code)
                ),
            ],
            'password' => ['required', Rules\Password::defaults()],
        ]);

        $email = preg_replace('/\D+/', '', $request->country_code.$request->mobile).'@lottery.local';

        $user = User::create([
            'name' => $request->name,
            'email' => $email,
            'country_code' => $request->country_code,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
