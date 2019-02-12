<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;
use andres3210\laraews\models\ExchangeFolder;

class ExchangeMailbox extends Model
{

    protected $fillable = ['host', 'email', 'role'];

    public function syncFolderStructure()
    {
        $exchange = new ExchangeClient();

        $folders = $exchange->listFolders();

        foreach( $folders AS $folder )
            ExchangeFolder::firstOrCreate(['item_id' => $folder->id],[
                'exchange_mailbox_id' => 4,
                'name' => $folder->name,
                'parent_id' => $folder->ParentFolderId
            ]);

        return $folders;
    }

}