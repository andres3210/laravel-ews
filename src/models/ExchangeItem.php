<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;

use andres3210\laraews\models\ExchangeFolder;
use andres3210\laraews\ExchangeClient;
use Mockery\CountValidator\Exception;

class ExchangeItem extends Model
{

    protected $fillable = [
        'item_id', 'exchange_mailbox_id', 'exchange_folder_id',
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

    public function setBodyAttribute($value)
    {
        $this->attributes['body'] = gzencode($value);
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
            $this->delete();
            exit();
        }


        return false;
    }

}