<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;

class ExchangeSubscription extends Model
{

    protected $fillable = ['subscription_id', 'connection', 'callback', 'expire_on'];

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

        if( $this->callback == null || function_exists($this->callback))
            return false;


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

        // Invoke Function on Subscription
        return call_user_func( $this->callback, $events, $this->toArray()['connection']);
    }
}