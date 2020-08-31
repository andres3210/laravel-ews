<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;
use andres3210\laraews\ExchangeClient;
use andres3210\laraews\models\ExchangeMailbox;
use andres3210\laraews\models\ExchangeItem;

class ExchangeFolder extends Model
{

    const MODE_PROGRESSIVE = 'PROGRESSIVE';
    const STATUS_PARTIAL_SYNC = 'PARTIAL_SYNC';

    protected $fillable = ['exchange_mailbox_id', 'item_id', 'parent_id', 'name'];


    /**
    |
    |--------------------------------------------------------------------------
    | Accessors and Mutators
    |--------------------------------------------------------------------------
    |
     */
    public function setItemIdAttribute($value)
    {
        $this->attributes['item_id'] = base64_decode($value);
    }

    public function getItemIdAttribute($value)
    {
        return base64_encode($value);
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


    /**
     * List items in remote exchange folder
     *
     * @param $search | dateFrom, dateTo
     * @return Array | EWS Email Items
     */
    public function getExchangeItems($search)
    {
        $exchange = $this->getExchangeConnection();
        $emails = $exchange->getFolderItems($this->item_id, $search);
        return $emails;
    }


    /**
     * progressive mode
     *  scans full folder starting from now till the beginning
     *
     * last mode
     *  scans last month of email items
     */
    public function syncExchange( $mode = 'last', $params = null )
    {
        $exchange = $this->getExchangeConnection();

        $search = [];
        switch($mode){
            case SELF::MODE_PROGRESSIVE:

                // Lock cron
                if($this->status == 'sync-in-progress' )
                    return;

                $this->status = 'sync-in-progress';
                if( !isset($this->status_data) || $this->status_data == '' ){
                    $status_data = (object)([
                        'syncMode'   => $mode,
                        'needleDate' => new \DateTime('now')
                    ]);
                    $this->status_data = json_encode($status_data);
                }
                else{
                    $status_data = json_decode($this->status_data);
                    $status_data->needleDate = new \DateTime($status_data->needleDate->date);
                }
                $this->save();

                $search['dateTo']   = $status_data->needleDate;
                $search['limit']    = 1000;
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

                $status_data = (object)([
                    'syncMode'   => $mode,
                    'needleDate' => new \DateTime('now')
                ]);
                break;
        }

        $downloadBody = true;
        if( $params != null ){

            /*if( isset($params['dateFromLimit']) ){
                if( !isset($search['dateFrom']) )
                    $search['dateFrom'] = new \DateTime('now');

                $search['dateFrom'] = $search['dateFrom'] < $params['dateFromLimit'] ? $params['dateFromLimit'] : $search['dateFrom'];
            }*/

            if( isset($params['skipBody']) && $params['skipBody'] )
                $downloadBody = false;
        }

        echo 'Search: '.print_r($search, 1);

        $items = $exchange->getFolderItems($this->item_id, $search);

        $results = [
            'listed'        => 0,
            'downloaded'    => 0,
            'inserted'      => 0,
            'existing'      => 0,
            're-linked'     => 0,
            'oldest'        => \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $status_data->needleDate->format('Y-m-d H:i:s'))
        ];

        if( count($items) == 0 && isset($search['dateFrom']) ){
            $results['oldest'] = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $search['dateFrom']->format('Y-m-d H:i:s'));
        }




        $bufferIds = [];
        $limit = 30;
        $itemsSize = count($items);
        foreach($items AS $index => $item){
            $results['listed']++;

            //echo $item->ItemId . PHP_EOL;
            $addToLocalDb = false;

            $existing = ExchangeItem::where(['item_id' => base64_decode($item->ItemId)])->first();

            if( !$existing ){
                //echo 'added new' . PHP_EOL;
                $bufferIds[] = $item->ItemId;
                $addToLocalDb = true;
            }


            // MySQL Indexes do not support the length of EWS Item Ids.
            // Id need to be re-verified to avoid false positive due to incomplete index
            else if( strcmp($item->ItemId, $existing->item_id) != 0 ){
                $bufferIds[] = $item->ItemId;
                $addToLocalDb = true;
                //echo "Subject " . $item->Subject . ' VS ' , $existing->subject . PHP_EOL;
                //echo "Date " . $item->DateTimeReceived . ' VS ' , $existing->created_at . PHP_EOL;
                //echo 'added possible duplicate id verified - ' . strcmp($item->ItemId, $existing->item_id) . PHP_EOL;
            }

            // Exchange is capable to have 1 Item in multiple folders in the same mailbox
            // We need to have a copy for the internal db
            else if( $this->id != $existing->exchange_folder_id ){
                //echo 'added duplicate, different folder' . PHP_EOL;
                $bufferIds[] = $item->ItemId;
                $addToLocalDb = true;
            }

            // Duplicate Item
            else
            {
                 //echo $item->DateTimeReceived .' >> '.$item->Subject .'('.$item->From.')'. PHP_EOL;
                 //echo 'Duplicate: ' . $existing->created_at->format('Y-m-d H:i:s') .' >> '.
                     //$existing->subject .'('.$existing->from.')'. PHP_EOL;

                $results['existing']++;
                $tmpDate = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $item->DateTimeReceived );
                if($results['oldest'] > $tmpDate)
                    $results['oldest'] = $tmpDate;
            }

