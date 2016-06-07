<?php

namespace Kommercio\Http\Controllers\Backend\Sales;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Kommercio\Facades\OrderHelper;
use Kommercio\Http\Controllers\Controller;
use Kommercio\Models\Customer;
use Kommercio\Models\Order\Order;
use Kommercio\Models\Order\LineItem;
use Kommercio\Http\Requests\Backend\Order\OrderFormRequest;
use Collective\Html\FormFacade;
use Illuminate\Support\Facades\Request as RequestFacade;
use Kommercio\Models\PriceRule\CartPriceRule;
use Kommercio\Models\Product;
use Kommercio\Models\Profile\Profile;
use Kommercio\Facades\PriceFormatter;
use Kommercio\Models\ShippingMethod\ShippingMethod;
use Kommercio\Models\Tax;

class OrderController extends Controller{
    public function index(Request $request)
    {
        $qb = Order::joinBillingProfile()
            ->joinShippingProfile();

        if($request->ajax() || $request->wantsJson()){
            $totalRecords = $qb->count();

            foreach($request->input('filter', []) as $searchKey=>$search){
                if(is_array($search) || trim($search) != ''){
                    if($searchKey == 'billing_full_name') {
                        $qb->whereRaw('CONCAT(BNAME.value, " ", BNAME.value) LIKE ?', ['%'.$search.'%']);
                    }elseif($searchKey == 'shipping_full_name') {
                        $qb->whereRaw('CONCAT(SNAME.value, " ", SNAME.value) LIKE ?', ['%'.$search.'%']);
                    }elseif($searchKey == 'reference'){
                        $qb->where($searchKey, 'LIKE', '%'.$search.'%');
                    }elseif($searchKey == 'checkout_at'){
                        if(!empty($search['from'])){
                            $qb->whereRaw('DATE_FORMAT(checkout_at, \'%Y-%m-%d\') <= ?', [$search['from']]);
                        }

                        if(!empty($search['to'])){
                            $qb->whereRaw('DATE_FORMAT(checkout_at, \'%Y-%m-%d\') >= ?', [$search['to']]);
                        }
                    }else{
                        $qb->where($searchKey, $search);
                    }
                }
            }

            $filteredRecords = $qb->count();

            $columns = $request->input('columns');
            foreach($request->input('order', []) as $order){
                $orderColumn = $columns[$order['column']];

                $qb->orderBy($orderColumn['name'], $order['dir']);
            }

            if($request->has('length')){
                $qb->take($request->input('length'));
            }

            if($request->has('start') && $request->input('start') > 0){
                $qb->skip($request->input('start'));
            }

            $orders = $qb->get();

            $meat = $this->prepareDatatables($orders, $request->input('start'));

            $data = [
                'draw' => $request->input('draw'),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $meat
            ];

            return response()->json($data);
        }

        return view('backend.order.index');
    }

