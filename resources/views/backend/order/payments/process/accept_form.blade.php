<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">Accept Payment</h4>
</div>

{!! Form::open(['route' => ['backend.sales.order.payment.process', 'process' => 'accept', 'id' => $payment->id]]) !!}
<div class="modal-body">
    <div class="form-body">
        @if($payment->amount == $payment->order->total && $payment->order->isProcessable)
            <div class="form-group" style="margin-bottom: 1em;">
                <label class="control-label col-md-3">Process Order</label>
                <div class="col-md-9">
                    <div class="checkbox-list">
                        <label class="checkbox-inline">
                            {!! Form::checkbox('process_order', 1, true, ['id' => 'process-order-checkbox']) !!} Yes
                        </label>
                    </div>
                </div>

                <div class="clearfix"></div>
            </div>

            <div data-enabled-dependent="process-order-checkbox" class="form-group" style="margin-bottom: 1em;">
                <label class="control-label col-md-3"></label>
                <div class="col-md-9">
                    <div class="checkbox-list">
                        <label class="checkbox-inline">
                            {!! Form::checkbox('send_notification', 1, true) !!} Send email notification to customer
                        </label>
                    </div>
                </div>

                <div class="clearfix"></div>
            </div>
        @endif

        @include('backend.master.form.fields.textarea', [
            'name' => 'reason',
            'label' => 'Notes',
            'key' => 'reason',
            'attr' => [
                'class' => 'form-control',
                'id' => 'reason',
                'rows' => 3
            ],
        ])

        <div class="clearfix"></div>
    </div>
</div>
<div class="modal-footer text-center">
    <button class="btn btn-primary"><i class="fa fa-check"></i> Confirm </button>
    <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-remove"></i> Cancel</button>
    {!! Form::hidden('backUrl', $backUrl) !!}
</div>
{!! Form::close() !!}