<?php

namespace Kommercio\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Kommercio\Facades\ProductIndexHelper;
use Kommercio\Facades\ProjectHelper;
use Kommercio\Models\File;
use Kommercio\Models\Interfaces\AuthorSignatureInterface;
use Illuminate\Support\Facades\Storage;
use Kommercio\Models\Interfaces\CacheableInterface;
use Kommercio\Models\Interfaces\ConfigVariableInterface;
use Kommercio\Models\Interfaces\ProductIndexInterface;
use Kommercio\Models\Interfaces\UrlAliasInterface;
use Kommercio\Models\UrlAlias;
use Kommercio\Observers\UrlAliasObserver;

class BackendServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton('navigation_helper', 'Kommercio\Helpers\NavigationHelper');

        $this->app['events']->listen('eloquent.creating*', function ($eventName, $models) {
            $model = $models[0];

            if ($model instanceof AuthorSignatureInterface) {
                $model->authorSign('creating');
            }
        });

        $this->app['events']->listen('eloquent.updating*', function ($eventName, $models) {
            $model = $models[0];

            if ($model instanceof AuthorSignatureInterface) {
                $model->authorSign('updating');
            }
        });

        $this->app['events']->listen('eloquent.saving*', function ($eventName, $models) {
            $model = $models[0];

            $traits = class_uses($model);

            // Run ToggleDate
            if(in_array('Kommercio\Traits\Model\ToggleDate', $traits)){
                $model->toggleByDate();
            }
        });

        $this->app['events']->listen('eloquent.saved*', function ($eventName, $models) {
            $model = $models[0];

            $traits = class_uses($model);

            // Because translations are not saved if model is not dirty, force save it for cache busting
            if (count($model->getDirty()) < 1) {
                if(method_exists($model, 'translations')){
                    foreach($model->translations as $translation){
                        $translation->save();
                    }
                }
            }

            if ($model instanceof UrlAliasInterface) {
                UrlAlias::saveAlias($model->getUrlAlias(), $model);
            }

            if ($model instanceof CacheableInterface) {
                foreach($model->getCacheKeys() as $cacheKey){
                    // array is for tags
                    if (is_array($cacheKey)) {
                        if(Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
                            Cache::tags($cacheKey)->flush();
                        }
                    } else {
                        Cache::forget($cacheKey);
                    }
                }
            }

            if(in_array('Kommercio\Traits\Model\FlatIndexable', $traits)){
                $model->saveFlatIndex();
            }
        });

        $this->app['events']->listen('eloquent.deleting*', function ($eventName, $models) {
            $model = $models[0];

            $traits = class_uses($model);

            // Delete media when model deleted
            if(in_array('Kommercio\Traits\Model\MediaAttachable', $traits)){
                foreach($model->media as $modelMedia){
                    if(!property_exists($model, 'forceDeleting') || $model->isForceDeleting()) {
                        $modelMedia->delete();
                    }
                }
            }

            if ($model instanceof UrlAliasInterface) {
                if(!property_exists($model, 'forceDeleting') || $model['forceDeleting']){
                    UrlAlias::deleteAlias($model->getInternalPathSlug().'/'.$model->id);
                }
            }

            if ($model instanceof File || is_a($model, 'Kommercio\Models\File')) {
                $storage = !empty($model->storage)?$model->storage:config('filesystems.default');
                $folder = rtrim($model->folder, '/') . '/';

                if(Storage::disk($storage)->exists($folder.$model->filename)){
                    Storage::disk($storage)->delete($folder.$model->filename);
                }
            }

            if ($model instanceof ProductIndexInterface) {
                foreach($model->getProductIndexRows() as $row){
                    ProductIndexHelper::getProductIndexQuery(false)->where('type', $model->getProductIndexType())->where('value', $row->id)->delete();
                }
            }

            if ($model instanceof CacheableInterface) {
                foreach($model->getCacheKeys() as $cacheKey){
                    // array is for tags
                    if (is_array($cacheKey)) {
                        if(Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
                            Cache::tags($cacheKey)->flush();
                        }
                    } else {
                        Cache::forget($cacheKey);
                    }
                }
            }

            if(in_array('Kommercio\Traits\Model\FlatIndexable', $traits)){
                $model->deleteFlatIndex();
            }
        });

        view()->composer(['project::backend.*', 'backend.*'], function ($view) {
            $activeStore = ProjectHelper::getActiveStore();
            $managedStores = Auth::check()?Auth::user()->getManagedStores():[];
            $otherStores = [];

            foreach($managedStores as $managedStore){
                if($activeStore->id != $managedStore->id){
                    $otherStores[] = $managedStore;
                }
            }

            $view->with('activeStore', $activeStore);
            $view->with('managedStores', $managedStores);
            $view->with('otherStores', $otherStores);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../backend_menu.php', 'backend_menu'
        );
    }
}