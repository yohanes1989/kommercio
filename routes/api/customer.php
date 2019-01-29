<?php

Route::prefix('public')
    ->namespace('Api\Frontend')
    ->group(function() {
        Route::prefix('customer')
            ->namespace('Customer')
            ->group(function() {

                Route::post('/create', [
                    'as' => 'api.frontend.customer.create',
                    'uses' => 'CustomerController@create',
                ]);

                Route::post('/update/{id}', [
                    'as' => 'api.frontend.customer.update',
                    'uses' => 'CustomerController@update',
                ]);
            });
    });

Route::namespace('Api')
    ->middleware(['backend.access'])
    ->group(function() {
        Route::prefix('customer')
            ->namespace('Customer')
            ->group(function() {

                Route::post('/create', [
                    'as' => 'api.customer.create',
                    'uses' => 'CustomerController@create',
                    'permissions' => ['create_customer'],
                ]);

                Route::put('/update/{id}', [
                    'as' => 'api.customer.update',
                    'uses' => 'CustomerController@update',
                    'permissions' => ['edit_customer'],
                ]);
            });
        });
