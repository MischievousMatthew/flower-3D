<?php

namespace App\Providers;

use App\Support\FriendlyValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Error messages use plain field names ("employee", not "employee id")
        Validator::resolver(fn ($translator, $data, $rules, $messages, $attributes) => new FriendlyValidator($translator, $data, $rules, $messages, $attributes));
    }
}
