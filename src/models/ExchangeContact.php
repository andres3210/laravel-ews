<?php

namespace andres3210\laraews\models;

use Illuminate\Database\Eloquent\Model;
use andres3210\laraews\ExchangeClient;
use andres3210\laraews\models\ExchangeMailbox;

use \jamesiarmes\PhpEws\Request\FindItemType;
use \jamesiarmes\PhpEws\Request\GetItemType;
use \jamesiarmes\PhpEws\Request\CreateItemType;

use jamesiarmes\PhpEws\Type\FolderIdType;
use \jamesiarmes\PhpEws\Type\ItemResponseShapeType;
use \jamesiarmes\PhpEws\Type\ContactsViewType;
use \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use \jamesiarmes\PhpEws\Type\ItemIdType;
use \jamesiarmes\PhpEws\Type\ContactItemType;
use \jamesiarmes\PhpEws\Type\PhoneNumberDictionaryType;
use \jamesiarmes\PhpEws\Type\EmailAddressDictionaryType;
use \jamesiarmes\PhpEws\Type\ExtendedPropertyType;
use \jamesiarmes\PhpEws\Type\EmailAddressDictionaryEntryType;
use \jamesiarmes\PhpEws\Type\CompleteNameType;

use \jamesiarmes\PhpEws\Enumeration\FolderQueryTraversalType;
use \jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use \jamesiarmes\PhpEws\Enumeration\EmailAddressKeyType;
use \jamesiarmes\PhpEws\Enumeration\FileAsMappingType;

use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;





class ExchangeContact extends Model
{

    protected $fillable = [
        'item_id', 'exchange_mailbox_id', 'exchange_address_book_id',
        'first_name', 'last_name', 'email', 'company_name'
    ];


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


    public function getItemIdAttribute($value)
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
    public static function ewsIndex($client, $contactBooks = [])
    {
        // Build the request to list all Contacts on Contact Address Books
        $request = new FindItemType();
        $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
        $request->ContactsView = new ContactsViewType();

        // Return all message properties.
        $request->ItemShape = new ItemResponseShapeType();
        //$request->ItemShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;
        $request->ItemShape->BaseShape = DefaultShapeNamesType::ID_ONLY;
        $request->Traversal = FolderQueryTraversalType::SHALLOW;

        // Find contacts in the contacts folder.
        if( count($contactBooks) > 0 ){
            // Specified ItemId folders
            foreach( $contactBooks AS $contactBook )
            {
                $folder_id = new \jamesiarmes\PhpEws\Type\FolderIdType();
                $folder_id->Id = $contactBook->item_id;
                $request->ParentFolderIds->FolderId[] = $folder_id;
            }
        }
        else
        {
            // Default Root Address Book [CONTACTS]
            $folder_id = new DistinguishedFolderIdType();
            $folder_id->Id = DistinguishedFolderIdNameType::CONTACTS;
            $request->ParentFolderIds->DistinguishedFolderId[] = $folder_id;
        }

        $response = $client->FindItem($request);

        // Iterate over the results, printing any error messages or contact ids.
        $response_messages = $response->ResponseMessages->FindItemResponseMessage;

        $contacts = [];
        foreach ($response_messages as $response_message)
        {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS)
                throw( new Exception($response_message->ResponseCode . ' - ' . $response_message->MessageText));

            // Iterate over the contacts that were found, printing the id of each.
            $items = $response_message->RootFolder->Items->Contact;
            foreach ($items as $item)
            {
                $contacts[] = $item->ItemId->Id;

                if( count($contacts) > 30 )
                    return $contacts;
            }
        }