            if( $downloadBody ) {
                if ((count($bufferIds) >= $limit || $index == ($itemsSize - 1)) && count($bufferIds) > 0) {

                    // Retrieve full body of all emails
                    $emails = $exchange->getEmailItem($bufferIds);

                    // Reset Buffer
                    $bufferIds = [];

                    foreach ($emails AS $email) {
                        $results['downloaded']++;

                        $itemDate = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $email->DateTimeCreated);
                        if ($results['oldest'] > $itemDate)
                            $results['oldest'] = $itemDate;

                        $newExchangeItem = new ExchangeItem([
                            'item_id' => $email->ItemId,
                            'exchange_folder_id' => $this->id,
                            'exchange_mailbox_id' => $this->exchange_mailbox_id,
                            'message_id' => $email->InternetMessageId,
                            'subject' => isset($email->Subject) ? $email->Subject : '',
                            'from' => isset($email->From) ? $email->From : 'no-email',
                            'to' => implode(',', $email->To),
                            'cc' => implode(',', $email->Cc),
                            'bcc' => implode(',', $email->Bcc),
                            'created_at' => $itemDate,
                            'body' => $email->Body,
                        ]);

                        // Only re-link items with empty ItemId
                        $existingHash = ExchangeItem::where('item_id', '=', '')
                            ->where('hash', '=', $newExchangeItem->getHash())
                            ->first();

                        if (!$existingHash) {
                            $newExchangeItem->save();
                            $results['inserted']++;
                        } else {
                            // Attach new ItemID and location
                            $existingHash->item_id = $email->ItemId;
                            $existingHash->exchange_folder_id = $this->id;
                            $existingHash->exchange_mailbox_id = $this->exchange_mailbox_id;
                            $existingHash->save();
                            $results['re-linked']++;
                        }
                    }
                }
            }// End of Download body
            else
            {
                $itemDate = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $item->DateTimeReceived);
                if ($results['oldest'] > $itemDate)
                    $results['oldest'] = $itemDate;

                // Validate if we have enough information to store email
                if( $addToLocalDb &&  isset($item->FromEmail) && filter_var($item->FromEmail, FILTER_VALIDATE_EMAIL) ){

                    $tmp = [
                        'item_id' => $item->ItemId,
                        'exchange_folder_id' => $this->id,
                        'exchange_mailbox_id' => $this->exchange_mailbox_id,
                        //'message_id' => $email->InternetMessageId,
                        'subject' => isset($item->Subject) ? $item->Subject : '',
                        'from' => $item->FromEmail,
                        'to' => filter_var($item->DisplayTo, FILTER_VALIDATE_EMAIL) ? $item->DisplayTo : '',
                        'cc' => '',
                        'bcc' => '',
                        'created_at' => $itemDate,
                        'body' => null,
                    ];
                    //echo print_r($tmp, 1);
                    $newExchangeItem = new ExchangeItem($tmp);

                    // Only re-link items with empty ItemId
                    $existingHash = ExchangeItem::where('item_id', '=', '')
                        ->where('hash', '=', $newExchangeItem->getHash())
                        ->first();

                    if (!$existingHash) {
                        $newExchangeItem->save();
                        $results['inserted']++;
                        //echo 'saved' . PHP_EOL;
                    }
                }

            }
        }

        if( $mode == self::MODE_PROGRESSIVE ){
            $status_data->needleDate = new \DateTime( $results['oldest']->format('Y-m-d H:i:s'));
            $this->status_data = json_encode($status_data);
            $this->status = self::STATUS_PARTIAL_SYNC;
            $this->save();
        }

        return $results;
    }

}