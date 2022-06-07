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
use ssplugin\ssmembership\models\Membership;
use ssplugin\ssmembership\records\Membership as Membershiprecord;

class MembershipPlan extends Component
{
    public function getMembershipPlan( $query = null )
    {
        $row = $this->_createQuery()
            ->where( $query )
            ->one();
        if ( !$row ) {
            return null;
        }
        return new Membership( $row );
    }

    public function getAllMembershipPlan( $query = null)
    {        
        $rows = $this->_createQuery()
            ->andwhere( $query )
            ->all();
        if( !empty( $rows ) ) {
            return $rows;
        }
        return null;
    }    

    public function getActiveMembershipPlan()
    {        
        $rows = $this->_createQuery()
            ->andwhere( ['enabled' => 1 ] )
            ->all();
        if( !empty( $rows ) ) {
            return $rows;
        }
        return null;
    }
    
    public function getMembershipPlanById( int $id )
    {
        $row = $this->_createQuery()
            ->andwhere( [ 'id' => $id ] )
            ->one();
        if ( !$row ) {
            return null;
        }        
        return new Membership( $row );
    }

    public function getMembershipPlanByUid( $uid )
    {
        $row = $this->_createQuery()
            ->andwhere( [ 'uid' => $uid ] )
            ->one();
        if ( !$row ) {
            return null;
        }        
        return new Membership( $row );
    }

    public function getMembershipPlanByHandle( $handle )
    {
        $row = $this->_createQuery()
            ->andwhere( [ 'handle' => $handle, 'enabled' => 1 ] )
            ->one();
        if ( !$row ) {
            return null;
        }
        return new Membership( $row );
    }

    private function _createQuery(): Query
    {
        $gateway = SsMembership::getInstance()->paymentGateway->getPaymentGateway();
        $isLive = 0;
        if( $gateway ){
            $isLive = $gateway->liveMode;
        }
        $query = (new Query)
            ->select([
                'id',
                'name',
                'prodId',
                'priceId',
                'handle',
                'amount',
                'currency',
                'interval',
                'enabled',
                'userGroupId',
                'gatewayId',
                'dateCreated',
                'dateUpdated',
                'uid',
                'isLive'
            ])
            ->from( '{{%ssmembership_plan}}' )
            ->where(['isLive' => $isLive])
            ->orderBy( 'dateCreated ASC' );
        return $query;
    }

    public function saveMembershipPlan( Membership $membership ): bool
    {
        $isExist = !$membership->id;
        if ( !$isExist ) {
            $record = Membershiprecord::findOne( $membership->id );
        } else {
            $record = new Membershiprecord;
        }
        $record->name        = $membership->name;
        $record->prodId      = $membership->prodId;
        $record->priceId     = $membership->priceId;
        $record->handle      = $membership->handle;
        $record->amount      = $membership->amount;
        $record->currency    = $membership->currency;
        $record->interval    = $membership->interval;
        $record->enabled     = $membership->enabled;
        $record->isLive      = $membership->isLive;        
        $record->userGroupId = $membership->userGroupId;
        $record->gatewayId   = $membership->gatewayId;
        if ( !$record->save() ) {
            return false;
        }
        $membership->id = $record->id;
        return true;
    }

    public function checkPlanExist( $record, $action )
    {
        if( $record ) {            
            $gateway = SsMembership::getInstance()->paymentGateway->getPaymentGateway();

            if( $action == 'edit' ){                
                $plan = $this->_createQuery()
                    ->where( [ 'isLive'=> $record->isLive, 'handle' => $record->handle ] )
                    ->andwhere( [ '!=', 'id', $record->id ] )
                    ->one();
            } else {
                $plan = $this->getMembershipPlan( [ 'handle' => $record->handle, 'isLive' => $gateway->liveMode ] );
            }
            
            if( !empty( $plan ) ){
                return false;
            }
            
            
            return true;
        } else {
            return false;
        }
    }
}
