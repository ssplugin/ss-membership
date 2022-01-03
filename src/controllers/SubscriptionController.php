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

use craft\helpers\ArrayHelper;
use ssplugin\ssmembership\models\Membership;
use ssplugin\ssmembership\models\Subscription;
use ssplugin\ssmembership\models\Log;
use ssplugin\ssmembership\services\Paymentgateway;
use ssplugin\ssmembership\services\Membershipplan as membershipService;

use Stripe;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Products;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\ApiErrorException;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

class SubscriptionController extends BaseController
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = [];
    
    /**
     *
     * @return mixed
     */
    public function actionIndex( $id )
    {
        $this->requireLogin();
        $subscriptions = $this->getInstance()->subscription->getAllSubscription( [ 'membershipPlanId' => $id ] );
        return $this->renderTemplate( 'ss-membership/subscription', [ 'subscriptions' => $subscriptions ] );
    }

    /**
     * Handle a request going to our plugin's edit action URL,
     * e.g.: actions/ss-membership/subscription/{id}
     *
     * @return mixed
     */
    public function actionEdit( $id )
    {       
        $this->requireLogin();
        $currSub = $this->getInstance()->subscription->getSubscriptionById( $id );
        
        if( !$currSub ){
            return $this->redirect( 'ss-membership/subscription' );
        }

        $subscriptions = $this->getInstance()->subscription->getAllSubscription( [ 'userId' => $currSub->userId ], ['!=', 'id', $id] );        
        
        return $this->renderTemplate('ss-membership/subscription/edit', ['subscription' => $currSub] );
    }

    public function actionCancel( )
    {        
        $this->requireLogin();
        $this->requirePostRequest();
        
        $request = Craft::$app->getRequest();
        
        if( $request->getBodyParam( 'subUid' ) ) {
            $subUid = $request->getValidatedBodyParam( 'subUid' );
        }
        if( !$subUid ) {
            Craft::$app->getSession()->setError( 'Body param "subUid" value not hashed or does not found.' );
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }
       
        if( !empty( $subUid ) ){
            $currSub = $this->getInstance()->subscription->getSubscription( [ 'uid' => $subUid ]);
            if( !$currSub ) {
                Craft::$app->getSession()->setError( 'Subscription does not found.' );
                return $this->redirect(Craft::$app->getRequest()->referrer);
            }
            $user = Craft::$app->getUser()->getIdentity();
            $actionBy = 'admin';
            if($user->id == $currSub->userId ){
                $actionBy = 'user';
            }
            
            $stripe = Stripe\Stripe::setApiKey( SsMembership::$STRIPE_SECRET );
            
            if( $request->getBodyParam('cancelType') == 'period_end' ) {

                $subscription = '';
                try {
                    $subscription = Stripe\Subscription::update( $currSub->stripeSubId, [ 'cancel_at_period_end' => true ] );
                    date_default_timezone_set("UTC");
                    $currSub->canceledAt = date( 'Y-m-d H:i:s', $subscription[ 'canceled_at' ] );
                    $currSub->cancelAt   = date( 'Y-m-d H:i:s', $subscription[ 'cancel_at' ] );
                    $currSub->cancelType = 'period_end';
                    $saveData = $this->getInstance()->subscription->saveSubscription( $currSub );

                    $this->Log( 'Subscription will cancel automatically at the end of the period.', $currSub[ 'id' ], $actionBy );
                    Craft::$app->getSession()->setNotice( 'Subscription will cancel automatically at the end of the period.', $actionBy );

                } catch( InvalidRequestException $e ) {
                    $this->Log( 'While canceling a subscription - '.$e->getMessage(), $currSub[ 'id' ], $actionBy );
                    Craft::$app->getSession()->setError( 'While Canceling a subscription - '.$e->getMessage() );
                }
            } else {
                
                try {
                    $stripe = $this->stripeClient();

                    $subscription = $stripe->subscriptions->retrieve(
                        $currSub->stripeSubId
                    );
                    
                    if( $subscription->status != 'canceled' ) {
                        $subscription = $stripe->subscriptions->cancel(
                            $currSub->stripeSubId
                        );
                        date_default_timezone_set("UTC");
                        $currSub->status = 'canceled';
                        $currSub->canceledAt = date( 'Y-m-d H:i:s', $subscription[ 'canceled_at' ] );
                        $currSub->cancelAt   = date( 'Y-m-d H:i:s' );
                        $currSub->cancelType = 'immediately';
                        $saveData = $this->getInstance()->subscription->saveSubscription( $currSub );

                        $this->Log( 'Subscription has canceled immediately.', $currSub[ 'id' ], $actionBy );

                        Craft::$app->getSession()->setNotice( 'Subscription has canceled immediately.' );                        
                    } else {
                        Craft::$app->getSession()->setError( 'Subscription already canceled.' );
                    }
                      
                } catch( InvalidRequestException $e ) {
                    $this->Log( 'While canceling a subscription - '.$e->getMessage(), $currSub[ 'id' ], $actionBy );
                    Craft::$app->getSession()->setError( 'While canceling a subscription - '.$e->getMessage() );
                }
            }
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }
    }    

    protected function assignUserToGroup( $user, $userGroups, $oldGroup )
    {        
        if( !empty( $user ) && !empty( $userGroups ) && !empty( $oldGroup ) ) {

            $groupIds  = ArrayHelper::getColumn( $userGroups, 'id' );
            $newGroups = array_diff( $groupIds, [ $oldGroup[ 'id' ] ] );

            //Assign user to related groups
            Craft::$app->getUsers()->assignUserToGroups( $user->id, $newGroups );
            return true;
        }
        return false;
    }

    public function actionSubscribe( )
    {
        $this->requireLogin();
        $this->requirePostRequest();
        
        $request = Craft::$app->getRequest();
        $user = Craft::$app->getUser()->getIdentity();

        $stripeToken = $request->getBodyParam( 'stripeToken' );
        $planUid = $request->getBodyParam( 'planUid' );

        //check subscription exist or not
        $currentUser = Craft::$app->getUser()->getIdentity();
        $subscriptions = SsMembership::getInstance()->subscription->getUserActiveSub( $currentUser->id );
        
        if( $subscriptions ) {
            Craft::$app->getSession()->setError( 'You have already active subscription plan.' );
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }

        if( empty( $planUid ) ) {
            Craft::$app->getSession()->setError( 'Request missing required body param - "planUid".' );
            return $this->redirect(Craft::$app->getRequest()->referrer);

        }

        //if planUid not passed with hash
        try {
            $planUid = $request->getValidatedBodyParam( 'planUid' );
        } catch ( BadRequestHttpException $e ) {
            Craft::$app->getSession()->setError( $e->getMessage().' - "planUid".' );
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }

        if( empty( $stripeToken ) ) {
            Craft::$app->getSession()->setError( 'Request missing required body param - "stripeToken".' );
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }
        
        $membershipPlan = SsMembership::getInstance()->membershipPlan->getMembershipPlanByUid( $planUid );
       
        if( !$membershipPlan ){
            Craft::$app->getSession()->setError( 'Membership plan is not found.' );
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }

        $group = Craft::$app->getUserGroups()->getGroupByid( $membershipPlan[ 'userGroupId' ] );
        
        if ( !$group ) {
            Craft::$app->getSession()->setError( $membershipPlan[ 'name' ].' membership plan with user group not found (groupId - '.$membershipPlan[ 'userGroupId' ].').' );
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }
        
        if( !$membershipPlan[ 'enabled' ] ){
            Craft::$app->getSession()->setError( $membershipPlan[ 'name' ]. ' membership plan Inactivated.' );
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }

        $stripe = Stripe\Stripe::setApiKey( SsMembership::$STRIPE_SECRET );

        // Check customer exist with stripe.
        $customer = $this->isCustomerExist( $user );

        if( !$customer ){
            $customer = $this->createCustomer( $user, $stripeToken );
            if( is_string($customer) ){
                Craft::$app->getSession()->setError( $customer );
                return $this->redirect(Craft::$app->getRequest()->referrer);
            }
        }
        if( empty( $customer ) ) {            
            Craft::$app->getSession()->setError( 'Error while creating customer, please try later.' );
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }
        
        // Creates a new subscription
        $subscription = $this->getInstance()->subscription->createSubscription( $customer, $membershipPlan[ 'priceId' ] );

        if( empty( $subscription ) ) {
            Craft::$app->getSession()->setError( 'Subscription not created with stripe, Something wrong happen.'  );
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }

        if( is_string( $subscription ) ) {
            Craft::$app->getSession()->setError( $subscription );
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }

        //Convert stripe object to array
        $data = $subscription->jsonSerialize();
        
        //create a new subscription        
        $subdata = new Subscription;        
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
            $saveData = $this->getInstance()->subscription->saveSubscription( $subdata );
            if( $saveData ) {                    
                //Payment invoice
                if( $subscription->latest_invoice ){
                    $invoice = $this->getInstance()->subscription->createInvoice( $subscription->latest_invoice, $subdata->id );
                }

                //Assign user group according plan group
                Craft::$app->getUsers()->assignUserToGroups( $user->id, [ $membershipPlan[ 'userGroupId' ]] );

                SsMembership::getInstance()->log->saveLog( 'Subscription created with plan - '.$membershipPlan[ 'name' ].' with user group - '.$group[ 'name' ], $subdata->id );
                
                Craft::$app->getSession()->setNotice( 'Subscription created successfully.' );
            } else {
                Craft::$app->getSession()->setError( 'Error while creating subscription, please try later.' );
            }
        }
        return $this->redirect(Craft::$app->getRequest()->referrer);
    }

    protected function isCustomerExist( $user )
    {
        $customer = '';
        if( $user ){
            try {
                $stripe   = $this->stripeClient();
                $cust = $stripe->customers->all( [ 'limit' => 1,'email' => $user->email ] );
                if( !empty( $cust->data ) ) {
                    $customer = $cust->data[0];
                }
            } catch( Stripe\Exception\ApiErrorException $e ) {
            }
        }
        return $customer;
    }

    protected function createCustomer( $user, $stripeToken )
    {
        $customer = '';
        try {
            if( $user ){           
                $customer = Customer::create(
                    array(
                        'email' => $user->email,
                        'name'  => $user->username,
                        'payment_method' => $stripeToken,
                        'invoice_settings' => [ 'default_payment_method' => $stripeToken ]
                    )
                );
            }
        } catch( Stripe\Exception\ApiErrorException $e ) {
            $error =  $e->getMessage();
            return $error;
        }
        return $customer;
    }

}
