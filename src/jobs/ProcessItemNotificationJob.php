<?php 

namespace andres3210\laraews\jobs;

// Laravel Classes
use Log;
use App\Jobs\Job;
//use App\Jobs\PushNotificationJob;

// Internal Classes
use andres3210\laraews\ExchangeClient;
use andres3210\laraews\models\ExchangeItem;
use andres3210\laraews\models\ExchangeFolder;
use Mockery\CountValidator\Exception;


class ProcessItemNotificationJob extends Job
{
    private $items = [];
    private $exchange = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($items, $exchange = null)
    {
        $this->items = $items;
        $this->exchange = $exchange != null ? $exchange : new ExchangeClient();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $ids = [];

        if( isset($this->items['MovedEvent']) ) 
        {
            echo 'Moved >> ' . count($this->items['MovedEvent']) . PHP_EOL;
            foreach($this->items['MovedEvent'] AS $item)
            {
                // echo print_r($item, 1);
                if( isset($item->ItemId) && isset($item->ItemId->Id) && isset($item->OldItemId) && isset($item->OldItemId->Id) )
                {
                    $existingItem = ExchangeItem::where('item_id', base64_decode($item->OldItemId->Id))
                        ->first();
                    
                    if( $existingItem )
                    {
                        echo 'found';
                        $existingItem->item_id = $item->ItemId->Id;
                        $existingItem->save();
                    }
                    // Queue for new download
                    else
                    {
                        echo 'not found - download it ' . $item->ItemId->Id . PHP_EOL;
                        $ids[] = $item->ItemId->Id;
                    }
                }
            }
        }
            

        
        if( isset($this->items['CreatedEvent']) )
        {
            echo 'Created >> ' . count($this->items['CreatedEvent']) . PHP_EOL;
            foreach($this->items['CreatedEvent'] AS $item)
                if( isset($item->ItemId) && isset($item->ItemId->Id) )
                    $ids[] = $item->ItemId->Id;
        }

        $insertedIds = [];
        if( count($ids) > 0 )
        {
            try
            {
                $emails = $this->exchange->getEmailItem($ids);

                foreach($emails AS $email)
                {
                    $folder = ExchangeFolder::where('item_id', '=', base64_decode($email->ParentFolderId))->first();

                    if( !$folder )
                        Log::warning('Unable to map folder id: ' . $email->ParentFolderId);
                    
                    $ewsItem = new ExchangeItem([
                        'item_id'               => $email->ItemId,
                        'exchange_folder_id'    => $folder ? $folder->id : null,
                        'exchange_mailbox_id'   => $folder ? $folder->exchange_mailbox_id : null,
                        'message_id'    => $email->InternetMessageId,
                        'subject'       => $email->Subject,
                        'from'          => isset($email->From) ? $email->From : 'no-email',
                        'to'            => implode(',', $email->To),
                        'cc'            => implode(',', $email->Cc),
                        'bcc'           => implode(',', $email->Bcc),
                        'created_at'    => \Carbon\Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $email->DateTimeCreated),
                        'header'        => $email->Header,
                        'body'          => $email->Body,
                        //'in_reply_to'   => $email->ConversationId,
                    ]);

                    // Init Vars
                    $ewsItem->internal_impersonated = false;
                    $ewsItem->spoof_detected = false;

                    // Extract email domain
                    $fromDomain = explode('@', $ewsItem->from)[1];
                    echo 'Domain From - ' . $fromDomain . PHP_EOL;

                    // @todo - internal domains to config file
                    $internalDomains = [
                        'canadavisa.com',
                        'canadavisadev.com'
                    ];

                    if( !in_array($fromDomain, $internalDomains) )
                    {
                        $senderServer = $ewsItem->extractSenderServer();
                        echo print_r(['info' => $senderServer], 1);

                        if( $senderServer != null && isset($senderServer->server) )
                        {
                            if( $senderServer->spf !== true )
                            {
                                echo 'Spoof Detected' . PHP_EOL;

                                // @todo - load allowed spoofing domains allowed to config file
                                // validate spoofing and internal 
                                $allowedInternalSpoofing = [
                                    'sendgrid.canadavisa.com', 'canadavisa.com',
                                    'sendgrid.com', 'mailgun.net'
                                ];
                                
                                $internal = false;    
                                foreach( $allowedInternalSpoofing AS $whitelisted )
                                    if( strpos( $senderServer->server, $whitelisted) )
                                        $internal = true;

                                $ewsItem->internal_impersonated = $internal;
                                $ewsItem->spoof_detected = !$internal;   
                            }
                        }

                    }

                    

                    $ewsItem->save();

                    $insertedIds[] = $ewsItem->id;
                }
                
            }
            catch(Exception $e){
                echo $e->getMessage() . PHP_EOL;
            }
        }

        return;
    }

    /**
     * Add items by id to the queue for async processing
     */
    public static function pushJob($items, $exchange)
    {
        // Push Job to the Queue
        if( count($items) > 0)
            dispatch(new self($items, $exchange));

        // Keep subscription
        return true;
    }
}