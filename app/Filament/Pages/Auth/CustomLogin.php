<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

class CustomLogin extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';
    protected static string $layout = 'filament.pages.auth.layout';
}