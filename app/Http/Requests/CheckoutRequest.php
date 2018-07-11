<?php

namespace App\Http\Requests;

use App\Alma\User as AlmaUser;
use App\Item;
use App\Rules\NotOnLoan;
use App\Rules\RequiresBarcode;
use App\Rules\ThingExists;
use App\Rules\UniqueAlmaUser;
use App\Rules\UserExists;
use App\Thing;
use App\User;
use Illuminate\Foundation\Http\FormRequest;
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
        $lib = \Auth::user();

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
            $item = Item::where('barcode', '=', array_get($input, 'thing.name'))->first();

            if (is_null($item)) {
                // Next, check if it matches a thing name.
                $thing = Thing::where('name', '=', array_get($input, 'thing.name'))->first();
            }
        } elseif (array_get($input, 'thing.type') == 'item') {
            $item = Item::where('id', '=', array_get($input, 'thing.id'))->first();
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

        // ======================== Lookup or import user ========================

        $user = null;
        $localUser = false;

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
                $name = explode(',', $userValue);
                $name = array_map('trim', $name);
                $user = User::where('lastname', '=', $name[0])
                    ->where('firstname', '=', $name[1])
                    ->first();
            } else {
                $name = null;

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
                if (!array_get($lib->options, 'guestcard_for_cardless_loans', false)) {
                    return ['user' => [new UserExists(null)]];
                }

                // If user was not found in Alma, create a local user if possible.
                if (!is_null($name)) {
                    $user = User::create([
                        'firstname' => $name[0],
                        'lastname' => $name[1]
                    ]);
                    \Log::info('Oppretter lokal bruker: ' . $user->lastname . ', ' . $user->firstname);
                    $localUser = true;
                } else {
                    // Create local user for card??
                    // if ($this->isLTID($user_input)) {
                    // }
                }
            }
        }

        $this->user = $user;
        $this->item = $item;
        $this->localUser = $localUser;

        return [
            'user' => [new UserExists($user)],
            'thing' => [new ThingExists($item), new NotOnLoan($item)],
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
        \Log::info('Importerte bruker fra Alma: "' . $almaUser->primaryId. '"');

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
