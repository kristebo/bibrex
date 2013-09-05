<?php

class Loan extends Eloquent {
	protected $guarded = array();
	protected $softDelete = true;
	public $errors;

	public function user()
	{
		return $this->belongsTo('User');
	}

	public function document()
	{
		return $this->belongsTo('Document');
	}

	public function reminders()
	{
		return $this->hasMany('Reminder');
	}

	public function representation($plaintext = false)
	{
		if ($this->document->thing->id == 1) {
			$s = rtrim($this->document->title,' :')
				. ($this->document->subtitle ? ' : ' . $this->document->subtitle : '');
			if (!$plaintext) {
				$s .= ' <small>(' . $this->document->dokid . ')</small>';
			}
			return $s;
		} else {
			return $this->document->thing->name;
		}
	}

	public function daysLeft() {
		if (is_null($this->due_at)) {
			return 999999;
		}
		$d1 = new DateTime($this->due_at);
		$d2 = new DateTime();
		$diff = $d2->diff($d1);
		$dl = intval($diff->format('%r%a'));
		if ($dl > 0) $dl++;
		return $dl;
	}

	public function daysLeftFormatted() {
		$d = $this->daysLeft();
		if ($d == 999999) 
			return 'Forfaller «aldri»';
		if ($d > 1)
			return '<span style="color:green;">Forfaller om ' . $d . ' dager</span>';
		if ($d == 1)
			return '<span style="color:orange;">Forfaller i morgen</span>';
		if ($d == 0)
			return '<span style="color:orange;">Forfaller i dag</span>';
		if ($d == -1)
			return '<span style="color:red;">Forfalt i går</span>';
		return'<span style="color:red;">Forfalt for ' . abs($d) . ' dager siden</span>';
	}

	private function ncipCheckout() {

		$results = DB::select('SELECT ltid, in_bibsys FROM users WHERE users.id = ?', array($this->user_id));
		if (empty($results)) dd("user not found");
		$user = $results[0];

		$ltid = $user->in_bibsys ? $user->ltid : Config::get('app.guest_ltid');

		$this->as_guest = !$user->in_bibsys;

		$results = DB::select('SELECT things.id, documents.dokid FROM things,documents WHERE things.id = documents.thing_id AND documents.id = ?', array($this->document_id));
		if (empty($results)) dd("thing not found");

		$thing = $results[0];
		$dokid = $thing->dokid;

		if ($thing->id == 1) {

			$ncip = App::make('NcipClient');
			$response = $ncip->checkOutItem($ltid, $dokid);

			// BIBSYS sometimes returns an empty response on successful checkouts.
			// We will therefore threat an empty response as success... for now...
			$logmsg = 'Lånte ut [[Document:' . $dokid . ']] til ' . $ltid . '';
			if ($this->as_guest) {
				$logmsg .= ' (midlertidig lånekort)';
			}
			$logmsg .= ' i BIBSYS.';
			if ((!$response->success && $response->error == 'Empty response') || ($response->success)) {
				if ($response->dueDate) {
					$this->due_at = $response->dueDate;
					$logmsg .= ' Fikk forfallsdato.';
				} else {
					$logmsg .= ' Fikk tom respons.';
				}
				Log::info($logmsg);
			} else {
				Log::info('Dokumentet [[Document:' . $dokid . ']] kunne ikke lånes ut i BIBSYS: ' . $response->error);
				$this->errors->add('checkout_error', 'Dokumentet kunne ikke lånes ut i BIBSYS: ' . $response->error);
				return false;
			}

		}
		return true;
	}

	/**
	 * Save the model to the database.
	 *
	 * @param  array  $options
	 * @return bool
	 */
	public function save(array $options = array())
	{
		$this->errors = new Illuminate\Support\MessageBag;
		if (!$this->exists) {
			if (!$this->ncipCheckout()) {
				return false;
			}
		}

		parent::save($options);
		return true;
	}

	/**
	 * Delete the model from the database.
	 *
	 * @return bool|null
	 */
	public function delete()
	{

		if ($this->document->thing->id == 1) {

			$dokid = $this->document->dokid;

			$ncip = App::make('NcipClient');
			$response = $ncip->checkInItem($dokid);

			if (!$response->success) {
				dd("Dokumentet kunne ikke leveres inn i BIBSYS: " . $response->error);
			}
			Log::info('Returnerte [[Document:' . $dokid . ']] i BIBSYS');
		}

		parent::delete();
	}

	/**
	 * Restore a soft-deleted model instance.
	 *
	 * @return bool|null
	 */
	public function restore()
	{
		if (!$this->ncipCheckout()) {
			return false;
		}
		parent::restore();
		return true;
	}

}