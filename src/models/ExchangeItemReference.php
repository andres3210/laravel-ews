<?php
/**
 * User: Jonathan Arias
 * Date: October 2019
 */

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;

class ExchangeItemReference extends Model
{

    protected $fillable = ['exchange_item_id', 'type', 'value'];

    public $timestamps = false;

    const TYPE_FOREIGN_EMAIL = 'foreignEmail';


    /**
     * |
     * |--------------------------------------------------------------------------
     * | Relationships
     * |--------------------------------------------------------------------------
     * |
     */
    public function exchangeItem()
    {
        return $this->belongsTo('andres3210\laraews\models\ExchangeItem', 'exchange_item_id', 'id');
    }
}