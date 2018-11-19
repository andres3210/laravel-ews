<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;

class ExchangeItem extends Model
{

    protected $fillable = ['item_id', 'subject', 'from', 'to', 'cc', 'bcc', 'body', 'attachment', 'created_at'];

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

}