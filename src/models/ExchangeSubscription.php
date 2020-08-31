<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;
use andres3210\laraews\jobs\ProcessItemNotificationJob;

class ExchangeSubscription extends Model
{

    protected $fillable = ['item_id', 'exchange_mailbox_id', 'callback', 'rules', 'keep_alive', 'expire_on'];


    protected $casts = [
        'rules' => 'array', // cast to json convert
    ];


    /**
    |
    |--------------------------------------------------------------------------
    | Setters and Getters
    |--------------------------------------------------------------------------
    |
     */
    public function getItemIdAttribute($value)
    {
        return base64_encode($value);
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


    public function handle($notification)
    {
        if($notification == null || !isset($notification->ResponseClass) || $notification->ResponseClass != 'Success')
            return false;

        if( !isset($notification->Notification) && !isset($notification->Notification->SubscriptionId) )
            return false;

        if( $this->expire_on != null &&  \Carbon\Carbon::now()  > $this->expire_on ){
            $this->delete();
            return false;
        }

        $eventTypes = [
            'CreatedEvent',
            'DeletedEvent',
            'ModifiedEvent',
            'NewMailEvent',
            'MovedEvent',
            'CopiedEvent',
            'FreeBusyChangedEvent'
        ];

        $events = [];
        foreach( $eventTypes AS $eventName){
            if( isset($notification->Notification->{$eventName}) ){

                // Internal Object
                if( !isset($events[$eventName]) )
                    $events[$eventName] = [];
                $items = &$events[$eventName];

                // Explore Xml
                $node = $notification->Notification->{$eventName};
                if( is_array($node) ){
                    foreach($node AS $event)
                        if( isset($event->ItemId) && isset($event->ItemId->Id) )
                            $items[] = $event;
                }
                else {
                    if( isset($node->ItemId) && isset($node->ItemId->Id) )
                        $items[] = $node;
                }
            }
        }

        // Standard Job to Download Items
        ProcessItemNotificationJob::pushJob($events, $this->mailbox->getExchangeConnection());
        
        // Custom Callback 
        //echo 'Attempt to notify callback ' . $this->callback . PHP_EOL;
        if( !empty($this->callback) )
        {
            try {
                if( @eval( $this->callback . "; return true;") !== true )
                    throw( new \Exception('Invalid App Function') );
                
                eval( $this->callback . ';');
            } 
            catch (\Exception $e) {
                //echo $e->getMessage();
            }
        }

        // Keep subscription alive
        return true;
    }
}