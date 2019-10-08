<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;

use andres3210\laraews\models\ExchangeFolder;
use andres3210\laraews\ExchangeClient;
use Mockery\CountValidator\Exception;

class ExchangeItem extends Model
{

    protected $fillable = [
        'item_id', 'exchange_mailbox_id', 'exchange_folder_id', 'message_id',
        'subject', 'from', 'to', 'cc', 'bcc', 'body', 'attachment', 'created_at'
    ];



    /**
    |
    |--------------------------------------------------------------------------
    | Accessors and Mutators
    |--------------------------------------------------------------------------
    |
     */
    public function getBodyAttribute($value)
    {
        try {
            return gzdecode($value);
        }
        catch(Exception $e){
            return 'invalid';
        }
    }

    public function getItemIdAttribute($value)
    {
        return base64_encode($value);
    }


    public function setBodyAttribute($value)
    {
        $this->attributes['body'] = gzencode($value);
    }

    public function setItemIdAttribute($value)
    {
        $this->attributes['item_id'] = base64_decode($value);
    }


    /**
    |
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    |
     */
    public function mailbox()
    {
        return $this->belongsTo('andres3210\laraews\models\ExchangeMailbox', 'exchange_mailbox_id', 'id');
    }


    /**
     * Get assignated connection to this folder
     *
     * @return ExchangeClient
     */
    public function getExchangeConnection()
    {
        return $this->mailbox->getExchangeConnection();
    }



    public function save(array $options = [])
    {
        $this->hash = $this->getHash();
        parent::save();
    }

    public function getHash(){
        return sha1(
            $this->message_id . ':' .
            $this->from . ':' .
            $this->to . ':' .
            $this->subject
        );
    }

    // Based on discussion:
    // - https://social.msdn.microsoft.com/Forums/en-US/b18243e4-543e-4463-8f39-bf47bc17e791/ews-itemid-structure-and-the-ways-it-can-change?forum=os_exchangeprotocols
    public function getItemIdObj()
    {
        $decoded = $this->item_id;
        $parts = (object)[
            'base64' => $this->item_id,
            'head' => mb_substr($decoded, 0, 43, '8bit'),
            'id' => mb_substr($decoded, 44, 114, '8bit')
        ];
        return $parts;
    }


    public function moveToFolder($id)
    {
        $folder = ExchangeFolder::findOrFail($id);
        if( !$folder || $folder->id == $this->exchange_folder_id )
            return false;

        $exchange = $this->getExchangeConnection();

        try
        {
            $moveResult = $exchange->moveEmailItem($this->item_id, $folder->item_id);
            if( $moveResult )
            {
                $this->item_id = $moveResult->Id;
                $this->exchange_folder_id = $folder->id;
                $this->save();
                return true;
            }
        }catch( Exception $e ){
            echo ' - move error -';
            print_r( $e->getMessage() );
            exit();
        }

        return false;
    }


    public function copyToFolder($id)
    {
        $folder = ExchangeFolder::findOrFail($id);
        $exchange = $this->getExchangeConnection();
        try
        {
            $result = $exchange->copyEmailItem($this->item_id, $folder->item_id);
            if( $result )
            {
                $cpItem = self::create(array_merge(
                    [
                        'item_id' => $result->Id,
                        'exchange_folder_id' => $folder->id,
                        'exchange_mailbox_id' => $folder->exchange_mailbox_id
                    ],
                    collect($this->toArray())->except(['id', 'item_id', 'exchange_folder_id', 'exchange_mailbox_id'])->toArray()
                ));
                return true;
            }
        }catch( Exception $e ){
            echo ' - move error -';
            print_r( $e->getMessage() );
            exit();
        }
    }

}