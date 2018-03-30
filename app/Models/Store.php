<?php

namespace Kommercio\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Kommercio\Facades\ProjectHelper;
use Kommercio\Models\Interfaces\CacheableInterface;
use Kommercio\Models\Store\OpeningTime;
use Kommercio\Traits\Model\HasDataColumn;

class Store extends Model implements CacheableInterface
{
    use HasDataColumn;

    const TYPE_ONLINE = 'online';
    const TYPE_OFFLINE = 'offline';

    protected $guarded = ['warehouses', 'contacts', 'location', 'openingTimes'];

    //Relations
    public function orders()
    {
        return $this->hasMany('Kommercio\Models\Order\Order');
    }

    public function openingTimes()
    {
        return $this->hasMany(OpeningTime::class)->orderBy('sort_order');
    }

    //Accessors
    public function getOrderCountAttribute()
    {
        $count = $this->orders()->checkout()->count();

        return $count;
    }

    public function getProductCountAttribute()
    {
        return $this->productDetails->count();
    }

    // Methods
    public function getLocationAttribute()
    {
        return [
            'address_1' => $this->address_1,
            'address_2' => $this->address_2,
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,
            'district_id' => $this->district_id,
            'area_id' => $this->area_id,
            'postal_code' => $this->postal_code,
        ];
    }

    public function getDefaultWarehouse()
    {
        $warehouse = $this->warehouses->get(0);

        return $warehouse;
    }

    public function getTaxes()
    {
        $taxes = Cache::rememberForever($this->getTable().'_'.$this->id.'.taxes', function(){
            $taxes = Tax::getTaxes([
                'store_id' => $this->id,
                'country_id' => $this->country_id,
                'state_id' => $this->state_id,
                'city_id' => $this->city_id,
                'district_id' => $this->district_id,
                'area_id' => $this->area_id,
            ]);

            return $taxes;
        });

        return $taxes;
    }

    /**
     * Check if store is open at given time
     *
     * @param Carbon $time
     * @return boolean
     */
    public function isOpen(Carbon $time = null)
    {
        if(!$time){
            $time = Carbon::now();
        }

        // Find opening times based on time
        $openingTimes = $this->getOpeningTimes($time);

        if($openingTimes->count() < 1){
            return FALSE;
        }

        return $openingTimes->first()->isOpen($time);
    }

    /**
     * Get OpeningTime based on given time
     *
     * @param Carbon|null $time
     * @return Collection
     */
    public function getOpeningTimes(Carbon $time = null)
    {
        if(ProjectHelper::cacheIsTaggable()) {
            $openingTimes = Cache::tags([$this->getTable() . '_' . $this->id . '_opening_times'])->rememberForever($this->getTable() . '_' . $this->id . '_opening_times_' . $time->format('U'), function() use ($time) {
                return $this->openingTimes()
                    ->withinDate($time->format('Y-m-d'))
                    ->withinTime($time->format('H:i:s'))
                    ->get();
            });
        } else {
            $openingTimes = $this->openingTimes()
                ->withinDate($time->format('Y-m-d'))
                ->withinTime($time->format('H:i:s'))
                ->get();
        }

        return $openingTimes;
    }

    /**
     * @inheritdoc
     */
    public function getCacheKeys()
    {
        $tableName = $this->getTable();
        $keys = [
            $tableName.'_'.$this->id.'.taxes',
            $tableName.'_'.$this->code,
            [
                $this->getTable() . '_' . $this->id . '_opening_times',
            ],
        ];

        return $keys;
    }

    //Static
    public static function findByCode($code) {
        $tableName = (new static)->getTable();

        return Cache::remember($tableName . '_' . $code, 3600, function() use ($code) {
            return static::where('code', $code)->first();
        });
    }

    public static function getTypeOptions($option=null)
    {
        $array = [
            self::TYPE_ONLINE => 'Online',
            self::TYPE_OFFLINE => 'Offline',
        ];

        if(empty($option)){
            return $array;
        }

        return (isset($array[$option]))?$array[$option]:$array;
    }

    public static function getDefaultStore()
    {
        $defaultStore = self::where('default', true)->first();

        if(!$defaultStore){
            $defaultStore = self::orderBy('created_at', 'ASC')->first();
        }

        return $defaultStore;
    }

    public static function getStoreOptions($all = false, $withAllOption = FALSE)
    {
        $stores = [];

        if($withAllOption){
            $stores += ['all' => 'All'];
        }

        if($all){
            $stores += self::orderBy('created_at', 'DESC')->pluck('name', 'id')->all();
        }else{
            $stores += Auth::user()->getManagedStores()->pluck('name', 'id')->all();
        }

        return $stores;
    }

    //Relations
    public function warehouses()
    {
        return $this->belongsToMany('Kommercio\Models\Warehouse')->withPivot('sort_order')->orderBy('sort_order', 'ASC');
    }

    public function productDetails()
    {
        return $this->hasMany('Kommercio\Models\ProductDetail')->productEntity();
    }

    public function orderLimits()
    {
        return $this->hasMany('Kommercio\Models\Order\OrderLimit');
    }

    public function users()
    {
        return $this->belongsToMany('Kommercio\Models\User');
    }
}
