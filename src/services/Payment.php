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
use ssplugin\ssmembership\models\Payment as PaymentModel;
use ssplugin\ssmembership\records\Payment as PaymentRecord;
use yii\web\NotFoundHttpException;
use Stripe;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Products;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Invoice;
use Stripe\PaymentIntent;

class Payment extends Component
{
	public function getPayment( $query )
    {
        $rows = $this->_createQuery()
            ->where($query)
            ->one();
        return new PaymentModel( $rows );
    }

    public function getPaymentBySubId( $id )
    {
        $rows = $this->_createQuery()
            ->where(['subscriptionId' => $id])
            ->orderBy('payDate DESC')
            ->all();
        if( !empty( $rows ) ) {            
            $newrow = array_map( function ( $row ) {
                return new PaymentModel( $row );
            }, $rows );
            return $newrow;
        }
        return false;
    }

    public function getPaymentById( $id )
    {
        $row = $this->_createQuery()
            ->where( [ 'paymentId' => $id ] )
            ->one();

        if (!$row) {
            return null;
        }
        
        return new PaymentModel( $row );
    }

    public function getAllPayment( $where = null, $whereNot = null )
    {
        $rows = $this->_createQuery()
            ->where( $where )
            ->andWhere( $whereNot )
            ->all();
        if( !empty( $rows ) ) {            
            $newrow = array_map( function ( $row ) {
                return new PaymentModel( $row );
            }, $rows );
            return $newrow;
        }
        return false;
    }

    public function savePayment( $invoice, $subId )
    {        
        if( !empty( $invoice ) && !empty( $subId ) ){            
            $isInvoiceExist = $this->getPaymentById( $invoice->id );
            if( !$isInvoiceExist ){
                $record = new PaymentRecord;
                $record->amount    = $invoice->amount_due;
                $record->paymentId = $invoice->id;
                $record->subscriptionId = $subId;
                date_default_timezone_set("UTC");
                $record->payDate = date( 'Y-m-d H:i:s', $invoice->created );
                $record->paymentData = json_encode( $invoice );
                $record->status = $invoice->status;                
                $record->save();
            }
        }
        return true;
    }

    public function test( )
    {
        $record = new PaymentRecord;
        $record->amount    = 100;
        $record->paymentId = 'sdfsdf';        
        $record->subscriptionId = 2;
        $record->payDate = date( 'Y-m-d H:i:s');
        $record->paymentData   = 'asdsad';
        
        $record->save();
        return true;
    }

	private function _createQuery(): Query
    {
        return (new Query)
            ->select([
                'id',
                'paymentId',
                'subscriptionId',
                'paymentData',
                'status',
                'amount',
                'payDate',
                'dateCreated',
                'dateUpdated',
                'uid'
            ])
            ->from('{{%ssmembership_payments}}')            
            ->orderBy('dateCreated ASC');
    }
}