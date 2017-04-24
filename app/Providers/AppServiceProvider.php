<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use App\Settings;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer(['dashboard', 'schema.*', 'object.*', 'errors.*'], function($view){
            $view->with('schemas', app(SchemaManager::class)->all());
            $view->with('settings', app(Settings::class));
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('App\Settings', function($app){
            return new Settings(request()->user->token());
        });

        $this->app->singleton('App\Services\SchemaManager', function($app){
            return new SchemaManager();
        });
        
        $this->app->singleton('App\Services\ObjectManager', function($app){
            return new ObjectManager();
        });
    }
}
