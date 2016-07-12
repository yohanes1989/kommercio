@extends('backend.master.form_template')

@section('breadcrumb')
    <li>
        <a href="{{ route('backend.customer.index') }}"><span>Customer</span></a>
        <i class="fa fa-circle"></i>
    </li>
    <li>
        <span>Customer - {{ $customer->fullName }}</span>
    </li>
@stop

@section('content')
    <div class="col-md-12">
        <div class="margin-bottom-10"><a href="{{ NavigationHelper::getBackUrl() }}" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a></div>

        <div class="portlet light portlet-fit portlet-form bordered">
            <div class="portlet-title">
                <div class="caption">
                    <span class="caption-subject sbold uppercase">Customer - {{ $customer->fullName }}</span>
                </div>
                <div class="actions">
                    @if(Gate::allows('access', ['edit_view']))
                        <a href="{{ route('backend.customer.edit', ['id' => $customer->id, 'backUrl' => Request::fullUrl()]) }}" class="btn btn-info"><i class="fa fa-pencil"></i> Edit </a>
                    @endif
                </div>
            </div>

            <div class="portlet-body">
                <div class="form-body" id="order-wrapper">
                    <div class="tabbable-bordered">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="active" role="presentation">
                                <a href="#tab_details" data-toggle="tab"> Details </a>
                            </li>
                            @can('access', ['view_order'])
                            <li role="presentation">
                                <a href="#tab_orders" data-toggle="tab"> Orders </a>
                            </li>
                            @endcan
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_details">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="portlet light bordered">
                                            <div class="portlet-title">
                                                <div class="caption">
                                                    <i class="fa fa-user"></i>
                                                    <span class="caption-subject">Customer Information</span>
                                                </div>
                                            </div>
                                            <div class="portlet-body">
                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Account: </div>
                                                    <div class="col-md-7 value"> <i class="fa fa-{{ isset($customer->user)?'check text-success':'remove text-danger' }}"></i> </div>
                                                </div>

                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Status: </div>
                                                    <div class="col-md-7 value"> <i class="fa fa-{{ isset($customer->user) && $customer->user->status == \Kommercio\Models\User::STATUS_ACTIVE?'check text-success':'remove text-danger' }}"></i> </div>
                                                </div>

                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Salute: </div>
                                                    <div class="col-md-7 value"> {{ $customer->getProfile()->salute }} </div>
                                                </div>

                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Name: </div>
                                                    <div class="col-md-7 value"> {{ $customer->fullName }} </div>
                                                </div>

                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Email: </div>
                                                    <div class="col-md-7 value"> {{ $customer->getProfile()->email }} </div>
                                                </div>

                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Phone Number: </div>
                                                    <div class="col-md-7 value"> {{ $customer->getProfile()->phone_number }} </div>
                                                </div>

                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Address: </div>
                                                    <div class="col-md-7 value"> {!! AddressHelper::printAddress($customer->getProfile()->getDetails()) !!} </div>
                                                </div>

                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Birthday: </div>
                                                    <div class="col-md-7 value"> {{ $customer->getProfile()->birthday?\Carbon\Carbon::createFromFormat('Y-m-d', $customer->getProfile()->birthday)->format('d M Y'):'' }} </div>
                                                </div>

                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Customer Since: </div>
                                                    <div class="col-md-7 value"> {{ $customer->created_at->format('d M Y, H:i') }} </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="portlet light bordered">
                                            <div class="portlet-title">
                                                <div class="caption">
                                                    <i class="fa fa-shopping-cart"></i>
                                                    <span class="caption-subject">Order Information</span>
                                                </div>
                                            </div>
                                            <div class="portlet-body">
                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Number of Order: </div>
                                                    <div class="col-md-7 value"> {{ $customer->orders->count() }} </div>
                                                </div>

                                                <div class="row static-info">
                                                    <div class="col-md-5 name"> Total Order: </div>
                                                    <div class="col-md-7 value"> {{ PriceFormatter::formatNumber($customer->total) }} </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="clearfix"></div>
                                </div>
                            </div>

                            @can('access', ['view_order'])
                            <div class="tab-pane" id="tab_orders">
                                <div class="form-body">
                                    <div class="table-scrollable">
                                        <table class="table table-hover">
                                            <thead>
                                            <tr>
                                                <th> # </th>
                                                <th> Order # </th>
                                                <th>Purchased On</th>
                                                @if(config('project.enable_delivery_date', FALSE))
                                                    <th>Delivery Date</th>
                                                @endif
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th> </th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($customer->orders as $idx => $order)
                                                    <tr>
                                                        <td>{{ $idx + 1 }}</td>
                                                        <td>{{ $order->reference }}</td>
                                                        <td>{{ $order->checkout_at->format('d M Y, H:i') }}</td>
                                                        @if(config('project.enable_delivery_date', FALSE))
                                                            <td>{{ $order->delivery_date->format('d M Y, H:i') }}</td>
                                                        @endif
                                                        <td>{{ PriceFormatter::formatNumber($order->total) }}</td>
                                                        <td><label class="label label-sm bg-{{ OrderHelper::getOrderStatusLabelClass($order->status) }} bg-font-{{ OrderHelper::getOrderStatusLabelClass($order->status) }}">{{ \Kommercio\Models\Order\Order::getStatusOptions($order->status, TRUE) }}</label></td>
                                                        <td><a href="{{ route('backend.sales.order.view', ['id' => $order->id, 'backUrl' => Request::getRequestUri()]) }}" class="btn btn-sm btn-default"><i class="fa fa-search"></i></a></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop