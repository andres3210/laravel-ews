<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;

use andres3210\laraews\models\ExchangeFolder;
use andres3210\laraews\ExchangeClient;
use Mockery\CountValidator\Exception;

use App\Library\SPFValidate;

class ExchangeItem extends Model
{

    protected $fillable = [
        'item_id', 'exchange_mailbox_id', 'exchange_folder_id', 'message_id',
        'subject', 'from', 'to', 'cc', 'bcc', 'header', 'body', 'attachment', 
        'internal_impersonated', 'spoof_detected',
        'created_at'
    ];

    protected $hidden = ['item_id', 'message_id', 'hash', 'header'];


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
            return 'invalid body gzip encoding';
        }
    }

    public function getItemIdAttribute($value)
    {
        return base64_encode($value);
    }

    public function getHeaderAttribute($value)
    {
        if( is_null($value) )
            return $value;

        try {
            return json_decode(gzdecode($value));
        }
        catch(Exception $e){
            return 'invalid header blob gzip encoding';
        }
    }


    public function setBodyAttribute($value)
    {
        $this->attributes['body'] = gzencode($value);
    }

    public function setItemIdAttribute($value)
    {
        $this->attributes['item_id'] = base64_decode($value);
    }

    public function setHeaderAttribute($value)
    {
        $this->attributes['header'] = gzencode(json_encode($value));
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

    public function references()
    {
        return $this->hasMany('andres3210\laraews\models\ExchangeItemReference', 'exchange_item_id');
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

        // After save, primary id should be available
        $this->extractForeignEmailAddresses();
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

        try {
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
        try {
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

                return $cpItem;
            }
        }catch( Exception $e ){
            echo ' - move error -';
            print_r( $e->getMessage() );
            exit();
        }
    }



    public static function extractEmailAddresses($string)
    {
        $pattern = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/';
        preg_match_all($pattern, $string, $aMatch);
        return isset($aMatch[0]) ? $aMatch[0] : [];
    }


    /**
     * This method lists all emails contained inside the EmailItem
     * - to, from, cc, bcc (if available)
     * - body mentions
     * 
     * Results are stored as EmailItemReference
     * 
     * Note: it excludes all email from the same domain as owner (i.e. owner@canadavisa.com)
     */
    public function extractForeignEmailAddresses()
    {
        // Result array
        $foreignEmails = [];


        // Extract Mailbox domain to exclude internal email addresses detection
        $domain = explode('@', $this->mailbox()->first()->email)[1];


        // Build routing emails array (for exclusion)
        $routingEmails = [];
        $fields = ['to', 'from', 'cc', 'bcc'];
        foreach( $fields AS $field )
        {
            $tmp = self::extractEmailAddresses($this->{$field});
            $routingEmails = array_merge($routingEmails, $tmp);
        }


        // Build emails found in body text and pass to result set
        $bodyEmails = self::extractEmailAddresses($this->body);
        foreach($bodyEmails AS $email)
        {
            if( !in_array($email, $routingEmails) && !in_array($email, $foreignEmails) && strpos($email, $domain) === false )
                $foreignEmails[] = $email;
        }


        // Build emails found in subject text
        $subjectEmails = self::extractEmailAddresses($this->subject);
        foreach($subjectEmails AS $email)
        {
            if( !in_array($email, $routingEmails) && !in_array($email, $foreignEmails) && strpos($email, $domain) === false  )
                $foreignEmails[] = $email;
        }

        // Sync to database
        if( count($foreignEmails) > 0 )
        {
            $this->references()->whereType(ExchangeItemReference::TYPE_FOREIGN_EMAIL)->delete();

            $buff = [];
            foreach($foreignEmails AS $email)
                $buff[] = [
                    'type'  => ExchangeItemReference::TYPE_FOREIGN_EMAIL,
                    'value' => $email
                ];

            if( isset($this->id)  && $this->id != null)
                $this->references()->createMany($buff);
        }

        return $foreignEmails;
    }


    public function extractSenderServer()
    {
        // @todo - Get server name from mailbox table or config file
        $exchangeInternalHostName = 'WIN-MKUPUNREHLI.canadavisadev.local';

        if( isset($this->header->Received) )
        {
            $emailDomain = explode('@', $this->from)[1];

            // look for first header containing email-domain.local
            for( $i = count($this->header->Received) - 1; $i >= 0; $i-- )
            {
                if(strpos($this->header->Received[$i], $exchangeInternalHostName) !== false )
                {
                    $parts = explode($exchangeInternalHostName, $this->header->Received[$i]);
                    $sender = explode(' ', str_replace(['from ', ' by'], '', $parts[0]));

                    $senderIP = str_replace(['(',')'], '', $sender[1]);

                    $senderObj = (object)[
                        'server'    => $sender[0],
                        'domain'    => $emailDomain,
                        'ip'        => $senderIP,
                        'spf'       => null
                    ];
                    
                    $senderObj->spf = SPFValidate::isAllowed($senderIP, $emailDomain);

                    return $senderObj;
                }
            }

            return null;
        }
    }

}