    protected function prepareDatatables($orders, $orderingStart=0)
    {
        $meat= [];

        foreach($orders as $idx=>$order){
            $orderAction = '';

            $orderAction .= '<div class="btn-group btn-group-sm dropup">';
            $orderAction .= '<a class="btn btn-default" href="'.route('backend.sales.order.view', ['id' => $order->id, 'backUrl' => RequestFacade::fullUrl()]).'"><i class="fa fa-search"></i></a>';

            if(in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PROCESSING])) {
                $orderAction .= '<button type="button" class="btn btn-default hold-on-click dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true" aria-expanded="true"><i class="fa fa-flag-o"></i></button><ul class="dropdown-menu" role="menu">';
                if (in_array($order->status, [Order::STATUS_PENDING])) {
                    $orderAction .= '<li><a class="modal-ajax" href="' . route('backend.sales.order.process', ['action' => 'processing', 'id' => $order->id, 'backUrl' => RequestFacade::fullUrl()]) . '"><i class="fa fa-toggle-right"></i> Process</a></li>';
                }

                if (in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PROCESSING])) {
                    $orderAction .= '<li><a class="modal-ajax" href="' . route('backend.sales.order.process', ['action' => 'completed', 'id' => $order->id, 'backUrl' => RequestFacade::fullUrl()]) . '"><i class="fa fa-check-circle"></i> Complete</a></li>';
                    $orderAction .= '<li><a class="modal-ajax" href="' . route('backend.sales.order.process', ['action' => 'cancelled', 'id' => $order->id, 'backUrl' => RequestFacade::fullUrl()]) . '"><i class="fa fa-remove"></i> Cancel</a></li>';
                }
                $orderAction .= '</ul>';
            }

            $orderAction .= '</div>';

            if($order->isEditable){
                $orderAction .= FormFacade::open(['route' => ['backend.sales.order.delete', 'id' => $order->id], 'class' => 'form-in-btn-group']);
                $orderAction .= '<div class="btn-group btn-group-sm">';
                $orderAction .= '<a class="btn btn-default" href="'.route('backend.sales.order.edit', ['id' => $order->id, 'backUrl' => RequestFacade::fullUrl()]).'"><i class="fa fa-pencil"></i></a>';

                if($order->isDeleteable) {
                    $orderAction .= '<button class="btn btn-default" data-toggle="confirmation" data-original-title="Are you sure?" title="" class="btn-link"><i class="fa fa-trash-o"></i></button></div>';
                }

                $orderAction .= FormFacade::close().'</div>';
            }

            $meat[] = [
                $idx + 1 + $orderingStart,
                $order->reference,
                $order->checkout_at?$order->checkout_at->format('d M Y, H:i'):'',
                $order->billing_full_name,
                $order->shipping_full_name,
                PriceFormatter::formatNumber($order->total),
                '<label class="label label-sm bg-'.OrderHelper::getOrderStatusLabelClass($order->status).' bg-font-'.OrderHelper::getOrderStatusLabelClass($order->status).'">'.Order::getStatusOptions($order->status, TRUE).'</label>',
                $orderAction
            ];
        }

        return $meat;
    }

    public function create()
    {
        $order = new Order();

        $lineItems = old('line_items', []);

        $shippingMethods = ShippingMethod::getShippingMethods();

        foreach(old('cartPriceRules', []) as $oldCartPriceRule){
            $cartPriceRules[] = CartPriceRule::findOrFail($oldCartPriceRule);
        }

        foreach(old('taxes', []) as $oldTax){
            $taxes[] = Tax::findOrFail($oldTax);
        }

        return view('backend.order.create', [
            'order' => $order,
            'lineItems' => $lineItems,
            'shippingMethods' => $shippingMethods,
            'taxes' => isset($taxes)?$taxes:[],
            'cartPriceRules' => isset($cartPriceRules)?$cartPriceRules:[],
        ]);
    }

    public function store(OrderFormRequest $request)
    {
        $order = new Order();

        $customer = null;
        if($request->has('existing_customer')){
            $customer = Customer::getByEmail($request->get('existing_customer'));
        }

        $order->delivery_date = $request->input('delivery_date', null);
        $order->store_id = $request->input('store_id');
        $order->currency = $request->input('currency');
        $order->conversion_rate = 1;
        $order->save();

        $order->saveProfile('billing', $request->input('profile'));
        $order->saveProfile('shipping', $request->input('shipping_profile'));

        $this->processLineItems($request, $order);

        $order->load('lineItems');
        $order->processStocks();
        $order->calculateTotal();

        if($request->input('action') == 'place_order'){
            $this->placeOrder($order);

            if(!$customer){
                $profileData = $request->input('profile');

                $customer = Customer::saveCustomer($profileData);
            }
        }else{
            $order->status = Order::STATUS_ADMIN_CART;
        }

        if($customer){
            $order->customer()->associate($customer);
        }

        $order->save();

        return redirect()->route('backend.sales.order.index')->with('success', ['This '.Order::getStatusOptions($order->status, true).' order has successfully been created.']);
    }

    public function view($id)
    {
        $order = Order::findOrFail($id);

        $lineItems = $order->lineItems;
        $billingProfile = $order->billingProfile?$order->billingProfile->fillDetails():new Profile();
        $shippingProfile = $order->shippingProfile?$order->shippingProfile->fillDetails():new Profile();

        return view('backend.order.view', [
            'order' => $order,
            'lineItems' => $lineItems,
            'billingProfile' => $billingProfile,
            'shippingProfile' => $shippingProfile
        ]);
    }

    public function edit($id)
    {
        $order = Order::findOrFail($id);

        $lineItems = old('line_items', $order->lineItems);

        $oldValues = old();

        if(!$oldValues){
            $oldValues['existing_customer'] = $order->customer?$order->customer->getProfile()->email:null;
            $oldValues['profile'] = $order->billingProfile?$order->billingProfile->getDetails():[];
            $oldValues['shipping_profile'] = $order->shippingProfile?$order->shippingProfile->getDetails():[];

            foreach($lineItems as $lineItem){
                $lineItemData = $lineItem->toArray();
                $lineItemTotal = $lineItem->calculateTotal();

                if($lineItem->isProduct){
                    $lineItemData['sku'] = $lineItem->product->sku;
                }

                if($lineItem->isCartPriceRule){
                    $oldValues['cart_price_rules'][] = $lineItem->line_item_id;
                }

                if($lineItem->isTax){
                    $oldValues['taxes'][] = $lineItem->line_item_id;
                }

                $lineItemData['lineitem_total_amount'] = $lineItemTotal;

                $oldValues['line_items'][] = $lineItemData;
            }

            Session::flashInput($oldValues);
        }

        foreach(old('cart_price_rules', []) as $oldCartPriceRule){
            $cartPriceRules[] = CartPriceRule::findOrFail($oldCartPriceRule);
        }

        foreach(old('taxes', []) as $oldTax){
            $taxes[] = Tax::findOrFail($oldTax);
        }

        return view('backend.order.edit', [
            'order' => $order,
            'lineItems' => $lineItems,
            'taxes' => isset($taxes)?$taxes:[],
            'cartPriceRules' => isset($cartPriceRules)?$cartPriceRules:[]
        ]);
    }

    public function update(OrderFormRequest $request, $id)
    {
        $order = Order::findOrFail($id);

        $customer = null;
        if($request->has('existing_customer')){
            $customer = Customer::getByEmail($request->get('existing_customer'));
        }

        $order->delivery_date = $request->input('delivery_date', null);
        $order->store_id = $request->input('store_id');
        $order->currency = $request->input('currency');
        $order->conversion_rate = 1;

        $order->saveProfile('billing', $request->input('profile'));
        $order->saveProfile('shipping', $request->input('shipping_profile'));

        //If PENDING order is updated, return all stocks first
        if($order->status == Order::STATUS_PENDING){
            $order->returnStocks();
        }

        $this->processLineItems($request, $order);

        $order->load('lineItems');
        $order->processStocks();
        $order->calculateTotal();

        if($request->input('action') == 'place_order'){
            $this->placeOrder($order);

            if(!$customer){
                $profileData = $request->input('profile');

                $customer = Customer::saveCustomer($profileData);
            }
        }else{

        }

        if($customer){
            $order->customer()->associate($customer);
        }

        $order->save();

        return redirect()->route('backend.sales.order.index')->with('success', ['This '.Order::getStatusOptions($order->status, true).' order has successfully been updated.']);
    }

    public function delete($id)
    {
        $order = Order::findOrFail($id);

        $order->delete();

        return redirect()->route('backend.sales.order.index')->with('success', ['This '.Order::getStatusOptions($order->status, true).' order has successfully been deleted.']);
    }

    public function process(Request $request, $process, $id=null)
    {
        $order = Order::find($id);

        if($request->isMethod('GET')){
            switch($process){
                case 'pending':
                    $processForm = 'pending_form';
                    break;
                case 'processing':
                    $processForm = 'processing_form';
                    break;
                case 'completed':
                    $processForm = 'completed_form';
                    break;
                case 'cancelled':
                    $processForm = 'cancelled_form';
                    break;
                default:
                    return response('No process is selected.');
                    break;
            }

            return view('backend.order.process.'.$processForm, [
                'order' => $order,
                'backUrl' => $request->get('backUrl', route('backend.sales.order.index'))
            ]);
        }else{
            switch($process){
                case 'processing':
                    $order->status = Order::STATUS_PROCESSING;
                    $message = 'Order has been set to <span class="label bg-'.OrderHelper::getOrderStatusLabelClass($order->status).' bg-font-'.OrderHelper::getOrderStatusLabelClass($order->status).'">Processing.</span>';
                    break;
                case 'completed':
                    $order->status = Order::STATUS_COMPLETED;
                    $message = 'Order has been <span class="label bg-'.OrderHelper::getOrderStatusLabelClass($order->status).' bg-font-'.OrderHelper::getOrderStatusLabelClass($order->status).'">Completed.</span>';
                    break;
                case 'cancelled':
                    $order->returnStocks();
                    $order->status = Order::STATUS_CANCELLED;
                    $message = 'Order has been <span class="label bg-'.OrderHelper::getOrderStatusLabelClass($order->status).' bg-font-'.OrderHelper::getOrderStatusLabelClass($order->status).'">Cancelled.</span>';
                    break;
                default:
                    $message = 'No process has been done.';
                    break;
            }

            $order->save();

            return redirect($request->input('backUrl', route('backend.sales.order.index')))->with('success', [$message]);
        }
    }

    public function copyCustomerInformation(Request $request, $type, $profile_id = null)
    {
        if($profile_id){
            $profile = Profile::findOrFail($profile_id);
            $profileDetails = $profile->getDetails();
        }else{
            $profileDetails = $request->input($request->input('source'));
        }

        //Flash to internally rendered form. We will remove flashed data later
        Session::flashInput([$type => $profileDetails]);

        $render = view('backend.order.customer_information', ['type' => $type])->render();

        Session::remove('_old_input');

        return response()->json(['data' => $render, '_token' => csrf_token()]);
    }

    public function lineItemRow(Request $request, $type, $id=null)
    {
        switch($type){
            case 'product':
                $model = Product::findOrFail($id);
                break;
            default:
                break;
        }

        $render = view('backend.order.line_items.form.product', [
            'product' => $model,
            'key' => $request->get('product_index')
        ])->render();

        return response()->json(['data' => $render, '_token' => csrf_token()]);
    }

    public function shippingOptions(Request $request)
    {
        $return = [];

        $shippingOptions = ShippingMethod::getShippingMethods();

        foreach($shippingOptions as $idx=>$shippingOption){
            $return[$idx] = [
                'shipping_method_id' => $shippingOption['shipping_method_id'],
                'name' => $shippingOption['name'],
                'price' => $shippingOption['price']
            ];
        }

        return response()->json($return);
    }

    public function getCartRules(Request $request)
    {
        //Create dummy order for subTotal calculation
        $order = new Order();

        $customer_email = null;
        if($request->has('profile.email')){
            $customer_email = $request->input('profile.email');
        }

        $deliveryDate = $request->input('delivery_date', null);
        $store_id = $request->input('store_id');
        $currency = $request->input('currency');

        $count = 0;
        foreach($request->input('line_items', []) as $lineItemDatum){
            if($lineItemDatum['line_item_type'] == 'product' && (empty($lineItemDatum['quantity']) || empty($lineItemDatum['sku']))){
                continue;
            }

            $lineItem = new LineItem();
            $lineItem->processData($lineItemDatum, $count);
            $order->lineItems[] = $lineItem;

            $count += 1;
        }

        $subtotal = $order->calculateProductTotal() + $order->calculateAdditionalTotal();

        $shippings = [];
        foreach($order->getShippingLineItems() as $shippingLineItem){
            $shippings[] = $shippingLineItem->line_item_id;
        }

        $priceRules = CartPriceRule::getCartPriceRules([
            'subtotal' => $subtotal,
            'currency' => $currency,
            'store_id' => $store_id,
            'customer_email' => $customer_email,
            'shippings' => $shippings
        ]);

        if($request->ajax()){
            return response()->json([
                'data' => $priceRules,
                '_token' => csrf_token()
            ]);
        }else{
            return $priceRules;
        }
    }

    protected function processLineItems(Request $request, $order)
    {
        $dummyOrder = new Order();

        $productCartPriceRules = [];
        $orderCartPriceRules = [];

        foreach($request->input('cart_price_rules', []) as $cart_price_rule_id){
            $cartPriceRule = CartPriceRule::findOrFail($cart_price_rule_id);

            if($cartPriceRule->offer_type == CartPriceRule::OFFER_TYPE_ORDER_DISCOUNT){
                $orderCartPriceRules[] = $cartPriceRule;
            }elseif($cartPriceRule->offer_type == CartPriceRule::OFFER_TYPE_PRODUCT_DISCOUNT){
                $productCartPriceRules[] = $cartPriceRule;
            }
        }

        $existingLineItems = $order->lineItems->all();
        $count = 0;

        $taxableTotal = 0;

        foreach($request->input('line_items') as $lineItemDatum){
            if($lineItemDatum['line_item_type'] == 'product' && empty($lineItemDatum['quantity'])){
                continue;
            }

            $lineItem = $this->reuseOrCreateLineItem($order, $existingLineItems, $count);

            $lineItem->processData($lineItemDatum, $count);
            $lineItem->save();

            $lineItemTotal = $lineItem->calculateTotal();

            if($lineItem->isProduct){
                foreach($productCartPriceRules as $productCartPriceRule){
                    $value = $productCartPriceRule->getValue($lineItemTotal);

                    $lineItemTotal += $value;
                    $productCartPriceRule->total += $value;
                }
            }

            if($lineItem->taxable){
                $taxableTotal += $lineItemTotal;
            }

            $count += 1;

            $dummyOrder->lineItems[] = $lineItem;
        }

        foreach($productCartPriceRules as $productCartPriceRule){
            $priceRuleLineItemDatum = [
                'cart_price_rule_id' => $productCartPriceRule->id,
                'line_item_type' => 'cart_price_rule',
                'lineitem_total_amount' => $productCartPriceRule->total,
            ];

            $lineItem = $this->reuseOrCreateLineItem($order, $existingLineItems, $count);

            $lineItem->processData($priceRuleLineItemDatum, $count);
            $lineItem->save();

            $count += 1;
        }

        $dummyOrderSubtotal = $dummyOrder->calculateSubtotal() + $dummyOrder->calculateAdditionalTotal();

        foreach($orderCartPriceRules as $orderCartPriceRule){
            $value = $orderCartPriceRule->getValue($dummyOrderSubtotal);
            $orderCartPriceRule->total += $value;
            $taxableTotal += $value;

            $priceRuleLineItemDatum = [
                'cart_price_rule_id' => $orderCartPriceRule->id,
                'line_item_type' => 'cart_price_rule',
                'lineitem_total_amount' => $orderCartPriceRule->total,
            ];

            $lineItem = $this->reuseOrCreateLineItem($order, $existingLineItems, $count);

            $lineItem->processData($priceRuleLineItemDatum, $count);
            $lineItem->save();

            $count += 1;
        }

        foreach($request->input('taxes', []) as $tax_id){
            $tax = Tax::findOrFail($tax_id);

            $taxLineItemDatum = [
                'tax_id' => $tax->id,
                'line_item_type' => 'tax',
                'lineitem_total_amount' => $tax->calculateTax($taxableTotal),
            ];

            $lineItem = $this->reuseOrCreateLineItem($order, $existingLineItems, $count);

            $lineItem->processData($taxLineItemDatum, $count);
            $lineItem->save();

            $count += 1;
        }

        //Delete unused line items
        foreach($existingLineItems as $existingLineItem){
            $existingLineItem->delete();
        }
    }

    protected function reuseOrCreateLineItem($order, &$existingLineItems, $count)
    {
        if(!isset($existingLineItems[$count])){
            $lineItem = new LineItem();
            $lineItem->order()->associate($order);
        }else{
            //Clone and reset existing line item and will eventually be updated with new data
            $lineItem = $existingLineItems[$count];
            unset($existingLineItems[$count]);

            $lineItem->clearData();
        }

        return $lineItem;
    }

    protected function placeOrder(Order $order)
    {
        $order->status = Order::STATUS_PENDING;
        $order->checkout_at = Carbon::now();
        $order->generateReference();

        //Call all shipping processing methods
        foreach($order->getShippingLineItems() as $shippingLineItem){
            $shippingLineItem->shippingMethod->getProcessor()->beforePlaceOrder($order);
        }

        return $order;
    }
}