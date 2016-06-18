<?php

namespace Kommercio\Http\Controllers\Backend\Report;

use Carbon\Carbon;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Kommercio\Http\Requests;
use Kommercio\Http\Controllers\Controller;
use Kommercio\Models\Order\LineItem;
use Kommercio\Models\Order\Order;
use Kommercio\Models\ShippingMethod\ShippingMethod;
use Kommercio\Models\Store;

class ReportController extends Controller
{
    public function salesYear(Request $request)
    {
        $orderStatusOptions = Order::getStatusOptions();
        $storeOptions = ['all' => 'All'] + Store::getStoreOptions();

        $ordersByYear = Order::checkout()->selectRaw("DATE_FORMAT(checkout_at, '%Y') AS order_year")->groupBy('order_year')->pluck('order_year', 'order_year')->toArray();
        $yearOptions = $ordersByYear ?: [Carbon::now()->format('Y')];

        $filter = [
            'order_date' => [
                'from' => $request->input('search.order_date.from'),
                'to' => $request->input('search.order_date.to')
            ],
            'delivery_date' => [
                'from' => $request->input('search.delivery_date.from'),
                'to' => $request->input('search.delivery_date.to')
            ],
            'status' => $request->input('search.status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING, Order::STATUS_COMPLETED]),
            'store' => $request->input('search.store', 'all'),
            'year' => $request->input('search.year', key($yearOptions))
        ];

        $qb = Order::selectRaw("DATE_FORMAT(checkout_at, '%c') AS month, SUM(total) AS total, SUM(discount_total) AS discount_total, SUM(shipping_total) AS shipping_total")
            ->whereIn('status', $filter['status']);

        if($filter['store'] != 'all'){
            $qb->where('store_id', $filter['store']);
        }

        $qb->whereRaw("DATE_FORMAT(checkout_at, '%Y') = ?", [$filter['year']])
            ->groupBy('month');

        $orders = $qb->get();

        $results = [];
        foreach ($orders as $order) {
            $results[$order->month] = $order;
        }

        //Get Months
        $months = array();
        for ($i = 1; $i <= 12; $i++) {
            $timestamp = mktime(0, 0, 0, $i, 1);
            $months[date('n', $timestamp)] = date('F', $timestamp);
        }

        return view('backend.report.sales_year', [
            'filter' => $filter,
            'orderStatusOptions' => $orderStatusOptions,
            'storeOptions' => $storeOptions,
            'yearOptions' => $yearOptions,
            'results' => $results,
            'months' => $months
        ]);
    }

    public function sales(Request $request)
    {
        $now = Carbon::now();

        $orderStatusOptions = Order::getStatusOptions();
        $storeOptions = ['all' => 'All'] + Store::getStoreOptions();

        $dateTypeOptions = ['checkout_at' => 'Order Date'];
        if (config('project.enable_delivery_date', false)) {
            $dateTypeOptions['delivery_date'] = 'Delivery Date';
        }

        $filter = [
            'date_type' => $request->input('search.date_type', key($dateTypeOptions)),
            'date' => [
                'from' => $request->input('search.date.from', $now->format('Y-m-01')),
                'to' => $request->input('search.date.to', $now->format('Y-m-t'))
            ],
            'status' => $request->input('search.status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING, Order::STATUS_COMPLETED]),
            'store' => $request->input('search.store', 'all'),
        ];

        $year = date_create_from_format('Y-m-d', $filter['date']['from']);

        $qb = Order::selectRaw("DATE_FORMAT(" . $filter['date_type'] . ", '%Y-%m-%d') AS date, SUM(total) AS total, SUM(discount_total) AS discount_total, SUM(shipping_total) AS shipping_total")
            ->whereIn('status', $filter['status'])
            ->groupBy('date');

        if($filter['store'] != 'all'){
            $qb->where('store_id', $filter['store']);
        }

        $qb->whereRaw("DATE_FORMAT(" . $filter['date_type'] . ", '%Y-%m-%d') >= ?", [$filter['date']['from']]);

        $qb->whereRaw("DATE_FORMAT(" . $filter['date_type'] . ", '%Y-%m-%d') <= ?", [$filter['date']['to']]);

        $orders = $qb->get();

        $results = [];
        foreach ($orders as $order) {
            $results[$order->date] = $order;
        }

        return view('backend.report.sales', [
            'filter' => $filter,
            'orderStatusOptions' => $orderStatusOptions,
            'storeOptions' => $storeOptions,
            'dateTypeOptions' => $dateTypeOptions,
            'results' => $results,
            'year' => $year->format('Y')
        ]);
    }

    public function delivery(Request $request)
    {
        $date = Carbon::now();
        $date->modify('+1 day');

        $orderStatusOptions = Order::getStatusOptions();
        $storeOptions = ['all' => 'All'] + Store::getStoreOptions();

        $shippingMethods = ShippingMethod::getShippingMethods();
        //$shippingMethodOptions = ['all' => 'All'];
        foreach($shippingMethods as $shippingMethodIdx=>$shippingMethod)
        {
            $shippingMethodOptions[$shippingMethodIdx] = $shippingMethod['name'];
        }

        $filter = [
            'shipping_method' => $request->input('search.shipping_method', array_keys($shippingMethodOptions)),
            'date_type' => 'delivery_date',
            'date' => $request->input('search.date', $date->format('Y-m-d')),
            'status' => $request->input('search.status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING]),
            'store' => $request->input('search.store', 'all'),
        ];

        $qb = Order::with('shippingProfile', 'lineItems')
            ->whereIn('status', $filter['status'])
            ->orderBy('checkout_at', 'ASC')
            ->joinShippingProfile();

        if($filter['store'] != 'all'){
            $qb->where('store_id', $filter['store']);
        }

        if(count($filter['shipping_method']) != count($shippingMethodOptions)){
            $qb->whereHas('lineItems', function($qb) use ($filter){
                foreach($filter['shipping_method'] as $idx=>$shippingMethod){
                    if($idx == 0){
                        $searchFunction = 'searchData';
                    }else{
                        $searchFunction = 'orSearchData';
                    }

                    $qb->lineItemType('shipping')->$searchFunction('shipping_method', $filter['shipping_method']);
                }
            });

            $shippingMethod = $shippingMethodOptions[$filter['shipping_method']];
        }else{
            $shippingMethod = 'All Delivery';
        }

        $qb->whereRaw("DATE_FORMAT(" . $filter['date_type'] . ", '%Y-%m-%d') = ?", [$filter['date']]);

        $deliveryDate = Carbon::createFromFormat('Y-m-d', $filter['date']);

        $orders = $qb->get();

        //Get Ordered Products. Use custom query because we want to sort by Sort Order
        $orderedProductsQb = LineItem::lineItemType('product')
            ->whereIn('order_id', $orders->pluck('id')->all())
            ->joinProduct()
            ->orderBy('PD.sort_order', 'ASC');

        $orderedProducts = [];
        foreach($orders as $order){
            foreach($orderedProductsQb->get() as $lineItem){
                if(!isset($orderedProducts[$lineItem->line_item_id])){
                    $orderedProducts[$lineItem->line_item_id] = [
                        'quantity' => 0,
                        'name' => $lineItem->product->name
                    ];
                }

                $orderedProducts[$lineItem->line_item_id]['quantity'] += $lineItem->quantity;
            }
        }

        return view('backend.report.delivery', [
            'filter' => $filter,
            'orderStatusOptions' => $orderStatusOptions,
            'storeOptions' => $storeOptions,
            'deliveryDate' => $deliveryDate,
            'orders' => $orders,
            'orderedProducts' => $orderedProducts,
            'shippingMethodOptions' => $shippingMethodOptions,
            'shippingMethod' => $shippingMethod
        ]);
    }
}