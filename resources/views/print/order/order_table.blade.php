<table class="content-table">
    <thead>
    <tr>
        <th style="width: 50px;"> </th>
        <th> Item </th>
        <th> Price </th>
        <th> Quantity </th>
        <th> Total </th>
    </tr>
    </thead>
    <tbody>
    <?php $shippingLineItems = []; ?>
    @if($order->lineItems)
        @foreach($order->lineItems as $idx=>$lineItem)
            @if($lineItem['line_item_type'] == 'fee')
                @include('emails.order.line_items.fee', ['key' => $idx, 'lineItem' => $lineItem])
            @elseif($lineItem['line_item_type'] == 'product')
                @include('emails.order.line_items.product', ['key' => $idx, 'lineItem' => $lineItem])
            @endif
        @endforeach
    @endif
    </tbody>
    <tfoot>
    <tr>
        <td class="no-border" colspan="2"></td>
        <td class="no-border" colspan="2" align="right"><strong>Subtotal</strong></td>
        <td class="no-border">{{ PriceFormatter::formatNumber($order->calculateProductTotal(true) + $order->calculateAdditionalTotal(true), $order->currency) }}</td>
    </tr>

    <tr>
        <td class="no-border" colspan="2"></td>
        <td class="no-border" colspan="2" align="right"><strong>Shipping</strong></td>
        <td class="no-border">{{ PriceFormatter::formatNumber($order->calculateShippingTotal(true), $order->currency) }}</td>
    </tr>

    @if($order->rounding_total > 0 || $order->rounding_total < 0)
        <tr>
            <td class="no-border" colspan="2"></td>
            <td class="no-border" colspan="2" align="right"><strong>Rounding</strong></td>
            <td class="no-border">{{ PriceFormatter::formatNumber($order->rounding_total, $order->currency) }}</td>
        </tr>
    @endif
    <tr>
        <td class="no-border" colspan="2"></td>
        <td class="no-border" colspan="2" align="right"><strong>Grand Total</strong></td>
        <td class="no-border">{{ PriceFormatter::formatNumber($order->total, $order->currency) }}</td>
    </tr>

    <hr/>

    @foreach($order->getCartPriceRuleLineItems() as $cartPriceRuleLineItem)
        <tr>
            <td class="no-border" colspan="2"></td>
            <td class="no-border" colspan="2" align="right"><strong>{{ $cartPriceRuleLineItem->getPrintName() }}</strong></td>
            <td class="no-border">{{ PriceFormatter::formatNumber($cartPriceRuleLineItem->total, $order->currency) }}</td>
        </tr>
    @endforeach

    @foreach($order->getTaxLineItems() as $taxLineItem)
        <tr>
            <td class="no-border" colspan="2"></td>
            <td class="no-border" colspan="2" align="right"><strong>Incl. {{ $taxLineItem->getPrintName() }}</strong></td>
            <td class="no-border">{{ PriceFormatter::formatNumber($taxLineItem->total, $order->currency) }}</td>
        </tr>
    @endforeach
    </tfoot>
</table>

@if(!empty($order->notes))
    <p class="text">
        <strong>Notes:</strong><br/>
        {!! nl2br($order->notes) !!}
    </p>
@endif

@foreach($order->additional_fields as $additionalFieldKey => $additionalField)
    <p class="text">
        <strong>{{ trans(LanguageHelper::getTranslationKey('order.additional_fields.'.$additionalFieldKey)) }}:</strong> {!! nl2br($additionalField) !!}
    </p>
@endforeach