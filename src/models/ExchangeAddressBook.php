<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;

use andres3210\laraews\ExchangeClient;
use andres3210\laraews\models\ExchangeMailbox;



use \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;

use \jamesiarmes\PhpEws\Enumeration\FolderQueryTraversalType;
use \jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;

use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType;


class ExchangeAddressBook extends Model
{

    protected $fillable = ['item_id', 'parent_item_id', 'exchange_mailbox_id',  'name'];

    protected $hidden = ['item_id', 'parent_item_id'];

    /**
     * |
     * |--------------------------------------------------------------------------
     * | Accessors and Mutators
     * |--------------------------------------------------------------------------
     * |
     */
    public function setItemIdAttribute($value)
    {
        $this->attributes['item_id'] = base64_decode($value);
    }


    public function setParentItemIdAttribute($value)
    {
        $this->attributes['parent_item_id'] = base64_decode($value);
    }


    public function getItemIdAttribute($value)
    {
        return base64_encode($value);
    }


    public function getParentItemIdAttribute($value)
    {
        return base64_encode($value);
    }


    /**
     * |
     * |--------------------------------------------------------------------------
     * | Exchange EWS Functions (GET, DETAILS, EDIT, CREATE, DELETE)
     * |--------------------------------------------------------------------------
     * |
     */
    public static function ewsIndex($client)
    {
        // Build the request to list all Contact Address Books
        $request = new \jamesiarmes\PhpEws\Request\FindFolderType();
        $request->FolderShape = new \jamesiarmes\PhpEws\Type\FolderResponseShapeType();
        $request->FolderShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;
        $request->Traversal = FolderQueryTraversalType::DEEP;

        $parent = new DistinguishedFolderIdType();
        $parent->Id = DistinguishedFolderIdNameType::CONTACTS;
        $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
        $request->ParentFolderIds->DistinguishedFolderId[] = $parent;


        $response = $client->FindFolder($request);
        $response_messages = $response->ResponseMessages->FindFolderResponseMessage;
        $contactBooks = [];
        foreach ($response_messages as $response_message)
        {
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS)
                throw( new Exception($response_message->ResponseCode . ' - ' . $response_message->MessageText));

            foreach( $response_message->RootFolder->Folders->ContactsFolder AS $folder )
            {
                if( $folder->FolderClass == 'IPF.Contact' )
                    $contactBooks[] = (object)[
                        'item_id'           => $folder->FolderId->Id,
                        'parent_item_id'    => isset($folder->ParentFolderId) ? $folder->ParentFolderId->Id : null,
                        'name'              => $folder->DisplayName,
                        'folder_class'      => $folder->FolderClass
                    ];
            }
        }

        return $contactBooks;
    }

}