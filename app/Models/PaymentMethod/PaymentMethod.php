<?php

namespace Kommercio\Models\PaymentMethod;

use Dimsav\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Kommercio\Models\Order\Order;
use Kommercio\Models\Order\Payment;
use Kommercio\Traits\Model\HasDataColumn;

class PaymentMethod extends Model
{
    use Translatable, HasDataColumn;

    public $timestamps = FALSE;
    public $translatedAttributes = ['name', 'message'];

    protected $fillable = ['name', 'class', 'message', 'sort_order'];
    private $_processor;

    //Relations
    public function payments()
    {
        return $this->hasMany('Kommercio\Models\Order\Payment');
    }

    public function orders()
    {
        return $this->hasMany('Kommercio\Models\Order\Order');
    }

    public function stores()
    {
        return $this->morphToMany('Kommercio\Models\Store', 'store_attachable');
    }

    //Methods
    public function getProcessor()
    {
        if(!isset($this->_processor)){
            $this->_processor = null;

            $classNames = [
                'Project\Project\PaymentMethods\\'.$this->class,
                'Kommercio\PaymentMethods\\'.$this->class,
            ];

            foreach($classNames as $className){
                if(class_exists($className)){
                    $this->_processor = new $className();
                    $this->_processor->setPaymentMethod($this);
                    break;
                }
            }
        }

        return $this->_processor;
    }

    /**
     * Render payment method form
     *
     * @param Order $order Order from which to render payment form
     * @return null|string
     * @throws \Exception
     * @throws \Throwable
     */
    public function renderForm(Order $order)
    {
        return $this->getProcessor()->getCheckoutForm($order);
    }

    public function renderSummary(Order $order)
    {
        return $this->getProcessor()->getSummary($order);
    }

    //Statics
    public static function getPaymentMethods($options = null)
    {
        $paymentMethods = self::orderBy('sort_order', 'ASC')->get();

        $return = [];
        foreach($paymentMethods as $paymentMethod){
            if($paymentMethod->getProcessor() && $paymentMethod->getProcessor()->validate($options)){
                $return[] = $paymentMethod;
            }
        }

        return $return;
    }
}
