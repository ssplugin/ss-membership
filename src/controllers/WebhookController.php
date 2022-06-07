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
use Stripe\Invoice;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\UnexpectedValueException;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use craft\helpers\UrlHelper;

class WebhookController extends BaseController
{

    // Protected Properties
    // =========================================================================
    public $enableCsrfValidation = false;
    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected array|int|bool $allowAnonymous = ['index', 'sub-update', 'sub-cancel'];
    

    /**
     *
     * @return mixed
     */
    public function actionIndex(  )
    {
        try {
            
            \Stripe\Stripe::setApiKey( SsMembership::$STRIPE_SECRET );
           
            $payload = @file_get_contents('php://input');
            $event = null;

            try {
                $event = \Stripe\Event::constructFrom(
                    json_decode($payload, true)
                );
            } catch( UnexpectedValueException $e) {            
                http_response_code(400);
                exit();
            }            
            // Handle the event
            switch ($event->type) {
                case 'customer.subscription.updated':
                    $subscriptionUpdate = $this->actionSubUpdate( $event );
                    break;
                case 'customer.subscription.deleted':
                    $subscriptionCancel = $this->actionSubCancel( $event );
                    break;
                default:                    
                    break;
            }

            http_response_code(200);

        } catch ( Exception $e ) {            
        }
    }    

    public function actionSubUpdate( $event ){
        
        if( empty( $event ) ){
            return false;
        }
        if( $event->type != 'customer.subscription.updated'){
            return false;
        }
        $subObj = $event->data->object;

        $subscription = SsMembership::getInstance()->subscription->getSubscriptionByStripeId( $subObj->id );

        if( !$subscription ) {
            return false;
        }

        //switch membership plan
        $membershipPlan = $this->getInstance()->membershipPlan->getMembershipPlan( [ 'priceId' => $subObj->plan->id ] );
        
        if( !$membershipPlan ) {
            $this->Log( 'webhook event.', $subscription->id, 'webhook' );
            return false;
        }

        // subscription successfully renew or switched.
        if( $subObj->status == 'active' ) {

            // Subscription cancel at period end
            if( $subObj->cancel_at_period_end ){
                if( $subscription->cancelType != 'period_end' ){
                    $subscription->canceledAt = date( 'Y-m-d H:i:s', $subObj->canceled_at );
                    $subscription->cancelAt   = date( 'Y-m-d H:i:s', $subObj->cancel_at );
                    $subscription->cancelType = 'period_end';

                    $this->Log( 'subscription will canceled at the end of period.', $subscription->id, 'webhook' );
                }
            }else{
                $subscription->name = $subObj->plan->name;
                $subscription->membershipPlanId = $membershipPlan[ 'id' ];
                $subscription->stripePriceId = $subObj->plan->id;
                $subscription->amount   = ( $subObj->plan->amount /100 );
                $subscription->interval = $subObj->plan->interval;
                $subscription->interval_count = $subObj->plan->interval_count;
                $subscription->subStartDate = date( 'Y-m-d H:i:s', $subObj->current_period_start );
                $subscription->subEndDate   = date( 'Y-m-d H:i:s', $subObj->current_period_end );
                $subscription->status = $subObj->status;
            }
            

            $saveData = $this->getInstance()->subscription->saveSubscription( $subscription );
            if( $saveData ){
               
                //payment invoice ( subscriptionId )
                if( $subObj->latest_invoice ){
                    $invoice = $this->getInstance()->subscription->createInvoice( $subObj->latest_invoice, $subscription->id );                     
                }
            }
        }

        // Subscription becomes past_due when payment to renew it fails
        if( $subObj->status == 'past_due' ) {
            $subscription->status = $subObj->status;
            $saveData = $this->getInstance()->subscription->saveSubscription( $subscription );
            
            //Remove user group.
            $oldGroup = Craft::$app->getUserGroups()->getGroupById( $membershipPlan[ 'userGroupId' ] );
            $user = $subscription->getUser();
            $userGroups = $user->getGroups();
            $this->assignUserGroup( $user, $userGroups, $oldGroup );
        }

        // subscription moves into incomplete if the initial payment attempt fails.
        if( $subObj->status == 'incomplete' ) {
            $subscription->status = $subObj->status;
            $saveData = $this->getInstance()->subscription->saveSubscription( $subscription );

            //Remove user group.
            $oldGroup = Craft::$app->getUserGroups()->getGroupById( $membershipPlan[ 'userGroupId' ] );
            $user = $subscription->getUser();
            $userGroups = $user->getGroups();
            $this->assignUserGroup( $user, $userGroups, $oldGroup );
        }
    }

    public function actionSubCancel( $event ){
        // whenever a customer's subscription ends.        
        if( empty( $event ) ){
            return false;
        }
        if( $event->type != 'customer.subscription.deleted'){
            return false;
        }

        $subObj = $event->data->object;
        
        if( $subObj->status != 'canceled') {
            return false;
        }
        
        $subscription = SsMembership::getInstance()->subscription->getSubscriptionByStripeId( $subObj->id );        
        if( !$subscription ) {
            return false;
        }
        $subscription->status = $subObj->status;
        $subscription->canceledAt = date( 'Y-m-d H:i:s', $subObj[ 'canceled_at' ] );
        $subscription->cancelAt   = date( 'Y-m-d H:i:s' );
        $subscription->cancelType = 'immediately';

        $saveData = $this->getInstance()->subscription->saveSubscription( $subscription );

        $this->Log( 'Subscription has canceled immediately.', $subscription->id, 'webhook' );

        $membershipPlan = $this->getInstance()->membershipPlan->getMembershipPlan( [ 'priceId' => $subObj->plan->id ] );

        //Remove user group.
        $oldGroup = Craft::$app->getUserGroups()->getGroupById( $membershipPlan[ 'userGroupId' ] );
        $user = $subscription->getUser();
        $userGroups = $user->getGroups();
        $this->assignUserGroup( $user, $userGroups, $oldGroup );
    }

    protected function assignUserGroup( $user, $userGroups, $oldGroup )
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
}
