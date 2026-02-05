<?php

namespace App\Services;

use App\Mail\VerificationCodeEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class TwoFactorService
{
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function enter($email)
    {
        $user = $this->userService->findUser($email);

        if (!$user) {
            return ['cachename' => 0,'message'=>'Our system doesn\'t have this email'];
        }

        // Генерация кода
        $randomCode = self::codeGenerate();

        // Отправка письма с пользователем и кодом
        Mail::to($user->email)->queue(new VerificationCodeEmail($user, $randomCode));

        // Кеширование кода на 10 минут
        $cachename = 'code' . $user->id;

        Cache::put($cachename, $randomCode, 600);

        return ['cachename' => $cachename,'message'=>'Our system doesn\'t have this email'];
    }

    public static function codeGenerate($length = 6)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $code;
    }


    public function verify($inputCode, $email)
    {
        $user = $this->userService->findUser($email);

        if (!$user)
            return false;

        $cacheName = 'code' . $user->id;

        $cachedCode = Cache::get($cacheName);

        if ($cachedCode && $inputCode === $cachedCode) {
            // Удалим код после успешной проверки для безопасности
            Cache::forget($cacheName);
            auth()->login($user);
            return true;
        }

        return false;
    }
}
