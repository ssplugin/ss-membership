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
use ssplugin\ssmembership\models\Log as LogModel;
use ssplugin\ssmembership\records\Log as LogRecord;
use yii\web\NotFoundHttpException;
use Stripe;

class Log extends Component
{
	public function getLog( $query )
    {
        $rows = $this->_createQuery()
            ->where( $query )
            ->one();
        return new LogModel( $rows );
    }

    public function getLogBySubId( $id )
    {
        $rows = $this->_createQuery()
            ->where(['referenceId' => $id])
            ->orderBy('dateCreated DESC')
            ->all();

        if( !empty( $rows ) ) {            
            $newrow = array_map( function ( $row ) {
                return new LogModel( $row );
            }, $rows );
            return $newrow;
        }
        return false;
    }

    public function getAllLog( $where = null )
    {
        $rows = $this->_createQuery()
            ->where( $where )
            ->all();

        if( !empty( $rows ) ) {            
            $newrow = array_map( function ( $row ) {
                return new LogModel( $row );
            }, $rows );
            return $newrow;
        }
        return false;
    }

    public function saveLog( $message, $id, $refBy = null )
    {
        if( !empty( $message ) && !empty( $id ) ) {

            $record = new LogRecord;
            $record->referenceId = $id;
            $record->logMessage  = $message;
            $record->referenceBy = $refBy;
            $record->save();
            return true;
        }
        return false;
    }

	private function _createQuery(): Query
    {
        return (new Query)
            ->select([
                'id',
                'referenceId',
                'logMessage',
                'referenceBy',
                'dateCreated',
                'dateUpdated',
                'uid'
            ])
            ->from( '{{%ssmembership_log}}' )
            ->orderBy( 'dateCreated ASC' );
    }
}