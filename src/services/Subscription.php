<?php
/**
 * SsMembership plugin for Craft CMS 3.x
 *
 * Craft CMS membership with stripe.
 *
 * @link      http://www.systemseeders.com/
 * @copyright Copyright (c) 2021 ssplugin
 */

namespace ssplugin\ssmembership\services;

use ssplugin\ssmembership\SsMembership;

use Craft;
use craft\base\Component;
use craft\db\Query;
use ssplugin\ssmembership\models\Subscription as SubscriptionModel;
use ssplugin\ssmembership\records\Subscription as SubscriptionRecord;
use yii\web\NotFoundHttpException;
use Stripe;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Products;
use Stripe\PaymentMethod;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use craft\helpers\DateTimeHelper;

class Subscription extends Component
{
	public function getSubscription( $query )
    {        
        // $query = ['userId' => '1', 'sdsd' => '1', ];
        $row = $this->_createQuery()
            ->where($query)
            ->one();

        if ( !$row ) {
            return null;
        }
        return new SubscriptionModel( $row );
    }

    public function getSubscriptionByUserId( $id )
    {
        $row = $this->_createQuery()
            ->andWhere(['userId' => $id])
            ->one();

        if (!$row) {
            return null;
        }
        
        return new SubscriptionModel($row);
    }

    public function getSubscriptionById( $id )
    {
        $row = $this->_createQuery()
            ->where( [ 'id' => $id ] )
            ->one();

        if (!$row) {
            return null;
        }
        
        return new SubscriptionModel( $row );
    }

    public function getSubscriptionByStripeId( $id )
    {
        $row = $this->_createQuery()
            ->where( [ 'stripeSubId' => $id ] )
            ->one();

        if ( !$row ) {
            return null;
        }
        
        return new SubscriptionModel( $row );
    }

    public function getAllSubscription( $where = null,  $whereNot = null )
    {
        $rows = $this->_createQuery()
            ->andWhere( $where )
            ->andWhere( $whereNot )
            ->all();
            
        if( !empty( $rows ) ) {
            
            $newrow = array_map( function ( $row ) {
                return new SubscriptionModel( $row );
            }, $rows );
            return $newrow;
        }
        return false;
    }

    public function getUserActiveSub( $userId )
    {
        $rows = $this->_createQuery()
            ->andWhere( [ 'userid' => $userId ] )
            ->andWhere( [ 'status' => 'active' ] )
            ->all();

        if( !empty( $rows ) ) {
            $newrow = array_map( function ( $row ) {
                return new SubscriptionModel( $row );
            }, $rows );
            return $newrow;
        }
        return false;
    }

    public function saveSubscription( SubscriptionModel $subscription ): bool
    {
       
        $isExist = !$subscription->id;
       
        if ( !$isExist ) {
            $record = SubscriptionRecord::findOne( $subscription->id );
        } else {
            $record = new SubscriptionRecord;
        }

        $record->name     = $subscription->name;
        $record->amount   = $subscription->amount;
        $record->currency = 'usd';
        $record->interval = $subscription->interval;        
        $record->status   = $subscription->status;
        $record->userId   = $subscription->userId;        
        $record->stripeSubId   = $subscription->stripeSubId;
        $record->stripePriceId = $subscription->stripePriceId;
        $record->payer_email   = $subscription->payer_email;
        $record->subStartDate  = $subscription->subStartDate;
        $record->subEndDate    = $subscription->subEndDate;
        $record->interval_count   = 1;
        $record->canceledAt = $subscription->canceledAt;
        $record->cancelAt = $subscription->cancelAt;
        $record->cancelType = $subscription->cancelType;
        $record->membershipPlanId = $subscription->membershipPlanId;    
        $record->stripeCustomerId = $subscription->stripeCustomerId;
        
        if ( !$record->save() ) {
            return false;
        }
        $subscription->id = $record->id;
        
        return true;
    }

	private function _createQuery(): Query
    {
        $gateway = SsMembership::getInstance()->paymentGateway->getPaymentGateway();
        return (new Query)
            ->select([
                'id',
                'name',
                'userId',
                'membershipPlanId',
                'stripeSubId',
                'stripeCustomerId',
                'stripePriceId',
                'interval',
                'interval_count',
                'payer_email',
                'currency',
                'amount',
                'status',
                'subStartDate',
                'subEndDate',
                'canceledAt',
                'cancelAt',
                'cancelType',
                'dateCreated',
                'dateUpdated',
                'uid'
            ])
            ->from('{{%ssmembership_subscription}}')
            ->where(['isLive' => $gateway->liveMode]) 
            ->orderBy('dateCreated DESC');
    }

