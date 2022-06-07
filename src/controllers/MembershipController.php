<?php
/**
 * SsMembership plugin for Craft CMS 3.x
 *
 * Craft CMS membership with stripe.
 *
 * @link      http://www.systemseeders.com/
 * @copyright Copyright (c) 2021 ssplugin
 */

namespace ssplugin\ssmembership\controllers;

use ssplugin\ssmembership\SsMembership;

use Craft;
use craft\web\Controller;
use craft\db\Query;

use ssplugin\ssmembership\models\Membership;
use ssplugin\ssmembership\models\SsPaymentGateway;
// use ssplugin\ssmembership\services\Paymentgateway;
use ssplugin\ssmembership\services\Membershipplan as membershipService;
use Stripe;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Products;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\PermissionException;
use Stripe\Invoice;
use Stripe\PaymentIntent;

class MembershipController extends BaseController
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected array|int|bool $allowAnonymous = [ 'add', 'edit', 'update-or-create', 'delete', 'payment', 'validate-keys'];

    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        $membership =  SsMembership::getInstance()->membershipPlan->getAllMembershipPlan();
        return $this->renderTemplate('ss-membership/membership', ['membership'=>$membership]);
    }
    
    public function actionAdd()
    {        
    	//check stripe details
    	$existPaymentGateway = SsMembership::getInstance()->paymentGateway->getPaymentGateway();
    	
    	if(!empty($existPaymentGateway->id) && !empty( $existPaymentGateway->testSecretKey ) ) {
    		$membership = new Membership();
        	return $this->renderTemplate( 'ss-membership/membership/add_plan', [ 'membership'=> $membership ] );
    	}
    	Craft::$app->getSession()->setError( 'Stripe public and secret keys not added yet.' );
    	return $this->redirect('ss-membership/config');
    }
    
    public function actionEdit( $id )
    {        
        $membership = SsMembership::getInstance()->membershipPlan->getMembershipPlanById( $id );
        if( empty( $membership ) ) {
            return $this->redirect( 'ss-membership/membership' );
        }
        return $this->renderTemplate( 'ss-membership/membership/edit_plan', [ 'membership' => $membership ] );
    }

    public function actionUpdateOrCreate()
    {       
        $this->requirePostRequest();
        $this->requireAdmin();

        $request = Craft::$app->getRequest();        
        $handle = $this->camelCase( $request->getBodyParam( 'name' ) );
        $gateway = SsMembership::getInstance()->paymentGateway->getPaymentGateway();

        if( $gateway ) {
        	if( $request->getBodyParam( 'id' ) ) {
                $record = SsMembership::getInstance()->membershipPlan->getMembershipPlanById( $request->getBodyParam( 'id' ) );
                if( !$record ){
                    return $this->redirect( 'ss-membership/membership' );
                }
        		$action = 'edit';
                $planHandle = $record->handle;
        	} else {
        		$action = 'add';
        		$record = new Membership;
                $record->amount = $request->getBodyParam( 'amount' );
                $record->interval = $request->getBodyParam( 'interval' );
        	}

	        $record->name = $request->getBodyParam( 'name' );
	        $record->gatewayId = $gateway->id;
	        $record->handle = $handle;
	        $record->currency = SsMembership::$CURRENCY;
	        $record->userGroupId = $request->getBodyParam( 'userGroupId' );

	        if( $request->getBodyParam( 'enabled' ) ) {
	        	$record->enabled = $request->getBodyParam( 'enabled' );
	        } else {
	        	$record->enabled = 0;
	        }

            $record->isLive = $this->isLive();

	        if( $record->validate() ) {

                $planExist = SsMembership::getInstance()->membershipPlan->checkPlanExist( $record, $action );
                if(!$planExist){
                    Craft::$app->getSession()->setError( 'Subscription plan name "'.$record->name.'" already exist.' );
                    if( $action == 'edit' ){                        
                        return $this->redirect( 'ss-membership/membership/'.$record->id );
                    }else{
                        return $this->redirect( 'ss-membership/membership/add' );
                    }
                    
                }
                // echo "<pre>";
                // print_r($isPlanExist);
                // exit();

                //create or update product
                if( empty( $record->priceId ) ) {
                    $price = $this->createProduct( $record );
                    if( !$price ) {
                        return $this->redirect( 'ss-membership/membership' );
                    }
                    $record->prodId = $price[ 'product' ];
                    $record->priceId = $price[ 'id' ];
                } else {
                    if( $handle != $planHandle ) {
                        $plan = $this->updateProduct( $record );
                        if( !$plan ) {
                            Craft::$app->getSession()->setError( 'This plan is not found in your stripe account.' );
                            return $this->renderTemplate( 'ss-membership/membership/'.$action.'_plan', [ 'membership' => $record ] );
                        }
                    }
                }

	       		$isSave = SsMembership::getInstance()->membershipPlan->saveMembershipPlan( $record );
	       		if( $isSave ) {
                    Craft::$app->getSession()->setNotice( 'Membership plan saved' );
                } else {
                    Craft::$app->getSession()->setNotice( 'Failed to save plan' );
                }
                return $this->redirect( 'ss-membership/membership' );
	       	}
	        return $this->renderTemplate( 'ss-membership/membership/'.$action.'_plan', [ 'membership' => $record ] );
        }
    }

    public function actionPayment()
    {
        $this->requireAdmin();
        $request = Craft::$app->getRequest()->getBodyParams();
        
        $existPaymentGateway = SsMembership::getInstance()->paymentGateway->getPaymentGateway();
        
        if ( Craft::$app->getRequest()->isPost ) {
            if( $request[ 'id' ] ) {
                $paygateway = SsMembership::getInstance()->paymentGateway->getPaymentGatewayById( $request[ 'id' ] );
            } else {
                $paygateway = new SsPaymentGateway();
            }
            
            $paygateway->livePublicKey = $request[ 'livePublicKey' ];
            $paygateway->liveSecretKey = $request[ 'liveSecretKey' ];
            $paygateway->testPublicKey = $request[ 'testPublicKey' ];
            $paygateway->testSecretKey = $request[ 'testSecretKey' ];

            if( $request[ 'liveMode' ] ) {
                $paygateway->liveMode = $request[ 'liveMode' ];
            } else {
                $paygateway->liveMode = 0;
            }
           
            if ( $paygateway->validate() ) {
                $testSecretKey = $this->validateKey( $request[ 'testSecretKey' ] );
                $liveSecretKey = $this->validateKey( $request[ 'liveSecretKey' ] );
                
                if( $testSecretKey && $liveSecretKey ) {

                    $isSave = SsMembership::getInstance()->paymentGateway->savePaymentGateway( $paygateway );
                    if( $isSave ) {
                        Craft::$app->getSession()->setNotice( 'Stripe configuration are saved' );
                    } else {
                        Craft::$app->getSession()->setNotice( 'Failed to save details' );
                    }
                }
            }
            return $this->renderTemplate( 'ss-membership/membership/payment', [ 'paygateway' => $paygateway ] );
        }
        return $this->renderTemplate( 'ss-membership/membership/payment', [ 'paygateway' => $existPaymentGateway ] );
    }

    protected function validateKey( $key )
    {
    	try {
            $stripe = Stripe\Stripe::setApiKey( $key );
            $charges = Stripe\Charge::all( [ 'limit' => 1 ] );
        } catch( AuthenticationException $e ){
            Craft::$app->getSession()->setError( $e->getMessage() );
            return false;
        } catch( PermissionException $p ){
            Craft::$app->getSession()->setError( $p->getMessage() );
            return false;
        }
        return true;
    }

    protected function createProduct( $record )
    {
        if( empty( $record ) ) {
            Craft::$app->getSession()->setError( 'Data not found while creating membership plan.' );
            return false;
        }
        try {
            $stripe = Stripe\Stripe::setApiKey( SsMembership::$STRIPE_SECRET );
            
            $product = \Stripe\Product::create([
                'name' => strtoupper( $record[ 'handle' ] ),
            ]);

            $price = '';
            if( $product ){
                $price = round( $record[ 'amount' ] * 100 );
                $price = \Stripe\Price::create([
                    'product' => $product->id,
                    'unit_amount' => $price,
                    'currency' => 'usd',
                    'recurring' => [
                        'interval' => $record[ 'interval' ],
                    ],
                    "metadata" => [ "plugin" => "ssmembership" ]
                ]); 
            }
            return $price;
        } catch( InvalidRequestException $e ) {
            $this->createLog( $e->getMessage() );
            Craft::$app->getSession()->setError( $e->getMessage() );
            return false;
        }
    }

    protected function updateProduct( $record )
    {
        if( empty( $record ) ) {
            Craft::$app->getSession()->setError( 'Data not found while creating membership plan.' );
            return false;
        }
        try {
            $stripe = Stripe\Stripe::setApiKey( SsMembership::$STRIPE_SECRET );
            $product = \Stripe\Product::update( $record[ 'prodId' ], ['name' => strtoupper( $record[ 'handle' ] ) ]);
            return $product;
        }catch( InvalidRequestException $e ) {
            $this->createLog( $e->getMessage() );
            return false;
        }
    }
}