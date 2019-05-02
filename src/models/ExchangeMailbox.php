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

        $exclude = [
            'Notes',
            'Calendar', 'Contacts', 'GAL Contacts', 'Recipient Cache', 'Common Views', 'Deferred Action',
            'ExchangeSyncData', 'Finder', 'Freebusy Data', 'Location', 'MailboxAssociations', 'ParkedMessages',
            'PeopleConnect', 'Recoverable Items', 'Calendar Logging', 'Deletions', 'Purges', 'Versions',
            'Schedule', 'Sharing', 'Shortcuts', 'System', 'TemporarySaves', 'Top of Information Store',
            'Conversation Action Settings', 'Deleted Items', 'Drafts', 'Journal', 'Junk Email',
            'Outbox', 'Working Set', 'Views', 'Favorites', 'My Contacts', 'MyContactsExtended', 'People I Know',
            'Reminders', 'Spooler Queue', 'To-Do Search', 'Tasks', 'AllItems', 'RSS Feeds',
        ];

        $partialTextExlcude = [
            'MS-OLK-BGPooledSearchFolder', 'MailboxReplicationService', 'TaskActive%', '{'
        ];

        // - 1) Clean up folder items to avoid importing unnecessary data
        $foldersClean = [];
        foreach( $folders AS $folder )
        {
            $continue = false;
            if( in_array($folder->name, $exclude) )
                $continue = true;


            foreach( $partialTextExlcude AS $search )
                if( strpos($folder->name, $search ) === 0 )
                    $continue = true;

            if( $continue )
                continue;

            $foldersClean[] = $folder;
        }

        // - 2) Store all Folders into DB
        $obj_folders = [];
        foreach( $foldersClean AS $folder ){
            $existing = ExchangeFolder::where([
                'item_id' => base64_decode($folder->id),
                'exchange_mailbox_id' => $this->id
            ])->first();

            if( !$existing ){
                $inserted = ExchangeFolder::create([
                    'item_id' => $folder->id,
                    'exchange_mailbox_id' => $this->id,
                    'name' => $folder->name
                ]);

                $inserted->exchange_parent_id = $folder->ParentFolderId;

                $obj_folders[] = $inserted;
            }
            else
            {
                $existing->exchange_parent_id = $folder->ParentFolderId;
                $obj_folders[] = $existing;
            }

        }

        // - 2) Map parent_id if we find folder in our internal DB
        foreach( $obj_folders AS &$objFolder )
        {
            if( $objFolder->exchange_parent_id != null )
            {
                $parent = ExchangeFolder::where([
                    'item_id' => base64_decode($objFolder->exchange_parent_id),
                    'exchange_mailbox_id' => $this->id
                ])->first();

                if($parent)
                {
                    $objFolder->parent_id = $parent->id;
                    unset($objFolder->exchange_parent_id);
                    $objFolder->save();
                }
            }
        }
        
        return $obj_folders;
    }

}