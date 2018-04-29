@extends('backend.master.form_template')

@section('breadcrumb')
    <li>
        <span>Sales</span>
        <i class="fa fa-circle"></i>
    </li>
    <li>
        <a href="{{ route('backend.sales.order.index') }}"><span>Order</span></a>
        <i class="fa fa-circle"></i>
    </li>
    <li>
        <span>Edit Order</span>
    </li>
@stop

@section('content')
    <div class="col-md-12">
        {!! Form::model($order, ['route' => ['backend.sales.order.update', 'id' => $order->id], 'class' => 'form-horizontal', 'data-order_id' => $order->id, 'id' => 'order-form']) !!}
        <div class="portlet light portlet-fit portlet-form bordered">
            <div class="portlet-title">
                <div class="caption">
                    <span class="caption-subject sbold uppercase">Edit Order</span>
                </div>
                <div class="actions">
                    @if(Gate::allows('access', ['place_order']) && (!$order->status || in_array($order->status, [\Kommercio\Models\Order\Order::STATUS_ADMIN_CART])))
                        <a data-modal_id="#place_order_modal" href="{{ route('backend.sales.order.process', ['action' => 'pending', 'id' => $order->id]) }}" class="btn place-order-btn blue modal-ajax"><i class="fa fa-save"></i> Place Order </a>
                    @endif
                    <button type="submit" name="action" value="save" class="btn blue-madison"><i class="fa fa-save"></i> Save </button>
                    <button class="btn btn-link" href="{{ NavigationHelper::getBackUrl() }}"><i class="fa fa-remove"></i> Cancel </button>
                </div>
            </div>

            <div class="portlet-body">
                <div class="form-body">
                    @include('backend.order.create_form')
                </div>

                <div class="form-actions text-center">
                    @if(Gate::allows('access', ['place_order']) && (!$order->status || in_array($order->status, [\Kommercio\Models\Order\Order::STATUS_ADMIN_CART])))
                        <a data-modal_id="#place_order_modal" href="{{ route('backend.sales.order.process', ['action' => 'pending', 'id' => $order->id]) }}" class="btn place-order-btn blue modal-ajax"><i class="fa fa-save"></i> Place Order </a>
                    @endif
                    <button type="submit" name="action" value="save" class="btn blue-madison"><i class="fa fa-save"></i> Save </button>
                    <button class="btn btn-link" href="{{ NavigationHelper::getBackUrl() }}"><i class="fa fa-remove"></i> Cancel </button>
                </div>
            </div>
        </div>
        {!! Form::close() !!}
    </div>
@stop
