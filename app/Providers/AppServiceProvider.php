<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use App\Services\RoleManager;
use App\Services\UserManager;
use App\Settings;
use App\Backend;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer(['dashboard', 'schema.*', 'object.*', 'errors.*', 'settings.*', 'users.*', 'roles.*', 'files.*'], function($view){
            $view->with('schemas', app(SchemaManager::class)->all());
            $view->with('settings', app(Settings::class));
        });

        View::composer('*', function($view){
            $view->with('backend', app(Backend::Class));
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('App\Backend', function($app){
            return new Backend();
        });

        $this->app->singleton('App\Settings', function($app){
            return new Settings();
        });

        $this->app->singleton('App\Services\SchemaManager', function($app){
            return new SchemaManager();
        });

        $this->app->singleton('App\Services\ObjectManager', function($app){
            return new ObjectManager();
        });

        $this->app->singleton('App\Services\RoleManager', function($app){
            return new RoleManager();
        });

        $this->app->singleton('App\Services\UserManager', function($app){
            return new UserManager();
        });
    }
}