    public function initSubscription( $request, $user )
    {       
        if( !$user ){
            throw new NotFoundHttpException('User does not found.');
        }
        
        if( !empty( $request ) ) {
            
            if( empty( $request[ 'ssplan_id' ] ) || empty( $request[ 'stripeToken' ] ) ) {
                throw new NotFoundHttpException( 'Plan id is not passed with stripe form.');
            }
            
            $membershipPlan = SsMembership::getInstance()->membershipPlan->getMembershipPlanById( $request[ 'ssplan_id' ] );
           
            if( !$membershipPlan ){
                throw new NotFoundHttpException( 'Membership plan is not found.' );
            }

            $isSub = $this->getSubscription([
                'userId' => $user->id,
                'membershipPlanId' => $membershipPlan->id,
                'status' => 'active'
            ]);

            if( !empty( $isSub->id ) ){
                throw new NotFoundHttpException( 'You have already subscribed with selected plan.' );
            }

            $group = Craft::$app->getUserGroups()->getGroupByid( $membershipPlan[ 'userGroupId' ] );

            if ( !$group ) {
                throw new NotFoundHttpException( 'User group is not attached with the "'.$membershipPlan[ 'name' ].'" plan. please contact to your administrator.' );
            }
            
            if( !$membershipPlan[ 'enabled' ] ){
                throw new NotFoundHttpException( 'Membership plan status is not enable.' );
            }

            $stripe = Stripe\Stripe::setApiKey( SsMembership::$STRIPE_SECRET );

            $usersub = $this->getSubscriptionByUserId( $user->id );

            // Create a new customer..
            if( $usersub ){
                $customer = $this->retriveCustomer( $usersub );
                if( empty( $customer ) ) {
                    $customer = $this->createCustomer( $request, $user );
                }
            } else {
                $customer = $this->createCustomer( $request, $user );
            }

            if( empty( $customer ) ) {
                throw new \Exception( 'Error while creating customer, please try later.' );
            }
            
            // Creates a new subscription 
            $subscription = $this->createSubscription( $customer, $membershipPlan[ 'priceId' ] );

            if( empty( $subscription ) ) {
                throw new \Exception( 'Error while creating subscription, please try later.' );
            }

            if( is_string( $subscription ) ) {
                Craft::$app->getSession()->setError( $subscription );
                return $this->redirect(Craft::$app->getRequest()->referrer);
            }

            //Convert stripe object to array
            $data = $subscription->jsonSerialize();
            
            //Check subscription
            $subdata = $this->getSubscriptionByStripeId( $data[ 'id' ] );
            if( empty( $subdata ) ){
                $subdata = new SubscriptionModel;
            }
            $subdata->name    = $membershipPlan[ 'name' ];
            $subdata->userId  = $user->id;
            $subdata->membershipPlanId = $membershipPlan[ 'id' ];
            $subdata->stripeSubId      = $data[ 'id' ];
            $subdata->stripeCustomerId = $data[ 'customer' ];
            $subdata->stripePriceId    = $data[ 'plan' ][ 'id' ];
            $subdata->amount   = ( $data[ 'plan' ][ 'amount' ] /100 );
            $subdata->currency = $data[ 'plan' ][ 'currency' ];
            $subdata->interval = $data[ 'plan' ][ 'interval' ];
            $subdata->interval_count = $data[ 'plan' ][ 'interval_count' ];
            $subdata->payer_email    = isset( $customer->email ) ? $customer->email : $user->email;
            date_default_timezone_set("UTC");
            $subdata->subStartDate = date( 'Y-m-d H:i:s', $data[ 'current_period_start' ] );
            $subdata->subEndDate   = date( 'Y-m-d H:i:s', $data[ 'current_period_end' ] );
            $subdata->status = $data[ 'status' ];

            if( $subdata->validate() ) {
                $saveData = $this->saveSubscription( $subdata );
                if( $saveData ) {   

                    //Payment invoice
                    if( $subscription->latest_invoice ){
                        $invoice = $this->createInvoice( $subscription->latest_invoice, $subdata->id );
                    }                    

                    //Assign user group according plan group
                    Craft::$app->getUsers()->assignUserToGroups( $user->id, [ $membershipPlan[ 'userGroupId' ]] );

                    SsMembership::getInstance()->log->saveLog( 'Subscription created with plan - '.$membershipPlan[ 'name' ].' with user group - '.$group[ 'name' ], $subdata->id );
                     Craft::$app->getSession()->setNotice( 'Subscription created successfully.' );

                } else {
                    throw new \Exception( 'Error while creating subscription, please try later.' );
                }
            }
        }
    }

    public function createSubscription( $customer, $priceId )
    {
        $subscription = '';
        try { 
            $subscription = Stripe\Subscription::create( array(
                "customer" => $customer->id,
                "items" => array(
                    array(
                        "price" => $priceId,
                    ),
                ),
            )); 
        }catch( InvalidRequestException $e ) {
            $error = $e->getMessage();
            return $error;
        }
        return $subscription;
    }

    // Create a customer..
    protected function createCustomer( $request, $user )
    {
        $customer = '';
        try {
            $email = isset( $request[ 'email' ] ) ? $request[ 'email' ] : $user->email;
            $name  = isset( $request[ 'name' ] )  ? $request[ 'name' ]  : $user->username;

            $customer = Customer::create(
                array(
                    'email' => $email,
                    'name'  => $name,
                    'payment_method' => $request[ 'stripeToken' ],
                    'invoice_settings' => ['default_payment_method' => $request[ 'stripeToken' ]]
                )
            );
            
            // $paymentMethod  = PaymentMethod::retrieve( $request[ 'stripeToken' ] );
            // $stripeResponse = PaymentMethod::attach( $request[ 'stripeToken' ], [ 'customer' => $customer->id ] );

        } catch( Stripe\Exception\ApiErrorException $e ) {
            $logMsg = $e->getMessage();
            throw new \Exception( $logMsg );
        }
        return $customer;
    }

    protected function retriveCustomer( $data )
    {
        $customer = '';
        try {
            $customer = Customer::retrieve( $data->stripeCustomerId );
        } catch( Stripe\Exception\ApiErrorException $e ) {
            throw new \Exception( $e->getMessage() );
        }
        return $customer;
    }

    public function createInvoice( $invoiceId, $subId )
    {
        try {
            $stripe = Stripe\Stripe::setApiKey( SsMembership::$STRIPE_SECRET );
            $invoice = Invoice::retrieve( $invoiceId, [] );

            if( $invoice ) {
                $invoice = SsMembership::getInstance()->payment->savePayment( $invoice, $subId );
            }
        } catch( Stripe\Exception\ApiErrorException $e ) {
            throw new \Exception( $e->getMessage() );
        }
        return $invoice;
    }
}