<tr class="line-item">
    <td>
        @if(!empty($child) && $child)
        {!! $lineItem->product->getThumbnail()?'<img style="width: 40px; height: auto;" src="'.asset($lineItem->product->getThumbnail()->getImagePath('backend_thumbnail')).'" />':'' !!}
        @else
        {!! $lineItem->product->getThumbnail()?'<img style="width: 80px; height: auto;" src="'.asset($lineItem->product->getThumbnail()->getImagePath('backend_thumbnail')).'" />':'' !!}
        @endif
    </td>
    <td>
        <div>{{ $lineItem->name }}</div>
        @if($lineItem->product->manufacturer)
            <div>Brand<span class="colon">:</span> {{ $lineItem->product->manufacturer->name }}</div>
        @endif
        @foreach($lineItem->product->productAttributes as $productAttribute)
            <div>{{ $productAttribute->name }}<span class="colon">:</span> {{ $productAttribute->pivot->productAttributeValue->name }}</div>
        @endforeach

        @foreach($lineItem->children as $childLineItem)
            <div>
                @foreach($lineItem->productConfigurations as $productConfiguration)
                    <br/>- <em>{{ $productConfiguration->pivot->label }}: {{ $productConfiguration->pivot->value }}</em>
                @endforeach
            </div>
        @endforeach

        @if(!empty($lineItem->notes) || $lineItem->productConfigurations->count() > 0)
            @if(!empty($lineItem->notes))
                <div>
                    <em>Notes</em><br/>
                    {!! nl2br($lineItem->notes) !!}
                </div>
            @endif
        @endif
    </td>
    @if(isset($showPrice) && $showPrice)
        <td>
            {{ PriceFormatter::formatNumber($lineItem->net_price, $lineItem->order->currency) }}
        </td>
        @endif
    <td>
        @if ($doItem)
            {{ $doItem->quantity+0 }}
        @else
            {{ $lineItem->quantity+0 }}
        @endif
    </td>
</tr>

@foreach($lineItem->product->composites as $productComposite)
    <?php $children = $lineItem->getChildrenByComposite($productComposite); ?>

    @if($children->count() > 0)
        <tr class="child-line-item child-line-item-header">
            <td colspan="100">
                {{ $productComposite->name }}
            </td>
        </tr>

        @foreach($children as $child)
            @include('emails.delivery_order.line_items.product', ['composite' => $childLineItem->productComposite, 'lineItem' => $child, 'child' => true, 'doItem' => null])
        @endforeach
    @endif
@endforeach
