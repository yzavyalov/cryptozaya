<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        Auth::logout();                            // выкидываем пользователя
        $request->session()->invalidate();         // инвалидируем сессию
        $request->session()->regenerateToken();    // регенерируем CSRF токен
        return redirect()->route('index');        // куда редиректи
    }
}