        return $contacts;
    }


    public static function ewsDetails($client, $ids)
    {
        if( count($ids) == 0 )
            return [];

        // Build the request.
        $request = new GetItemType();
        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;
        $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();

        // Iterate over the contact ids, setting each one on the request.
        foreach ($ids as $contact_id) {
            $item = new ItemIdType();
            $item->Id = $contact_id;
            $request->ItemIds->ItemId[] = $item;
        }

        $response = $client->GetItem($request);

        $contacts = [];

        // Iterate over the results, printing any error messages or contact names.
        $response_messages = $response->ResponseMessages->GetItemResponseMessage;
        foreach ($response_messages as $response_message) {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS)
                throw( new \Exception($response_message->ResponseCode . ' - ' . $response_message->MessageText));

            // Iterate over the contacts.
            foreach ($response_message->Items->Contact as $item){
                $contacts[] = (object)[
                    "item_id"       => $item->ItemId->Id,
                    "parent_folder_id" => isset($item->ParentFolderId) && isset($item->ParentFolderId->Id) ?
                        $item->ParentFolderId->Id : null,
                    'company_name'  => $item->CompanyName,
                    'first_name'    => $item->CompleteName->FirstName,
                    'last_name'     => $item->CompleteName->LastName,
                    'email'         => isset($item->EmailAddresses->Entry[0]) ? $item->EmailAddresses->Entry[0]->{'_'} : null
                ];
            }

        }

        return $contacts;
    }


    public static function ewsStore($client, &$item)
    {
        // Build the request object.
        $request = new CreateItemType();

        // Save to specific Address Book
        //$request->SavedItemFolderId = new \jamesiarmes\PhpEws\Type\TargetFolderIdType();
        //$request->SavedItemFolderId->FolderId = new FolderIdType();
        //$request->SavedItemFolderId->FolderId->Id = 'AAMkADEzYzdiMDU3LWE4ZmQtNGI3My1hNTViLTNkYTE4N2NhZThmMAAuAAAAAAABJesM8IQQSYrHtScgKiGmAQConzvlxVLxRagQBO9zQuhVAAJOZwiWAAA=';

        $contact = new ContactItemType();
        $contact->FileAsMapping = FileAsMappingType::FIRST_SPACE_LAST;
        $contact->GivenName = $item->first_name;
        $contact->Surname = $item->last_name;
        $contact->CompanyName = $item->company_name;

        // Set an email address.
        $email = new EmailAddressDictionaryEntryType();
        $email->Key = EmailAddressKeyType::EMAIL_ADDRESS_1;
        $email->_ = $item->email;
        $contact->EmailAddresses = new EmailAddressDictionaryType();
        $contact->EmailAddresses->Entry[] = $email;
        $request->Items->Contact[] = $contact;


        $response = $client->CreateItem($request);

        // Iterate over the results, printing any error messages or contact ids.
        $response_messages = $response->ResponseMessages->CreateItemResponseMessage;
        foreach ($response_messages as $response_message)
        {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS)
                throw( new \Exception($response_message->ResponseCode . ' - ' . $response_message->MessageText));

            // Iterate over the created contacts.
            if(isset($response_message->Items->Contact) && isset($response_message->Items->Contact[0]))
            {
                //return $response_message->Items->Contact[0]->ItemId->Id;
                $item->item_id = $response_message->Items->Contact[0]->ItemId->Id;
                return true;
            }
        }

        return false;
    }


    public static function ewsUpdate($client, $item)
    {
        // Build the request.
        $request = new UpdateItemType();
        $request->ConflictResolution = ConflictResolutionType::ALWAYS_OVERWRITE;

        // Only email is supported at this time with version 2006
        $updateFields = [
            'email' => '_',
            //'first_name' => 'GivenName',
            //'last_name' => 'Surname',
            //'company_name' => 'CompanyName'
        ];

        // Iterate over the contacts to be updated.
        foreach ( $updateFields as $origin => $destination )
        {
            if( isset($item->{$origin}) && !empty($item->{$origin}) )
            {
                // Build out item change request.
                $change = new ItemChangeType();
                $change->ItemId = new ItemIdType();
                $change->ItemId->Id = $item->item_id;
                $change->Updates = new NonEmptyArrayOfItemChangeDescriptionsType();

                switch($origin)
                {
                    case 'email':
                        $field = new SetItemFieldType();
                        $field->IndexedFieldURI = new PathToIndexedFieldType();
                        $field->IndexedFieldURI->FieldURI = DictionaryURIType::CONTACTS_EMAIL_ADDRESS;
                        $field->IndexedFieldURI->FieldIndex = EmailAddressKeyType::EMAIL_ADDRESS_1;
                        $field->Contact = new ContactItemType();

                        $entry = new EmailAddressDictionaryEntryType();
                        $entry->_ = $item->email;
                        $entry->Key = EmailAddressKeyType::EMAIL_ADDRESS_1;
                        $field->Contact->EmailAddresses = new EmailAddressDictionaryType();
                        $field->Contact->EmailAddresses->Entry = $entry;

                        $change->Updates->SetItemField[] = $field;
                        break;

                    default:
                        $field = new SetItemFieldType();
                        $field->IndexedFieldURI = new PathToIndexedFieldType();
                        $field->IndexedFieldURI->FieldURI = 'contacts:' . $destination;

                        $field->Contact = new ContactItemType();
                        $field->Contact->{$destination} = $item->{$origin};
                        $change->Updates->SetItemField[] = $field;
                        break;
                }

                $request->ItemChanges[] = $change;
            }


        }

        $response = $client->UpdateItem($request);

        $response_messages = $response->ResponseMessages->UpdateItemResponseMessage;
        foreach ($response_messages as $response_message) {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS)
                throw( new \Exception($response_message->ResponseCode . ' - ' . $response_message->MessageText));

            // Iterate over the updated contacts, printing the id of each.
            foreach ($response_message->Items->Contact as $item)
                return $item->ItemId->Id;
        }
    }


    public static function ewsDelete($client, $id)
    {
        $request = new DeleteItemType();
        $request->DeleteType = DisposalType::HARD_DELETE;
        $request->SendMeetingCancellations = CalendarItemCreateOrDeleteOperationType::SEND_TO_NONE;

        $item = new ItemIdType();
        $item->Id = $id;
        $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
        $request->ItemIds->ItemId[] = $item;

        $response = $client->DeleteItem($request);

        $response_messages = $response->ResponseMessages->DeleteItemResponseMessage;
        foreach ($response_messages as $response_message) {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS)
                throw(new \Exception($response_message->ResponseCode . ' - ' . $response_message->MessageText));
            else
                return true;
        }
    }

}

