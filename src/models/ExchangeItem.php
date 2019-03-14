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

        $exchange = new ExchangeClient();

        try{
            echo 'attempt move';
            $moveResult = $exchange->moveEmailItem($this->item_id, $folder->item_id);
            if( $moveResult ){
                $this->item_id = $moveResult->Id;
                $this->exchange_folder_id = $folder->id;
                $this->save();
                return true;
            }
        }catch( Exception $e ){
            echo ' - move error -';
            print_r( $e->getMessage() );
            //$this->delete();
            exit();
        }

        return false;
    }

}