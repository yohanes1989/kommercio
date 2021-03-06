<?php

namespace Kommercio\PaymentMethods;

use Carbon\Carbon;
use Kommercio\Facades\ProjectHelper;
use Kommercio\Models\Order\Order;
use Kommercio\Models\Order\Payment;
use Kommercio\Models\PaymentMethod\PaymentMethod;
use Illuminate\Http\Request;
use PayPal\Api\FlowConfig;
use PayPal\Api\InputFields;
use PayPal\Api\Presentation;
use PayPal\Api\WebProfile;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Rest\ApiContext;

class PaypalExpressCheckout extends PaymentMethodAbstract implements PaymentMethodSettingFormInterface
{
    private $_apiContext;

    /**
     * @inheritdoc
     */
    public function getSummary(Order $order, $options = null)
    {
        $view = ProjectHelper::getViewTemplate('frontend.order.payment_method.paypal.express_checkout');

        return view($view, ['order' => $order, 'paymentMethod' => $this])->render();
    }

    public function saveForm(Request $request)
    {
        $apiContext = $this->getApiContext();

        $new = true;
        $webProfile = null;
        $currentList = WebProfile::get_list($apiContext);

        //Find profile that ends with _kommercio
        foreach($currentList as $profile){
            if(strpos($profile->getName(), '_kommercio') !== false){
                $webProfile = $profile;
                $new = false;
                break;
            }
        }

        $name = ProjectHelper::getConfig('project_machine_name').'_kommercio';

        if(empty($webProfile)){
            $flowConfig = new FlowConfig();
            $presentation = new Presentation();
            $inputFields = new InputFields();

            $webProfile = new WebProfile();
            $webProfile
                ->setName($name)
                ->setFlowConfig($flowConfig)
                ->setPresentation($presentation)
                ->setInputFields($inputFields)
                ->setTemporary(false);
        }else{
            $flowConfig = $webProfile->getFlowConfig();
            $presentation = $webProfile->getPresentation();
            $inputFields = $webProfile->getInputFields();
        }

        $flowConfig->setLandingPageType('Billing');
        $flowConfig->setUserAction('commit');
        $flowConfig->setReturnUriHttpMethod('GET');

        $presentation
            ->setBrandName(ProjectHelper::getConfig('client_name'))
            ->setLocaleCode('US')
            ->setReturnUrlLabel('Return')
            ->setNoteToSellerLabel('Thank you for shopping with us!');

        $inputFields
            ->setAllowNote(false)
            ->setNoShipping(1)
            ->setAddressOverride(1);

        if($new){
            try{
                $response = $webProfile->create($apiContext);
                $this->paymentMethod->saveData(['web_experience_profile_id' => $response->getId()]);
            } catch (PayPalConnectionException $e) {
                \Log::info($e->getData());
            }
        }else{
            try{
                $webProfile->update($apiContext);
                $this->paymentMethod->saveData(['web_experience_profile_id' => $webProfile->getId()]);
            } catch (PayPalConnectionException $e) {
                \Log::info($e->getData());
            }
        }

        $this->paymentMethod->save();
    }

    public function settingForm()
    {
        return ProjectHelper::getViewTemplate('backend.payment_method.Paypal.ExpressCheckout.additional_setting_form');
    }

    public function getIsProduction()
    {
        return $this->paymentMethod->getData('is_production', false);
    }

    public function getEnvironment()
    {
        return $this->getIsProduction()?'production':'sandbox';
    }

    public function getEmail()
    {
        return $this->paymentMethod->getData('email');
    }

    public function getClientId()
    {
        return $this->paymentMethod->getData('client_id');
    }

    public function getSecretKey()
    {
        return $this->paymentMethod->getData('secret_key');
    }

    public function getApiContext()
    {
        if(!isset($this->_apiContext)){
            $this->_apiContext = new ApiContext(
                new OAuthTokenCredential(
                    $this->getClientId(),
                    $this->getSecretKey()
                )
            );

            if($this->getEnvironment() == 'production'){
                $this->_apiContext->setConfig([
                    'mode' => 'live',
                    'log.LogEnabled' => true,
                    'log.FileName' => storage_path('logs/PayPal.log'),
                    'log.LogLevel' => 'INFO', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
                    'cache.enabled' => true,
                ]);
            }else{
                $this->_apiContext->setConfig([
                    'mode' => 'sandbox',
                    'log.LogEnabled' => true,
                    'log.FileName' => storage_path('logs/PayPal.log'),
                    'log.LogLevel' => 'DEBUG', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
                    'cache.enabled' => true,
                ]);
            }
        }

        return $this->_apiContext;
    }

    //Statics
    public static function additionalSettingValidation(Request $request)
    {
        return [
            'data.email' => 'required|email',
            'data.secret_key' => 'required',
            'data.client_id' => 'required'
        ];
    }
}