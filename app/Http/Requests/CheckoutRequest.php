<?php

namespace App\Http\Requests;

use App\Alma\User as AlmaUser;
use App\Item;
use App\Rules\ConfirmationNeeded;
use App\Rules\NotOnLoan;
use App\Rules\NotTrashed;
use App\Rules\RequiresBarcode;
use App\Rules\ThingExists;
use App\Rules\UniqueAlmaUser;
use App\Rules\UserBarcodeExists;
use App\Rules\UserExists;
use App\Thing;
use App\User;
use Illuminate\Foundation\Http\FormRequest;
use Scriptotek\Alma\Bibs\Item as AlmaItem;
use Scriptotek\Alma\Client as AlmaClient;

class CheckoutRequest extends FormRequest
{
    public $item;
    public $user;
    public $localUser;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $library = \Auth::user();

        $input = $this->all();

        $alma = app(AlmaClient::class);

        // ======================== Lookup item and thing ========================

        $thing = null;
        $item = null;

        if (empty(array_get($input, 'thing.name'))) {
            // No thing was entered
        } elseif (!array_get($input, 'thing.type')) {
            // A thing was entered manually, not selected from the typeahead menu.
            // First check if the value matches a barcode.
            $item = Item::withTrashed()->where('barcode', '=', array_get($input, 'thing.name'))->first();

            if (is_null($item)) {
                // Next, check if it matches a thing name.
                $thing = Thing::where('name', '=', array_get($input, 'thing.name'))->first();
            }

            if (is_null($item) && !empty($library->library_code)) {
                // Next, check if it can be found in Alma.
                // If the library doesn't have a library code set, it means we should not check Alma.
                $item = $alma->items->fromBarcode(array_get($input, 'thing.name'));
            }
        } elseif (array_get($input, 'thing.type') == 'item') {
            $item = Item::withTrashed()->where('id', '=', array_get($input, 'thing.id'))->first();
        } elseif (array_get($input, 'thing.type') == 'thing') {
            $thing = Thing::where('id', '=', array_get($input, 'thing.id'))->first();
        }

        if (is_null($item) && !is_null($thing)) {
            // Then find a generic item, if one exists
            if (!$thing->library_settings->loans_without_barcode) {
                return [
                    'thing' => [new RequiresBarcode()],
                ];
            }

            $item = Item::where('thing_id', '=', $thing->id)->whereNull('barcode')->first();
            if (!$item) {
                \Log::info('Creating generic item for thing ' . $thing->id);
                $item = new Item();
                $item->thing_id = $thing->id;
                $item->save();
            }
        }

        if (!is_null($item) && $item->thing_id == 1 && !empty($library->library_code)) {
            // Local copy of an Alma item
            $item = $alma->items->fromBarcode($item->barcode);
        }

        // ======================== Lookup or import user ========================

        $user = null;

        if (empty(array_get($input, 'user.name'))) {
            // No user was entered
        } elseif (array_get($input, 'user.type') == 'local') {
            // Lookup local user by id
            $user = User::find(array_get($input, 'user.id'));
        } elseif (array_get($input, 'user.id')) {
            // Import user from Alma by primary ID
            $query = 'primary_id~' . array_get($input, 'user.id');
            $users = $this->almaSearch($alma, $query);
            if (count($users) == 1) {
                $user = $this->importUser($users[0]);
            } elseif (count($users) > 1) {
                return ['user' => [new UniqueAlmaUser($query)]];
            }
        } else {
            $userValue = array_get($input, 'user.name');

            if (strpos($userValue, ',') !== false) {
                // Try looking up local user by name
                $fullname = explode(',', $userValue);
                $fullname = array_map('trim', $fullname);
                $user = User::where('lastname', '=', $fullname[0])
                    ->where('firstname', '=', $fullname[1])
                    ->first();
            } else {
                $fullname = null;

                // Try looking up local user by barcode
                $user = User::where('barcode', '=', $userValue)
                    ->orWhere('university_id', '=', $userValue)
                    ->orWhere('alma_primary_id', '=', $userValue)
                    ->first();
            }


            if (is_null($user)) {
                // If user was not found locally, try Alma.
                // Check if the input value matches primary_id first,
                // since there is less risk of matching multiple users.
                $query = 'primary_id~' . $userValue;
                $users = $this->almaSearch($alma, $query);
                if (count($users) == 0) {
                    $query = 'ALL~' . $userValue;
                    $users = $this->almaSearch($alma, $query);
                }
                if (count($users) == 1) {
                    $user = $this->importUser($users[0]);
                } elseif (count($users) > 1) {
                    return ['user' => [new UniqueAlmaUser($query)]];
                }
            }

            if (is_null($user)) {
                // User was not found locally or in Alma.

                // If the input looks like a barcode, we will make suggestions based on that.
                if (AlmaUser::isUserBarcode($userValue)) {
                    return ['user' => [new UserExists(null, [
                        'barcode' => $userValue,
                    ])]];
                }

                // If the input looks like a name, we will make suggestions based on that.
                if (!is_null($fullname)) {
                    return ['user' => [new UserExists(null, [
                        'firstname' => $fullname[1],
                        'lastname' => $fullname[0],
                    ])]];
                }
            }
        }

        $this->user = $user;
        $this->item = $item;

        return [
            'confirmed' => [new ConfirmationNeeded($user)],
            'user' => [new UserExists($user)],
            'thing' => [new ThingExists($item), new NotTrashed($item), new NotOnLoan($alma, $item)],
        ];
    }

    protected function importUser(AlmaUser $almaUser)
    {
        $barcode = $almaUser->getBarcode();
        $univId = $almaUser->getUniversityId();
        $user = User::where('barcode', '=', $barcode)
            ->orWhere('university_id', '=', $univId)
            ->orWhere('alma_primary_id', '=', $almaUser->primaryId)
            ->first();

        if (is_null($user)) {
            $user = new User();
        }
        $user->mergeFromAlmaResponse($almaUser);
        $user->save();

        \Log::info(sprintf(
            'Importerte en bruker fra Alma (<a href="%s">Detaljer</a>)',
            action('UsersController@getShow', $user->id)
        ));

        return $user;
    }

    protected function almaSearch($alma, $query)
    {
        if (is_null($alma->key)) {
            \Log::warning('Cannot search Alma users since no Alma API key is configured.');
            return collect([]);
        }

        return collect($alma->users->search($query, ['limit' => 2]))->map(function ($user) {
            return new AlmaUser($user);
        });
    }
}
