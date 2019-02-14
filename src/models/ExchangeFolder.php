<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;
use andres3210\laraews\ExchangeClient;
use andres3210\laraews\models\ExchangeItem;

class ExchangeFolder extends Model
{

    protected $fillable = ['exchange_mailbox_id', 'item_id', 'parent_id', 'name'];

    public function syncExchange( $mode = 'last' ){

        $exchange = new ExchangeClient();

        $search = [];

        switch($mode){
            case 'all':
                break;

            case 'last':
            default:
                $lastItem = ExchangeItem::where([
                    'exchange_mailbox_id' => 4,
                    'exchange_folder_id' => $this->id
                ])->orderBy('created_at', 'ASC')->first();

                $endDate = new \DateTime('now');
                if( $lastItem )
                    $endDate = $lastItem->created_at;

                $startDate = new \DateTime( $endDate->format('Y-m-d H:i:s'));
                $startDate->modify('- 1 month');

                $search['dateFrom'] = $startDate;
                $search['dateTo']   = $endDate;
                break;
        }

        $items = $exchange->getFolderItems($this->item_id, $search);

        $inserted = 0;
        $bufferIds = [];
        $limit = 30;
        foreach($items AS $key => $item){

            $existing = ExchangeItem::where(['item_id' => $item->ItemId])->first();

            if( !$existing )
                $bufferIds[] = $item->ItemId;

            if( (count($bufferIds) >= $limit || !next($items)) && count($bufferIds) > 0 ){
                $emails = $exchange->getEmailItem($bufferIds);

                // Reset Buffer
                $bufferIds = [];

                foreach($emails AS $email){
                    $newExchangeItem = new ExchangeItem([
                        'item_id'               => $email->ItemId,
                        'exchange_folder_id'    => $this->id,
                        'exchange_mailbox_id'   => $this->exchange_mailbox_id,
                        'message_id'    => $email->InternetMessageId,
                        'subject'       => $email->Subject,
                        'from'          => isset($email->From) ? $email->From : 'no-email',
                        'to'            => implode(',', $email->To),
                        'cc'            => implode(',', $email->Cc),
                        'bcc'           => implode(',', $email->Bcc),
                        'created_at'    => \Carbon\Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $email->DateTimeCreated),
                        'body'          => $email->Body,
                    ]);

                    $existingHash = ExchangeItem::where('hash', '=', $newExchangeItem->getHash())->first();

                    if( !$existingHash ){
                        $inserted = $newExchangeItem->save();

                        if($inserted){
                            echo 'Inserted '.$inserted->id . PHP_EOL;
                            $inserted++;
                        }
                    }
                    else{
                        // Attach new ItemID and location
                        $existingHash->item_id              = $email->ItemId;
                        $existingHash->exchange_folder_id   = $this->id;
                        $existingHash->exchange_mailbox_id  = $this->exchange_mailbox_id;
                        $existingHash->save();
                    }
                }
            }
        }

        return ['inserted' => $inserted];
    }

}