<?php

namespace Kommercio\Models\Profile;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Kommercio\Models\Address\Area;
use Kommercio\Models\Address\City;
use Kommercio\Models\Address\Country;
use Kommercio\Models\Address\District;
use Kommercio\Models\Address\State;
use Kommercio\Traits\Model\HasAddress;

class Profile extends Model {
    use HasAddress;

    protected $with = ['details'];
    protected $guarded = [];
    protected $profileDetails = [];

    private $_profileFilled = false;

    //Relations
    public function details()
    {
        return $this->hasMany('Kommercio\Models\Profile\ProfileDetail', 'profile_id');
    }

    public function profileable()
    {
        return $this->morphTo();
    }

    //Methods
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->profileDetails)) {
            return $this->profileDetails[$key];
        }

        return parent::getAttribute($key);
    }

    public function __isset($key)
    {
        if(array_key_exists($key, $this->profileDetails)){
            return TRUE;
        }

        return parent::__isset($key);
    }

    public function getDetails()
    {
        if ($this->exists) {
            $profileDetails = Cache::rememberForever('profile_details_' . $this->id, function() {
                $this->fillDetails();

                return $this->profileDetails;
            });

            $this->profileDetails = $profileDetails;
        }

        return $this->profileDetails;
    }

    public function fillDetails($refresh = FALSE)
    {
        if(!$this->_profileFilled || $refresh){
            $details = $this->getRelationValue('details');

            $fullNameParts = [];

            foreach($details as $detail){
                $this->profileDetails[$detail->identifier] = $detail->value;
                if(in_array($detail->identifier, ['first_name', 'last_name'])){
                    $fullNameParts[] = $detail->value;
                }
            }

            if($fullNameParts){
                $this->profileDetails['full_name'] = implode(' ', $fullNameParts);
            }

            $this->_profileFilled = TRUE;
        }

        return $this;
    }

    public function saveDetails($details)
    {
        if(!$this->exists){
            throw new \LogicException('Profile doesn\'t exist.');
        }

        if(isset($details['full_name'])){
            $explodedFullName = explode(' ', $details['full_name']);

            $details['first_name'] = array_shift($explodedFullName);

            if(!empty($explodedFullName)){
                $details['last_name'] = implode(' ', $explodedFullName);
            }else{
                $details['last_name'] = '';
            }

            unset($details['full_name']);
        }

        // Remove custom city if city_id is given
        if (isset($details['custom_city'])) {
            if (!empty($details['city_id'])) {
                $details['custom_city'] = '';
            }
        }

        $profileFieldsFromAttributes = array_keys($details);

        $flippedProfiles = array_flip($profileFieldsFromAttributes);

        foreach($this->details as $profile){
            if(isset($details[$profile->identifier])){
                $detailValue = trim($details[$profile->identifier]);

                if($detailValue == ''){
                    $profile->delete();
                }else{
                    $profile->update([
                        'value' => $detailValue
                    ]);

                    $this->profileDetails[$profile->identifier] = $detailValue;
                }
            }

            //Remove updated field from attributes so it won't get processed in next process
            unset($flippedProfiles[$profile->identifier]);
        }

        $flippedProfiles = array_flip($flippedProfiles);

        foreach($flippedProfiles as $newProfile){
            if(trim($details[$newProfile]) !== ''){
                ProfileDetail::create([
                    'identifier' => $newProfile,
                    'value' => $details[$newProfile],
                    'profile_id' => $this->id
                ]);
            }
        }

        Cache::forget('profile_details_' . $this->id);
    }

    //Scopes
    public function scopeJoinAddress($query)
    {
        $profileDetailQuery = $this->details();

        $countryTable = with(new Country())->getTable();
        $stateTable = with(new State())->getTable();
        $cityTable = with(new City())->getTable();
        $districtTable = with(new District())->getTable();
        $areaTable = with(new Area())->getTable();

        $query->leftJoin($profileDetailQuery->getRelated()->getTable().' AS VADDRESS1', function($join) use ($profileDetailQuery){
            $join->on('VADDRESS1.'.$profileDetailQuery->getForeignKeyName(), '=', $this->getQualifiedKeyName())
                ->where('VADDRESS1.identifier', '=', 'address_1');
        });

        $query->leftJoin($profileDetailQuery->getRelated()->getTable().' AS VADDRESS2', function($join) use ($profileDetailQuery){
            $join->on('VADDRESS2.'.$profileDetailQuery->getForeignKeyName(), '=', $this->getQualifiedKeyName())
                ->where('VADDRESS2.identifier', '=', 'address_2');
        });

        $query->leftJoin($profileDetailQuery->getRelated()->getTable().' AS VSTATE', function($join) use ($profileDetailQuery){
            $join->on('VSTATE.'.$profileDetailQuery->getForeignKeyName(), '=', $this->getQualifiedKeyName())
                ->where('VSTATE.identifier', '=', 'state_id');
        })->leftJoin($stateTable.' AS STATE', 'STATE.id', '=', 'VSTATE.value');

        $query->leftJoin($profileDetailQuery->getRelated()->getTable().' AS VCITY', function($join) use ($profileDetailQuery){
            $join->on('VCITY.'.$profileDetailQuery->getForeignKeyName(), '=', $this->getQualifiedKeyName())
                ->where('VCITY.identifier', '=', 'city_id');
        })->leftJoin($cityTable.' AS CITY', 'CITY.id', '=', 'VCITY.value');

        $query->leftJoin($profileDetailQuery->getRelated()->getTable().' AS VDISTRICT', function($join) use ($profileDetailQuery){
            $join->on('VDISTRICT.'.$profileDetailQuery->getForeignKeyName(), '=', $this->getQualifiedKeyName())
                ->where('VDISTRICT.identifier', '=', 'district_id');
        })->leftJoin($districtTable.' AS DISTRICT', 'DISTRICT.id', '=', 'VDISTRICT.value');

        $query->leftJoin($profileDetailQuery->getRelated()->getTable().' AS VAREA', function($join) use ($profileDetailQuery){
            $join->on('VAREA.'.$profileDetailQuery->getForeignKeyName(), '=', $this->getQualifiedKeyName())
                ->where('VAREA.identifier', '=', 'area_id');
        })->leftJoin($areaTable.' AS AREA', 'AREA.id', '=', 'VAREA.value');

        $query->leftJoin($profileDetailQuery->getRelated()->getTable().' AS VPOSTAL', function($join) use ($profileDetailQuery){
            $join->on('VPOSTAL.'.$profileDetailQuery->getForeignKeyName(), '=', $this->getQualifiedKeyName())
                ->where('VPOSTAL.identifier', '=', 'postal_code');
        });

        $query->selectRaw($this->getTable().'.*, VADDRESS1.value AS address_1, VADDRESS2.value AS address_2, STATE.name AS state, CITY.name AS city, DISTRICT.name AS district, AREA.name AS area, VPOSTAL.value AS postal_code');
    }

    public function scopeWhereField($query, $key, $value, $operator='=')
    {
        $query->whereHas('details', function($qb) use ($key, $value, $operator){
            $qb->where('identifier', $key)
                ->where('value', $operator, $value);
        });
    }

    public function scopeWhereFields($query, $filters, $or=FALSE)
    {
        $masterQb = ProfileDetail::query();

        $profileIds = [];

        foreach($filters as $idx=>$filter) {
            $qb = clone $masterQb;
            $filter['operator'] = isset($filter['operator']) ? $filter['operator'] : '=';

            if(in_array($filter['key'], ['state', 'country', 'city', 'district', 'area'])){
                $qb->where(function($innerQb) use ($filter){
                    $addressClass = '\Kommercio\Models\Address\\'.studly_case($filter['key']);
                    $addressModel = call_user_func($addressClass.'::find', $filter['value']);

                    if($addressModel){
                        $addressId = $addressModel->id;
                    }else{
                        $addressId = 'FALSE_ID';
                    }

                    $innerQb->where('identifier', $filter['key'].'_id')
                        ->where('value', $filter['operator'], $addressId);
                });
            }else{
                $qb->where(function($innerQb) use ($filter){
                    $innerQb->where('identifier', $filter['key'])
                        ->where('value', $filter['operator'], $filter['value']);
                });
            }

            $ids = $qb->pluck('profile_id')->all();

            foreach ($ids as $id) {
                if (!isset($profileIds[$id])) {
                    $profileIds[$id] = 1;
                } else {
                    $profileIds[$id] += 1;
                }
            }
        };

        if (!$or) {
            $filteredIds = array_keys(array_filter($profileIds, function($count) use ($filters) {
                return $count >= count($filters);
            }));

            $query->whereIn('id', $filteredIds);
        } else {
            $query->whereIn('id', array_keys($profileIds));
        }

        /*$method = 'whereHas';

        if($or){
            $method = 'orWhereHas';
        }

        foreach($filters as $idx=>$filter){
            $filter['operator'] = isset($filter['operator'])?$filter['operator']:'=';

            if(in_array($filter['key'], ['state', 'country', 'city', 'district', 'area'])){
                $query->$method('details', function($qb) use ($filter){
                    $addressClass = '\Kommercio\Models\Address\\'.studly_case($filter['key']);
                    $tableName = with(new $addressClass())->getTable();

                    $qb->join($tableName.' AS A', function($join) use ($filter){
                        $join->on('A.id', '=', 'value')->where('identifier', '=', $filter['key'].'_id');
                    })->where('identifier', $filter['key'].'_id')->where('A.name', $filter['operator'], $filter['value']);
                });
            }else{
                $query->$method('details', function($qb) use ($filter){
                    $qb->where('identifier', $filter['key'])
                        ->where('value', $filter['operator'], $filter['value']);
                });
            }
        }*/
    }
}
