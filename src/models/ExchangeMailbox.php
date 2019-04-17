<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;

use andres3210\laraews\models\ExchangeFolder;
use andres3210\laraews\ExchangeClient;

class ExchangeMailbox extends Model
{

    protected $fillable = ['host', 'email', 'role'];


    /**
    |
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    |
     */
    public function folders()
    {
        return $this->hasMany('andres3210\laraews\models\ExchangeFolder');
    }


    /**
     * Get assignated connection to this folder
     *
     * @return ExchangeClient
     */
    public function getExchangeConnection()
    {
        $exchange = ExchangeClient::getConnection($this->ews_connection);
        $exchange->setImpersonationByEmail($this->email);
        return $exchange;
    }


    public function syncFolderStructure()
    {
        $exchange = $this->getExchangeConnection();
        $folders = $exchange->listFolders();

        foreach( $folders AS $folder ){
            $existing = ExchangeFolder::where([
                'item_id' => base64_decode($folder->id),
                'exchange_mailbox_id' => $this->id
            ])->first();

            if( !$existing )
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