<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;

use andres3210\laraews\models\ExchangeFolder;
use andres3210\laraews\ExchangeClient;

class ExchangeMailbox extends Model
{

    protected $fillable = ['host', 'email', 'role'];

    public function syncFolderStructure()
    {
        $env = 'dev';
        if( strpos($this->email, 'canadavisa.com') !== false)
            $env = 'prod';

        $exchange = new ExchangeClient(null, null, null, null, $env);

        if($this->email != env('EXCHANGE_EMAIL'))
            $exchange->setImpersonationByEmail($this->email);

        $folders = $exchange->listFolders();

        foreach( $folders AS $folder ){
            $existing = ExchangeFolder::where([
                'item_id' => $folder->id,
                'exchange_mailbox_id' => $this->id
            ])->first();

            // re-compare ID as mysql is limited to 171 bytes and EWS ID is longer
            if( !$existing || $existing->item_id != $folder->id )
                ExchangeFolder::create([
                    'item_id' => $folder->id,
                    'exchange_mailbox_id' => $this->id,
                    'name' => $folder->name,
                    'parent_id' => $folder->ParentFolderId
                ]);
        }


        return $folders;
    }

}